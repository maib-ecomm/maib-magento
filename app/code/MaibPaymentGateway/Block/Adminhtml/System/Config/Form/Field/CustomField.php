<?php

namespace Magento\MaibPaymentGateway\Block\Adminhtml\System\Config\Form\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class CustomField extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $storeManager;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        $store = $this->storeManager->getStore();
        $baseUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        switch ($element->getId()) {
            case 'payment_other_maib_gateway_configuration_maibmerchants_ok_url':
                $url = $baseUrl . 'maib/payment/success';
                break;
            case 'payment_other_maib_gateway_configuration_maibmerchants_fail_url':
                $url = $baseUrl . 'maib/payment/fail';
                break;
            case 'payment_other_maib_gateway_configuration_maibmerchants_callback_url':
                $url = $baseUrl . 'maib/payment/callback';
                break;
            default:
                $url = '';
                break;
        }

        $element->setValue($url);
        $element->setReadonly(true, true);
        return parent::_getElementHtml($element);
    }
}
