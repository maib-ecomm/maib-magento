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
    protected $_canRefund = true;

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

                throw new \Magento\Framework\Exception\LocalizedException(
                    __('No valid response from maib API, order_id: %1', $orderId)
                );
            } else {
                $logger->info(
                    'Pay endpoint response: ' . json_encode($response, JSON_PRETTY_PRINT) . ', order_id: ' . $orderId
                );

                $orderStatusId = $scopeConfig->getValue('payment/maib_gateway/configuration_order_status/pending_status_id');

                $orderInfo->setStatus($orderStatusId);

                $payment->setTransactionId($response->payId);
                $payment->setIsTransactionClosed(false);

                $resultRedirect = $redirectFactory->create();
                $resultRedirect->setPath($response->payUrl);
                return $resultRedirect;
            }
        } catch (Exception $ex) {
            $logger->info(
                'Error no payment: ' . $ex->getMessage()
            );

            throw new \Magento\Framework\Exception\LocalizedException(
                __('Payment failed! Please try again.')
            );
        }
    }

    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $transactionId = $payment->getParentTransactionId() ?: $payment->getTransactionId();

        if (!$transactionId) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('No transaction ID found for capture.')
            );
        }

        $payment->setTransactionId($transactionId);
        $payment->setIsTransactionClosed(true);

        $order = $payment->getOrder();
        $invoice = $order->getInvoiceCollection()->getLastItem();
        if ($invoice) {
            $invoice->setTransactionId($transactionId);
            $invoice->save();
        }

        return $this;
    }

    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $objectManager = ObjectManager::getInstance();
        $logger = $objectManager->get(LoggerInterface::class);
        $scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $orderInfo = $payment->getOrder();
        $orderId = $orderInfo->getIncrementId();
        $paymentMethodCode = $payment->getMethod();
        $transactionId = $payment->getParentTransactionId() ?: $payment->getTransactionId();

        if (!$orderId || !$orderInfo || $paymentMethodCode != 'maib_gateway') {
            return;
        }
        
        $logger->info(
            'Initiate Refund Payment Request to maib API, pay_id: ' . $transactionId . ', order_id: ' . $orderId
        );
    
        $params = ['payId' => strval($transactionId)];

        try {
            // Initiate Refund Payment Request to maib API
            $response = MaibApiRequest::create()->refund(
                $params,
                $this->getAccessToken()
            );

            $logger->info(
                'Response from refund endpoint: ' . json_encode($response, JSON_PRETTY_PRINT) . ', order_id: ' . $orderId
            );
    
            if ($response && $response->status === "OK") {
                $logger->info(
                    'Full refunded payment ' . $transactionId . ' for order ' . $orderId
                );

                $orderStatusId = $scopeConfig->getValue('payment/maib_gateway/configuration_order_status/refunded_status_id');

                $orderInfo->setStatus($orderStatusId);
            } else if ($response && $response->status === "REVERSED") {
                $logger->info(
                    'Already refunded payment ' . $transactionId . ' for order ' . $orderId
                );

                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Already refunded payment %1 for order %2', $transactionId, $orderId)
                );
            } else {
                $logger->info(
                    'Failed refund payment ' . $transactionId . ' for order ' . $orderId
                );

                throw new \Magento\Framework\Exception\LocalizedException(
                    __('Failed refund payment %1 for order %2', $transactionId, $orderId)
                );
            }
        } catch (Exception $e) {
            $logger->info(
                'Failed refund payment ' . $transactionId . ' for order ' . $orderId
            );

            throw new \Magento\Framework\Exception\LocalizedException(
                __('Failed refund payment %1 for order %2', $transactionId, $orderId)
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