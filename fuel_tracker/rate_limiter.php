<?php
declare(strict_types=1);

function require_rate_limit(int $maxRequests, int $windowSeconds, string $bucket = 'default', string $responseType = 'json'): void
{
    if ($maxRequests < 1 || $windowSeconds < 1) {
        return;
    }

    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = hash('sha256', $bucket . '|' . $clientIp);
    $storageDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fuel_tracker_rate_limits';

    if (!is_dir($storageDir) && !mkdir($storageDir, 0700, true) && !is_dir($storageDir)) {
        error_log('Rate limiter storage directory could not be created: ' . $storageDir);
        return;
    }

    if (random_int(1, 100) === 1) {
        cleanup_rate_limit_files($storageDir, $windowSeconds);
    }

    $filePath = $storageDir . DIRECTORY_SEPARATOR . $key . '.json';
    $now = time();
    $state = [
        'window_start' => $now,
        'count' => 0,
    ];

    $handle = fopen($filePath, 'c+');
    if ($handle === false) {
        error_log('Rate limiter state file could not be opened: ' . $filePath);
        return;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return;
        }

        $contents = stream_get_contents($handle);
        if ($contents !== false && $contents !== '') {
            $decoded = json_decode($contents, true);
            if (is_array($decoded) && isset($decoded['window_start'], $decoded['count'])) {
                $state = [
                    'window_start' => (int) $decoded['window_start'],
                    'count' => (int) $decoded['count'],
                ];
            }
        }

        if (($now - $state['window_start']) >= $windowSeconds) {
            $state = [
                'window_start' => $now,
                'count' => 0,
            ];
        }

        $state['count']++;
        $retryAfter = max(1, $windowSeconds - ($now - $state['window_start']));

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($state));
        fflush($handle);

        if ($state['count'] > $maxRequests) {
            send_rate_limit_response($retryAfter, $responseType);
        }
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function cleanup_rate_limit_files(string $storageDir, int $windowSeconds): void
{
    $expiresBefore = time() - max($windowSeconds * 2, 3600);
    foreach (glob($storageDir . DIRECTORY_SEPARATOR . '*.json') ?: [] as $filePath) {
        if (is_file($filePath) && filemtime($filePath) < $expiresBefore) {
            @unlink($filePath);
        }
    }
}

function send_rate_limit_response(int $retryAfter, string $responseType): void
{
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    header('X-RateLimit-Retry-After: ' . $retryAfter);

    if ($responseType === 'html') {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<tr><td colspan="11" class="text-center text-warning">Too many requests. Please try again in ' . $retryAfter . ' seconds.</td></tr>';
        exit;
    }

    if ($responseType === 'text') {
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Too many requests. Please try again in ' . $retryAfter . ' seconds.';
        exit;
    }

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'message' => 'Too many requests. Please try again shortly.',
        'retry_after' => $retryAfter,
    ]);
    exit;
}
