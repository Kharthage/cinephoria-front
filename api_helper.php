<?php
include 'config/api.php';
function appelerApi(string $url, string $method = 'GET', array $data = [], $token = null): array {
    
    if ($token === null && isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
    }
    
    $ch = curl_init();

    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = "Authorization: Bearer $token";
    }
    
    $options = [
        CURLOPT_URL => API_URL . $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers
    ];

    if ($method !== 'GET' && !empty($data)) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    error_log("Réponse brute : " . var_export($response, true));
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'code' => 0,
            'success' => false,
            'message' => "Erreur réseau : $error",
            'data' => null
        ];
    }

    curl_close($ch);
    $parsed = json_decode($response, true);

    return [
        'code' => $httpCode,
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'message' => $parsed['message'] ?? null,
        'data' => $parsed ?? null
    ];
}