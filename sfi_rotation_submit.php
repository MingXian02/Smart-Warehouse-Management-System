<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get the raw POST data from frontend
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data from frontend'
    ]);
    exit;
}

// Validate required field
if (!isset($data['locationPoint'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required field: locationPoint'
    ]);
    exit;
}

// Get location point from request
$locationPoint = trim($data['locationPoint']);

if (empty($locationPoint)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Location point cannot be empty'
    ]);
    exit;
}

// Get session token
session_start();
$token = $_SESSION['auth_token'] ?? '';

if (!$token) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: Missing authentication token'
    ]);
    exit;
}

// ✅ CRITICAL FIX: Use POST request WITH query parameter (like your working Postman)
// The API expects POST method but with point in QUERY STRING (not body)
$api_url = "http://192.188.11.100:5091/api/OutBoundForPick/VehicleBodyRotation?point=" . urlencode($locationPoint);

$ch = curl_init($api_url);

// Set as POST request
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Send empty body (since point is in query string)
curl_setopt($ch, CURLOPT_POSTFIELDS, '');

// Set headers - MUST include Content-Type even for empty body
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',  // Required for POST even with empty body
    'Accept: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Check for CURL errors
if ($curl_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'CURL error: ' . $curl_error,
        'locationPoint' => $locationPoint
    ]);
    exit;
}

// Parse API response
$result = json_decode($response, true);

// Check if JSON decode failed
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid API response format',
        'raw_response' => $response,
        'locationPoint' => $locationPoint
    ]);
    exit;
}

// Check API success
if ($http_code === 200 && isset($result['success']) && $result['success'] === true) {
    // ✅ SUCCESS
    echo json_encode([
        'success' => true,
        'message' => $result['msg'] ?? 'Rotation completed successfully',
        'errorCode' => $result['errorCode'] ?? 0,
        'locationPoint' => $locationPoint,
        'api_response' => $result
    ]);
} else {
    // ❌ FAILED
    $errorMsg = $result['msg'] ?? $result['message'] ?? 'Rotation failed';
    http_response_code($http_code >= 400 ? $http_code : 500);
    echo json_encode([
        'success' => false,
        'message' => $errorMsg,
        'errorCode' => $result['errorCode'] ?? $http_code,
        'locationPoint' => $locationPoint,
        'http_code' => $http_code,
        'api_response' => $result
    ]);
}
?>