<?php

require_once dirname(__DIR__) . '/auth_security.php';

$failures = [];

function expect_turnstile($condition, $message)
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

function configure_turnstile_test($siteKey, $secretKey, $allowedHostnames, $requestHostname)
{
    $_ENV['TURNSTILE_SITE_KEY'] = $siteKey;
    $_ENV['TURNSTILE_SECRET_KEY'] = $secretKey;
    $_ENV['TURNSTILE_ALLOWED_HOSTNAMES'] = $allowedHostnames;
    $_SERVER['HTTP_HOST'] = $requestHostname;
}

configure_turnstile_test(
    '1x00000000000000000000AA',
    '1x0000000000000000000000000000000AA',
    'gso.etracker.tagbilaran.gov.ph',
    'gso.etracker.tagbilaran.gov.ph'
);
expect_turnstile(
    turnstile_configuration_issue() === 'test_keys_not_allowed',
    'Production host must reject Cloudflare test credentials.'
);

configure_turnstile_test(
    '1x00000000000000000000AA',
    '1x0000000000000000000000000000000AA',
    'localhost,127.0.0.1',
    'localhost:8080'
);
expect_turnstile(
    turnstile_configuration_issue() === null,
    'Localhost must continue to allow Cloudflare test credentials.'
);

configure_turnstile_test(
    'production-site-key',
    'production-secret-key',
    'gso.etracker.tagbilaran.gov.ph',
    'gso.etracker.tagbilaran.gov.ph'
);
expect_turnstile(
    turnstile_configuration_issue() === null,
    'A complete production configuration must be accepted.'
);

$validResponse = validate_turnstile_response([
    'success' => true,
    'action' => 'login',
    'hostname' => 'gso.etracker.tagbilaran.gov.ph',
]);
expect_turnstile($validResponse['success'] === true, 'Valid production response must pass.');

$wrongAction = validate_turnstile_response([
    'success' => true,
    'action' => 'register',
    'hostname' => 'gso.etracker.tagbilaran.gov.ph',
]);
expect_turnstile($wrongAction['reason'] === 'action_mismatch', 'Wrong action must be rejected.');

$wrongHostname = validate_turnstile_response([
    'success' => true,
    'action' => 'login',
    'hostname' => 'attacker.example',
]);
expect_turnstile($wrongHostname['reason'] === 'hostname_mismatch', 'Wrong hostname must be rejected.');

$loginSource = file_get_contents(dirname(__DIR__) . '/login_v2.php');
expect_turnstile(
    preg_match('/id="login_btn"[^>]*disabled[^>]*aria-disabled="true"/', $loginSource) === 1,
    'Login button must render disabled before verification.'
);
expect_turnstile(
    strpos($loginSource, 'data-callback="onTurnstileSuccess"') !== false
        && strpos($loginSource, 'data-expired-callback="onTurnstileExpired"') !== false
        && strpos($loginSource, 'data-error-callback="onTurnstileError"') !== false,
    'Turnstile lifecycle callbacks must remain connected.'
);
expect_turnstile(
    strpos($loginSource, "loginForm.addEventListener('submit'") !== false,
    'Login form must retain its no-token submission guard.'
);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}" . PHP_EOL);
    }
    exit(1);
}

echo 'Turnstile security tests passed.' . PHP_EOL;
