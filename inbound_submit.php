<?php
include('config.php');
session_start();
$token = $_SESSION['auth_token'] ?? null;

// Set proper JSON response header
header('Content-Type: application/json');

// Authentication check
if (!$token) {
    echo json_encode(["success" => false, "message" => "Authentication required. Please log in again."]);
    exit;
}

// Get data from frontend
$erpOrderNo = $_POST['erp_order_no'] ?? '';
$area = $_POST['area'] ?? '';
$containerNo = $_POST['container_no'] ?? '';

// Validate required fields individually for better error messages
if (empty($erpOrderNo)) {
    echo json_encode(["success" => false, "message" => "ERP Order Number is missing"]);
    exit;
}
if (empty($area)) {
    echo json_encode(["success" => false, "message" => "Destination area is not selected"]);
    exit;
}
if (empty($containerNo)) {
    echo json_encode(["success" => false, "message" => "Container number is required"]);
    exit;
}

// Process items
$items = [];
foreach ($_POST['items'] ?? [] as $item) {
    // Ensure item is an array
    if (!is_array($item)) continue;
    
    $qty = (float)($item['qty'] ?? 0);
    if ($qty > 0) {
        $items[] = [
            'item_id' => trim($item['item_id'] ?? ''),
            'part_number' => trim($item['part_number'] ?? ''),
            'qty' => $qty,
            'cascade' => trim($item['cascade'] ?? ''),
            'grid' => trim($item['grid'] ?? ''),
            'slot' => trim($item['slot'] ?? ''),
            'total' => trim($item['total'] ?? '')
        ];
    }
}

if (empty($items)) {
    echo json_encode(["success" => false, "message" => "No valid items with quantity greater than 0"]);
    exit;
}

// === Build API payload for your GroupStock endpoint ===
$apiItems = [];
foreach ($items as $item) {
    // Skip if item_id is empty
    if (empty($item['item_id'])) continue;
    
    $apiItems[] = [
        "condition" => [
            "id" => $item['item_id'],
            "version" => 0
        ],
        "groupNum" => (float)$item['qty'],  // Use actual quantity instead of 0
        "cascadeContainerNo" => $item['cascade'],
        "sorterChute" => $item['grid'], // Assuming grid = sorterChute
        "slotNo" => $item['slot']
    ];
}

// If no valid items after filtering
if (empty($apiItems)) {
    echo json_encode(["success" => false, "message" => "No valid items to process (missing item IDs)"]);
    exit;
}

// Prepare URL with proper encoding
$url = "http://192.188.11.100:5091/api/InBoundForInOrder/GroupStock?containerNo=" . urlencode($containerNo) . "&areaNo=" . urlencode($area);

$json_data = json_encode($apiItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Debug logging (uncomment for troubleshooting)
// error_log("=== INBOUND SUBMIT DEBUG ===");
// error_log("URL: " . $url);
// error_log("Request: " . $json_data);
// error_log("HTTP Code: " . $http_code);
// error_log("Curl Error: " . $curl_error);
// error_log("Response: " . $response);

// Handle cURL errors
if ($curl_error) {
    error_log("cURL Error: " . $curl_error);
    echo json_encode(["success" => false, "message" => "Network error: " . $curl_error]);
    exit;
}

// Handle HTTP errors
if ($http_code >= 400) {
    echo json_encode(["success" => false, "message" => "Server error (HTTP " . $http_code . ")"]);
    exit;
}

// Handle empty response
if ($response === '') {
    echo json_encode(["success" => false, "message" => "Empty response from server"]);
    exit;
}

// Parse response
$result = json_decode($response, true);

// Handle JSON parse errors
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON Parse Error: " . json_last_error_msg());
    error_log("Raw Response: " . $response);
    echo json_encode(["success" => false, "message" => "Invalid response format from server"]);
    exit;
}

// Check API response
if (isset($result['success']) && $result['success'] === true) {
    echo json_encode(["success" => true, "message" => "Palletizing completed successfully!"]);
} else {
    // Extract error message from various possible formats
    $errorMsg = "Unknown API error";
    
    if (isset($result['msg'])) {
        $errorMsg = $result['msg'];
    } elseif (isset($result['message'])) {
        $errorMsg = $result['message'];
    } elseif (isset($result['error'])) {
        $errorMsg = $result['error'];
    } elseif (is_string($result)) {
        $errorMsg = $result;
    }
    
    // Log the full error for debugging
    error_log("API Error Response: " . json_encode($result));
    
    echo json_encode(["success" => false, "message" => "API Error: " . $errorMsg]);
}
?>