<?php

/**
 * Загрузка конфигурации брендов и сайтов
 */
function getBrandsConfig() {
    $configFile = __DIR__ . '/../config/brands.json';
    if (file_exists($configFile)) {
        $json = file_get_contents($configFile);
        $data = json_decode($json, true);
        if ($data && !empty($data['brands'])) {
            return $data;
        }
    }
    
    // Резервная конфигурация по умолчанию
    return [
        'brands' => [
            [
                'id' => 'infiniti',
                'name' => 'Infiniti',
                'siteUrl' => 'https://www.infinitipartsdeal.com',
                'siteCode' => 'IPD',
                'searchPlaceholder' => 'Search by Part Number, Part Name, or Vehicle'
            ],
            [
                'id' => 'mercedes-benz',
                'name' => 'Mercedes-Benz',
                'siteUrl' => 'https://www.mbpartsgiant.com',
                'siteCode' => 'MBPG',
                'searchPlaceholder' => 'Search by Part Number, Part Name, or Vehicle'
            ]
        ]
    ];
}

/**
 * Загрузка WMI справочника марок
 */
function getWmiConfig() {
    $wmiFile = __DIR__ . '/../config/wmi.json';
    if (file_exists($wmiFile)) {
        $json = file_get_contents($wmiFile);
        $data = json_decode($json, true);
        if ($data && !empty($data['brands'])) {
            return $data;
        }
    }
    return ['brands' => []];
}

/**
 * Определение марки по первым 3 символам VIN (WMI)
 */
function detectBrandByVin($vin) {
    if (empty($vin) || strlen($vin) < 3) {
        return null;
    }
    $wmi = strtoupper(substr(trim($vin), 0, 3));
    $wmiConfig = getWmiConfig();
    
    foreach ($wmiConfig['brands'] as $brandId => $b) {
        if (!empty($b['wmi']) && in_array($wmi, $b['wmi'], true)) {
            return $b;
        }
    }
    return null;
}

/**
 * Поиск конфигурации бренда по ID, названию, алиасам, URL сайта или VIN
 */
function findBrandConfig($brandParam = '', $url = '', $vin = '') {
    $config = getBrandsConfig();
    $brands = $config['brands'];

    // 1. Поиск по переданному ID бренда, названию или алиасам
    if (!empty($brandParam)) {
        $brandParamLower = strtolower(trim($brandParam));
        
        // Точное совпадение с id или name
        foreach ($brands as $b) {
            if (strtolower($b['id']) === $brandParamLower || strtolower($b['name']) === $brandParamLower) {
                return $b;
            }
        }

        // Поиск по массиву aliases
        foreach ($brands as $b) {
            if (!empty($b['aliases']) && is_array($b['aliases'])) {
                foreach ($b['aliases'] as $alias) {
                    if (strtolower($alias) === $brandParamLower) {
                        return $b;
                    }
                }
            }
        }

        // Нестрогий поиск по подстроке (например 'mercedes' внутри 'mercedes-benz' или наоборот)
        foreach ($brands as $b) {
            $idLower = strtolower($b['id']);
            $nameLower = strtolower($b['name']);
            if (stripos($idLower, $brandParamLower) !== false || stripos($brandParamLower, $idLower) !== false ||
                stripos($nameLower, $brandParamLower) !== false || stripos($brandParamLower, $nameLower) !== false) {
                return $b;
            }
        }
    }

    // 2. Автоматическое определение марки по VIN-коду (WMI)
    if (!empty($vin)) {
        $detected = detectBrandByVin($vin);
        if ($detected && !empty($detected['id'])) {
            foreach ($brands as $b) {
                if (strtolower($b['id']) === strtolower($detected['id'])) {
                    return $b;
                }
            }
        }
    }

    // 3. Поиск по хосту в URL
    if (!empty($url)) {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            foreach ($brands as $b) {
                $brandHost = parse_url($b['siteUrl'], PHP_URL_HOST);
                if (stripos($host, $brandHost) !== false) {
                    return $b;
                }
            }
        }
    }

    // Если был запрошен конкретный бренд/URL/VIN, но ничего не найдено - возвращаем null
    if (!empty($brandParam) || !empty($url) || !empty($vin)) {
        return null;
    }

    // По умолчанию возвращаем первый бренд, только если все параметры пусты
    return $brands[0] ?? null;
}

