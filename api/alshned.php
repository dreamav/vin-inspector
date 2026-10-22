<?php

/**
 * Поиск предложений деталей на сайте alshnedauto.com
 *
 * @param string $oem Номер OEM запчасти
 * @return array Данные о найденных товарах
 */
function searchAlshnedAuto($oem) {
    $cleanOem = trim($oem);
    if (empty($cleanOem)) {
        return [
            'success' => false,
            'query' => '',
            'searchUrl' => 'https://alshnedauto.com/',
            'count' => 0,
            'products' => []
        ];
    }

    $queriesToTry = [$cleanOem];

    // Если в OEM есть пробелы, пробуем заменить их на дефисы и убрать пробелы
    if (strpos($cleanOem, ' ') !== false) {
        $queriesToTry[] = str_replace(' ', '-', $cleanOem);
        $queriesToTry[] = str_replace(' ', '', $cleanOem);
    }

    // Если код без дефиса и состоит из 10 символов (например 963025NA4A), пробуем формат с дефисом (96302-5NA4A)
    if (preg_match('/^([A-Z0-9]{5})([A-Z0-9]{5})$/i', $cleanOem, $splits)) {
        $queriesToTry[] = $splits[1] . '-' . $splits[2];
    }

    $queriesToTry = array_unique($queriesToTry);
    $allProducts = [];

    foreach ($queriesToTry as $query) {
        $suggestUrl = 'https://alshnedauto.com/search/suggest.json?q=' . urlencode($query) . '&resources[type]=product&resources[options][unavailable_products]=show';

        $ch = curl_init($suggestUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_ENCODING => '',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/plain, */*',
                'Referer: https://alshnedauto.com/'
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($response)) {
            $data = json_decode($response, true);
            $rawProducts = $data['resources']['results']['products'] ?? [];
            if (!empty($rawProducts)) {
                foreach ($rawProducts as $item) {
                    $prodId = (string)($item['id'] ?? '');
                    if (isset($allProducts[$prodId])) {
                        continue;
                    }

                    $imgUrl = '';
                    if (!empty($item['image'])) {
                        $imgUrl = $item['image'];
                    } elseif (!empty($item['featured_image']['url'])) {
                        $imgUrl = $item['featured_image']['url'];
                    }
                    if ($imgUrl && strpos($imgUrl, '//') === 0) {
                        $imgUrl = 'https:' . $imgUrl;
                    }

                    $prodUrl = $item['url'] ?? '';
                    if ($prodUrl && strpos($prodUrl, 'http') !== 0) {
                        $prodUrl = 'https://alshnedauto.com' . $prodUrl;
                    }

                    $price = isset($item['price']) ? (float)$item['price'] : 0.0;
                    $priceFormatted = '$' . number_format($price, 2, '.', ' ');

                    $allProducts[$prodId] = [
                        'id' => $prodId,
                        'title' => $item['title'] ?? '',
                        'price' => $price,
                        'priceFormatted' => $priceFormatted,
                        'image' => $imgUrl,
                        'url' => $prodUrl,
                        'available' => !empty($item['available']),
                        'vendor' => $item['vendor'] ?? ''
                    ];
                }
                break;
            }
        }
    }

    $productsList = array_values($allProducts);
    $searchPageUrl = 'https://alshnedauto.com/search?q=' . urlencode($cleanOem);

    return [
        'success' => true,
        'query' => $cleanOem,
        'searchUrl' => $searchPageUrl,
        'count' => count($productsList),
        'products' => $productsList
    ];
}
