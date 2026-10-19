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
$oem = trim($_GET['oem'] ?? $_POST['oem'] ?? '');
$categoryUrl = trim($_GET['categoryUrl'] ?? $_POST['categoryUrl'] ?? '');
$inputPartUrl = trim($_GET['partUrl'] ?? $_POST['partUrl'] ?? '');
$brandParam = trim($_GET['brand'] ?? $_POST['brand'] ?? '');

$selectedOption = trim($_GET['option'] ?? $_POST['option'] ?? $_GET['filter'] ?? $_POST['filter'] ?? '');

if (empty($oem) && !empty($inputPartUrl)) {
    if (preg_match('/~([a-zA-Z0-9-]+)\.html/i', $inputPartUrl, $oemMatch)) {
        $oem = $oemMatch[1];
    }
}

if (empty($oem) && empty($inputPartUrl)) {
    echo json_encode([
        'success' => false,
        'error' => 'OEM-код детали или ссылка не указаны'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$oem = strtoupper(preg_replace('/\s+/', '', $oem));
$vin = strtoupper(preg_replace('/\s+/', '', $vin));

// Определяем бренд и сайт из конфигурации (по параметру бренда или переданному URL)
$brandConfig = findBrandConfig($brandParam, $categoryUrl ?: $inputPartUrl, $vin);
if (!$brandConfig) {
    echo json_encode([
        'success' => false,
        'error' => "Каталог запчастей для марки '{$brandParam}' пока не подключен"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$siteUrl = rtrim($brandConfig['siteUrl'], '/');
$siteCode = $brandConfig['siteCode'];
$brandName = $brandConfig['name'];


// Шаг 1: Получаем ссылку на деталь
if (!empty($inputPartUrl)) {
    $fullPartUrl = $inputPartUrl;
    $parsedInput = parse_url($inputPartUrl);
    if (empty($vin) && !empty($parsedInput['query'])) {
        parse_str($parsedInput['query'], $q);
        if (!empty($q['vin'])) $vin = strtoupper($q['vin']);
    }
    if (!empty($selectedOption)) {
        parse_str($parsedInput['query'] ?? '', $q);
        $currFilter = trim($q['filter'] ?? '', '() ');
        if (!empty($currFilter)) {
            if (strpos($currFilter, $selectedOption) === false) {
                $q['filter'] = '(' . $selectedOption . ';' . $currFilter . ')';
            } else {
                $q['filter'] = '(' . $currFilter . ')';
            }
        } else {
            $q['filter'] = '(' . $selectedOption . ')';
        }
        $cleanPath = explode('?', $inputPartUrl)[0];
        $fullPartUrl = $cleanPath . '?' . http_build_query($q);
    }
} else {
    // Ищем деталь через внутренний API каталога марки
    $searchWordsApi = $siteUrl . "/api/search/search-words?searchText=" . urlencode($oem);

    $ch = curl_init($searchWordsApi);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: application/json, text/plain, */*',
            'Origin: ' . $siteUrl,
            'Referer: ' . ($categoryUrl ?: $siteUrl . '/'),
            'Site: ' . $siteCode,
            'X-Site: ' . $siteCode
        ]
    ]);

    $searchResponse = curl_exec($ch);
    curl_close($ch);

    $searchData = json_decode($searchResponse, true);

    if (!$searchData || empty($searchData['data']['redirectUrl'])) {
        $host = parse_url($siteUrl, PHP_URL_HOST);
        echo json_encode([
            'success' => false,
            'error' => "Деталь с OEM-кодом {$oem} не найдена в каталоге {$host} ({$brandName})",
            'raw' => $searchData
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $partRedirectPath = $searchData['data']['redirectUrl'];

    // Шаг 2: Формируем ссылку на страницу детали с привязкой параметров автомобиля
    $queryArgs = [];
    if (!empty($categoryUrl)) {
        $parsedCat = parse_url($categoryUrl);
        if (!empty($parsedCat['query'])) {
            parse_str($parsedCat['query'], $queryArgs);
        }
    }

    if (!empty($vin)) {
        $queryArgs['vin'] = $vin;
    }

    if (!empty($selectedOption)) {
        $existingFilter = trim($queryArgs['filter'] ?? '', '() ');
        if (!empty($existingFilter)) {
            if (strpos($existingFilter, $selectedOption) === false) {
                $queryArgs['filter'] = '(' . $selectedOption . ';' . $existingFilter . ')';
            } else {
                $queryArgs['filter'] = '(' . $existingFilter . ')';
            }
        } else {
            $queryArgs['filter'] = '(' . $selectedOption . ')';
        }
    }

    $queryString = http_build_query($queryArgs);
    $fullPartUrl = (strpos($partRedirectPath, 'http') === 0) 
        ? $partRedirectPath . ($queryString ? (strpos($partRedirectPath, '?') !== false ? '&' : '?') . $queryString : '')
        : $siteUrl . $partRedirectPath . ($queryString ? '?' . $queryString : '');
}

// Шаг 3: Загружаем страницу детали с параметрами автомобиля (для проверки совместимости)
$ch = curl_init($fullPartUrl);
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
        'Referer: ' . ($categoryUrl ?: $siteUrl . '/')
    ]
]);

$partHtml = curl_exec($ch);
curl_close($ch);

// Шаг 4: Извлекаем содержимое div с классом pn-check-confirm-text и заголовок детали
$confirmText = '';
$confirmBlock = '';
$status = 'unknown'; // 'fit', 'not-fit', 'partial', 'unknown'
$vehicleInfo = '';
$partTitle = '';
$rawCheckBlock = '';

// Поиск блока pn-check-confirm-text
if (preg_match('/<div[^>]*class=["\'][^"\']*pn-check-confirm-text[^"\']*["\'][^>]*>(.*?)<\/div>/is', $partHtml, $textMatch)) {
    $confirmBlock = $textMatch[0];
    $confirmText = trim(strip_tags($textMatch[1]));
}

// Поиск полного блока проверки pn-check
if (preg_match('/<div[^>]*class=["\'][^"\']*pn-check\s+[^"\']*["\'][^>]*>.*?<\/div>\s*<\/div>/is', $partHtml, $checkBlockMatch)) {
    $rawCheckBlock = $checkBlockMatch[0];
}

// Определение статуса совместимости на основе класса блока pn-check и текста подтверждения
if (strpos($rawCheckBlock, 'pn-check-partial') !== false || stripos($confirmText, 'additional information') !== false) {
    $status = 'partial';
} elseif (strpos($rawCheckBlock, 'pn-check-not-fit') !== false || stripos($confirmText, 'not fit') !== false) {
    $status = 'not-fit';
} elseif (strpos($rawCheckBlock, 'pn-check-fit') !== false || stripos($confirmText, 'fits') !== false) {
    $status = 'fit';
}

// Поиск информации об авто из блока проверки pn-check-vehicle-info
if (preg_match('/<div[^>]*class=["\'][^"\']*pn-check-vehicle-info[^"\']*["\'][^>]*>(.*?)<\/div>/is', $partHtml, $vInfoMatch)) {
    $vehicleInfo = trim(preg_replace('/\s+/', ' ', strip_tags($vInfoMatch[1])));
}

// Поиск названия детали из h1
if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $partHtml, $h1Match)) {
    $partTitle = trim(preg_replace('/\s+/', ' ', strip_tags($h1Match[1])));
}

// Шаг 4.1: Извлекаем decodeVehicleInfo со страницы детали
$decodeVehicleInfo = null;
$pos = strpos($partHtml, '"decodeVehicleInfo":');
if ($pos !== false) {
    $start = $pos + strlen('"decodeVehicleInfo":');
    $depth = 0;
    $end = $start;
    $len = strlen($partHtml);
    for ($i = $start; $i < $len; $i++) {
        if ($partHtml[$i] === '{') {
            $depth++;
        } elseif ($partHtml[$i] === '}') {
            $depth--;
            if ($depth === 0) {
                $end = $i;
                break;
            }
        }
    }
    $rawObj = substr($partHtml, $start, $end - $start + 1);
    $cleanObj = preg_replace('/:\s*undefined\b/', ':null', $rawObj);
    $decodeVehicleInfo = json_decode($cleanObj, true);
    if ($decodeVehicleInfo) {
        if (empty($decodeVehicleInfo['make']) && !empty($decodeVehicleInfo['currentMake'])) {
            $decodeVehicleInfo['make'] = $decodeVehicleInfo['currentMake'];
        }
        if (empty($decodeVehicleInfo['model']) && !empty($decodeVehicleInfo['currentModel'])) {
            $decodeVehicleInfo['model'] = $decodeVehicleInfo['currentModel'];
        }
        if (empty($decodeVehicleInfo['year']) && !empty($decodeVehicleInfo['currentYear'])) {
            $decodeVehicleInfo['year'] = (int)$decodeVehicleInfo['currentYear'];
        }
    }
}

// Шаг 4.2: Если статус partial (требуется уточнение), запрашиваем список опций через API каталога
$optionsList = [];
$needsOptions = false;
if ($status === 'partial') {
    $needsOptions = true;
    if ($decodeVehicleInfo) {
        $optCh = curl_init($siteUrl . "/api/option/option-list");
        curl_setopt_array($optCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($decodeVehicleInfo),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json, text/plain, */*',
                'Origin: ' . $siteUrl,
                'Referer: ' . $fullPartUrl,
                'Site: ' . $siteCode,
                'X-Site: ' . $siteCode
            ]
        ]);
        $optRes = curl_exec($optCh);
        curl_close($optCh);

        $optData = json_decode($optRes, true);
        if ($optData && !empty($optData['data']['optionList'])) {
            $optionsList = $optData['data']['optionList'];
        }
    }
}

// Шаг 5: Убираем параметры строки запроса (query parameters) из сохраненной ссылки на деталь
$cleanPartUrl = strtok($fullPartUrl, '?');

// Загружаем чистую страницу детали без параметров запроса
$chClean = curl_init($cleanPartUrl);
curl_setopt_array($chClean, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_ENCODING => '',
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Referer: ' . $fullPartUrl
    ]
]);

