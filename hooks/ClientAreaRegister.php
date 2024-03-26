<?php

$hook = [
    'hook'           => 'ClientAreaRegister',
    'function'       => 'ClientAreaRegister_clientarea',
    'description'    => [
        'english'    => 'After Client Registration (OTP) Mobile Verification'
    ],
    'type'           => 'client',
    'extra'          => '',
    'defaultmessage' => 'Уважаемый {firstname} {lastname}, код активации номера {otp}.',
    'variables'      => '{firstname},{lastname},{otp}'
];

if (!function_exists('ClientAreaRegister_clientarea')) {
    function ClientAreaRegister_clientarea($args)
    {
        $class    = new Sms();
        $template = $class->getTemplateDetails(__FUNCTION__);
        
        if ($template['active'] == 0) {
            return null;
        }
    }
}

return $hook;