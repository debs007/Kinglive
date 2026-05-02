<?php
return [
    'app_id'          => env('AGORA_APP_ID', ''),
    'certificate'     => env('AGORA_APP_CERTIFICATE', ''),
    'app_certificate' => env('AGORA_APP_CERTIFICATE', ''), // alias for DB override
    'token_builder'   => env('AGORA_TOKEN_BUILDER', 'v2'),
];