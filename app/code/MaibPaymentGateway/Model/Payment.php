<?php
namespace Magento\MaibPaymentGateway\Model;

use Magento\Payment\Model\Method\AbstractMethod;

class Payment extends AbstractMethod
{
    protected $_code = 'maib_gateway';

    protected $_isOffline = false;

    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canVoid = true;
    protected $_canUseCheckout = true;

    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        // Implement payment authorization logic
        // Example: make API call to payment gateway
        // Here you should handle the authorization process
        // For now, we will just simulate the process
        $payment->setTransactionId('txn123');
        $payment->setIsTransactionClosed(false);
        return $this;
    }
}
