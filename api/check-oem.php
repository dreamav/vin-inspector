<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/alshned.php';

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
    $alshnedData = searchAlshnedAuto($oem);
    echo json_encode([
        'success' => false,
        'oem' => $oem,
        'vin' => $vin,
        'brand' => $brandParam,
        'status' => 'unknown',
        'confirmText' => "Каталог запчастей для марки '{$brandParam}' пока не подключен",
        'error' => "Каталог запчастей для марки '{$brandParam}' пока не подключен",
        'alshned' => $alshnedData
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$siteUrl = rtrim($brandConfig['siteUrl'], '/');
$siteCode = $brandConfig['siteCode'];
$brandName = $brandConfig['name'];

/**
 * Получает данные детали, статус совместимости с VIN и список совместимых авто
 */
function fetchPartDetails($siteUrl, $siteCode, $targetOem, $vin, $categoryUrl = '', $inputPartUrl = '', $selectedOption = '') {
    $fullPartUrl = '';

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
        $searchWordsApi = $siteUrl . "/api/search/search-words?searchText=" . urlencode($targetOem);
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
            return [
                'success' => false,
                'raw' => $searchData
            ];
        }

        $partRedirectPath = $searchData['data']['redirectUrl'];
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

    // Шаг 2: Загружаем страницу детали с параметрами автомобиля (для проверки совместимости)
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

    $confirmText = '';
    $confirmBlock = '';
    $status = 'unknown'; // 'fit', 'not-fit', 'partial', 'unknown'
    $vehicleInfo = '';
    $partTitle = '';
    $rawCheckBlock = '';

    if (preg_match('/<div[^>]*class=["\'][^"\']*pn-check-confirm-text[^"\']*["\'][^>]*>(.*?)<\/div>/is', $partHtml, $textMatch)) {
        $confirmBlock = $textMatch[0];
        $confirmText = trim(strip_tags($textMatch[1]));
    }

    if (preg_match('/<div[^>]*class=["\'][^"\']*pn-check\s+[^"\']*["\'][^>]*>.*?<\/div>\s*<\/div>/is', $partHtml, $checkBlockMatch)) {
        $rawCheckBlock = $checkBlockMatch[0];
    }

    if (strpos($rawCheckBlock, 'pn-check-partial') !== false || stripos($confirmText, 'additional information') !== false) {
        $status = 'partial';
    } elseif (strpos($rawCheckBlock, 'pn-check-not-fit') !== false || stripos($confirmText, 'not fit') !== false) {
        $status = 'not-fit';
    } elseif (strpos($rawCheckBlock, 'pn-check-fit') !== false || stripos($confirmText, 'fits') !== false) {
        $status = 'fit';
    }

    if (preg_match('/<div[^>]*class=["\'][^"\']*pn-check-vehicle-info[^"\']*["\'][^>]*>(.*?)<\/div>/is', $partHtml, $vInfoMatch)) {
        $vehicleInfo = trim(preg_replace('/\s+/', ' ', strip_tags($vInfoMatch[1])));
    }

    if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $partHtml, $h1Match)) {
        $partTitle = trim(preg_replace('/\s+/', ' ', strip_tags($h1Match[1])));
    }

    // Поиск Replaced By (номер детали, на которую заменена текущая)
    $replacedBy = '';
    if (preg_match('/"replacedBy"\s*:\s*"([^"]+)"/i', $partHtml, $rbMatch)) {
        $val = trim($rbMatch[1]);
        if ($val !== '' && strtolower($val) !== 'null') {
            $replacedBy = strtoupper(preg_replace('/\s+/', '', $val));
        }
    }
    if (empty($replacedBy) && preg_match('/"name"\s*:\s*"Replaced By"\s*,\s*"desc"\s*:\s*"([^"]+)"/i', $partHtml, $rbMatch)) {
        $val = trim($rbMatch[1]);
        if ($val !== '' && strtolower($val) !== 'null') {
            $replacedBy = strtoupper(preg_replace('/\s+/', '', $val));
        }
    }
    if (empty($replacedBy) && preg_match('/Replaced By\s*<\/span>\s*<div[^>]*>.*?<div[^>]*>([^<]+)<\/div>/is', $partHtml, $rbMatch)) {
        $val = trim(strip_tags($rbMatch[1]));
        if ($val !== '') {
            $replacedBy = strtoupper(preg_replace('/\s+/', '', $val));
        }
    }
    if (empty($replacedBy) && preg_match('/Replaced By\s*<\/th>\s*<td[^>]*>([^<]+)<\/td>/is', $partHtml, $rbMatch)) {
        $val = trim(strip_tags($rbMatch[1]));
        if ($val !== '') {
            $replacedBy = strtoupper(preg_replace('/\s+/', '', $val));
        }
    }

    // Извлекаем decodeVehicleInfo со страницы детали
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

    // Опции для partial
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

    // Шаг 3: Чистая страница детали без параметров запроса для таблицы совместимости
    $cleanPartUrl = strtok($fullPartUrl, '?');
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

    $fitmentLi = '';
    $fitmentDiv = '';
    $fitmentVehicles = [];

    if (preg_match_all('/<li[^>]*data-id=["\']Vehicle Fitment["\'][^>]*>(.*?)<\/li>/is', $cleanHtml, $liMatches)) {
        foreach ($liMatches[0] as $match) {
            if (strpos($match, '<div') !== false) {
                $fitmentLi = $match;
                break;
            }
        }
    }

    if (!empty($fitmentLi)) {
        if (preg_match('/<div[^>]*class=["\'][^"\']*fit-vehicle-list[^"\']*["\'][^>]*>(.*?)<\/div>/is', $fitmentLi, $divMatch)) {
            $fitmentDiv = $divMatch[0];
        } elseif (preg_match('/<div[^>]*>(.*?)<\/div>/is', $fitmentLi, $divFallback)) {
            $fitmentDiv = $divFallback[0];
        }
    }

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

    return [
        'success' => true,
        'oem' => $targetOem,
        'confirmText' => $confirmText,
        'confirmBlock' => $confirmBlock,
        'rawCheckBlock' => $rawCheckBlock,
        'status' => $status,
        'vehicleInfo' => $vehicleInfo,
        'partTitle' => $partTitle,
        'replacedBy' => $replacedBy,
        'decodeVehicleInfo' => $decodeVehicleInfo,
        'needsOptions' => $needsOptions,
        'options' => $optionsList,
        'selectedOption' => $selectedOption,
        'fullPartUrl' => $fullPartUrl,
        'cleanPartUrl' => $cleanPartUrl,
        'fitmentLi' => $fitmentLi,
        'fitmentDiv' => $fitmentDiv,
        'fitmentVehicles' => $fitmentVehicles
    ];
}

