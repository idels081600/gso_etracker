<?php

declare(strict_types=1);

const MODULE_ASSET_VERSION = '3.5.0';

$modules = [
    'app' => 'app.mjs',
    'scoring-engine' => 'scoring-engine.mjs',
    'tournament-app' => 'tournament-app.mjs',
    'tournament-engine' => 'tournament-engine.mjs',
    'tournament-sync' => 'tournament-sync.mjs',
    'mobile-scorer' => 'mobile-scorer.mjs',
];

$module = $_GET['module'] ?? '';
if (!is_string($module) || !isset($modules[$module])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo 'JavaScript module not found.';
    exit;
}

$source = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . $modules[$module]);
if ($source === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    echo 'Unable to load JavaScript module.';
    exit;
}

$moduleUrl = static fn (string $name): string => sprintf(
    '"./js-module.php?module=%s&v=%s"',
    rawurlencode($name),
    rawurlencode(MODULE_ASSET_VERSION)
);

$source = strtr($source, [
    '"./scoring-engine.mjs"' => $moduleUrl('scoring-engine'),
    '"./tournament-engine.mjs"' => $moduleUrl('tournament-engine'),
    '"./tournament-sync.mjs"' => $moduleUrl('tournament-sync'),
]);

header('Content-Type: application/javascript; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');
echo $source;
