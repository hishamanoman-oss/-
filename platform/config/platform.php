<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Deployment Mode
    |--------------------------------------------------------------------------
    | Supported modes: "saas" (multi-tenant shared database) or
    | "self_hosted" (single-tenant default with Ed25519 license validation).
    */
    'deployment_mode' => env('PLATFORM_DEPLOYMENT_MODE', env('APP_DEPLOYMENT_MODE', 'saas')),

    /*
    |--------------------------------------------------------------------------
    | Ed25519 Self-Hosted License Verification Public Key (Base64)
    |--------------------------------------------------------------------------
    */
    'license_public_key' => env('PLATFORM_LICENSE_PUBLIC_KEY', ''),
];
