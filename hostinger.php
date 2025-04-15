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
use WHMCS\Database\Capsule;
use Hostinger\WhmcsModule\Helpers\Helper;
use Hostinger\WhmcsModule\Constants;
use Hostinger\WhmcsModule\Services\BillingOrderService;
use Hostinger\WhmcsModule\Services\Service;
use Hostinger\WhmcsModule\Services\VirtualMachineService;
use Hostinger\WhmcsModule\Views\ClientAreaView;

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

        Capsule::table(Constants::HOSTINGER_TABLE)->insert([
            'hservice_id' => $vmId,
            'subscription_id' => $subscriptionId,
            'whmcs_service_id' => $params['serviceid'],
            'details' => json_encode($vm),
            'updated_at' => date('Y-m-d H:i:s', time())
        ]);
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
        $service = new Service($params);

        $result = $service->getService($params['serviceid']);
        if (!$result->hservice_id) {
            return "No VM ID found for service";
        }

        $apiClient->stopVirtualMachineV1($result->hservice_id);
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
        $service = new Service($params);

        $result = $service->getService($params['serviceid']);
        if (!$result->hservice_id) {
            return "No VM ID found for service";
        }

        $apiClient->startVirtualMachineV1($result->hservice_id);
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

        $service = new Service($params);
        $result = $service->getService($params['serviceid']);
        if (!$result->subscription_id) {
            return "No Subscription ID found for service";
        }

        $request = new BillingV1SubscriptionCancelRequest([
            'reasonCode' => 'USER_REQUEST',
            'cancelOption' => 'IMMEDIATE'
        ]);

        $apiClient->cancelSubscriptionV1($result->subscription_id, $request);
    } catch (Exception $e) {
        return "Terminate error: " . $e->getMessage();
    }
}

/**
 * Show Client Area
 *
 * @param $params
 * @return array|string
 */
function hostinger_ClientArea($params)
{
    $view = new ClientAreaView();

    return $view->render($params);
}


/**
 * Custom Admin Area Buttons
 *
 * @return array
 */
function hostinger_AdminCustomButtonArray($params)
{
    $vmService = new VirtualMachineService($params);

    return [
        'Update Details' => $vmService->getVmDetails($params)
    ];
}
