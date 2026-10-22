<?php

return [
    // Requests slower than this are logged as "slow_request" (0 disables).
    'slow_request_ms' => (int) env('LOG_SLOW_REQUEST_MS', 1500),

    // Bearer token for GET /api/v1/metrics (Prometheus format). Unset = endpoint returns 404.
    'metrics_token' => env('METRICS_TOKEN'),

    // Queue backlog beyond which /health reports "degraded".
    'queue_backlog_warning' => (int) env('HEALTH_QUEUE_BACKLOG_WARNING', 500),

    // Browser errors are accepted at most this many per minute per client and truncated to these sizes.
    'client_error_rate_per_minute' => 20,
    'client_error_max_message' => 500,
    'client_error_max_stack' => 4000,
];
