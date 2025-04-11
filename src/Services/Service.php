<?php

namespace Hostinger\WhmcsModule\Services;

class Service
{
    protected $params;

    public function __construct($params)
    {
        $this->params = $params;
    }
}
