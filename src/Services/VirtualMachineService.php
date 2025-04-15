<?php

namespace Hostinger\WhmcsModule\Services;

use Exception;
use Hostinger\Api\VPSVirtualMachineApi;
use Hostinger\Model\VPSV1VirtualMachineSetupRequest;
use Hostinger\Model\VPSV1VirtualMachineVirtualMachineResource;
use Hostinger\Model\VPSV1VirtualMachineVirtualMachineResourceIpv4;
use Hostinger\Model\VPSV1VirtualMachineVirtualMachineResourceIpv6;
use Hostinger\Model\VPSV1VirtualMachineVirtualMachineResourceTemplate;
use Hostinger\WhmcsModule\Constants;
use Hostinger\WhmcsModule\Helpers\Helper;
use WHMCS\Database\Capsule;

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

        $this->apiClient->setupNewVirtualMachineV1($vm->getId(), $setupVmRequest);

        return $vm;
    }

    public function getVmData($vmId)
    {
        try {
            return $this->apiClient->getVirtualMachineV1($vmId);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function updateVmDetails($serviceId)
    {
        $service = $this->getService($serviceId);
        if (!$service) return;

        $data = $this->getVmData($service->hservice_id);
        $isRunningState = $data->getState() === VPSV1VirtualMachineVirtualMachineResource::STATE_RUNNING;

        $update = [
            'domain' => $data->getHostname(),
            'ns1' => $data->getNs1(),
            'ns2' => $data->getNs2(),
            'domainstatus' => $isRunningState ? Constants::STATUS_ACTIVE : $data->getState(),
            'lastupdate' => date('Y-m-d H:i:s', time()),
            'username' => 'root',
        ];

        Capsule::table(Constants::HOSTINGER_TABLE)
            ->where('whmcs_service_id', $serviceId)
            ->update([
                'details' => json_encode($data),
                'updated_at' => $update['lastupdate']
            ]);

        Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->update($update);
    }

    public function getVmDetails($params, $force = false)
    {
        $service = $this->getService($params['serviceid']);

        $currentDetails = $service->details ? json_decode($service->details, true) : [];
        $updatedAt = $service->updatedAt ? strtotime($service->updatedAt) : null;
        $isMoreThanFiveMinutes = time() - $updatedAt > 5 * 60;

        if ($force || !$currentDetails || $isMoreThanFiveMinutes) {
            $this->updateVmDetails($params['serviceid']);
        }

        return new VPSV1VirtualMachineVirtualMachineResource(array_merge(
            $currentDetails,
            [
                'ipv4' => new VPSV1VirtualMachineVirtualMachineResourceIpv4($currentDetails['ipv4']),
                'ipv6' => new VPSV1VirtualMachineVirtualMachineResourceIpv6($currentDetails['ipv6']),
                'template' => new VPSV1VirtualMachineVirtualMachineResourceTemplate($currentDetails['template']),
            ],
        ));
    }

    public function restartVm($vmId)
    {
        try {
            return $this->apiClient->restartVirtualMachineV1($vmId);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
