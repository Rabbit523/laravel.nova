<?php

return [
    'certificate_store_path'        => env('CERTIFICATE_PATH', storage_path() . '/pass.p12'),
    'certificate_store_password'    => env('CERTIFICATE_PASS', ''),
    'wwdr_certificate_path'         => env('WWDR_CERTIFICATE', resource_path() . '/assets/wallet/AppleWWDRCA.pem'),

    'pass_type_identifier'          => env('PASS_TYPE_IDENTIFIER', ''),
    'organization_name'             => env('ORGANIZATION_NAME', ''),
    'team_identifier'               => env('TEAM_IDENTIFIER', ''),
];
