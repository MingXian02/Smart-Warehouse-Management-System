<?php
include('config.php');
session_start();
$token = $_SESSION['auth_token'];
// Echo safely for JS consumption (NOT in production!)
echo "<script>console.log('PHP Token:', '" . htmlspecialchars($token, ENT_QUOTES) . "');</script>";
$pdaInput = $_REQUEST['pdaInput'] ?? '';
if (empty($pdaInput)) {
    // Show empty state instead of crashing
    echo '<div style="padding:40px;text-align:center;color:#667eea;font-size:18px;">
        Please enter a container number
    </div>';
    exit;
}

function getSpecificOutOrderDetail($outOrderNo, $outOrderDId) {
    $token = $_SESSION['auth_token'];
    $params = [
        "pageIndex" => 1,
        "pageRows" => 99999,
        "sortField" => "",
        "sortType" => "",
        "search" => [
            "outOrderNo" => "$outOrderNo",
            "outOrderStatus" => 2,
            "outOrderType" => 1,
            "skuNo" => "",
            "skuName" => "",
            "batchNo" => "",
            "outTemporaryStorage" => 0,
            "erpNo" => ""
        ]
    ];

    $url = "http://192.188.11.100:5091/api/OutOrder/GetOutOrderDDataList";
    $json_data = json_encode($params);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) return "";
    
    $result = json_decode($response, true);
    
    if (isset($result['success']) && $result['success'] === true && !empty($result['data'])) {
        foreach ($result['data'] as $item) {
            if ($item['id'] == $outOrderDId) {
                if (!empty($item['jmrDetails'])) {
                    $rows = '';
                    $hasValidQty = false;
                    foreach ($item['jmrDetails'] as $detail) {
                        if (isset($detail['trxQty']) && $detail['trxQty'] > 0) {
                            $hasValidQty = true;
                            $rows .= '<tr>';
                            $rows .= '<td style="font-size:10px;text-align:center;">' . htmlspecialchars($outOrderNo) . '</td>';
                            $rows .= '<td style="font-size:10px;text-align:center;">' . htmlspecialchars($detail['trxNo']) . '</td>';
                            $rows .= '<td style="font-size:10px;text-align:center;">' . htmlspecialchars($detail['trxQty']) . '</td>';
                            $rows .= '</tr>';
                        }
                    }

                    if ($hasValidQty) {
                        $table = '<div class="job-order-table" style="margin-top:15px; margin-bottom:15px;">';
                        $table .= '<table border="1" class="pureOffic" style="border-collapse: collapse; width: 100%; text-align: left; font-size: 10px;">';
                        $table .= '<thead style="background-color: #f2f2f2;">';
                        $table .= '<tr><th style="padding:6px;text-align:center;">Outbound Order No.</th><th style="padding:6px;text-align:center;">Job No</th><th style="padding:6px;text-align:center;">Quantity</th></tr>';
                        $table .= '</thead>';
                        $table .= '<tbody>' . $rows . '</tbody></table>';
                        $table .= '</div>';
                        return $table;
                    }
                    return ""; 
                }
                return ""; 
            }
        }
    }
    return ""; 
}
    

$params = [
    "pageIndex" => 1,
    "pageRows" => 99999,
    "sortField" => "",
    "sortType" => "",
    "search" => [
        "outOrderNo" => "",
        "outBillNo" => "",
        "pickBillStatus" => 2,
        "pickBillType" => 1,
        "containerNo" => "$pdaInput",
        "isReplenishment" => 0
    ]
];

$url = "http://192.188.11.100:5091/api/OutBoundForPick/GetPickBillDataList";

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
$result = json_decode($response, true);

// ===== 预处理：计算每个容器+网格的总槽数 =====
$totalSlotsMap = [];

