<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/defines.php';
require_once ROOTPATH . '/init.php';

use Hostinger\WhmcsModule\Constants;
use WHMCS\Database\Capsule;

if (!$_SESSION['adminid']) {
    die('Access denied');
}

/** Create service ID maping table */
if (!Capsule::schema()->hasTable(Constants::HOSTINGER_TABLE)) {
    Capsule::schema()->create(
        Constants::HOSTINGER_TABLE,
        function ($table) {
            $table->integer('whmcs_service_id')->unique();
            $table->integer('hservice_id')->index();
            $table->integer('subscription_id')->index();
            $table->text('details')->nullable();
            $table->timestamp('updated_at')->nullable();
        }
    );
}

if ($_GET['truncate']) {
    // Delete components
    Capsule::table('tblproductconfiglinks')->truncate();
    Capsule::table('tblproductconfiggroups')->truncate();
    Capsule::table('tblproductconfigoptions')->truncate();
    Capsule::table('tblproductconfigoptionssub')->truncate();

    // Delete products
    Capsule::table('tblproducts')->truncate();

    // Delete upgrades
    Capsule::table('tblproduct_upgrade_products')->truncate();

    // Delete pricing
    Capsule::table('tblpricing')->truncate();

    // Upgrades
    Capsule::table('tblupgrades')->truncate();

    // Config options
    Capsule::table('tblhostingconfigoptions')->truncate();

    echo 'Product tables truncated. ';
} else {
    echo 'Install successfull. ';
}
