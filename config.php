<?php
// config.php

define('GUESTY_BASE_URL', 'https://booking.guesty.com');
define('GUESTY_CLIENT_ID', '0oatt4ug3pJw7DJ5w5d7');         // Replace with your client_id
define('GUESTY_CLIENT_SECRET', '7Sj0CKxTQFU742jY8dNmcdgI76M__USkPRY19ZoqUF3n2BHcchG0vNt-Vy3Ll7Je');  // Replace with your client_secret

session_start();

/**
 * Get a valid access token (cached in session with expiry)
 */
function getAccessToken() {
    // Check if we have a cached token that hasn't expired
    if (
        isset($_SESSION['guesty_token']) &&
        isset($_SESSION['guesty_token_expires']) &&
        time() < $_SESSION['guesty_token_expires']
    ) {
        return $_SESSION['guesty_token'];
    }

    $url = GUESTY_BASE_URL . '/oauth2/token';

    $postData = http_build_query([
        'grant_type'    => 'client_credentials',
        'scope'         => 'booking_engine:api',
        'client_id'     => GUESTY_CLIENT_ID,
        'client_secret' => GUESTY_CLIENT_SECRET,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Cache-Control: no-cache',
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to get token. HTTP $httpCode: $response");
    }

    $data = json_decode($response, true);

    if (!isset($data['access_token'])) {
        throw new Exception("No access_token in response");
    }

    // Cache token in session (expire 5 minutes before actual expiry for safety)
    $_SESSION['guesty_token'] = $data['access_token'];
    $_SESSION['guesty_token_expires'] = time() + ($data['expires_in'] ?? 3600) - 300;

    return $data['access_token'];
}

/**
 * Make an authenticated GET request to the Guesty API
 */
function guestyGet($endpoint, $params = []) {
    $token = getAccessToken();
    $url = GUESTY_BASE_URL . $endpoint;

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("API error HTTP $httpCode: $response");
    }

    return json_decode($response, true);
}

/**
 * Make an authenticated POST request to the Guesty API
 */
// function guestyPost($endpoint, $body = []) {
//     $url = GUESTY_BASE_URL . $endpoint;
//     $ch = curl_init($url);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_POST, true);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
//     curl_setopt($ch, CURLOPT_HTTPHEADER, [
//         'Authorization: Bearer ' . . $token,
//         'Content-Type: application/json',
//     ]);
//     $response = curl_exec($ch);
//     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//     curl_close($ch);

//     $data = json_decode($response, true);

//     if ($httpCode >= 400) {
//         $msg = $data['error']['message'] ?? $data['message'] ?? 'Unknown error';
//         throw new Exception("API error HTTP $httpCode: $msg");
//     }

//     return $data;
// }



function guestyPost($endpoint, $body = []) {
    $token = getAccessToken();
    $url = GUESTY_BASE_URL . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return json_decode($response, true);
}
