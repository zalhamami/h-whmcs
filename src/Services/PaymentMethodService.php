<?php

namespace Hostinger\WhmcsModule\Services;

use Exception;
use Hostinger\Api\BillingPaymentMethodsApi;
use Hostinger\WhmcsModule\Helper;

class PaymentMethodService extends Service
{
    protected BillingPaymentMethodsApi $apiClient;

    public function __construct($params)
    {
        parent::__construct($params);
        $this->apiClient = new BillingPaymentMethodsApi(config: Helper::getApiConfig($params));
    }

    public function getDefaultPaymentMethod()
    {
        $methods = $this->apiClient->getPaymentMethodListV1();

        if (empty($methods) || !is_array($methods)) {
            throw new Exception("No payment method available for order");
        }

        return isset($methods[0]['id']) ? $methods[0]['id'] : (int)$methods[0];
    }
}
