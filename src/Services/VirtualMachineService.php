<?php

namespace Hostinger\WhmcsModule\Services;

use Exception;
use Hostinger\Api\VPSVirtualMachineApi;
use Hostinger\Model\VPSV1VirtualMachineSetupRequest;
use Hostinger\WhmcsModule\Helper;

class VirtualMachineService extends Service
{
    protected VPSVirtualMachineApi $apiClient;

    public function __construct($params)
    {
        parent::__construct($params);
        $this->apiClient = new VPSVirtualMachineApi(config: Helper::getApiConfig($params));
    }

    public function getVmBySubscriptionId($subscriptionId)
    {
        $vmList = $this->apiClient->getVirtualMachineListV1();

        foreach ($vmList as $vm) {
            if (isset($vm['subscriptionId']) && $vm['subscriptionId'] === $subscriptionId) {
                return $vm;
            }
        }

        return null;
    }

    public function setupInstance($subscriptionId, VPSV1VirtualMachineSetupRequest $setupVmRequest)
    {
        $vm = $this->getVmBySubscriptionId($subscriptionId);
        if (!$vm) {
            throw new Exception("VM is not found (subscription $subscriptionId)");
        }

        return $this->apiClient->setupNewVirtualMachineV1($vm['id'], $setupVmRequest);
    }
}
