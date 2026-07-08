<?php

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asset_page_security_tags(string $assetPrefix = ''): string
{
    $assetPrefix = rtrim($assetPrefix, '/');
    $assetPrefix = $assetPrefix === '' ? '' : $assetPrefix . '/';

    return asset_csrf_meta()
        . PHP_EOL
        . '<script src="' . htmlspecialchars($assetPrefix . 'assets/app-security.js', ENT_QUOTES, 'UTF-8') . '" defer></script>'
        . PHP_EOL
        . '<link rel="stylesheet" href="' . htmlspecialchars($assetPrefix . 'assets/app-ui.css', ENT_QUOTES, 'UTF-8') . '">'
        . PHP_EOL
        . '<link rel="stylesheet" href="' . htmlspecialchars($assetPrefix . 'assets/white-theme.css', ENT_QUOTES, 'UTF-8') . '">';
}