$cleanHtml = curl_exec($chClean);
curl_close($chClean);

// Шаг 6: Находим li с data-id="Vehicle Fitment" и извлекаем содержимое div с таблицей совместимости
$fitmentLi = '';
$fitmentDiv = '';
$fitmentVehicles = [];

// Ищем блок контента <li data-id="Vehicle Fitment"> (второй li, содержащий div)
if (preg_match_all('/<li[^>]*data-id=["\']Vehicle Fitment["\'][^>]*>(.*?)<\/li>/is', $cleanHtml, $liMatches)) {
    foreach ($liMatches[0] as $match) {
        if (strpos($match, '<div') !== false) {
            $fitmentLi = $match;
            break;
        }
    }
}

// Извлекаем div с классом fit-vehicle-list из li
if (!empty($fitmentLi)) {
    if (preg_match('/<div[^>]*class=["\'][^"\']*fit-vehicle-list[^"\']*["\'][^>]*>(.*?)<\/div>/is', $fitmentLi, $divMatch)) {
        $fitmentDiv = $divMatch[0];
    } elseif (preg_match('/<div[^>]*>(.*?)<\/div>/is', $fitmentLi, $divFallback)) {
        $fitmentDiv = $divFallback[0];
    }
}

// Извлекаем список совместимых автомобилей построчно из таблицы
if (!empty($fitmentDiv) && preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $fitmentDiv, $trMatches)) {
    foreach ($trMatches[1] as $tr) {
        if (preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $tr, $tdMatches)) {
            $row = array_map(function($td) {
                return trim(preg_replace('/\s+/', ' ', strip_tags($td)));
            }, $tdMatches[1]);
            if (!empty($row)) {
                $fitmentVehicles[] = [
                    'yearMakeModel' => $row[0] ?? '',
                    'trimEngine' => $row[1] ?? '',
                    'options' => $row[2] ?? ''
                ];
            }
        }
    }
}

echo json_encode([
    'success' => true,
    'vin' => $vin,
    'oem' => $oem,
    'brand' => $brandName,
    'brandId' => $brandConfig['id'],
    'siteUrl' => $siteUrl,
    'confirmText' => $confirmText ?: 'Результат проверки не найден',
    'status' => $status,
    'needsOptions' => $needsOptions,
    'options' => $optionsList,
    'selectedOption' => $selectedOption,
    'decodeVehicleInfo' => $decodeVehicleInfo,
    'partTitle' => $partTitle,
    'vehicleInfo' => $vehicleInfo,
    'categoryUrl' => $categoryUrl ?: "{$siteUrl}/page_product/category?vin=" . $vin,
    'partUrl' => $fullPartUrl,
    'cleanPartUrl' => $cleanPartUrl,
    'fitmentDiv' => $fitmentDiv,
    'fitmentLi' => $fitmentLi,
    'fitmentVehicles' => $fitmentVehicles,
    'rawBlock' => $confirmBlock,
    'rawCheckBlock' => $rawCheckBlock
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
