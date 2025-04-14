<?php

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/adminfunctions.php';
require_once __DIR__ . '/../../../includes/clientfunctions.php';
require_once __DIR__ . '/../../../includes/configoptionsfunctions.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/hostinger.php';

use WHMCS\Database\Capsule;
use Hostinger\WhmcsModule\Constants;

/**
 * Utility function to create or update a configurable option.
 */
function upsertConfigurableOption($groupId, $optionName, array $values)
{
    $existingOption = Capsule::table('tblproductconfigoptions')
        ->where('gid', $groupId)
        ->where('optionname', $optionName)
        ->first();

    if ($existingOption) {
        // Delete old sub-options
        Capsule::table('tblproductconfigoptionssub')->where('configid', $existingOption->id)->delete();
        $optionId = $existingOption->id;
    } else {
        $optionId = Capsule::table('tblproductconfigoptions')->insertGetId([
            'gid' => $groupId,
            'optionname' => $optionName,
            'optiontype' => 1, // Dropdown
            'qtyminimum' => 0,
            'qtymaximum' => 0,
            'order' => 0,
        ]);
    }

    // Insert new sub-options
    $i = 0;
    foreach ($values as $value) {
        Capsule::table('tblproductconfigoptionssub')->insert([
            'configid' => $optionId,
            'optionname' => $value,
            'sortorder' => $i++,
        ]);
    }
}

/**
 * Create or fetch the configurable option group.
 */
function getOrCreateConfigurableGroup($groupName)
{
    $group = Capsule::table('tblproductconfiggroups')->where('name', $groupName)->first();
    if ($group) {
        return $group->id;
    }

    return Capsule::table('tblproductconfiggroups')->insertGetId([
        'name' => $groupName,
        'description' => 'Auto-generated by Hostinger module',
    ]);
}

try {
    $server = Capsule::table('tblservers')
    ->where('type', 'hostinger')
    ->first();

    if (!$server) {
        throw new Exception("No Hostinger server found. Please add one in WHMCS → Setup → Products/Services → Servers.");
    }

    $serverParams = ['serverid' => $server->id];
    $groupId = getOrCreateConfigurableGroup(Constants::CONFIGURABLE_OPTION_GROUP);

    $dcOptions = array_values(hostinger_LoadDatacenters($serverParams));
    upsertConfigurableOption($groupId, Constants::CONFIG_LABEL_DATACENTER, $dcOptions);

    $osOptions = array_values(hostinger_LoadOsTemplates($serverParams));
    upsertConfigurableOption($groupId, Constants::CONFIG_LABEL_OS, $osOptions);

    echo "Configurable Options updated under group ID: {$groupId}\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
