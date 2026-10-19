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
$brandParam = trim($_GET['brand'] ?? $_POST['brand'] ?? '');

if (empty($vin)) {
    echo json_encode([
        'success' => false,
        'error' => 'VIN-код не передан'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$vin = strtoupper(preg_replace('/\s+/', '', $vin));

// Определяем целевой сайт из config файла по марке (или автоматически по VIN)
$brandConfig = findBrandConfig($brandParam, '', $vin);
$siteUrl = rtrim($brandConfig['siteUrl'], '/');
$siteCode = $brandConfig['siteCode'];
$brandName = $brandConfig['name'];

// Шаг 1: Отправляем запрос на целевой сайт марки
$redirectApiUrl = $siteUrl . "/api/url/vehicle-redirect";
$payload = json_encode([
    'vin' => $vin,
    'filter' => null,
    'site' => $siteCode
]);

$ch = curl_init($redirectApiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_ENCODING => '',
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json, text/plain, */*',
        'Origin: ' . $siteUrl,
        'Referer: ' . $siteUrl . '/',
        'Site: ' . $siteCode,
        'X-Site: ' . $siteCode
    ]
]);

$responseJson = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resData = json_decode($responseJson, true);

if (!$resData || empty($resData['data']['url'])) {
    $host = parse_url($siteUrl, PHP_URL_HOST);
    echo json_encode([
        'success' => false,
        'error' => "Автомобиль с VIN {$vin} не найден на сайте {$host} ({$brandName})",
        'raw' => $resData
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$categoryPath = $resData['data']['url'];
$fullCategoryUrl = $siteUrl . $categoryPath;

// Шаг 2: Получаем страницу каталога найденного авто
$ch = curl_init($fullCategoryUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_ENCODING => '',
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Referer: ' . $siteUrl . '/'
    ]
]);

$html = curl_exec($ch);
curl_close($ch);

// Шаг 3: Извлекаем блок <div class="category-main-title"><h1>...</h1></div>
$carTitle = '';
$rawBlock = '';

if (preg_match('/<div[^>]*class=["\'][^"\']*category-main-title[^"\']*["\'][^>]*>(.*?)<\/div>/is', $html, $divMatch)) {
    $rawBlock = $divMatch[0];
    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $divMatch[1], $h1Match)) {
        $carTitle = trim(strip_tags($h1Match[1]));
    }
}

// Извлекаем параметры из query строки URL для дополнительных деталей
$parsedUrl = parse_url($categoryPath);
parse_str($parsedUrl['query'] ?? '', $queryParams);

$make = $queryParams['make'] ?? $brandName;
$model = $queryParams['model'] ?? '';
$year = $queryParams['year'] ?? '';
$filter = $queryParams['filter'] ?? '';

// Возвращаем результат
echo json_encode([
    'success' => true,
    'carTitle' => $carTitle ?: ($year . ' ' . $make . ' ' . $model),
    'vin' => $vin,
    'data' => [
        'vin' => $vin,
        'brand' => $brandName,
        'brandId' => $brandConfig['id'],
        'siteUrl' => $siteUrl,
        'title' => $carTitle ?: ($year . ' ' . $make . ' ' . $model),
        'make' => $make,
        'model' => $model,
        'year' => $year,
        'driveType' => str_replace(['(', ')', '3='], '', $filter),
        'url' => $fullCategoryUrl,
        'rawBlock' => $rawBlock
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
