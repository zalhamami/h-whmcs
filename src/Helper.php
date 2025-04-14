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
        if (function_exists('decrypt')) {
            try {
                return decrypt($encryptedValue);
            } catch (Exception $e) {
                // Fall back to localAPI if direct decrypt fails
            }
        }
        if (function_exists('localAPI')) {
            $response = localAPI('DecryptPassword', ['password2' => $encryptedValue]);
            if (!empty($response['password'])) {
                return $response['password'];
            }
        }
        
        return $encryptedValue;
    }

    /**
     * Format the plan list into key-value pairs for dropdown (priceId => "PlanName - Term").
     */
    public static function formatPlanOptions($planList)
    {
        $options = [];
        foreach ($planList as $planOption) {
            $planName = $planOption['planName'];
            $period   = $planOption['period'];
            $unit     = $planOption['periodUnit'];

            $termLabel = self::formatPeriod($period, $unit);
            $options[$planOption['priceId']] = "{$planName} - {$termLabel}";
        }
        return $options;
    }

    public static function getPlansFromResponse($response)
    {
        $planOptions = [];
        foreach ($response as $item) {
            if (!empty($item['category']) && strtoupper($item['category']) === 'VPS') {
                $planName = $item['name'] ?? $item['id'];
                if (!empty($item['prices']) && is_array($item['prices'])) {
                    foreach ($item['prices'] as $priceItem) {
                        $planOptions[] = [
                            'priceId'    => $priceItem['id'],
                            'planName'   => $planName,
                            'period'     => $priceItem['period'] ?? 1,
                            'periodUnit' => $priceItem['periodUnit'] ?? ''
                        ];
                    }
                }
            }
        }

        return self::formatPlanOptions($planOptions);
    }

    /**
     * Format the datacenter list into key-value pairs (id => "City (Country)").
     */
    public static function formatDatacenterOptions($locations)
    {
        $options = [];
        foreach ($locations as $dc) {
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

    public static function getConfigOptionWithFallback($params, $label)
    {
        return $params['configoptions'][$label] ?? $params['configoption' . self::getConfigIndex($label)] ?? null;
    }

    private static function getConfigIndex($label)
    {
        $map = [
            'Plan'        => 1,
            'Datacenter'  => 2,
            'OS Template' => 3
        ];
        return $map[$label] ?? null;
    }
}
