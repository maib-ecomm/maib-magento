<?php

namespace Magento\MaibPaymentGateway\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

class Callback extends Action
{
    private $logger;
    private $scopeConfig;

    public function __construct(
        Context $context
    )
    {
        parent::__construct($context);
        $this->logger = $context->getObjectManager()->get(\Magento\Payment\Model\Method\Logger::class);
        $this->scopeConfig = $context->getObjectManager()->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
    }

    public function execute()
    {
        if ($this->getRequest()->isGet()) {
            $this->messageManager->addError(__('Error callback URL'));
            $this->_redirect('checkout/cart');

            return;
        }
        
        $json = file_get_contents("php://input");
        $data = json_decode($json, true);

        if (!isset($data["signature"]) || !isset($data["result"])) {
            $this->logger->debug([
                "Callback URL - Signature or Payment data not found in notification."
            ]);
            exit();
        }

        $this->logger->debug([
            sprintf(
                "Notification on Callback URL: %s",
                json_encode($data, JSON_PRETTY_PRINT)
            )
        ]);

        $dataResult = $data["result"]; // Data from "result" object
        $sortedDataByKeys = $this->sortByKeyRecursive($dataResult); // Sort an array by key recursively
        $projectSignatureConfigPath = 'payment/maib_gateway/configuration_maibmerchants/project_signature';
        $key = $this->scopeConfig->getValue($projectSignatureConfigPath, \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT); // Signature Key from Project settings
        $sortedDataByKeys[] = $key; // Add checkout Secret Key to the end of data array
        $signString = implode(":", $sortedDataByKeys); // Implode array recursively
        $sign = base64_encode(hash("sha256", $signString, true)); // Result Hash

        $payId = isset($dataResult["payId"]) ? $dataResult["payId"] : false;
        $orderId = isset($dataResult["orderId"])
            ? (int) $dataResult["orderId"]
            : false;
        $status = isset($dataResult["status"])
            ? $dataResult["status"]
            : false;

        if ($sign !== $data["signature"]) {
            echo "ERROR";
            $this->logger->debug([
                sprintf("Signature is invalid: %s", $sign)
            ]);
            exit();
        }

        echo "OK";
        $this->logger->debug([
            sprintf("Signature is valid: %s", $sign)
        ]);

        if (!$orderId || !$status) {
            $this->logger->debug([
                "Callback URL - Order ID or Status not found in notification."
            ]);
            exit();
        }

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $orderInfo = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId);

        if (!$orderInfo) {
            $this->logger->debug([
                "Callback URL - Order ID not found in Magento Orders."
            ]);
            exit();
        }

        if ($status === "OK") {
            // Payment success logic
            $orderStatusSuccessConfigPath = 'payment/maib_gateway/configuration_order_status/completed_status_id';
            $orderStatusId = $this->scopeConfig->getValue($orderStatusSuccessConfigPath, \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
            $orderNote = sprintf(
                "Payment_Info: %s",
                json_encode($dataResult, JSON_PRETTY_PRINT)
            );

            $this->logger->debug([
                $orderNote
            ]);
            
            $orderInfo->setStatus($orderStatusId);
        } else {
            // Payment failure logic
            $orderStatusFailConfigPath = 'payment/maib_gateway/configuration_order_status/failed_status_id';
            $orderStatusId = $this->scopeConfig->getValue($orderStatusFailConfigPath, \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT);
            $orderNote = sprintf(
                "Payment_Info: %s",
                json_encode($dataResult, JSON_PRETTY_PRINT)
            );

            $this->logger->debug([
                $orderNote
            ]);
            
            $orderInfo->setStatus($orderStatusId);
        }

        exit();
    }

    // Helper function: Sort an array by key recursively
    private function sortByKeyRecursive(array $array)
    {
        ksort($array, SORT_STRING);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sortByKeyRecursive($value);
            }
        }
        return $array;
    }
}
