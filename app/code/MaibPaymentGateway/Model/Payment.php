<?php

namespace Magento\MaibPaymentGateway\Model;

use Exception;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Framework\App\ObjectManager;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\MaibPaymentGateway\Api\MaibApiRequest;
use Magento\MaibPaymentGateway\Api\MaibAuthRequest;

class Payment extends AbstractMethod
{
    protected $_code = 'maib_gateway';

    protected $_isOffline = false;

    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canVoid = true;
    protected $_canUseCheckout = true;

    protected $cache;

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $objectManager = ObjectManager::getInstance();
        $storeManager = $objectManager->get(\Magento\Store\Model\StoreManagerInterface::class);
        $scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $logger = $objectManager->get(LoggerInterface::class);
        $redirectFactory = $objectManager->get(\Magento\Framework\Controller\Result\RedirectFactory::class);

        $store = $storeManager->getStore();
        $baseUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        $okUrl = $baseUrl . 'maib/payment/success';
        $failUrl = $baseUrl . 'maib/payment/fail';
        $callbackUrl = $baseUrl . 'maib/payment/callback';

        $orderInfo = $payment->getOrder();
        $orderId = $orderInfo->getIncrementId();

        $description = [];
        $productItems = [];

        foreach ($orderInfo->getAllItems() as $item) {
            $description[] = $item->getQtyOrdered() . " x " . $item->getName();

            $productItems[] = [
                "id" => $item->getProductId(),
                "name" => $item->getName(),
                "price" => $item->getPrice(),
                "quantity" => (float) number_format(
                    $item->getQtyOrdered(),
                    1,
                    ".",
                    ""
                ),
            ];
        }

        $billingAddress = $orderInfo->getBillingAddress();

        $storeLocale = $scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store->getId()
        );

        $params = [
            "amount" => $orderInfo->getGrandTotal(),
            "currency" => $orderInfo->getOrderCurrencyCode(),
            "clientIp" => $orderInfo->getRemoteIp(),
            "language" => $storeLocale,
            "description" => substr(implode(", ", $description), 0, 124),
            "orderId" => $orderId,
            "clientName" => $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
            "email" => $billingAddress->getEmail(),
            "phone" => substr($billingAddress->getTelephone(), 0, 40),
            "delivery" => $orderInfo->getShippingAmount(),
            "okUrl" => $okUrl,
            "failUrl" => $failUrl,
            "callbackUrl" => $callbackUrl,
            "items" => $productItems,
        ];

        $logger->info(
            'Order params: ' . json_encode($params, JSON_PRETTY_PRINT)
        );

        try {
            // Initiate Direct Payment Request to maib API
            $response = MaibApiRequest::create()->pay(
                $params,
                $this->getAccessToken()
            );

            if (!isset($response->payId)) {
                $logger->info(
                    'No valid response from maib API, order_id: ' . $orderId
                );

                $resultRedirect = $redirectFactory->create();
                $resultRedirect->setPath('checkout/checkout');
                return $resultRedirect;
            } else {
                $logger->info(
                    'Pay endpoint response: ' . json_encode($response, JSON_PRETTY_PRINT) . ', order_id: ' . $orderId
                );

                $orderStatusId = $scopeConfig->getValue('payment/maib_gateway/configuration_order_status/pending_status_id');

                $orderInfo->setStatus($orderStatusId);

                $resultRedirect = $redirectFactory->create();
                $resultRedirect->setPath($response->payUrl);
                return $resultRedirect;
            }
        } catch (Exception $ex) {
            $logger->info(
                'Error no payment: ' . $ex->getMessage()
            );

            throw new \Exception(
                __('Payment failed! Please try again.')
            );
        }
    }

    private function getAccessToken()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $logger = $objectManager->get(LoggerInterface::class);
        $cache = $objectManager->get(CacheInterface::class);
        
        $projectId = $scopeConfig->getValue('payment/maib_gateway/configuration_maibmerchants/project_id');
        $projectSecret = $scopeConfig->getValue('payment/maib_gateway/configuration_maibmerchants/project_secret');
        $projectSignature = $scopeConfig->getValue('payment/maib_gateway/configuration_maibmerchants/project_signature');

        // Check if access token exists in cache and is not expired
        if ($cache->load("access_token") &&
            $cache->load("access_token_expires") > time()
        ) {
            $accessToken = $cache->load("access_token");

            $logger->info(
                'Successful received Access Token from cache.'
            );

            return $accessToken;
        }

        try {
            // Initiate Get Access Token Request to Maib API
            $response = MaibAuthRequest::create()->generateToken(
                $projectId,
                $projectSecret
            );

            $logger->info(
                'Successful received Access Token from Maib API.'
            );

            $accessToken = $response->accessToken;

            // Store the access token and its expiration time in cache
            $cache->save(
                $accessToken,
                "access_token",
                [],
                $response->expiresIn
            );
            $cache->save(
                time() + $response->expiresIn,
                "access_token_expires",
                [],
                $response->expiresIn
            );
        } catch (LocalizedException $ex) {
            $logger->error(
                'Access token error: ' . $ex->getMessage()
            );

            $this->_redirect('checkout/checkout');

            return;
        }

        return $accessToken;
    }
}
