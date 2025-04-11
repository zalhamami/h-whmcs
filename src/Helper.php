<?php
namespace Hostinger\WhmcsModule;

use WHMCS\Database\Capsule;
use Exception;
use Hostinger\Configuration;

class Helper
{
    /**
     * Initialize the API client using credentials from the WHMCS server record.
     */
    public static function getApiClient($params)
    {
        // Get API base URL and token from the server record
        list($apiUrl, $apiToken) = self::getApiCredentials($params['serverid']);
        return new HostingerApiClient($apiUrl, $apiToken);
    }

    /**
     * Retrieve and decrypt API credentials (base URL and token) from the WHMCS servers table.
     */
    public static function getApiCredentials($serverId)
    {
        // Fetch server record from database
        $server = Capsule::table('tblservers')->find($serverId);
        if (!$server) {
            throw new Exception("Server credentials not found");
        }
        $apiUrl   = 'https://' . $server->hostname;
        $encToken = $server->password;
        // Decrypt the API token using WHMCS encryption
        $apiToken = self::decryptPassword($encToken);
        if (!$apiToken) {
            throw new Exception("API token decryption failed");
        }
        return [$apiUrl, $apiToken];
    }

    /**
     * Get the API configuration for the given server.
     */
    public static function getApiConfig($params)
    {
        $server = Capsule::table('tblservers')->find($params['serverid']);
        if (!$server) {
            throw new Exception("Server credentials not found");
        }

        $apiToken = self::decryptPassword($server->password);

        $config = Configuration::getDefaultConfiguration()
            ->setAccessToken($apiToken)
            ->setHost($server->hostname);

        return $config;
    }

    /**
     * Decrypt an encrypted password using WHMCS provided methods.
     */
    private static function decryptPassword($encryptedValue)
    {
        // Use WHMCS built-in decrypt function if available
        if (function_exists('decrypt')) {
            try {
                return decrypt($encryptedValue);
            } catch (Exception $e) {
                // Fall back to localAPI if direct decrypt fails
            }
        }
        // Attempt decryption via WHMCS localAPI as a fallback
        if (function_exists('localAPI')) {
            $response = localAPI('DecryptPassword', ['password2' => $encryptedValue]);
            if (!empty($response['password'])) {
                return $response['password'];
            }
        }
        // If decryption is not possible, return the original (encrypted) value
        return $encryptedValue;
    }

    /**
     * Format the plan list into key-value pairs for dropdown (priceId => "PlanName - Term").
     */
    public static function formatPlanOptions($planList)
    {
        $options = [];
        foreach ($planList as $planOption) {
            // Each $planOption has 'priceId', 'planName', 'period', 'periodUnit'
            $planName = $planOption['planName'];
            $period   = $planOption['period'];
            $unit     = $planOption['periodUnit'];
            // Convert period/unit to a human-friendly term (e.g., 1 month vs 12 months)
            $termLabel = self::formatPeriod($period, $unit);
            $options[$planOption['priceId']] = "{$planName} - {$termLabel}";
        }
        return $options;
    }

    /**
     * Format the datacenter list into key-value pairs (id => "City (Country)").
     */
    public static function formatDatacenterOptions($locations)
    {
        $options = [];
        foreach ($locations as $dc) {
            // Each $dc has 'id', 'name' (code), 'city', 'location' (country code)
            $city    = !empty($dc['city']) ? $dc['city'] : $dc['name'];
            $country = !empty($dc['location']) ? strtoupper($dc['location']) : '';
            $label   = $country ? "{$city} ({$country})" : $city;
            $options[$dc['id']] = $label;
        }
        return $options;
    }

    /**
     * Format the OS templates list into key-value pairs (template ID => name).
     */
    public static function formatOsOptions($templates)
    {
        $options = [];
        foreach ($templates as $tpl) {
            $options[$tpl['id']] = $tpl['name'];
        }
        return $options;
    }

    /**
     * Helper to format billing period and unit into a clean term label.
     * For example: (1, 'month') -> '1 Month'; (12, 'month') -> '12 Months'
     */
    private static function formatPeriod($period, $unit)
    {
        $unit = strtolower($unit);
        switch ($unit) {
            case 'month':
                return $period == 1 ? "1 Month" : "{$period} Months";
            case 'year':
                return $period == 1 ? "1 Year" : "{$period} Years";
            case 'week':
                return $period == 1 ? "1 Week" : "{$period} Weeks";
            case 'day':
                return $period == 1 ? "1 Day" : "{$period} Days";
            default:
                return "{$period} " . ucfirst($unit);
        }
    }
}
