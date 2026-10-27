<?php

require_once dirname(__DIR__) . '/auth_security.php';

$failures = [];

function expect_rate_limit($condition, $message)
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$config = login_endpoint_rate_limit_config();
$primaryIp = '203.0.113.250';
$otherIp = '203.0.113.251';
$startedAt = 1700000000;

clear_login_endpoint_rate_limit($primaryIp);
clear_login_endpoint_rate_limit($otherIp);

for ($attempt = 1; $attempt <= $config['max_requests']; $attempt++) {
    $result = consume_login_endpoint_rate_limit($primaryIp, $startedAt);
    expect_rate_limit($result['storage_available'] === true, 'Rate-limit storage must be available.');
    expect_rate_limit($result['limited'] === false, "Request {$attempt} should be allowed.");
}

$blocked = consume_login_endpoint_rate_limit($primaryIp, $startedAt);
expect_rate_limit($blocked['limited'] === true, 'The first request over the threshold must be blocked.');
expect_rate_limit(
    $blocked['retry_after'] === $config['block_seconds'],
    'A newly blocked client must receive the full block duration.'
);

$stillBlocked = consume_login_endpoint_rate_limit($primaryIp, $startedAt + 60);
expect_rate_limit($stillBlocked['limited'] === true, 'A blocked client must remain blocked before expiry.');
expect_rate_limit(
    $stillBlocked['retry_after'] === $config['block_seconds'] - 60,
    'Retry timing must count down from the block expiry.'
);

$otherClient = consume_login_endpoint_rate_limit($otherIp, $startedAt + 60);
expect_rate_limit($otherClient['limited'] === false, 'One IP address must not block a different IP address.');

$afterExpiry = consume_login_endpoint_rate_limit($primaryIp, $startedAt + $config['block_seconds'] + 1);
expect_rate_limit($afterExpiry['limited'] === false, 'Requests must be allowed again after block expiry.');

$checkLoginSource = file_get_contents(dirname(__DIR__) . '/check_login.php');
$rateLimitPosition = strpos($checkLoginSource, 'consume_login_endpoint_rate_limit');
$databasePosition = strpos($checkLoginSource, "require_once 'passlip/dbh.php'");
expect_rate_limit(
    $rateLimitPosition !== false && $databasePosition !== false && $rateLimitPosition < $databasePosition,
    'The endpoint limiter must run before the database connection.'
);
expect_rate_limit(
    strpos($checkLoginSource, 'http_response_code(429)') !== false
        && strpos($checkLoginSource, "header('Retry-After: '") !== false,
    'Blocked requests must return HTTP 429 with Retry-After.'
);

clear_login_endpoint_rate_limit($primaryIp);
clear_login_endpoint_rate_limit($otherIp);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}" . PHP_EOL);
    }
    exit(1);
}

echo 'Login endpoint rate-limit tests passed.' . PHP_EOL;
