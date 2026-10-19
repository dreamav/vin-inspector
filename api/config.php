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
 * Поиск конфигурации бренда по ID, названию или URL сайта
 */
function findBrandConfig($brandParam = '', $url = '') {
    $config = getBrandsConfig();
    $brands = $config['brands'];

    // 1. Поиск по переданному ID бренда или названию
    if (!empty($brandParam)) {
        $brandParamLower = strtolower(trim($brandParam));
        foreach ($brands as $b) {
            if (strtolower($b['id']) === $brandParamLower || strtolower($b['name']) === $brandParamLower) {
                return $b;
            }
        }
    }

    // 2. Поиск по хосту в URL
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

    // По умолчанию возвращаем первый бренд (Infiniti)
    return $brands[0];
}
