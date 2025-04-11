<?php
if (!defined("WHMCS")) die("This file cannot be accessed directly");

require_once __DIR__ . '/vendor/autoload.php';

use Hostinger\Api\BillingCatalogApi;
use Hostinger\WhmcsModule\Helper;
use Hostinger\WhmcsModule\ServiceHelper;
use Hostinger\WhmcsModule\Constants;

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
            'SimpleMode' => true
        ],
        Constants::CONFIG_LABEL_DATACENTER => [
            'Type'       => 'dropdown',
            'Loader'     => 'hostinger_LoadDatacenters',
            'SimpleMode' => true
        ],
        Constants::CONFIG_LABEL_OS => [
            'Type'       => 'dropdown',
            'Loader'     => 'hostinger_LoadOsTemplates',
            'SimpleMode' => true
        ]
    ];
}

/**
 * Loader function to fetch VPS plan options from Hostinger API.
 */
function hostinger_LoadPlans($params)
{
    try {
        $api = new BillingCatalogApi(config: )
        $plans = $apiClient->getVpsPlans();
        // Format options as key-value pairs (priceId => "PlanName - Term")
        return Helper::formatPlanOptions($plans);
    } catch (Exception $e) {
        // Throw an exception with a plain message (WHMCS will display it)
        throw new Exception("Failed to fetch VPS plans");
    }
}

/**
 * Loader function to fetch datacenter options from Hostinger API.
 */
function hostinger_LoadDatacenters($params)
{
    try {
        $apiClient = Helper::getApiClient($params);
        $locations = $apiClient->getDataCenters();
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
        $apiClient = Helper::getApiClient($params);
        $templates = $apiClient->getOsTemplates();
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
        $apiClient    = Helper::getApiClient($params);
        $planPriceId  = $params['configoption1'];    // selected Plan price item ID
        $datacenterId = $params['configoption2'];    // selected Datacenter ID
        $templateId   = $params['configoption3'];    // selected OS template ID
        $hostname     = $params['domain'] ?: 'vps' . $params['serviceid'];  // default hostname if none provided

        // Create the VPS instance via Hostinger API
        $result = $apiClient->createVpsInstance($planPriceId, $datacenterId, $templateId, $hostname);
        $vmId  = $result['vmId'];
        $subId = $result['subscriptionId'];

        // Save the VM ID and subscription ID in WHMCS custom fields for this service
        ServiceHelper::saveCustomFieldValue($params, Constants::CUSTOM_FIELD_VM_ID, $vmId);
        ServiceHelper::saveCustomFieldValue($params, Constants::CUSTOM_FIELD_SUB_ID, $subId);
    } catch (Exception $e) {
        // Return a plain error string to WHMCS on failure
        return "CreateAccount error: " . $e->getMessage();
    }
    // Return nothing (empty string) to indicate success
}

/**
 * Suspend the VPS (e.g. stop the virtual machine).
 */
function hostinger_SuspendAccount($params)
{
    try {
        $apiClient = Helper::getApiClient($params);
        // Retrieve the VM ID from custom fields
        $vmId = ServiceHelper::getCustomFieldValue($params, Constants::CUSTOM_FIELD_VM_ID);
        if (!$vmId) {
            return "No VM ID found for service";
        }
        // Stop (power off) the VM via API
        $apiClient->stopVirtualMachine($vmId);
    } catch (Exception $e) {
        return "Suspend error: " . $e->getMessage();
    }
    // On success, return nothing
}

/**
 * Unsuspend the VPS (e.g. start the virtual machine).
 */
function hostinger_UnsuspendAccount($params)
{
    try {
        $apiClient = Helper::getApiClient($params);
        $vmId = ServiceHelper::getCustomFieldValue($params, Constants::CUSTOM_FIELD_VM_ID);
        if (!$vmId) {
            return "No VM ID found for service";
        }
        // Start (power on) the VM via API
        $apiClient->startVirtualMachine($vmId);
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
        $apiClient = Helper::getApiClient($params);
        $subId = ServiceHelper::getCustomFieldValue($params, Constants::CUSTOM_FIELD_SUB_ID);
        if (!$subId) {
            return "No Subscription ID found for service";
        }
        // Cancel the subscription on Hostinger (stops future billing and removes the VPS as per Hostinger policy)
        $apiClient->cancelSubscription($subId);
    } catch (Exception $e) {
        return "Terminate error: " . $e->getMessage();
    }
}
