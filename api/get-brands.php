<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';

$config = getBrandsConfig();

echo json_encode([
    'success' => true,
    'brands' => $config['brands']
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
