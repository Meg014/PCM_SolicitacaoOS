<?php
declare(strict_types=1);

use function Cake\Core\env;

return ['Sso' => [
    'enabled' => filter_var(env('PCM_SSO_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'development' => env('APP_ENV', 'production') === 'development',
    'issuer' => env('PCM_SSO_ISSUER', ''),
    'clientId' => env('PCM_SSO_CLIENT_ID', 'pcm-solicitacao-os'),
    'clientSecret' => env('PCM_SSO_CLIENT_SECRET', ''),
    'callback' => env('PCM_SSO_CALLBACK', ''),
    'logoutReturn' => env('PCM_SSO_LOGOUT_RETURN', ''),
    'centralUrl' => env('PCM_CENTRAL_URL', ''),
    'integrationSecret' => env('PCM_CENTRAL_INTEGRATION_SECRET', ''),
]];
