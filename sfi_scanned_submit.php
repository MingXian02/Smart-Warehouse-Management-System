<?php
// 强行关闭错误显示在页面上，防止干扰 JSON 输出
error_reporting(0); 
ini_set('display_errors', 0);
session_start();

header('Content-Type: application/json');

try {
    // Get the JSON data from POST (sent from JavaScript)
    $inputData = json_decode(file_get_contents('php://input'), true);
    
    if (!$inputData || !is_array($inputData)) {
        throw new Exception("No valid data provided. Raw input: " . file_get_contents('php://input'));
    }
    
    $token = $_SESSION['auth_token'] ?? null;
    
    if (!$token) {
        throw new Exception("No authentication token found");
    }

    // Use the incoming data directly (this is already properly formatted from JavaScript)
    $params = $inputData;

    $url = "http://192.188.11.100:5091/api/OutBoundForPick/OutBillPick";
    $json_data = json_encode($params);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_data),
        'Authorization: Bearer ' . $token
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception('cURL error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    // Process the API response
    $result = json_decode($response, true);
    
    // Return JSON response
    if ($result && isset($result['success']) && $result['success'] === true) {
        echo json_encode([
            'success' => true,
            'message' => 'Scan processed successfully',
            'data' => $result,
            'processed_items' => count($params)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to process scan',
            'error_response' => $response,
            'debug_info' => [
                'input_data' => $inputData,
                'http_code' => $http_code
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'raw_input' => file_get_contents('php://input'),
            'expected_format' => 'Array of objects with id, version, pickNum, etc.'
        ]
    ]);
}
?>