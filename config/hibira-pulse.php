<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Account API token
    |--------------------------------------------------------------------------
    |
    | A personal API token with read access, from
    | https://hibira.io/portal/api-tokens. The card reads your app's usage
    | and plan limits with it. You can restrict the token to this one app.
    |
    */

    'token' => env('HIBIRA_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | App
    |--------------------------------------------------------------------------
    |
    | The same credentials as your broadcasting connection. The key and secret
    | are optional: with them, the card also lists the busiest channels.
    |
    */

    'app_id' => env('HIBIRA_APP_ID'),
    'key' => env('HIBIRA_APP_KEY'),
    'secret' => env('HIBIRA_APP_SECRET'),
    'host' => env('HIBIRA_HOST', 'ws.hibira.io'),
    'port' => (int) env('HIBIRA_PORT', 443),
    'scheme' => env('HIBIRA_SCHEME', 'https'),

    /*
    |--------------------------------------------------------------------------
    | Card
    |--------------------------------------------------------------------------
    |
    | cache_seconds: how long one reading is reused (the dashboard polls).
    | channels: how many of the busiest channels to list, 0 to hide the list.
    |
    */

    'cache_seconds' => (int) env('HIBIRA_PULSE_CACHE_SECONDS', 60),
    'channels' => (int) env('HIBIRA_PULSE_CHANNELS', 10),

    'api_url' => env('HIBIRA_API_URL', 'https://hibira.io/api/v1'),

];
