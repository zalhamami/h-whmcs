<?php

namespace Hostinger\WhmcsModule;

class Constants
{
    const HOSTINGER_TABLE = 'hostinger_services';
    const SERVER_TYPE = 'hostinger';

    // Config option labels
    const CONFIG_LABEL_PLAN       = 'Plan';
    const CONFIG_LABEL_DATACENTER = 'Datacenter';
    const CONFIG_LABEL_OS         = 'Operating System';

    // Custom field labels (for storing provisioned service info)
    const CUSTOM_FIELD_VM_ID  = 'Hostinger VM ID';
    const CUSTOM_FIELD_SUB_ID = 'Hostinger Subscription ID';
    
    /**
     * WHMCS Configurable Option group label for Datacenter (used in Configurable Options)
     */
    const CONFIGURABLE_OPTION_GROUP = 'Hostinger Options';

    /**
     * Hostinger VPS action lock
     */
    const ACTION_LOCKED = 'locked';
    const ACTION_UNLOCKED = 'unlocked';

    /* WHMCS Statuses */
    const STATUS_ACTIVE = 'Active';

    const VM_ACTION_REBOOT = 'reboot';
    const VM_ACTION_RESET_FIREWALL = 'resetFirewall';
}
