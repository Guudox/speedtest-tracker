<?php

use Carbon\Carbon;

$legacyConnectivityUrl = env('SPEEDTEST_CHECKINTERNET_URL');
$preflightUrl = env('SPEEDTEST_EXTERNAL_IP_URL', $legacyConnectivityUrl ?: 'http://connectivitycheck.gstatic.com/generate_204');
$preflightHostname = env('SPEEDTEST_INTERNET_CHECK_HOSTNAME');

if ($preflightHostname === null || $preflightHostname === '') {
    $derivedHostname = parse_url((string) $preflightUrl, PHP_URL_HOST);
    $preflightHostname = is_string($derivedHostname) && $derivedHostname !== ''
        ? $derivedHostname
        : '1.1.1.1';
}

return [
    /**
     * General settings.
     */
    'build_date' => Carbon::parse('2026-02-08'),

    'build_version' => 'v1.13.9',

    'content_width' => env('CONTENT_WIDTH', '7xl'),

    'prune_results_older_than' => (int) env('PRUNE_RESULTS_OLDER_THAN', 0),

    'public_dashboard' => env('PUBLIC_DASHBOARD', false),

    'default_chart_range' => strtolower(env('DEFAULT_CHART_RANGE', '24h')),

    'service' => strtolower(env('SPEEDTEST_SERVICE', 'ookla')),

    /**
     * Speedtest settings.
     */
    'schedule' => env('SPEEDTEST_SCHEDULE', false),

    'servers' => env('SPEEDTEST_SERVERS'),

    'blocked_servers' => env('SPEEDTEST_BLOCKED_SERVERS'),

    'interface' => env('SPEEDTEST_INTERFACE'),

    'iperf3' => [
        'host' => env('IPERF3_HOST'),
        'port' => (int) env('IPERF3_PORT', 5201),
        'duration' => (int) env('IPERF3_DURATION', 10),
        'parallel' => (int) env('IPERF3_PARALLEL', 1),
        'bind' => env('IPERF3_BIND'),
    ],

    'preflight' => [
        'external_ip_url' => $preflightUrl,
        'internet_check_hostname' => $preflightHostname,
        'skip_ips' => env('SPEEDTEST_SKIP_IPS'),
    ],

    /**
     * IP filtering settings.
     */
    'allowed_ips' => env('ALLOWED_IPS'),

    /**
     * Threshold settings.
     */
    'threshold_enabled' => env('THRESHOLD_ENABLED', false),

    'threshold_download' => env('THRESHOLD_DOWNLOAD', 0),

    'threshold_upload' => env('THRESHOLD_UPLOAD', 0),

    'threshold_ping' => env('THRESHOLD_PING', 0),
];
