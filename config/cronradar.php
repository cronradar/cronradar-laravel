<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CronRadar API Key
    |--------------------------------------------------------------------------
    |
    | Your CronRadar API key from https://cronradar.com/dashboard
    | Format: ck_app_xxxxx_yyyyy
    |
    | This can also be set via the CRONRADAR_API_KEY environment variable.
    |
    */

    'api_key' => env('CRONRADAR_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for CronRadar API. Usually you don't need to change this.
    |
    */

    'base_url' => env('CRONRADAR_BASE_URL', 'https://cronradar.com'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Method
    |--------------------------------------------------------------------------
    |
    | The HTTP method to use for pings. Options: GET, POST
    |
    */

    'method' => env('CRONRADAR_METHOD', 'GET'),

    /*
    |--------------------------------------------------------------------------
    | Default Grace Period
    |--------------------------------------------------------------------------
    |
    | Default grace period in seconds for monitors (how long to wait before
    | alerting after a missed execution).
    |
    */

    'grace_period' => env('CRONRADAR_GRACE_PERIOD', 60),

    /*
    |--------------------------------------------------------------------------
    | Monitor All Tasks
    |--------------------------------------------------------------------------
    |
    | When enabled, all scheduled tasks are automatically monitored without
    | needing ->monitor() on each task. Use ->skipMonitor() to opt-out.
    |
    | false = Selective mode: Only tasks with ->monitor() are monitored
    | true = MonitorAll mode: All tasks monitored unless ->skipMonitor()
    |
    */

    'monitor_all' => env('CRONRADAR_MONITOR_ALL', false),

];
