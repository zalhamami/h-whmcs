<?php

namespace Hostinger\WhmcsModule;

use Exception;

class HostingerApiClient
{
    private $apiUrl;
    private $apiToken;

    public function __construct($baseUrl, $token)
    {
        // Ensure no trailing slash in base URL
        $this->apiUrl  = rtrim($baseUrl, '/');
        $this->apiToken = $token;
    }

    /**
     * Perform an HTTP request to the Hostinger API.
     */
    private function request($method, $endpoint, $body = null)
    {
        $url = $this->apiUrl . $endpoint;
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        // Set headers for authorization and JSON content
        $headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("API connection error: $error");
        }
        curl_close($ch);
        // Decode JSON response
        $data = json_decode($response, true);
        if ($httpCode >= 400 || $data === null) {
            // Construct error message from response if available
            $msg = isset($data['error']) ? $data['error'] : "HTTP $httpCode Error";
            throw new Exception("Hostinger API error: " . $msg);
        }
        return $data;
    }

    /**
     * Get all VPS plan price options from the billing catalog.
     */
    public function getVpsPlans()
    {
        $catalog = $this->request('GET', '/api/billing/v1/catalog');
        $planOptions = [];
        foreach ($catalog as $item) {
            if (!empty($item['category']) && strtoupper($item['category']) === 'VPS') {
                $planName = $item['name'] ?? $item['id'];
                if (!empty($item['prices']) && is_array($item['prices'])) {
                    foreach ($item['prices'] as $priceItem) {
                        // Each priceItem corresponds to a specific billing term for the plan
                        $planOptions[] = [
                            'priceId'    => $priceItem['id'],
                            'planName'   => $planName,
                            'period'     => $priceItem['period'] ?? 1,
                            'periodUnit' => $priceItem['period_unit'] ?? ''
                        ];
                    }
                }
            }
        }
        return $planOptions;
    }

    /**
     * Get list of all available data centers.
     */
    public function getDataCenters()
    {
        return $this->request('GET', '/api/vps/v1/data-centers');
    }

    /**
     * Get list of all available OS templates.
     */
    public function getOsTemplates()
    {
        return $this->request('GET', '/api/vps/v1/templates');
    }

    /**
     * Create a new VPS instance by placing an order and retrieving the new VM's details.
     */
    public function createVpsInstance($priceId, $datacenterId, $templateId, $hostname)
    {
        // Fetch available payment methods and use the first one for the order
        $methods = $this->request('GET', '/api/billing/v1/payment-methods');
        if (empty($methods) || !is_array($methods)) {
            throw new Exception("No payment method available for order");
        }
        $paymentMethodId = isset($methods[0]['id']) ? $methods[0]['id'] : (int)$methods[0];

        // Prepare order payload (subscribe to the selected VPS plan)
        $orderPayload = [
            'payment_method_id' => $paymentMethodId,
            'items' => [
                [
                    'item_id'  => $priceId,
                    'quantity' => 1
                ]
            ]
        ];
        // Place the order for the VPS subscription
        $orderResult = $this->request('POST', '/api/billing/v1/orders', $orderPayload);
        if (empty($orderResult['subscription_id'])) {
            throw new Exception("Failed to create VPS subscription");
        }

        $subscriptionId = $orderResult['subscription_id'];

        // Try to retrieve the new virtual machine ID associated with this subscription
        // TODO: VPS setup request
        $vmId = null;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $vmList = $this->request('GET', '/api/vps/v1/virtual-machines');
            foreach ($vmList as $vm) {
                if (isset($vm['subscription_id']) && $vm['subscription_id'] === $subscriptionId) {
                    $vmId = $vm['id'];
                    break 2;  // exit both loop and retry attempts
                }
            }
            sleep(2); // wait a moment before retrying (allow provisioning to complete)
        }
        if (!$vmId) {
            throw new Exception("VPS created, but VM ID not found (subscription $subscriptionId)");
        }

        // Note: Optionally, the Hostinger API might allow setting the hostname via an endpoint.
        // Assuming the provided hostname is automatically applied during creation if required.

        return [
            'vmId'           => $vmId,
            'subscriptionId' => $subscriptionId
        ];
    }

    /**
     * Stop (power off) a virtual machine by ID.
     */
    public function stopVirtualMachine($vmId)
    {
        $this->request('POST', "/api/vps/v1/virtual-machines/$vmId/stop");
    }

    /**
     * Start (power on) a virtual machine by ID.
     */
    public function startVirtualMachine($vmId)
    {
        $this->request('POST', "/api/vps/v1/virtual-machines/$vmId/start");
    }

    /**
     * Cancel a subscription by ID (terminate the associated VPS service).
     */
    public function cancelSubscription($subscriptionId)
    {
        $this->request('DELETE', "/api/billing/v1/subscriptions/$subscriptionId");
    }
}
