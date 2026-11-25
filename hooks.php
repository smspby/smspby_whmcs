<?php
if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

require_once __DIR__ . '/smsclass.php';
$class = new Sms();
$hooks = $class->getHooks();

foreach($hooks as $hook){
    add_hook($hook['hook'], 1, $hook['function'], "");
}

require_once __DIR__ . '/hooks/otpActionHook.php';