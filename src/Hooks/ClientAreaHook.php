<?php

use Hostinger\WhmcsModule\Constants;
use WHMCS\Database\Capsule;
use WHMCS\Lang;

function getHostingByServer($id)
{
    return Capsule::table('tblhosting')
        ->join('tblservers', 'tblservers.id', '=', 'tblhosting.server')
        ->where('tblhosting.id', $id)
        ->where('tblhosting.domainstatus', 'Active')
        ->where('tblservers.type', Constants::SERVER_TYPE)
        ->first();
}

add_hook('ClientAreaPrimarySidebar', 1, function ($sidebar) {

    if ($_REQUEST['action'] !== 'productdetails' || !$_REQUEST['id']) {
        return;
    }

    $id = $_REQUEST['id'];

    if (!getHostingByServer($id)) {
        return;
    }

    $actions = $sidebar->addChild(
        'actionsMenu', [
            'name' => 'Manage Server',
            'label' => Lang::trans('Management'),
            'order' => 15,
            'icon' => 'fas fa-cogs',
        ]
    );

    $actions->addChild(
        'actionsMenuRebootItem', [
            'name' => 'Reboot',
            'label' => Lang::trans('Reboot'),
            'uri' => "clientarea.php?action=productdetails&id={$id}&act=reboot",
            'order' => 0,
            'icon' => 'fa-fw fa-sync-alt'
        ]
    );

    $actions->addChild(
        'actionsMenuResetFirewallItem', [
            'name' => 'Reset Firewall',
            'label' => Lang::trans('Reset Firewall'),
            'uri' => "clientarea.php?action=productdetails&id={$id}&act=resetFirewall",
            'order' => 6,
            'icon' => 'fa-fw fa-fire'
        ]
    );
});

add_hook('ClientAreaSecondarySidebar', 1, function ($sidebar)
{
    if ($_REQUEST['action'] !== 'productdetails' || !$_REQUEST['id']) {
        return;
    }

    $id = $_REQUEST['id'];

    if (!getHostingByServer($id)) {
        return;
    }

    $sidebar->addChild(
        'actionsMenu', [
            'name' => 'Server Information',
            'label' => Lang::trans('Server Information'),
            'order' => 15,
            'icon' => 'fas fa-info',
        ]
    );
});

add_hook('ClientAreaFooterOutput', 1, function ($params) {
    if ($params['module'] === Constants::SERVER_TYPE && $params['action'] === 'productdetails') {
        return '<script type="text/javascript" src="modules/servers/' . Constants::SERVER_TYPE . '/assets/js/clientarea.js"></script>';
    }
});
