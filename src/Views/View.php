<?php

namespace Hostinger\WhmcsModule\Views;

use Exception;

class View
{
    protected function executeAction($methodName, $params, $details)
    {
        if (method_exists($this, $methodName)) {
            return call_user_func([$this, $methodName], $params, $details);
        } else {
            throw new Exception("Method $methodName does not exist.");
        }
    }
}