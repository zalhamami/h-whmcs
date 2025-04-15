<?php

namespace Hostinger\WhmcsModule\Services;

use Exception;
use Hostinger\Api\BillingOrdersApi;
use Hostinger\Model\BillingV1OrderStoreRequest;
use Hostinger\Model\BillingV1OrderStoreRequestItemsInner;
use Hostinger\WhmcsModule\Helpers\Helper;

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

        $item = new BillingV1OrderStoreRequestItemsInner([
            'itemId' => $priceId,
            'quantity' => 1
        ]);
        $orderPayload = new BillingV1OrderStoreRequest([
            'paymentMethodId' => $paymentMethodId,
            'items' => [$item],
        ]);

        $orderResult = $this->apiClient->createNewServiceOrderV1($orderPayload);
        if (empty($orderResult->getSubscriptionId())) {
            throw new Exception("Failed to create VPS subscription");
        }

        return $orderResult;
    }
}
