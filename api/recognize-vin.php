<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Метод не поддерживается. Используйте POST.'
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
}

$projectRoot = dirname(__DIR__);
$tmpDir = $projectRoot . DIRECTORY_SEPARATOR . 'ocr' . DIRECTORY_SEPARATOR . 'tmp_images';

if (!is_dir($tmpDir)) {
    @mkdir($tmpDir, 0777, true);
}

// Ensure unique temp file path
$tmpFileName = 'vin_' . time() . '_' . mt_rand(10000, 99999) . '.jpg';
$tmpFilePath = $tmpDir . DIRECTORY_SEPARATOR . $tmpFileName;

$fileSaved = false;

// 1. Check multipart/form-data upload
if (!empty($_FILES)) {
    $fileKey = null;
    foreach (['photo', 'image', 'file'] as $k) {
        if (isset($_FILES[$k])) {
            $fileKey = $k;
            break;
        }
    }
    if ($fileKey === null) {
        $keys = array_keys($_FILES);
        $fileKey = $keys[0];
    }

    $uploaded = $_FILES[$fileKey];
    if ($uploaded['error'] === UPLOAD_ERR_OK && is_uploaded_file($uploaded['tmp_name'])) {
        $fileSaved = move_uploaded_file($uploaded['tmp_name'], $tmpFilePath);
    }
}

// 2. Check JSON payload with base64 data
if (!$fileSaved) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $data = json_decode($rawInput, true);
        if (is_array($data) && !empty($data['image'])) {
            $imageData = $data['image'];
            // Remove data URI scheme header if present
            if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                $imageData = substr($imageData, strpos($imageData, ',') + 1);
            }
            $decoded = base64_decode($imageData);
            if ($decoded !== false && strlen($decoded) > 0) {
                $fileSaved = (file_put_contents($tmpFilePath, $decoded) !== false);
            }
        }
    }
}

if (!$fileSaved || !file_exists($tmpFilePath) || filesize($tmpFilePath) === 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Файл фотографии не передан или повреждён при загрузке.'
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
}

// Locate Python executable and recognition script
$pythonCandidates = [
    $projectRoot . DIRECTORY_SEPARATOR . 'ocr' . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe', // Windows venv
    $projectRoot . DIRECTORY_SEPARATOR . 'ocr' . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',        // Linux/VDS venv
    $projectRoot . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
    $projectRoot . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python',
    'python3',
    'python'
];

$pythonBin = 'python';
foreach ($pythonCandidates as $candidate) {
    if (file_exists($candidate)) {
        $pythonBin = $candidate;
        break;
    }
}

$scriptPath = $projectRoot . DIRECTORY_SEPARATOR . 'ocr' . DIRECTORY_SEPARATOR . 'recognize_vin.py';
if (!file_exists($scriptPath)) {
    $scriptPath = $projectRoot . DIRECTORY_SEPARATOR . 'recognize_vin.py';
}

// Execute OCR recognition command
$cmd = escapeshellarg($pythonBin) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($tmpFilePath) . ' 2>&1';
$output = shell_exec($cmd);

// CRITICAL CLEANUP: Delete temporary uploaded image immediately to prevent disk clutter
if (file_exists($tmpFilePath)) {
    @unlink($tmpFilePath);
}

// Parse JSON output from Python script
$jsonStart = strpos($output, '{');
$jsonEnd = strrpos($output, '}');

if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd >= $jsonStart) {
    $jsonStr = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
    $result = json_decode($jsonStr, true);
    if (is_array($result)) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit(0);
    }
}

// Fallback in case of raw Python exception or unparsed output
echo json_encode([
    'success' => false,
    'vin' => null,
    'confidence' => 0.0,
    'check_digit_valid' => false,
    'error' => 'Ошибка выполнения модуля распознавания: ' . trim($output)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
