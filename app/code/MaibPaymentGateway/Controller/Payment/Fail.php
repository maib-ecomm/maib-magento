<?php

namespace Magento\MaibPaymentGateway\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

class Fail extends Action
{
    private $logger;
    private $request;

    public function __construct(
        Context $context
    )
    {
        parent::__construct($context);
        $this->logger = $context->getObjectManager()->get(\Magento\Payment\Model\Method\Logger::class);
        $this->request = $context->getObjectManager()->get(\Magento\Framework\App\RequestInterface::class);
    }

    public function execute()
    {
        $payId = $this->request->getParam('payId');
        $orderId = $this->request->getParam('orderId');

        if ($payId && $orderId) {
            $this->logger->debug([
                "Return to Ok URL. Pay ID: " .
                    $payId .
                    ", Order ID: " .
                    $orderId
            ]);

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $orderInfo = $objectManager->create('\Magento\Sales\Model\OrderRepository')->get($orderId);

            if ($orderInfo) {
                $this->_redirect('checkout/cart');

                return;
            }
            else {
                $this->logger->debug([
                    "Fail URL: Order not found."
                ]);

                $this->messageManager->addError(__('Error no payment'));
                $this->_redirect('checkout');

                return;
            }
        }
        else {
            $this->logger->debug([
                "Fail URL: Invalid or missing payId/orderId."
            ]);

            $this->messageManager->addError(__('Error no payment'));
            $this->_redirect('checkout');

            return;
        }
    }
}
