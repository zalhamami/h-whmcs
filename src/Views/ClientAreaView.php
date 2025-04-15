<?php

namespace Hostinger\WhmcsModule\Views;

use Exception;
use Hostinger\WhmcsModule\Constants;
use Hostinger\WhmcsModule\Helpers\Helper;
use Hostinger\WhmcsModule\Services\VirtualMachineService;

class ClientAreaView extends View
{
    public function render($params)
    {
        $action = !empty($_REQUEST['act']) ? $_REQUEST['act'] : null;

        try {
            $vmService = new VirtualMachineService($params);

            $details = $vmService->getVmDetails($params);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        $isLocked = $details->getActionsLock() === Constants::ACTION_LOCKED;
        if ($isLocked) {
            return $this->serverBusy($details);
        }

        if ($action) {
            return $this->executeAction($action, $params, $details);
        }

        return $this->default($details);
    }

    public function default($details)
    {
        return [
            'tabOverviewReplacementTemplate' => 'templates/clientarea/clientarea.tpl',
            'templateVariables' => [
                'details' => $details
            ]
        ];
    }

    public function serverBusy($details)
    {
        return [
            'tabOverviewReplacementTemplate' => 'templates/clientarea/serverbusy.tpl',
            'templateVariables' => [
                'details' => $details
            ]
        ];
    }

    public function reboot($params, $details)
    {
        $vmService = new VirtualMachineService($params);
        if (!empty($_POST['confirm'])) {
            $vmService->restartVm($details->getId());

            Helper::redirect(
                Helper::getActionLink($params, Constants::VM_ACTION_REBOOT)
            );
        }

        return [
            'tabOverviewReplacementTemplate' => 'templates/clientarea/pages/reboot.tpl',
            'templateVariables' => [
                'details' => $details
            ]
        ];
    }
}
