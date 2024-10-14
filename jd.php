<?php
function generateDeviceId() {
    $prefix = "001168.";
    $id = $prefix . bin2hex(random_bytes(16));
    return $id;
}

function loginAndGetToken($loginUrl, $deviceId) {
    $headers = [
        'Content-Type: application/json; charset=utf-8',
        'User-Agent: BeesVPN/2 CFNetwork/1568.100.1 Darwin/24.0.0',
        'Accept: */*',
        'Accept-Language: zh-CN,zh-Hans;q=0.9'
    ];

    $payload = [
        "invite_token" => "",
        "device_id" => $deviceId
    ];

    $ch = curl_init($loginUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'cURL error: ' . curl_error($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
        echo 'Failed to login, HTTP status code: ' . $httpCode;
        return null;
    }

    $result = json_decode($response, true);
    return $result['data']['token'] ?? null;
}

function fetchAndProcessSubscription($subscribeUrl, $token) {
    $headers = [
        'Accept: */*',
        'User-Agent: BeesVPN/2 CFNetwork/1568.100.1 Darwin/24.0.0'
    ];

    $ch = curl_init($subscribeUrl . '?token=' . $token);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'cURL error: ' . curl_error($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode != 200) {
        echo 'Failed to fetch subscription, HTTP status code: ' . $httpCode;
        return null;
    }

    $result = json_decode($response, true);
    $urls = [];
    foreach ($result['data'] as $item) {
        foreach ($item['list'] as $subItem) {
            $url = str_replace('vless:/\\/', 'vless://', $subItem['url'] ?? '');
            if ($url) {
                $urls[] = $url;
            }
        }
    }
    return $urls;
}

function main() {
    $apiUrl = 'https://94.74.97.241/api/v1';
    $loginEndpoint = '/passport/auth/loginByDeviceId';
    $subscribeEndpoint = '/client/appSubscribe';

    $deviceId = generateDeviceId();
    $token = loginAndGetToken($apiUrl . $loginEndpoint, $deviceId);
    if (!$token) {
        return;
    }

    $urls = fetchAndProcessSubscription($apiUrl . $subscribeEndpoint, $token);
    if (!$urls) {
        return;
    }

    $content = implode("\n", $urls);
    $encodedContent = base64_encode($content);
    header('Content-Type: text/plain');
    echo $encodedContent;
}

main();