// Проверяем исходный OEM
$partData = fetchPartDetails($siteUrl, $siteCode, $oem, $vin, $categoryUrl, $inputPartUrl, $selectedOption);

if (!$partData['success']) {
    $host = parse_url($siteUrl, PHP_URL_HOST);
    $alshnedData = searchAlshnedAuto($oem);
    echo json_encode([
        'success' => false,
        'oem' => $oem,
        'vin' => $vin,
        'brand' => $brandName,
        'siteUrl' => $siteUrl,
        'status' => 'not-in-catalog',
        'confirmText' => "Деталь с OEM-кодом {$oem} не найдена в каталоге {$host}",
        'error' => "Деталь с OEM-кодом {$oem} не найдена в каталоге {$host} ({$brandName})",
        'alshned' => $alshnedData,
        'raw' => $partData['raw'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$isReplaced = false;
$replacedBy = $partData['replacedBy'] ?? '';
$originalOem = $oem;
$originalStatus = $partData['status'];
$originalConfirmText = $partData['confirmText'];
$originalPartTitle = $partData['partTitle'];
$originalPartUrl = $partData['fullPartUrl'];

// Автоматическая проверка номера замены (Replaced By), если исходная деталь не подходит
if (!empty($replacedBy) && $replacedBy !== $oem && $partData['status'] !== 'fit') {
    $repData = fetchPartDetails($siteUrl, $siteCode, $replacedBy, $vin, $categoryUrl, '', $selectedOption);
    if ($repData && $repData['success']) {
        $isReplaced = true;
        // Если деталь замены подошла (This part fits)
        if ($repData['status'] === 'fit') {
            $partData['status'] = 'fit';
            $partData['confirmText'] = 'This part fits';
            $partData['partTitle'] = $repData['partTitle'];
            $partData['fullPartUrl'] = $repData['fullPartUrl'];
            $partData['cleanPartUrl'] = $repData['cleanPartUrl'];
            $partData['fitmentDiv'] = $repData['fitmentDiv'];
            $partData['fitmentLi'] = $repData['fitmentLi'];
            $partData['fitmentVehicles'] = $repData['fitmentVehicles'];
            if (!empty($repData['vehicleInfo'])) {
                $partData['vehicleInfo'] = $repData['vehicleInfo'];
            }
        } elseif ($repData['status'] === 'partial') {
            $partData['status'] = 'partial';
            $partData['confirmText'] = $repData['confirmText'];
            $partData['needsOptions'] = true;
            $partData['options'] = $repData['options'];
            $partData['fitmentVehicles'] = $repData['fitmentVehicles'];
        }
    }
}

// Поиск предложений детали на alshnedauto.com (по исходному и замененному артикулу)
$alshnedData = searchAlshnedAuto($oem);
if (!empty($replacedBy) && $replacedBy !== $oem) {
    $repAlshned = searchAlshnedAuto($replacedBy);
    if ($repAlshned && !empty($repAlshned['products'])) {
        $existingIds = [];
        if (!empty($alshnedData['products'])) {
            foreach ($alshnedData['products'] as $p) {
                $existingIds[$p['id']] = true;
            }
        } else {
            $alshnedData['products'] = [];
        }
        foreach ($repAlshned['products'] as $rp) {
            if (!isset($existingIds[$rp['id']])) {
                $existingIds[$rp['id']] = true;
                $alshnedData['products'][] = $rp;
            }
        }
        $alshnedData['count'] = count($alshnedData['products']);
    }
}

echo json_encode([
    'success' => true,
    'vin' => $vin,
    'oem' => $oem,
    'brand' => $brandName,
    'brandId' => $brandConfig['id'],
    'siteUrl' => $siteUrl,
    'confirmText' => $partData['confirmText'] ?: 'Результат проверки не найден',
    'status' => $partData['status'],
    'isReplaced' => $isReplaced,
    'replacedBy' => $replacedBy,
    'originalOem' => $originalOem,
    'originalStatus' => $originalStatus,
    'originalConfirmText' => $originalConfirmText,
    'originalPartTitle' => $originalPartTitle,
    'originalPartUrl' => $originalPartUrl,
    'needsOptions' => $partData['needsOptions'],
    'options' => $partData['options'],
    'selectedOption' => $partData['selectedOption'],
    'decodeVehicleInfo' => $partData['decodeVehicleInfo'],
    'partTitle' => $partData['partTitle'],
    'vehicleInfo' => $partData['vehicleInfo'],
    'categoryUrl' => $categoryUrl ?: "{$siteUrl}/page_product/category?vin=" . $vin,
    'partUrl' => $partData['fullPartUrl'],
    'cleanPartUrl' => $partData['cleanPartUrl'],
    'fitmentDiv' => $partData['fitmentDiv'],
    'fitmentLi' => $partData['fitmentLi'],
    'fitmentVehicles' => $partData['fitmentVehicles'],
    'rawBlock' => $partData['confirmBlock'],
    'rawCheckBlock' => $partData['rawCheckBlock'],
    'alshned' => $alshnedData
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
