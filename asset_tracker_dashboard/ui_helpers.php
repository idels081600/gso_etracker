<?php

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asset_page_security_tags(): string
{
    return asset_csrf_meta()
        . PHP_EOL
        . '<script src="assets/app-security.js" defer></script>'
        . PHP_EOL
        . '<link rel="stylesheet" href="assets/app-ui.css">'
        . PHP_EOL
        . '<link rel="stylesheet" href="assets/white-theme.css">';
}
