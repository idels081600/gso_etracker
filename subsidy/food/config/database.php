<?php

require_once __DIR__ . '/../bootstrap.php';

$mysqli = new mysqli(
    env('DB_HOST'),
    env('DB_USER'),
    env('DB_PASS'),
    env('DB_NAME')
);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

return $mysqli;