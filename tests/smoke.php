<?php

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

define('WHMCS', true);

function check($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function select_query($table, $columns, $where)
{
    if ($table === 'mod_Smspby_templates') {
        return ['active' => 1];
    }
    if ($table === 'mod_Smspby_otp') {
        return $where['user_id'] === 1 ? ['otp' => '123456'] : false;
    }
    return false;
}

function mysql_fetch_assoc($result)
{
    return $result;
}

function mysql_fetch_array($result)
{
    return $result;
}

function update_query($table, $values, $where)
{
    $GLOBALS['updatedOtp'] = [$table, $values, $where];
}

function add_hook($name, $priority, $callback, $fallback = '')
{
    check(is_string($name) && is_callable($callback), 'Invalid hook registration');
    $GLOBALS['registeredHooks'][$name][] = $callback;
}

$moduleDirectory = realpath($argv[1] ?? dirname(__DIR__));
check($moduleDirectory !== false, 'Module directory is missing');
chdir(sys_get_temp_dir());

require $moduleDirectory . '/hooks.php';
require $moduleDirectory . '/smspby_whmcs.php';

$metadata = (new Sms())->getHooks();
check(count($metadata) > 0 && $metadata === (new Sms())->getHooks(), 'Hook metadata must survive repeated reads');
check(isset($registeredHooks['InvoicePaid'], $registeredHooks['AdminAreaClientSummaryPage']), 'SMS and OTP hooks must load');
check(smspby_whmcs_config()['name'] === 'SMSp.by', 'Module configuration failed to load');

$_GET = $_POST = [];
$_ADDONLANG = [];
require $moduleDirectory . '/lang/russian.php';
ob_start();
smspby_whmcs_output(['_lang' => $_ADDONLANG]);
$settingsOutput = ob_get_clean();
check(strpos($settingsOutput, 'tabselected') !== false, 'Settings tab failed to render');

$sms = new Sms();
$sms->setUserid(2);
$sms->setGsmnumber('+375000000000');
check(!$sms->verifyotp(''), 'OTP verification must fail without a stored OTP');
$sms->setUserid(1);
check($sms->verifyotp('123456') && $updatedOtp[1]['verify'] === 1, 'OTP verification failed');

unset($_SERVER['HTTP_HOST']);
$sdk = $sms->createObject();
check($sdk->getOptions()['website'] === '', 'SDK must load without a web request');
$response = $sdk->pullReport('123');
check($response['status'] === false && isset($response['error']), 'Missing credentials must return an error');

printf("Module smoke check passed on PHP %s\n", PHP_VERSION);
