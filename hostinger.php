<?php
if (!defined("WHMCS")) die("This file cannot be accessed directly");

require_once __DIR__ . '/vendor/autoload.php';

use Hostinger\Api\BillingCatalogApi;
use Hostinger\Api\BillingSubscriptionsApi;
use Hostinger\Api\VPSDataCentersApi;
use Hostinger\Api\VPSOSTemplatesApi;
use Hostinger\Api\VPSVirtualMachineApi;
use Hostinger\Model\BillingV1SubscriptionCancelRequest;
use Hostinger\Model\VPSV1VirtualMachineSetupRequest;
use Hostinger\WhmcsModule\Helper;
use Hostinger\WhmcsModule\ServiceHelper;
use Hostinger\WhmcsModule\Constants;
use Hostinger\WhmcsModule\Services\BillingOrderService;
use Hostinger\WhmcsModule\Services\VirtualMachineService;

/**
 * Define module metadata for WHMCS.
 */
function hostinger_MetaData()
{
    return array(
        'DisplayName' => 'Hostinger',
        'APIVersion'  => '1.0',
        'RequiresServer' => true
    );
}

/**
 * Define module configuration options.
 */
function hostinger_ConfigOptions($params)
{
    return [
        Constants::CONFIG_LABEL_PLAN => [
            'Type'       => 'dropdown',
            'Loader'     => 'hostinger_LoadPlans',
            'SimpleMode' => true,
        ],
        Constants::CONFIG_LABEL_DATACENTER => [
            'Type'       => 'dropdown',
            'Loader'     => 'hostinger_LoadDatacenters',
            'SimpleMode' => true,
            'Description' => 'Optional default',
        ],
        Constants::CONFIG_LABEL_OS => [
            'Type'       => 'dropdown',
            'Loader'     => 'hostinger_LoadOsTemplates',
            'SimpleMode' => true,
            'Description' => 'Optional default',
        ],
    ];
}

/**
 * Loader function to fetch VPS plan options from Hostinger API.
 */
function hostinger_LoadPlans($params)
{
    try {
        $apiClient = new BillingCatalogApi(config: Helper::getApiConfig($params));
        $response = $apiClient->getCatalogItemListV1();

        return Helper::getPlansFromResponse($response);
    } catch (Exception $e) {
        throw new Exception("Failed to fetch VPS plans");
    }
}

/**
 * Loader function to fetch datacenter options from Hostinger API.
 */
function hostinger_LoadDatacenters($params)
{
    try {
        $apiClient = new VPSDataCentersApi(config: Helper::getApiConfig($params));
        $locations = $apiClient->getDataCentersListV1();

        return Helper::formatDatacenterOptions($locations);
    } catch (Exception $e) {
        throw new Exception("Failed to fetch datacenters");
    }
}

/**
 * Loader function to fetch OS template options from Hostinger API.
 */
function hostinger_LoadOsTemplates($params)
{
    try {
        $apiClient = new VPSOSTemplatesApi(config: Helper::getApiConfig($params));
        $templates = $apiClient->getTemplateListV1();

        return Helper::formatOsOptions($templates);
    } catch (Exception $e) {
        throw new Exception("Failed to fetch OS templates");
    }
}

/**
 * Provision a new VPS on Hostinger.
 */
function hostinger_CreateAccount($params)
{
    try {
        $planPriceId  = Helper::getConfigOptionWithFallback($params, Constants::CONFIG_LABEL_PLAN);
        // TODO fetch values dynamically
        $datacenterId = 9;
        $templateId   = 1013;
        $password     = $params['password'];
        $hostname     = $params['domain'] ?: 'srv' . $params['serviceid'];
        
        $billingService = new BillingOrderService($params);
        $order = $billingService->createOrder($planPriceId);

        $vmService = new VirtualMachineService($params);
        $setupVmRequest = new VPSV1VirtualMachineSetupRequest([
            'templateId' => $templateId,
            'hostname' => $hostname,
            'dataCenterId' => $datacenterId,
            'password' => $password
        ]);
        $vm = $vmService->setupInstance($order['subscriptionId'], $setupVmRequest);

        $vmId  = $vm['id'];
        $subscriptionId = $vm['subscriptionId'];

        ServiceHelper::saveCustomFieldValue($params, Constants::CUSTOM_FIELD_VM_ID, $vmId);
        ServiceHelper::saveCustomFieldValue($params, Constants::CUSTOM_FIELD_SUB_ID, $subscriptionId);
    } catch (Exception $e) {
        return "CreateAccount error: " . $e->getMessage();
    }
}

/**
 * Suspend the VPS (e.g. stop the virtual machine).
 */
function hostinger_SuspendAccount($params)
{
    try {
        $apiClient = new VPSVirtualMachineApi(config: Helper::getApiConfig($params));

        $vmId = ServiceHelper::getCustomFieldValue($params, Constants::CUSTOM_FIELD_VM_ID);
        if (!$vmId) {
            return "No VM ID found for service";
        }

        $apiClient->stopVirtualMachineV1($vmId);
    } catch (Exception $e) {
        return "Suspend error: " . $e->getMessage();
    }
}

/**
 * Unsuspend the VPS (e.g. start the virtual machine).
 */
function hostinger_UnsuspendAccount($params)
{
    try {
        $apiClient = new VPSVirtualMachineApi(config: Helper::getApiConfig($params));

        $vmId = ServiceHelper::getCustomFieldValue($params, Constants::CUSTOM_FIELD_VM_ID);
        if (!$vmId) {
            return "No VM ID found for service";
        }

        $apiClient->startVirtualMachineV1($vmId);
    } catch (Exception $e) {
        return "Unsuspend error: " . $e->getMessage();
    }
}

/**
 * Terminate the VPS (cancel the Hostinger subscription).
 */
function hostinger_TerminateAccount($params)
{
    try {
        $apiClient = new BillingSubscriptionsApi(config: Helper::getApiConfig($params));

        $subId = ServiceHelper::getCustomFieldValue($params, Constants::CUSTOM_FIELD_SUB_ID);
        if (!$subId) {
            return "No Subscription ID found for service";
        }

        $request = new BillingV1SubscriptionCancelRequest([
            'reasonCode' => 'USER_REQUEST',
            'cancelOption' => 'IMMEDIATE'
        ]);

        $apiClient->cancelSubscriptionV1($subId, $request);
    } catch (Exception $e) {
        return "Terminate error: " . $e->getMessage();
    }
}
