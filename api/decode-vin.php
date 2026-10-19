<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';

$vin = trim($_GET['vin'] ?? $_POST['vin'] ?? '');

if (empty($vin)) {
    echo json_encode([
        'success' => false,
        'error' => 'VIN-код не передан'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$vin = strtoupper(preg_replace('/\s+/', '', $vin));

if (strlen($vin) < 11) {
    echo json_encode([
        'success' => false,
        'error' => 'VIN-код слишком короткий (минимум 11-17 символов)'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Запрос к официальному бесплатному API NHTSA vPIC
$nhtsaUrl = "https://vpic.nhtsa.dot.gov/api/vehicles/decodevinvalues/" . urlencode($vin) . "?format=json";

$ch = curl_init($nhtsaUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING => '',
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
]);

$responseJson = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || empty($responseJson)) {
    echo json_encode([
        'success' => false,
        'error' => 'Не удалось получить ответ от NHTSA API: ' . ($curlErr ?: "HTTP {$httpCode}")
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$resData = json_decode($responseJson, true);
$result = $resData['Results'][0] ?? null;

if (!$result || empty($result['Make'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Автомобиль с данным VIN не найден в базе NHTSA'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$make = ucwords(strtolower(trim($result['Make'])));
$model = trim($result['Model']);
$year = trim($result['ModelYear']);
$bodyClass = trim($result['BodyClass']);
$driveType = trim($result['DriveType']);
$engineDisp = trim($result['DisplacementL']);
$engineCyl = trim($result['EngineCylinders']);
$engineHp = trim($result['EngineHP']);
$plantCountry = trim($result['PlantCountry']);

// Формируем красивую строку двигателя
$engineStr = '';
if (!empty($engineDisp)) {
    $engineStr .= $engineDisp . 'L ';
}
if (!empty($engineCyl)) {
    $engineStr .= 'V' . $engineCyl . ' ';
}
if (!empty($engineHp)) {
    $engineStr .= "({$engineHp} л.с.)";
}
$engineStr = trim($engineStr);

$carTitle = trim("{$year} {$make} {$model}");

// Сопоставляем марку с известными каталогами запчастей
$brandId = strtolower($make);
$catalogSite = '';
$catalogSiteCode = '';
$catalogHost = '';

if (stripos($make, 'Infiniti') !== false) {
    $brandId = 'infiniti';
    $catalogSite = 'https://www.infinitipartsdeal.com';
    $catalogSiteCode = 'IPD';
    $catalogHost = 'infinitipartsdeal.com';
} elseif (stripos($make, 'Mercedes') !== false) {
    $brandId = 'mercedes-benz';
    $catalogSite = 'https://www.mbpartsgiant.com';
    $catalogSiteCode = 'MBPG';
    $catalogHost = 'mbpartsgiant.com';
} elseif (stripos($make, 'Lincoln') !== false || stripos($make, 'Ford') !== false) {
    $brandId = stripos($make, 'Lincoln') !== false ? 'lincoln' : 'ford';
    $catalogSite = 'https://www.fordpartsgiant.com';
    $catalogSiteCode = 'FPG';
    $catalogHost = 'fordpartsgiant.com';
}

echo json_encode([
    'success' => true,
    'vin' => $vin,
    'make' => $make,
    'model' => $model,
    'year' => $year,
    'carTitle' => $carTitle,
    'bodyClass' => $bodyClass,
    'driveType' => $driveType,
    'engine' => $engineStr,
    'plantCountry' => $plantCountry,
    'brandId' => $brandId,
    'catalogSite' => $catalogSite,
    'catalogSiteCode' => $catalogSiteCode,
    'catalogHost' => $catalogHost,
    'hasCatalog' => !empty($catalogSite)
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
