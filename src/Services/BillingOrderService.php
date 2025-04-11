<?php

namespace Hostinger\WhmcsModule\Services;

use Exception;
use Hostinger\Api\BillingOrdersApi;
use Hostinger\Model\BillingV1OrderStoreRequest;
use Hostinger\WhmcsModule\Helper;

class BillingOrderService extends Service
{
    protected BillingOrdersApi $apiClient;

    public function __construct($params)
    {
        parent::__construct($params);
        $this->apiClient = new BillingOrdersApi(config: Helper::getApiConfig($params));
    }

    public function createOrder($priceId, $paymentMethodId = null)
    {
        if (!$paymentMethodId) {
            $paymentMethodService = new PaymentMethodService($this->params);
            $paymentMethodId = $paymentMethodService->getDefaultPaymentMethod();
        }

        $orderPayload = new BillingV1OrderStoreRequest([
            'payment_method_id' => $paymentMethodId,
            'items' => [
                [
                    'item_id'  => $priceId,
                    'quantity' => 1
                ]
            ]
        ]);

        $orderResult = $this->apiClient->createNewServiceOrderV1($orderPayload);
        if (empty($orderResult['subscriptionId'])) {
            throw new Exception("Failed to create VPS subscription");
        }

        return $orderResult;
    }
}
