<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/alshned.php';

$oem = trim($_GET['oem'] ?? $_POST['oem'] ?? '');

if (empty($oem)) {
    echo json_encode([
        'success' => false,
        'error' => 'OEM-код не передан',
        'count' => 0,
        'products' => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = searchAlshnedAuto($oem);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
