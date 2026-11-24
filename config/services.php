<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Configuration for external services and API integrations.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Email Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RateHawk API Integration
    |--------------------------------------------------------------------------
    |
    | Hotel supplier API configuration.
    | Supports sandbox and production environments.
    |
    */

    'ratehawk' => [
        'environment' => env('RATEHAWK_ENV', 'sandbox'),

        'sandbox_url' => 'https://api-sandbox.ratehawk.com',

        'production_url' => 'https://api.ratehawk.com',

        'key_id' => env('RATEHAWK_KEY_ID'),

        'api_key' => env('RATEHAWK_API_KEY'),
         'allow_sandbox_finish' => env('RATEHAWK_ALLOW_SANDBOX_FINISH', false),

        // Destination autocomplete scope (GET /api/v1/b2b/destinations, /admin/hotels/destinations):
        //   all           - worldwide RateHawk multicomplete regions (default)
        //   sandbox_known - only destinations known to return sandbox inventory
        //                   (Dubai, Paris, Los Angeles); no supplier call is made.
        // The RateHawk sandbox returns no regions from multicomplete, so use
        // sandbox_known until a production key is configured.
        'destination_scope' => env('RATEHAWK_DESTINATION_SCOPE', 'all'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Open Exchange Rates API
    |--------------------------------------------------------------------------
    |
    | Exchange rate provider for USD, EGP and SAR.
    |
    | Base currency: USD
    | Provider: Open Exchange Rates
    |
    | The API key is stored securely in the .env file.
    |
    */

    'open_exchange_rates' => [
        'app_id' => env('OPENEXCHANGERATES_APP_ID'),

        'url' => env(
            'OPENEXCHANGERATES_URL',
            'https://openexchangerates.org/api/latest.json'
        ),

        'timeout' => (int) env('OPENEXCHANGERATES_TIMEOUT', 10),
    ],

];