if (isset($result['data']) && is_array($result['data'])) {
    foreach ($result['data'] as $row) {
        $cascadeContainer = $row['cascadeContainerNo'] ?? '';
        $gridNumber = preg_replace('/[^0-9]/', '', $row['skuAttr2'] ?? '');
        
        if (empty($cascadeContainer) || empty($gridNumber)) {
            continue;
        }
        
        $rawSlotData = trim($row['skuAttr3'] ?? '');
        if (preg_match('/^(\d+)$/', $rawSlotData, $matches)) {
            $slotNum = intval($matches[1]);
            $key = $cascadeContainer . '_' . $gridNumber;
            
            if (!isset($totalSlotsMap[$key])) {
                $totalSlotsMap[$key] = $slotNum;
            } else {
                $totalSlotsMap[$key] = max($totalSlotsMap[$key], $slotNum);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Picking System</title>
    <style>
        /* ===== RESET & BASE STYLES ===== */
        /* ===== GLOBAL RESET ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', 'Microsoft YaHei', sans-serif;
    background: linear-gradient(135deg,hsl(0, 0.00%, 100.00%) 0%,rgb(255, 255, 255) 100%);
    min-height: 100vh;
    padding: 12px;
    color: #2c3e50;
    touch-action: manipulation;
}

.container {
    max-width: 100%;
    margin: 0 auto;
}

/* ===== HEADER ===== */
.header {
    text-align: center;
    color: #000000;
    margin-bottom: 20px;
    padding: 16px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 3px 15px rgba(0, 0, 0, 0.1);
    border: 1px solid #d1e7dd;
}

.header h1 {
    font-size: 14px;
    margin-bottom: 8px;
    color: hsl(242, 40.60%, 44.90%);
    font-weight: 700;
    line-height: 1.2;
}

.header .subtitle {
    font-size: 1em;
    color: hsl(308, 100.00%, 54.70%);
    font-weight: 500;
}

/* ===== CARD DESIGN ===== */
.pick-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08), 0 2px 6px rgba(46, 125, 50, 0.1);
    margin: 16px 0;
    padding: 18px;
    border: 1px solid #d1e7dd;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.pick-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 10px;
    margin-bottom: 12px;
    border-bottom: 1px solid #f0f7f4;
    flex-wrap: wrap;
    gap: 8px;
}

.card-header h3 {
    font-size: 1.1em;
    color: hsl(329, 63.30%, 49.20%);
    font-weight: 600;
    line-height: 1.3;
}

.status-badge {
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 16px;
    font-size: 0.75em;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(46, 125, 50, 0.3);
    white-space: nowrap;
}

.location-tag {
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
    color: white;
    padding: 6px 12px;
    border-radius: 10px;
    font-weight: 700;
    font-size: 1em;
    margin: 8px 0;
    display: inline-block;
    box-shadow: 0 2px 8px rgba(76, 175, 80, 0.2);
}

.sku-name {
    font-size: 12px;
    font-weight: 600;
    color: hsl(287, 99.20%, 51.60%);
    margin: 4px 0;
    display: block;
}

.sku-no {
    color: hsl(288, 76.40%, 56.90%);
    font-size: 0.85em;
    margin: 4px 0;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.sku-no::before {
    content: '•';
    color: #6d28d9; /* Replaced green with purple */
    font-size: 1.2em;
}

/* ===== GRID SECTION ===== */
.grid-section {
    margin: 12px 0;
}

.grid-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 8px;
}

.grid-title {
    font-size: 1em;
    font-weight: 600;
    color: #1e293b;
    flex: 1;
    min-width: 150px;
}

.toggle-arrow {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
    border: 2px solid #e2e8f0;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.toggle-arrow:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.toggle-arrow:active {
    transform: scale(0.95);
}

.arrow-icon {
    color: hsl(292, 52.20%, 51.60%);
    width: 16px;
    height: 16px;
    transition: transform 0.3s ease;
}

.toggle-arrow.open .arrow-icon {
    transform: rotate(180deg);
}

/* ===== GRID LAYOUT ===== */
.grid-main-container {
    margin: 12px 0;
    display: none;
    justify-content: center;
    width: 100%;
    opacity: 0;
    max-height: 0;
    overflow: hidden;
    transition: opacity 0.3s ease, max-height 0.3s ease;
}

.grid-main-container.show {
    display: flex;
    opacity: 1;
    max-height: 500px;
}

.grid-container-wrapper {
    max-width: 480px;
    margin: 0 auto;
}

.grid-row {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 16px;
    margin-bottom: 12px;
}

.grid-pair {
    display: flex;
    gap: 8px;
}

.grid-separator {
    width: 2px;
    height: 36px;
    background: #a8d8c4;
    border-radius: 2px;
    flex-shrink: 0;
}

.grid-box {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(145deg, #f0f7f4, #e6f2eb);
    border-radius: 8px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.15s ease;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    border: 2px solid transparent;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.grid-box:active {
    transform: scale(0.95);
}

.grid-box.active {
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
    color: white;
    box-shadow: 0 4px 14px rgba(109, 40, 217, 0.5); /* Purple shadow */
    border-color: #6d28d9; /* Purple border */
}

/* ===== MODAL STYLES ===== */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(6px);
    z-index: 9999;
    display: none;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.modal-overlay.active {
    display: block;
    opacity: 1;
}

.modal,
.rotation-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
}

.modal-content,
.rotation-modal-content {
    background: white;
    margin: auto;
    padding: 20px;
    border-radius: 12px;
    width: 95%;
    max-width: 400px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    position: relative;
    border: 1px solid #d1e7dd;
    animation: slideUp 0.3s ease;
    max-height: 85vh;
    overflow-y: auto;
}

.rotation-modal-content {
    /* Replaced orange border with a soft purple from your gradient */
    border: 2px solid #a18cd1; /* This is the purple tone from your gradient at 70% */
    max-width: 420px;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(40px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal h3,
.rotation-modal h3 {
    margin: 0 0 20px 0;
    text-align: center;
    color: #6d28d9; /* Purple instead of green */
    font-size: 1.4em;
    font-weight: 700;
}

.rotation-modal h3 {
    color: #a18cd1; /* Soft purple from your gradient (70% point) */
    font-size: 1.5em;
    position: relative;
    padding-bottom: 12px;
}

.rotation-modal h3::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 3px;
    /* Replaced orange gradient with your pink-purple gradient */
    background: linear-gradient(90deg, #ff9a9e, #fbc2eb, #a18cd1);
    border-radius: 3px;
}

.close {
    color: #9e9e9e;
    position: absolute;
    right: 12px;
    top: 12px;
    font-size: 28px;
    cursor: pointer;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #f0f7f4;
    transition: all 0.3s ease;
}

.close:hover {
    background: #e8f5e9;
    transform: scale(1.1);
}

.modal-subtitle {
    background: #f0f7f4;
    padding: 6px 10px;
    border-radius: 8px;
    margin-bottom: 15px;
    font-size: 0.9em;
    color: #6d28d9; /* Purple text */
    border-left: 3px solid #6d28d9; /* Purple border */
}

/* ===== PORT GRID ===== */
.port-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-top: 15px;
}

.port-box {
    height: 55px;
    min-height: 55px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
    border-radius: 8px;
    font-weight: 700;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border: 2px solid #d1e7dd;
    position: relative;
    overflow: hidden;
    padding: 4px;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.port-box:hover {
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}

.port-box:active {
    transform: scale(0.98);
}

.port-box.selected {
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
    color: white;
    box-shadow: 0 4px 14px rgba(109, 40, 217, 0.5); /* Purple shadow */
    border-color: #6d28d9; /* Purple border */
}

/* ===== SELECTION INFO ===== */
.selection-info {
    font-weight: 700;
    color: #6d28d9; /* Purple text */
    margin-top: 16px;
    min-height: 32px;
    padding: 8px 12px;
    background: #f0ecf9; /* Light purple background */
    border-radius: 8px;
    border-left: 4px solid #6d28d9; /* Purple border */
    font-size: 0.95em;
    display: none;
    align-items: center;
    gap: 8px;
}

.selection-info::before {
    content: '✓';
    color: #6d28d9; /* Purple checkmark */
    font-size: 1.3em;
    font-weight: bold;
}

/* ===== QUANTITY ROW ===== */
.qty-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    background: #f0f7f4;
    padding: 10px;
    margin-top: 14px;
    border-radius: 12px;
    border: 1px solid #d1e7dd;
    gap: 6px;
}

.qty-item {
    text-align: center;
    padding: 6px;
    border-radius: 8px;
    background: white;
    font-size: 0.9em;
}

.qty-item span {
    color: #6d28d9; /* Purple text */
    display: block;
    margin-bottom: 3px;
    font-weight: 600;
}

.qty-item b {
    color: #6d28d9; /* Purple bold text */
    font-weight: 700;
    font-size: 1.2em;
}

/* ===== CARD FOOTER ===== */
.card-footer {
    margin-top: 18px;
    padding-top: 14px;
    border-top: 1px solid #d1e7dd;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.footer-left,
.footer-right {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
    min-width: 120px;
}

.row-checker {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: #6d28d9; /* Purple checkbox */
}

.input-qty {
    text-align: right;
    padding: 6px 10px;
    border: 2px solid #a8d8c4;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    background: white;
    width: 100px;
    max-width: 40%;
    transition: all 0.3s ease;
}

.input-qty:focus {
    outline: none;
    border-color: #6d28d9; /* Purple focus border */
    box-shadow: 0 0 0 3px rgba(109, 40, 217, 0.2); /* Purple glow */
}

.input-qty:disabled {
    background: #f0f7f4;
    color: #95a5a6;
    cursor: not-allowed;
}

.input-qty:enabled {
    pointer-events: auto !important;
    background: white !important;
    color: #6d28d9 !important; /* Purple text */
    cursor: text !important;
    opacity: 1 !important;
}

/* ===== SUBMIT BUTTON ===== */
.submit-btn {
    position: fixed;
    bottom: 16px;
    right: 16px;
    left: 16px;
    padding: 14px;
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1.1em;
    font-weight: 700;
    box-shadow: 0 6px 20px rgba(109, 40, 217, 0.4); /* Purple shadow */
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.submit-btn::before {
    content: '✓';
    font-size: 1.1em;
}

.submit-btn:hover {
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 8px 25px rgba(109, 40, 217, 0.45); /* Enhanced purple shadow */
}

.submit-btn:active {
    transform: translateY(1px);
}

/* ===== ROTATION BUTTON ===== */
.rotation-btn {
    padding: 6px 14px;
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.9em;
    cursor: pointer;
    box-shadow: 0 2px 6px hsla(66, 91.6%, 58%, 0.3);
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.rotation-btn:hover {
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 4px 10px rgba(223, 161, 142, 0.4);
    background: linear-gradient(135deg, #ff9a9e 0%,#c0644a 20%, #fbc2eb 40%,hsl(258, 43.50%, 51.40%) 70%, #84fab0 100%);
}

.rotation-btn:active {
    transform: translateY(1px);
}

/* ===== ROTATION MODAL SPECIFIC ===== */
.location-input-container {
    margin-top: 15px;
}

.location-label {
    display: block;
    font-weight: 600;
    color: #5d4037;
    margin-bottom: 6px;
    font-size: 1em;
}

.location-input {
    width: 100%;
    padding: 10px 12px;
    border: 3px solid #ffcc80;
    border-radius: 8px;
    font-size: 1.1em;
    font-weight: 600;
    text-align: center;
    transition: all 0.3s ease;
    background: #fff3e0;
}

.location-input:focus {
    outline: none;
    border-color:hsl(296, 77.80%, 61.20%);
    box-shadow: 0 0 0 4px rgba(255, 152, 0, 0.2);
    background: white;
}

.location-input::placeholder {
    color: #9e9e9e;
    opacity: 1;
}

.rotation-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.rotation-btn-confirm,
.rotation-btn-cancel {
    flex: 1;
    padding: 10px;
    border: none;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.95em;
    cursor: pointer;
    transition: all 0.3s ease;
}

.rotation-btn-confirm {
    background: linear-gradient(135deg, #ff9a9e 0%,#c0644a 20%, #fbc2eb 40%,hsl(258, 43.50%, 51.40%) 70%, #84fab0 100%)
    color: white;
    box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4);
}

.rotation-btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(255, 152, 0, 0.5);
    background: linear-gradient(135deg, #ff8a00, #e65100);
}

.rotation-btn-cancel {
    background: linear-gradient(135deg, #9e9e9e, #616161);
    color: white;
    box-shadow: 0 4px 12px rgba(158, 158, 158, 0.3);
}

.rotation-btn-cancel:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(158, 158, 158, 0.4);
    background: linear-gradient(135deg, #757575, #424242);
}

.rotation-info {
    margin-top: 12px;
    padding: 8px 12px;
    background: #fff3e0;
    border-left: 4px solid #ff9800;
    border-radius: 8px;
    font-size: 0.95em;
    color: #5d4037;
}

.rotation-success {
    background: #f0ecf9; /* Light purple */
    border-left-color: #6d28d9; /* Purple border */
    color: #6d28d9; /* Purple text */
}

.rotation-error {
    background: #ffebee;
    border-left-color: #f44336;
    color: #c62828;
}

/* ===== PART NUMBER INPUT ===== */
.part-validation {
    margin-top: 16px;
    width: 100%;
}

.part-validation label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #6d28d9; /* Purple label */
}

.part-input {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #a8d8c4;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    transition: all 0.3s ease;
}

.part-input:focus {
    outline: none;
    border-color: #6d28d9; /* Purple focus */
    box-shadow: 0 0 0 3px rgba(109, 40, 217, 0.2); /* Purple glow */
}

.part-input.valid {
    border-color: #6d28d9; /* Purple valid */
    background-color: #f0ecf9; /* Light purple background */
}

.part-input.invalid {
    border-color: #f44336;
    background-color: #ffebee;
}

.scanner-hint {
    font-size: 0.8em;
    color: #6c757d;
    margin-top: 4px;
    display: block;
}

.part-status {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    min-height: 22px;
    font-size: 0.95em;
    font-weight: 600;
}

.part-status.success {
    color: #6d28d9; /* Purple success */
    animation: slideUpFade 0.3s ease-out;
    background: #f0ecf9; /* Light purple */
    padding: 6px 10px;
    border-radius: 6px;
    border-left: 3px solid #6d28d9; /* Purple border */
}

.part-status.error {
    color: #c62828;
}

@keyframes slideUpFade {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.masked-input {
    font-family: monospace;
    letter-spacing: 2px;
}

/* ===== JOB ORDER TABLE ===== */
.job-order-table {
    margin: 20px 0;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(109, 40, 217, 0.15); /* Purple shadow */
    background: white;
    border: 1px solid #d1e7dd;
    animation: fadeInUp 0.5s ease-out;
}

.job-order-table table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.job-order-table th,
.job-order-table td {
    padding: 12px 10px;
    border: 1px solid #e2e8f0;
    text-align: center;
    font-size: 0.95em;
    transition: all 0.2s ease;
}

.job-order-table th {
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
    color: white;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    overflow: hidden;
}

.job-order-table th::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
}

.job-order-table tr:nth-child(even) {
    background-color: #f8f9fa;
}

.job-order-table tr:nth-child(odd) {
    background-color: #ffffff;
}

.job-order-table tr:hover {
    background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(109, 40, 217, 0.1); /* Purple shadow */
}

.job-order-table tr:hover td {
    border-color: #a8d8c4;
    color: #6d28d9; /* Purple text */
    font-weight: 600;
}

.job-order-table td:first-child,
.job-order-table th:first-child {
    border-left: 3px solid #6d28d9; /* Purple border */
}

.job-order-table td:last-child,
.job-order-table th:last-child {
    border-right: 3px solid #6d28d9; /* Purple border */
}

.job-order-table td:nth-child(2) {
    font-weight: 700;
    color: #6d28d9; /* Purple text */
    background: #f0ecf9; /* Light purple */
    border-radius: 6px;
}

.job-order-table td:last-child {
    font-weight: 700;
    color: #6d28d9; /* Purple text */
    background: #f1f8e9;
    border-radius: 6px;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* ===== LOADING ===== */
#loadingBBB {
    text-align: center;
    padding: 40px;
    color: #6d28d9; /* Purple text */
    font-size: 1.2em;
}

/* ===== PREVENT MODAL FLICKERING ===== */
body.modal-open {
    overflow: hidden;
    padding-right: 0 !important;
}

/* ===== RESPONSIVE - TABLET ===== */
@media (min-width: 768px) {
    .modal-content,
    .rotation-modal-content {
        margin: 12% auto;
        padding: 30px;
        border-radius: 24px;
        max-width: 480px;
    }

    .rotation-modal-content {
        max-width: 420px;
        margin: 18% auto;
    }

    .submit-btn {
        left: auto;
        width: auto;
        padding: 16px 40px;
        border-radius: 45px;
    }

    .job-order-table th,
    .job-order-table td {
        padding: 8px 6px;
        font-size: 0.85em;
    }
}

/* ===== RESPONSIVE - DESKTOP ===== */
@media (min-width: 1024px) {
    .container {
        max-width: 1200px;
    }

    body {
        padding: 20px;
    }

    .header {
        padding: 16px 24px;
        border-radius: 16px;
        margin-bottom: 24px;
    }

    .pick-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08), 0 3px 10px rgba(109, 40, 217, 0.12); /* Purple shadow */
    }

    .grid-box:hover:not(.active) {
        transform: translateY(-1px) scale(1.03);
    }

    .submit-btn {
        position: fixed;
        bottom: 24px;
        right: 24px;
        left: auto;
        width: auto;
        padding: 12px 32px;
        border-radius: 40px;
        font-size: 1em;
    }

    .submit-btn:hover {
        transform: translateY(-2px) scale(1.03);
        box-shadow: 0 8px 25px rgba(109, 40, 217, 0.45), 0 4px 14px rgba(109, 40, 217, 0.35); /* Purple shadow */
    }
}

/* ===== MOBILE SPECIFIC ===== */
@media (max-width: 480px) {
    .grid-box {
        width: 42px;
        height: 42px;
        font-size: 13px;
    }

    .grid-row {
        gap: 12px;
    }

    .grid-pair {
        gap: 4px;
    }

    .grid-separator {
        width: 1px;
        margin: 0 2px;
        height: 28px;
    }

    .modal-content,
    .rotation-modal-content {
        max-width: 95%;
        margin: 20% auto 15%;
        padding: 18px;
    }

    .port-box {
        height: 50px;
        font-size: 12px;
    }

    .location-input {
        font-size: 1em;
        padding: 10px;
    }
}
    </style>
</head>
<body>
    <div id="modal-overlay" class="modal-overlay"></div>
    <div class="container">
        <div class="header">
            <h1>📦 Order Picking System</h1>
            <div class="subtitle">Container: <?php echo htmlspecialchars($pdaInput); ?>
                <button type="button" class="rotation-btn" onclick="openRotationModal('<?php echo htmlspecialchars($pdaInput); ?>', '')">
                    🔄 Rotation
                </button>  
            </div>
        </div>

        <div id="loadingBBB" style="display:none;">Loading...</div>
<!-- Rotation Modal -->
<div id="rotationModal" class="rotation-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:10001;backdrop-filter:blur(6px);">
    <div class="rotation-modal-content" style="background:white;margin:15% auto;padding:30px;border-radius:24px;width:95%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,0.3);position:relative;animation:slideUp 0.4s cubic-bezier(0.34,1.56,0.64,1);">
        <span class="close" onclick="closeRotationModal()" style="color:#9e9e9e;position:absolute;right:18px;top:18px;font-size:32px;cursor:pointer;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:all 0.3s ease;background:#f0f7f4;">&times;</span>
        
        <h3 style="margin:0 0 20px 0;text-align:center;color:#a18cd1;font-size:1.8em;font-weight:700;position:relative;padding-bottom:12px;">
            🔄 Rotation - <?php echo htmlspecialchars($pdaInput); ?>
            <span style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:80px;height:3px;background:linear-gradient(90deg, #ff9a9e, #fbc2eb, #a18cd1);border-radius:3px;"></span>
        </h3>
        
        <div class="location-input-container" style="margin-top:25px;">
            <label class="location-label" style="display:block;font-weight:600;color:#5d4037;margin-bottom:10px;font-size:1.1em;">📍 Input Location Point:</label>
            <input type="text"
                   id="locationPointInput"
                   class="location-input"
                   placeholder="Scan or Enter Location Point"
                   autofocus
                   onkeypress="handleLocationKeyPress(event)"
                   style="width:100%;padding:14px;border:2px solid #e2e8f0;border-radius:12px;font-size:1.2em;font-weight:600;text-align:center;transition:all 0.3s ease;background:#fff;">
        </div>
        
        <div class="rotation-info" id="rotationInfo" style="margin-top:15px;padding:12px;background:#f8fafc;border-left:4px solid #a18cd1;border-radius:8px;font-size:0.95em;color:#475569;">
            Please scan or enter the location point for rotation
        </div>
        
        <div class="rotation-actions" style="display:flex;gap:12px;margin-top:25px;">
            <button class="rotation-btn-cancel" onclick="closeRotationModal()" style="flex:1;padding:12px;background:white;color:black;border:none;border-radius:10px;font-weight:600;font-size:1em;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 12px hsla(0, 0.00%, 100.00%, 0.30);">
                Cancel
            </button>
            <button class="rotation-btn-confirm" onclick="submitRotation()" style="flex:1;padding:12px;background:linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);color:white;border:none;border-radius:10px;font-weight:600;font-size:1em;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 12px rgba(161, 140, 209, 0.3);">
                Confirm
            </button>
        </div>
    </div>
</div>

        <?php
        if (isset($result['data']) && is_array($result['data'])) {
            foreach ($result['data'] as $index => $row) {
                $pending = max(0, (float)$row['pickNum'] - (float)$row['pickFinishNum']);
                $isTPContainer = strpos(strtoupper($row['containerNo']), 'TP') === 0;
                
                $cascadeContainer = $row['cascadeContainerNo'] ?? '';
                $gridNumber = preg_replace('/[^0-9]/', '', $row['skuAttr2'] ?? '');
                $key = $cascadeContainer . '_' . $gridNumber;
                
                $totalSlots = isset($totalSlotsMap[$key]) ? $totalSlotsMap[$key] : 8;
                $totalSlots = min(max(1, $totalSlots), 8);
                
                $targetSlotNum = 1;
                $actualSlotIdentifier = "1";
                
                $rawSlotData = trim($row['skuAttr3'] ?? '');
                
                if (!empty($rawSlotData)) {
                    if (preg_match('/^(\d+)$/', $rawSlotData, $matches)) {
                        $targetSlotNum = intval($matches[1]);
                        $actualSlotIdentifier = $rawSlotData;
                        $targetSlotNum = min(max(1, $targetSlotNum), $totalSlots);
                    } else {
                        $actualSlotIdentifier = $rawSlotData;
                    }
                }
        ?>
        <div class="pick-form-container">
            <form id="APITable_<?php echo $index; ?>" enctype="multipart/form-data" autocomplete="off">
                <div class="pick-card">
                    <div class="card-header">
                        <h3>#<?php echo ($index + 1); ?> | <b><?php echo $row['containerNo']; ?></b></h3>
                        <span class="status-badge"><?php echo $row['billStatusName']; ?></span>
                    </div>

                    <div class="location-tag"><?php echo $row['skuNo']; ?><br/><span class="sku-name"><?php echo $row['skuName']; ?></span></div>

                    <div class="sku-no">Cascade Container: <?php echo $row['cascadeContainerNo']; ?></div>
                    <div class="sku-no">Grid: <strong><?php echo $row['skuAttr2']; ?></strong></div>
                    <div class="sku-no">Target Slot: <strong style="color:#2e7d32;"><?php echo $actualSlotIdentifier; ?></strong></div>
                    <div class="sku-no">Total Slots: <strong style="color:#2e7d32;"><?php echo $totalSlots; ?></strong></div>

                    <?php if (!$isTPContainer && $totalSlots > 0): ?>
                    <div class="grid-section">
                        <div class="grid-header">
                            <div class="grid-title">📍 Select Grid Slot</div>
                            <button type="button" class="toggle-arrow" onclick="toggleGrid(<?php echo $index; ?>)">
                                <svg class="arrow-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6,9 12,15 18,9"></polyline>
                                </svg>
                            </button>
                        </div>

                        <div class="grid-main-container" id="grid-container-<?php echo $index; ?>">
                            <div class="grid-container-wrapper">
                                <!-- Row 1 -->
                                <div class="grid-row">
                                    <div class="grid-pair">
                                        <div class="grid-box <?php echo ($gridNumber == 1) ? 'active' : ''; ?>" data-grid="1" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 1)">1</div>
                                        <div class="grid-box <?php echo ($gridNumber == 2) ? 'active' : ''; ?>" data-grid="2" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 2)">2</div>
                                    </div>
                                    <div class="grid-separator"></div>
                                    <div class="grid-pair">
                                        <div class="grid-box <?php echo ($gridNumber == 9) ? 'active' : ''; ?>" data-grid="9" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 9)">9</div>
                                        <div class="grid-box <?php echo ($gridNumber == 10) ? 'active' : ''; ?>" data-grid="10" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 10)">10</div>
                                    </div>
                                </div>

                                <!-- Row 2 -->
                                <div class="grid-row">
                                    <div class="grid-pair">
                                        <div class="grid-box <?php echo ($gridNumber == 3) ? 'active' : ''; ?>" data-grid="3" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 3)">3</div>
                                        <div class="grid-box <?php echo ($gridNumber == 4) ? 'active' : ''; ?>" data-grid="4" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 4)">4</div>
                                    </div>
                                    <div class="grid-separator"></div>
                                    <div class="grid-pair">
                                        <div class="grid-box <?php echo ($gridNumber == 11) ? 'active' : ''; ?>" data-grid="11" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 11)">11</div>
                                        <div class="grid-box <?php echo ($gridNumber == 12) ? 'active' : ''; ?>" data-grid="12" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 12)">12</div>
                                    </div>
                                </div>

                                <!-- Row 3 -->
                                <div class="grid-row">
                                    <div class="grid-pair">
                                        <div class="grid-box <?php echo ($gridNumber == 5) ? 'active' : ''; ?>" data-grid="5" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 5)">5</div>
                                        <div class="grid-box <?php echo ($gridNumber == 6) ? 'active' : ''; ?>" data-grid="6" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 6)">6</div>
                                    </div>
                                    <div class="grid-separator"></div>
                                    <div class="grid-pair">
                                        <div class="grid-box <?php echo ($gridNumber == 13) ? 'active' : ''; ?>" data-grid="13" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 13)">13</div>
                                        <div class="grid-box <?php echo ($gridNumber == 14) ? 'active' : ''; ?>" data-grid="14" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 14)">14</div>
                                    </div>
                                </div>

                                <!-- Row 4 -->
                                <div class="grid-row">
                                    <div class="grid-pair">
                                        <div class="grid-box <?php echo ($gridNumber == 7) ? 'active' : ''; ?>" data-grid="7" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 7)">7</div>
                                        <div class="grid-box <?php echo ($gridNumber == 8) ? 'active' : ''; ?>" data-grid="8" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 8)">8</div>
                                    </div>
                                    <div class="grid-separator"></div>
                                    <div class="grid-pair">
                                        <div class="grid-box <?php echo ($gridNumber == 15) ? 'active' : ''; ?>" data-grid="15" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 15)">15</div>
                                        <div class="grid-box <?php echo ($gridNumber == 16) ? 'active' : ''; ?>" data-grid="16" data-index="<?php echo $index; ?>" onclick="openPortModal(<?php echo $index; ?>, 16)">16</div>
                                    </div>
                                </div>
                            </div>
                        </div>   
                        
                        <div class="qty-row">
                            <div class="qty-item">
                                <span>📦 Picking</span>
                                <b><?php echo (float)$row['pickNum']; ?></b>
                            </div>
                            <div class="qty-item">
                                <span>✅ Issued</span>
                                <b><?php echo (float)$row['pickFinishNum']; ?></b>
                            </div>
                            <div class="qty-item">
                                <span>📏 UOM</span>
                                <b style="color:#2e7d32;"><?php echo $row['unit']; ?></b>
                            </div>
                        </div>
                    
                        <?php 
                        $outOrderNo = $row['outOrderNo'];
                        $outOrderDId = $row['outOrderDId'];
                        $jobOrderTable = getSpecificOutOrderDetail($outOrderNo, $outOrderDId);
                        if (!empty($jobOrderTable)) {
                            echo $jobOrderTable;
                        }
                        ?>

                        <div class="card-footer">
                            <div class="footer-left">
                                <input type="checkbox" 
                                    class="row-checker" 
                                    onclick="selectRow(this, <?php echo $index; ?>)" 
                                    data-id="<?php echo $row['id']; ?>" 
                                    data-version="<?php echo $row['version']; ?>"
                                    data-index="<?php echo $index; ?>"> 
                                <span style="font-weight:600; color:#4d7c5f;">Select for Picking</span>
                            </div>
                            <div class="footer-right">
                                <span style="font-weight:600; color:#4d7c5f;">Pick Qty:</span>
                                <input type="number" class="input-qty" value="<?php echo $pending; ?>" id="input_<?php echo $index; ?>" placeholder="Quantity" disabled>
                            </div>
                        </div>

                        <!-- Selection Info -->
                        <div id="selection-info-<?php echo $index; ?>" class="selection-info"></div>

                        <!-- Modal -->
                        <div id="modal-<?php echo $index; ?>" class="modal">
                            <div class="modal-content">
                                <span class="close" onclick="closeModal(<?php echo $index; ?>)">&times;</span>
                                <h3>📦 Select Slot</h3>
                                <div class="modal-subtitle">
                                    <div style="margin-bottom: 6px;">
                                        <span style="color:#4d7c5f;">Grid:</span>
                                        <strong style="color:#2e7d32; font-size:1.1em;" id="modal-grid-<?php echo $index; ?>">?</strong>
                                    </div>
                                    <div>
                                        <span style="color:#4d7c5f;">Total Slots:</span>
                                        <strong style="color:#2e7d32;"><?php echo $totalSlots; ?></strong> |
                                        <span style="color:#4d7c5f;">Target:</span>
                                        <strong style="color:#c62828;">Slot <?php echo $targetSlotNum; ?></strong>
                                    </div>
                                </div>
                                <div class="port-grid" id="port-grid-<?php echo $index; ?>">
                                    <?php for($p = 1; $p <= $totalSlots; $p++): 
                                        $isSelected = ($p == $targetSlotNum) ? 'selected' : '';
                                    ?>
                                    <div class="port-box <?php echo $isSelected; ?>" 
                                         data-port="<?php echo $p; ?>" 
                                         data-label="Slot <?php echo $p; ?>"
                                         data-index="<?php echo $index; ?>"
                                         onclick="selectSlot(<?php echo $index; ?>, <?php echo $p; ?>, 'Slot <?php echo $p; ?>')">
                                        <div style="display:flex; flex-direction:column; align-items:center; gap:4px;">
                                            <span style="font-size:0.8em; opacity:0.8;">Slot</span>
                                            <span style="font-size:1.3em; font-weight:800;"><?php echo $p; ?></span>
                                        </div>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                                <div style="margin-top: 15px;">
                                    <label style="font-weight:600; color:#2d6a4f; font-size:0.95em;">Your Part Number:</label>
                                    <input type="text" 
                                        id="partNo_<?php echo $index; ?>"
                                        class="part-input masked-input"
                                        placeholder="Scan barcode or type part number..."
                                        data-expected="<?php echo htmlspecialchars($row['skuCode']); ?>"
                                        autocomplete="off"
                                        autocorrect="off"
                                        autocapitalize="off"
                                        spellcheck="false"
                                        style="width:100%; padding:10px 12px; border:2px solid #a8d8c4; border-radius:8px; font-size:1em; font-weight:600; margin-top:5px;"
                                        onkeydown="handlePartKey(event, <?php echo $index; ?>)"
                                        onblur="validatePartDebounced(<?php echo $index; ?>, true)">
                                    <div class="scanner-hint">SCAN or TYPE • Press ENTER to validate</div>
                                    <div id="partStatus_<?php echo $index; ?>" class="part-status"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="sku-no" style="color:#c62828; font-style:italic; margin-top:10px; font-weight:600;">
                        ⚠️ This container has no slots (TP type)
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php } } ?>

        <button type="button" class="submit-btn" onclick="submitAllPicks()">
            Picking All
        </button>
    </div>

    <script>
        // Store selections
        const itemSelections = {};
        const validationTimers = {};

        function selectRow(checkbox, index) {
            const partInput = document.getElementById(`partNo_${index}`);
            const expectedPartNo = partInput ? partInput.dataset.expected : '';
            const enteredPartNo = partInput ? partInput.value.trim() : '';
            
            if (checkbox.checked && enteredPartNo.toUpperCase() !== expectedPartNo.toUpperCase()) {
                checkbox.checked = false;
                alert(`⚠️ Please check your part number first by clicking grid!`);
                partInput.focus();
                return;
            }
            
            const allCheckboxes = document.querySelectorAll('.row-checker');
            const allInputs = document.querySelectorAll('.input-qty');
            const allCards = document.querySelectorAll('.pick-card');
            
            if (checkbox.checked) {
                allCheckboxes.forEach((cb, i) => {
                    if (i !== index) cb.checked = false;
                });
                
                allInputs.forEach((input, i) => {
                    input.disabled = (i !== index);
                    if (i !== index) input.value = '';
                });
                
                const targetInput = document.getElementById('input_' + index);
                targetInput.disabled = false;
                targetInput.removeAttribute('readonly');
                targetInput.style.pointerEvents = 'auto';
                
                allCards.forEach((card, i) => {
                    card.style.borderColor = (i === index) ? '#2e7d32' : '#d1e7dd';
                });
                
                setTimeout(() => {
                    targetInput.focus();
                    targetInput.select();
                }, 100);
                
            } else {
                const targetInput = document.getElementById('input_' + index);
                targetInput.disabled = true;
                document.querySelector(`#APITable_${index} .pick-card`).style.borderColor = '#d1e7dd';
            }
        }

        function toggleGrid(index) {
            const gridContainer = document.getElementById(`grid-container-${index}`);
            const toggleBtn = document.querySelector(`#APITable_${index} .toggle-arrow`);
            
            if(gridContainer.classList.contains('show')) {
                gridContainer.classList.remove('show');
                toggleBtn.classList.remove('open');
            } else {
                gridContainer.classList.add('show');
                toggleBtn.classList.add('open');
            }
        }

        function openPortModal(index, gridNum) {
            const clickedBox = event.currentTarget;
            
            if (!clickedBox.classList.contains('active')) {
                return;
            }
            
            itemSelections[index] = itemSelections[index] || {};
            itemSelections[index].grid = gridNum;
            
            document.getElementById(`modal-grid-${index}`).textContent = gridNum;
            document.getElementById(`modal-${index}`).style.display = 'flex';
            
            const infoDiv = document.getElementById(`selection-info-${index}`);
            infoDiv.innerHTML = `Grid ${gridNum} selected`;
            infoDiv.style.display = 'flex';
            
            openModalOverlay();
        }

        function closeModal(index) {
            document.getElementById(`modal-${index}`).style.display = 'none';
            closeModalOverlay();
        }

        function selectSlot(index, portNum, slotLabel) {
            const portBoxes = document.querySelectorAll(`#port-grid-${index} .port-box`);
            portBoxes.forEach(port => {
                port.classList.remove('selected');
                if(parseInt(port.dataset.port) === portNum) {
                    port.classList.add('selected');
                }
            });
            
            itemSelections[index] = itemSelections[index] || {};
            itemSelections[index].slotNum = portNum;
            itemSelections[index].slotLabel = slotLabel;
            
            const selectionInfo = document.getElementById(`selection-info-${index}`);
            const gridNum = itemSelections[index].grid || document.getElementById(`modal-grid-${index}`).textContent;
            selectionInfo.innerHTML = `Grid ${gridNum} • ${slotLabel} selected`;
            selectionInfo.style.display = 'flex';
            
            closeModal(index);
        }

        let currentRotationContainer = '';
        let currentRotationId = '';

        function openRotationModal(containerNo, itemId) {
            currentRotationContainer = containerNo;
            currentRotationId = itemId;
            
            document.getElementById('locationPointInput').value = '';
            document.getElementById('rotationInfo').textContent = `Rotating container: ${containerNo}`;
            document.getElementById('rotationInfo').className = 'rotation-info';
            
            document.getElementById('rotationModal').style.display = 'flex';
            
            setTimeout(() => {
                document.getElementById('locationPointInput').focus();
            }, 300);
            
            openModalOverlay();
        }

        function closeRotationModal() {
            document.getElementById('rotationModal').style.display = 'none';
            currentRotationContainer = '';
            currentRotationId = '';
            closeModalOverlay();
        }

        function openModalOverlay() {
            document.body.classList.add('modal-open');
            document.getElementById('modal-overlay').classList.add('active');
        }

        function closeModalOverlay() {
            const allModals = document.querySelectorAll('.modal[style*="display: flex"], .rotation-modal[style*="display: flex"]');
            if (allModals.length === 0) {
                document.body.classList.remove('modal-open');
                document.getElementById('modal-overlay').classList.remove('active');
            }
        }

        function handleLocationKeyPress(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitRotation();
            }
        }

        function submitRotation() {
            const locationPoint = document.getElementById('locationPointInput').value.trim();
            
            if (!locationPoint) {
                const infoDiv = document.getElementById('rotationInfo');
                infoDiv.textContent = '⚠️ Please enter a location point!';
                infoDiv.className = 'rotation-info rotation-error';
                return;
            }
            
            const infoDiv = document.getElementById('rotationInfo');
            infoDiv.textContent = '🔄 Processing rotation...';
            infoDiv.className = 'rotation-info';
            
            const rotationData = {
                locationPoint: locationPoint
            };
            
            console.log('Rotation data:', rotationData);
            
            fetch('sfi_rotation_submit.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer <?php echo $token; ?>'
                },
                body: JSON.stringify(rotationData),
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Rotation response:', data);
                
                if (data.success === true) {
                    infoDiv.textContent = '✅ ' + (data.message || 'Rotation completed successfully!');
                    infoDiv.className = 'rotation-info rotation-success';
                    
                    setTimeout(() => {
                        closeRotationModal();
                        alert('✅ Rotation successful: ' + locationPoint);
                    }, 1500);
                } else {
                    infoDiv.textContent = '❌ ' + (data.message || 'Rotation failed');
                    infoDiv.className = 'rotation-info rotation-error';
                    
                    setTimeout(() => {
                        document.getElementById('locationPointInput').focus();
                    }, 1000);
                }
            })
            .catch((error) => {
                console.error('Rotation error:', error);
                infoDiv.textContent = '❌ Rotation failed: ' + (error.message || 'Unknown error');
                infoDiv.className = 'rotation-info rotation-error';
            });
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const overlay = document.getElementById('modal-overlay');
                if (overlay.classList.contains('active')) {
                    if (document.getElementById('rotationModal').style.display === 'flex') {
                        closeRotationModal();
                        return;
                    }
                    document.querySelectorAll('.modal').forEach(modal => {
                        if (modal.style.display === 'flex') {
                            const index = modal.id.replace('modal-', '');
                            closeModal(index);
                        }
                    });
                }
            }
            
            if (event.key === 'Enter') {
                event.preventDefault();
                
                const activeInput = document.querySelector('.input-qty:focus');
                if (activeInput && activeInput.value.trim() !== '') {
                    const index = activeInput.id.replace('input_', '');
                    
                    const checkbox = document.querySelector(`.row-checker[data-id][onclick*="${index}"]`);
                    if (checkbox && !checkbox.checked) {
                        checkbox.click();
                    }
                    
                    submitAllPicks();
                }
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.input-qty').forEach(input => {
                input.addEventListener('click', function() {
                    if (!this.disabled) {
                        this.focus();
                    }
                });
                
                input.addEventListener('keydown', function(e) {
                    if (this.disabled) {
                        e.preventDefault();
                    }
                });
            });
        });


        window.partInputValues = {};
        document.querySelectorAll('.masked-input').forEach(input => {
            let actualValue = '';

            input.addEventListener('input', function(e) {
                const newValue = e.target.value;
                const previousLength = actualValue.length;
                const currentLength = newValue.length;

                if (currentLength > previousLength) {
                    const newChars = newValue.slice(previousLength);
                    actualValue += newChars;
                    this.value = '◆'.repeat(actualValue.length);
                }
                else if (currentLength < previousLength) {
                    actualValue = actualValue.slice(0, currentLength);
                    this.value = '◆'.repeat(actualValue.length);
                }
                else if (currentLength === 0) {
                    actualValue = '';
                    this.value = '';
                }
                else {
                    this.value = '◆'.repeat(actualValue.length);
                }

                // ✅ Store real value in global object
                const index = this.id.replace('partNo_', '');
                window.partInputValues[index] = actualValue;
            });

            input.addEventListener('blur', function() {
                const index = this.id.replace('partNo_', '');
                this.dataset.realValue = window.partInputValues[index] || '';
            });

            input.addEventListener('focus', function() {
                this.value = actualValue;
            });
        });

        function submitAllPicks() {
            let hasSelected = false;
            let submitData = [];
            
            document.querySelectorAll('.row-checker:checked').forEach(checked => {
                const index = checked.dataset.index;
                const qtyInput = document.getElementById('input_' + index);
                
                const qty = qtyInput.value.trim();
                if (!qty || parseFloat(qty) <= 0) {
                    return;
                }
                
                hasSelected = true;
                const selection = itemSelections[index] || {};
                submitData.push({
                    "id": checked.getAttribute('data-id'),
                    "version": parseInt(checked.getAttribute('data-version')),
                    "pickNum": parseFloat(qty),
                    "isSuperPick": false,
                    "selectedGrid": selection.grid || null,
                    "selectedPort": selection.slotLabel || null
                });
            });
            
            if (!hasSelected) {
                alert("⚠️ Please select items with valid quantity!");
                return;
            }
            
            console.log("Ready for submit ", submitData);
            
            fetch('sfi_scanned_submit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(submitData),
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                console.log('Success:', data);
                alert('✅ Picking completed successfully!');
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ Submission failed: ' + error.message);
            });
        }

        document.addEventListener('touchstart', function(event) {
            if (event.touches.length > 1) {
                event.preventDefault();
            }
        });

        function handlePartKey(event, index) {
            if (event.key === 'Enter') {
                event.preventDefault();
                validatePartNow(index);
            } else if (event.key === 'Escape') {
                document.getElementById(`partNo_${index}`).value = '';
                clearPartStatus(index);
            }
        }

        function validatePartNow(index) {
            const input = document.getElementById(`partNo_${index}`);
            // ✅ Always get the real typed/scanned value
            const actual = (window.partInputValues[index] || '').trim().toUpperCase();
            const expected = input.dataset.expected.trim().toUpperCase();
            const statusDiv = document.getElementById(`partStatus_${index}`);
            
            if (validationTimers[index]) {
                clearTimeout(validationTimers[index]);
                delete validationTimers[index];
            }
            
            if (!actual) {
                clearPartStatus(index);
                input.classList.remove('valid', 'invalid');
                return;
            }
            
            if (actual === expected) {
                input.classList.add('valid');
                input.classList.remove('invalid');
                
                statusDiv.className = 'part-status success';
                statusDiv.innerHTML = `<span>✓</span> Part number correct!`;
                
                const checkbox = document.querySelector(`.row-checker[data-index="${index}"]`);
                if (checkbox && !checkbox.checked) {
                    checkbox.checked = true;
                    
                    const event = new Event('change', { bubbles: true });
                    checkbox.dispatchEvent(event);
                    
                    const card = checkbox.closest('.pick-card');
                    if (card) {
                        card.style.opacity = '0.7';
                        card.style.borderLeft = '4px solid #4caf50';
                    }
                }
                
                setTimeout(() => {
                    const modalWithIndex = document.getElementById(`modal-${index}`);
                    if (modalWithIndex) {
                        modalWithIndex.style.display = 'none';
                        closeModalOverlay();
                    }
                    
                    setTimeout(() => {
                        input.value = '';
                        clearPartStatus(index);
                        input.classList.remove('valid', 'invalid');
                    }, 300);
                }, 800);
                
            } else {
                input.classList.add('invalid');
                input.classList.remove('valid');
                statusDiv.className = 'part-status error';
                
                // 🔍 Show helpful debug hint
                const expectedDisplay = input.dataset.expected.trim();
                const realEntered = input.value.trim();
                statusDiv.innerHTML = `
                    <span>✗</span> Wrong part number!<br>
                    <small style="font-weight:normal; opacity:0.8;">
                        You entered: <strong>${realEntered}</strong><br>
                        Expected: <strong>${expected}</strong>
                    </small>
                `;
            }
        }

        function validatePartDebounced(index, force = false) {
            const input = document.getElementById(`partNo_${index}`);
            const value = input.value.trim();
            
            if (validationTimers[index]) {
                clearTimeout(validationTimers[index]);
                delete validationTimers[index];
            }
            
            if (!value) {
                clearPartStatus(index);
                input.classList.remove('valid', 'invalid');
                return;
            }
            
            if (force) {
                validatePartNow(index);
            } else {
                validationTimers[index] = setTimeout(() => {
                    validatePartNow(index);
                    delete validationTimers[index];
                }, 1500);
            }
        }

        function clearPartStatus(index) {
            const statusDiv = document.getElementById(`partStatus_${index}`);
            if (statusDiv) {
                statusDiv.className = 'part-status';
                statusDiv.innerHTML = '';
            }
        }
    </script>
</body>
</html>