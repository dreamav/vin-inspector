<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';

$wmiData = getWmiConfig();

echo json_encode([
    'success' => true,
    'data' => $wmiData
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
