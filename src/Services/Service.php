<?php

namespace Hostinger\WhmcsModule\Services;

use Exception;
use Hostinger\WhmcsModule\Constants;
use WHMCS\Database\Capsule;

class Service
{
    protected $params;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function getService($serviceId)
    {
        $service = Capsule::table(Constants::HOSTINGER_TABLE)
            ->where('whmcs_service_id', $serviceId)
            ->first();

        if (!$service) {
            throw new Exception('Unable to find related service');
        }

        return $service;
    }
}
