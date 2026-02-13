<?php
// inbound_scanned.php - FULLY DEBUGGED & FIXED
include('config.php');
session_start();

$token = $_SESSION['auth_token'] ?? null;
if (!$token) {
    echo '<div style="color:#ef4444; padding:16px; background:#fef2f2; border-radius:12px; font-weight:600; text-align:center;">
        ❌ Session expired. Please login again.
    </div>';
    exit;
}

$erpOrderNo = trim($_POST['pdaInput'] ?? '');
if ($erpOrderNo === '') {
    echo '<div style="color:#f59e0b; padding:16px; background:#fffbeb; border-radius:12px; font-weight:600; text-align:center;">
        ⚠️ No ERP Order Number detected. Please scan again.
    </div>';
    exit;
}

// === FETCH INBOUND ORDER DETAILS ===
$params = [
    "pageIndex" => 1,
    "pageRows" => 99999,
    "sortField" => "",
    "sortType" => "",
    "search" => [
        "inOrderNo" => "",
        "inOrderStatus" => null,
        "inOrderType" => null,
        "skuNo" => "",
        "batchNo" => "",
        "erpNo" => $erpOrderNo
    ]
];

$url = "http://192.188.11.100:5091/api/InOrder/GetInOrderDataList";
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

$result = json_decode($response, true);

if (!isset($result['data']) || !is_array($result['data']) || empty($result['data'])) {
    echo '<div style="color:#ef4444; padding:16px; background:#fef2f2; border-radius:12px; font-weight:600; text-align:center;">
        ❌ No inbound order found for: ' . htmlspecialchars($erpOrderNo) . '
    </div>';
    exit;
}

$order = $result['data'][0];
$details = $order['inOrderDetailDtos'] ?? [];

if (empty($details)) {
    echo '<div style="color:#ef4444; padding:16px; background:#fef2f2; border-radius:12px; font-weight:600; text-align:center;">
        ❌ No items found in inbound order: ' . htmlspecialchars($erpOrderNo) . '
    </div>';
    exit;
}

$areas = [
    '01' => 'RM Warehouse',
    '02' => 'Finished Goods Warehouse',
    '03' => 'RM-AGV Warehouse'
];
?>

<style>
.split-item-btn {
    padding: 12px 28px;
    background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
    border: none;
    border-radius: 50px;
    color: white;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    font-weight: 600;
    font-size: 16px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(106, 17, 203, 0.35);
    transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    letter-spacing: 0.3px;
    outline: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.split-item-btn:hover {
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 7px 22px rgba(106, 17, 203, 0.45);
    background: linear-gradient(135deg, #5a0dbb 0%, #1a67e0 100%);
}

.split-item-btn:active {
    transform: translateY(1px) scale(0.98);
    box-shadow: 0 3px 10px rgba(106, 17, 203, 0.3);
}

.action-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.select-all-btn {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.select-all-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.clear-all-btn {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.clear-all-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
}

/* CRITICAL FIX: Prevent invisible overlays from blocking clicks */
.modal-overlay,
#modal-overlay {
    pointer-events: none !important;
}
.modal-overlay[style*="display: block"],
#modal-overlay[style*="display: block"] {
    pointer-events: auto !important;
}
</style>

<!-- ROTATION MODAL OVERLAY -->
<div id="modal-overlay" class="modal-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);backdrop-filter:blur(6px);z-index:9999;display:none;opacity:0;transition:opacity 0.2s ease;"></div>

<div style="margin-top:20px; padding:3px; border-radius:20px;
background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%) border-box;">

    <div style="background:#fff; padding:28px; border-radius:18px; box-shadow:0 10px 25px rgba(0,0,0,.1); margin-bottom:100px;">

        <!-- Order Header -->
        <div style="text-align:center; margin-bottom:20px; padding-bottom:15px; border-bottom:2px solid #f8fafc;">
            <h3 style="font-size:1.4rem; font-weight:800; color:#1e293b; margin-bottom:8px;">Palletizing Order</h3>
            <div style="font-family:monospace; color:#64748b; font-size:0.95rem;">
                ERP: <?php echo htmlspecialchars($erpOrderNo); ?>
                <button type="button" class="rotation-btn" onclick="openRotationModal('<?php echo htmlspecialchars($erpOrderNo); ?>', '')"
                        style="padding:6px 14px;background:linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);color:white;border:none;border-radius:8px;font-weight:600;font-size:0.9em;cursor:pointer;box-shadow:0 2px 6px hsla(66,91.6%,58%,0.3);display:inline-flex;align-items:center;gap:6px;margin-left:12px;">
                    🔄 Rotation
                </button>
            </div>
        </div>

        <!-- DESTINATION SELECTOR (SHARED) - FIXED: Added onchange inline -->
        <div style="margin-bottom:24px;">
            <label style="font-weight:700; font-size:0.9rem; margin-bottom:8px; display:block;">📦 Destination Area</label>
            <select id="areaSelect" onchange="handleAreaChange()" style="width:100%; padding:14px; border-radius:12px; border:2px solid #e2e8f0; font-size:1rem; font-weight:600; background:white; height:58px;">
                <option value="" selected disabled>Select Area</option>
                <?php foreach ($areas as $code => $name): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- SELECT ALL / CLEAR ALL BUTTONS -->
        <div style="margin-bottom:20px; display:flex; gap:12px; justify-content:center;">
            <button class="action-btn select-all-btn" onclick="selectAllItems()">
                ✓ Select All
            </button>
            <button class="action-btn clear-all-btn" onclick="clearAllItems()">
                ✗ Clear All
            </button>
        </div>

        <!-- ALL ITEMS - EACH IN OWN CARD -->
        <div id="itemsContainer" style="display:flex; flex-direction:column; gap:16px;">
            <?php foreach ($details as $index => $detail):
                $plan = (float)($detail['planNum'] ?? 0);
                $grouped = (float)($detail['groupedNum'] ?? 0);
                $remaining = max(0, $plan - $grouped);
                $itemId = $detail['id'];
            ?>
            <div class="item-card" data-item-id="<?php echo $index; ?>" style="
                background: white;
                border-radius: 16px;
                padding: 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.05);
                border: 2px solid #e2e8f0;
                position: relative;
                overflow: hidden;
            ">
                <input type="hidden" class="hidden-item-id" value="<?php echo htmlspecialchars($detail['id']); ?>">
                <input type="hidden" class="hidden-part-number" value="<?php echo htmlspecialchars($detail['skuCode'] ?? 'N/A'); ?>">

                <!-- Top accent bar -->
                <div style="height:4px; background:linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%); margin: -20px -20px 16px -20px;"></div>

                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                    <div>
                        <div style="font-size:1.1rem; font-weight:800; color:#1e293b;">
                            <?php echo htmlspecialchars($detail['skuNo'] ?? 'N/A'); ?>
                        </div>
                        <div style="font-size:0.85rem; color:#64748b; margin-top:4px;">
                            <?php echo htmlspecialchars($detail['skuName'] ?? ''); ?>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:0.9rem; font-weight:700; color:#334155;">
                            <?php echo htmlspecialchars($detail['unit'] ?? 'PCS'); ?>
                        </div>
                    </div>
                </div>

                <div style="display:flex; gap:12px; margin-bottom:16px;">
                    <div style="flex:1;">
                        <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:4px;">
                            Plan Qty
                        </label>
                        <div style="font-size:1.1rem; font-weight:700; color:#0d9488;"><?php echo $plan; ?></div>
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:4px;">
                            Remaining
                        </label>
                        <div style="font-size:1.1rem; font-weight:700; color:#7c2d12;"><?php echo $remaining; ?></div>
                    </div>
                </div>

                <!-- Quantity Input -->
                <div style="margin-bottom:16px;">
                    <label style="font-size:0.75rem; font-weight:700; color:#475569; display:block; margin-bottom:6px;">
                        Palletizing Quantity
                    </label>
                    <input type="number"
                           class="qty-input"
                           value="<?php echo $remaining; ?>"
                           min="0"
                           max="<?php echo $remaining; ?>"
                           data-item-index="<?php echo $index; ?>"
                           data-item-id="<?php echo $itemId; ?>"
                           style="width:100%; padding:10px; border-radius:8px; border:2px solid #cbd5e1; font-size:1rem; font-weight:600;"
                           oninput="validateItemQty(this)">
                </div>


                <!-- CONTAINER & AGV FIELDS WITH SLOT MEMORY -->
                <div class="agv-container-section" style="margin-top:16px; padding-top:16px; border-top:1px solid #f1f5f9; display:none;">
                    <!-- Cascade Container -->
                    <div style="margin-bottom:12px;">
                        <label style="font-size:.75rem; font-weight:800; color:#475569;">Cascade Container</label>
                        <input type="text"
                            class="cascade-input"
                            value=""
                            placeholder="Scan Container ID"
                            data-item-id="<?php echo $itemId; ?>"
                            style="width:100%; padding:10px; margin-top:4px; border-radius:8px; border:1px solid #cbd5e1;">
                    </div>

                    <!-- Grid Field -->
                    <div style="margin-bottom:12px;">
                        <label style="font-size:.75rem; font-weight:800; color:#475569;">Grid</label>
                        <input type="text"
                            class="grid-input"
                            value=""
                            placeholder="Enter Grid"
                            data-item-id="<?php echo $itemId; ?>"
                            oninput="handleGridInput(this)"
                            style="width:100%; padding:10px; border-radius:8px; border:1px solid #cbd5e1;">
                    </div>

                    <!-- Total Slots (Set First) -->
                    <div style="margin-bottom:12px;">
                        <label style="font-size:.75rem; font-weight:800; color:#475569;">
                            📊 TOTAL SLOTS 
                            <span style="color:#10b981; font-size:0.7rem;">(Set this first!)</span>
                        </label>
                        <input type="number"
                            class="total-input"
                            value=""
                            placeholder="How many slots?"
                            min="1"
                            max="99"
                            data-item-id="<?php echo $itemId; ?>"
                            onchange="handleTotalSlotsChange(this)"
                            style="width:100%; padding:10px; border-radius:8px; border:2px solid #10b981; font-weight:700; font-size:1.1rem;">
                    </div>

                    <!-- Slot Selection Display -->
                    <div class="slot-selection-display" style="margin-bottom:12px; display:none;">
                        <label style="font-size:.75rem; font-weight:800; color:#475569; margin-bottom:8px; display:block;">
                            🎯 Select Target Slot
                        </label>
                        <div class="slot-grid" style="display:grid; grid-template-columns:repeat(4, 1fr); gap:8px;">
                            <!-- Slot buttons will be dynamically generated here -->
                        </div>
                    </div>

                    <!-- Hidden input to store selected slot -->
                    <input type="hidden" class="slot-input" value="" data-item-id="<?php echo $itemId; ?>">
                    
                    <!-- Slot Summary Display -->
                    <div class="slot-summary" style="padding:10px; background:#f0fdf4; border-radius:8px; border-left:3px solid #10b981; display:none;">
                        <div style="font-size:0.8rem; color:#166534; font-weight:600;">
                            <span class="summary-text">No slot selected</span>
                        </div>
                    </div>
                </div>

                <!-- CHECKBOX FOR PALLETIZING -->
                <div style="margin-top:12px; padding:10px; background:#f8fafc; border-radius:8px; display:flex; align-items:center; gap:10px;">
                    <input type="checkbox"
                           class="palletize-checkbox"
                           id="palletize_<?php echo $itemId; ?>"
                           data-item-id="<?php echo $itemId; ?>"
                           onchange="togglePalletizeItem(this, '<?php echo $itemId; ?>')">
                    <label for="palletize_<?php echo $itemId; ?>" style="font-weight:600; color:#475569;">
                        Ready for Palletizing
                    </label>
                </div>

                <!-- SPLIT BUTTON FOR THIS ITEM -->
                <div style="margin-top:16px; text-align:center;">
                    <button class="split-item-btn" onclick="splitItemCardWithSlots(this)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M12 5v14M5 12h14" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Split Item
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <input type="hidden" id="erpOrderNo" value="<?php echo htmlspecialchars($erpOrderNo); ?>">
        <input type="hidden" id="totalItems" value="<?php echo count($details); ?>">
    </div>
</div>

<!-- FIXED CIRCULAR PALLETIZING BUTTON - ADDED ONCLICK INLINE -->
<div style="position:fixed; bottom:24px; right:24px; z-index:1000;">
    <button id="palletizeBtn"
            onclick="openPalletizeModal()"
            style="width: 60px; height: 60px; border-radius: 50%; padding: 0;
                   background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
                   color: white; border: none; font-size: 1.4rem; font-weight: bold;
                   box-shadow: 0 6px 20px rgba(161, 140, 209, 0.4);
                   cursor: pointer; display: flex; align-items: center;
                   justify-content: center; transition: all 0.2s ease;"
            onmouseover="this.style.transform='scale(1.1)'; this.style.boxShadow='0 8px 25px rgba(161, 140, 209, 0.6)';"
            onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 6px 20px rgba(161, 140, 209, 0.4)';">
        📦
    </button>
</div>

<!-- PALLETIZING MODAL -->
<div id="palletizeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:10002; backdrop-filter:blur(6px);">
    <div style="background:white; margin:15% auto; padding:30px; border-radius:20px; max-width:450px; box-shadow:0 20px 50px rgba(0,0,0,0.4); animation:slideIn 0.3s ease;">
        <div style="text-align:center; margin-bottom:25px;">
            <h3 style="color:#1e293b; font-size:1.4rem; font-weight:800;">📦 Palletizing</h3>
            <p style="color:#64748b; margin-top:8px;">ERP: <?php echo htmlspecialchars($erpOrderNo); ?></p>
        </div>

        <div style="margin-bottom:25px;">
            <label style="display:block; font-weight:600; color:#334155; margin-bottom:8px;">Scan Container Number:</label>
            <input type="text"
                   id="containerScanInput"
                   placeholder="Scan container barcode..."
                   style="width:100%; padding:14px; border:2px solid #e2e8f0; border-radius:12px; font-size:1.1rem; text-align:center;"
                   autocomplete="off">
        </div>

        <!-- ITEMS TO PALLETIZE LIST -->
        <div style="margin-bottom:25px; max-height:200px; overflow-y:auto;">
            <h4 style="font-weight:600; color:#334155; margin-bottom:12px;">Items to Palletize:</h4>
            <div id="palletizeItemsList" style="display:flex; flex-direction:column; gap:8px;">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>

        <div style="display:flex; gap:12px;">
            <button onclick="closePalletizeModal()" 
                    style="flex:1; padding:12px; background:#f1f5f9; color:#475569; border:none; border-radius:10px; font-weight:600;">
                Cancel
            </button>
            <button onclick="submitPalletizeDirectly()" 
                    id="submitPalletizeBtn"
                    style="flex:1; padding:12px; background:linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%); color:white; border:none; border-radius:10px; font-weight:600;">
                Confirm & Submit
            </button>
        </div>
    </div>
</div>

<!-- ROTATION MODAL -->
<!-- ROTATION MODAL -->
<div id="rotationModal" class="rotation-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;z-index:10001;">
    <div class="rotation-modal-content" style="background:white;margin:15% auto;padding:30px;border-radius:24px;width:95%;max-width:500px;box-shadow:0 20px 60px rgba(0,0,0,0.3);position:relative;animation:slideUp 0.4s cubic-bezier(0.34,1.56,0.64,1);">
        <span class="close" onclick="closeRotationModal()" style="color:#9e9e9e;position:absolute;right:18px;top:18px;font-size:32px;cursor:pointer;width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:all 0.3s ease;background:#f0f7f4;">&times;</span>
        <h3 style="margin:0 0 20px 0;text-align:center;color:#e65100;font-size:1.8em;font-weight:700;position:relative;padding-bottom:12px;">🔄 Rotation - <?php echo htmlspecialchars($erpOrderNo); ?></h3>
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
            <button class="rotation-btn-cancel" onclick="closeRotationModal()" style="flex:1;padding:12px;background:white;color:black;border:none;border-radius:10px;font-weight:600;font-size:1em;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 12px rgba(161, 140, 209, 0.3);">
                Cancel
            </button>
            <button class="rotation-btn-confirm" onclick="submitRotation()" style="flex:1;padding:12px;background:linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);color:white;border:none;border-radius:10px;font-weight:600;font-size:1em;cursor:pointer;transition:all 0.3s ease;box-shadow:0 4px 12px rgba(161, 140, 209, 0.3);">
                Confirm
            </button>
        </div>
    </div>
</div>

<!-- ERROR MODAL -->
<div id="errorModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); z-index:10001; backdrop-filter:blur(6px);">
    <div style="background:white; margin:15% auto; padding:30px; border-radius:20px; max-width:450px; box-shadow:0 20px 50px rgba(0,0,0,0.4); animation:slideIn 0.3s ease;">
        <div style="text-align:center; margin-bottom:25px;">
            <h3 style="color:#dc2626; font-size:1.4rem; font-weight:800;">❌ Validation Error</h3>
            <p id="errorMessage" style="color:#64748b; margin-top:8px; min-height:40px;"></p>
        </div>
        
        <div style="text-align:center;">
            <button onclick="closeErrorModal()" 
                    style="padding:12px 30px; background:#dc2626; color:white; border:none; border-radius:10px; font-weight:600;">
                Keep Editing
            </button>
        </div>
    </div>
</div>

<style>
@keyframes slideIn {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(40px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<script>
// ========================================
// GLOBAL VARIABLES
// ========================================
let nextItemIndex = <?php echo count($details); ?>;
let areaSelect = null;
let currentRotationContainer = '';
let currentRotationId = '';

// ========================================
// SELECT ALL / CLEAR ALL FUNCTIONS
// ========================================
window.selectAllItems = function() {
    console.log('📋 Select All clicked');
    document.querySelectorAll('.palletize-checkbox').forEach(checkbox => {
        checkbox.checked = true;
        togglePalletizeItem(checkbox, checkbox.dataset.itemId);
    });
    console.log('✅ All items selected');
};

window.clearAllItems = function() {
    console.log('📋 Clear All clicked');
    document.querySelectorAll('.palletize-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        togglePalletizeItem(checkbox, checkbox.dataset.itemId);
    });
    console.log('✅ All items cleared');
};

// ========================================
// PALLETIZING MODAL FUNCTIONS
// ========================================
window.openPalletizeModal = function() {
    console.log('📦 Palletize button clicked!');
    console.log('Area select value:', areaSelect?.value);
    
    if (!areaSelect || !areaSelect.value) {
        console.warn('⚠️ No area selected');
        showError("Please select a destination area first!");
        return;
    }
    
    const checkedItems = document.querySelectorAll('.palletize-checkbox:checked');
    console.log('✓ Checked items count:', checkedItems.length);
    
    if (checkedItems.length === 0) {
        console.warn('⚠️ No items selected');
        showError("Please select at least one item for palletizing!");
        return;
    }
    
    const modal = document.getElementById('palletizeModal');
    if (modal) {
        console.log('✅ Opening palletize modal...');
        modal.style.display = 'flex';
        
        const input = document.getElementById('containerScanInput');
        if (input) {
            input.focus();
            input.value = '';
            input.onkeydown = function(e) {
                if (e.key === 'Enter') {
                    submitPalletizeDirectly();
                }
            };
        }
        
        updatePalletizeModal();
    }
};

window.closePalletizeModal = function() {
    const modal = document.getElementById('palletizeModal');
    if (modal) modal.style.display = 'none';
    const input = document.getElementById('containerScanInput');
    if (input) input.value = '';
};

// ========================================
// AREA SELECTION & FIELD TOGGLE
// ========================================
window.handleAreaChange = function() {
    console.log('🔄 handleAreaChange called');
    
    if (!areaSelect) {
        areaSelect = document.getElementById('areaSelect');
        console.log('Retrieved areaSelect:', areaSelect);
    }
    
    if (!areaSelect) {
        console.error('❌ areaSelect is NULL!');
        return;
    }
    
    const selectedArea = areaSelect.value;
    const isAGVArea = selectedArea === '03';
    
    console.log('📍 Area selected:', selectedArea);
    console.log('🔧 Is AGV Area?', isAGVArea);
    
    // Show/hide AGV sections for ALL item cards
    const agvSections = document.querySelectorAll('.agv-container-section');
    console.log('Found AGV sections:', agvSections.length);
    
    agvSections.forEach((section, index) => {
        const displayValue = isAGVArea ? 'block' : 'none';
        section.style.display = displayValue;
        console.log(`Section ${index}: set display to ${displayValue}`);
    });
    
    // Clear all AGV-related inputs if switching away from AGV area
    if (!isAGVArea) {
        document.querySelectorAll('.cascade-input, .grid-input, .slot-input, .total-input').forEach(input => {
            input.value = '';
        });
        console.log('✅ Cleared all AGV inputs');
    } else {
        console.log('✅ AGV fields should now be visible!');
    }
};

// ========================================
// UPDATE PALLETIZE MODAL LIST
// ========================================
function updatePalletizeModal() {
    const itemsList = document.getElementById('palletizeItemsList');
    if (!itemsList) return;
    
    itemsList.innerHTML = '';
    let hasSelected = false;
    
    document.querySelectorAll('.palletize-checkbox:checked').forEach(checkbox => {
        hasSelected = true;
        const itemCard = checkbox.closest('.item-card');
        const skuNo = itemCard.querySelector('div[style*="font-size:1.1rem"]').textContent.trim();
        const qtyInput = itemCard.querySelector('.qty-input');
        
        const itemDiv = document.createElement('div');
        itemDiv.style.padding = '8px';
        itemDiv.style.background = '#f1f5f9';
        itemDiv.style.borderRadius = '8px';
        itemDiv.style.display = 'flex';
        itemDiv.style.justifyContent = 'space-between';
        itemDiv.innerHTML = `<span>${skuNo}</span><span>Qty: ${qtyInput?.value || 0}</span>`;
        
        itemsList.appendChild(itemDiv);
    });
    
    if (!hasSelected) {
        itemsList.innerHTML = '<div style="text-align:center; color:#64748b; padding:12px;">No items selected for palletizing</div>';
    }
}

// ========================================
// PALLETIZE CHECKBOX TOGGLE
// ========================================
window.togglePalletizeItem = function(checkbox, itemId) {
    updatePalletizeModal();
};

// ========================================
// VALIDATE QUANTITY
// ========================================
window.validateItemQty = function(input) {
    const maxQty = parseFloat(input.max);
    const currentQty = parseFloat(input.value) || 0;
    if (currentQty > maxQty) input.value = maxQty;
    if (currentQty < 0) input.value = 0;
};

// ========================================
// SPLIT ITEM
// ========================================
window.splitItemCard = function(button) {
    const originalCard = button.closest('.item-card');
    const originalQtyInput = originalCard.querySelector('.qty-input');
    const originalQty = parseInt(originalQtyInput.value) || 0;
    
    if (originalQty <= 1) {
        showError("Quantity must be greater than 1 to split");
        return;
    }
    
    const newCard = originalCard.cloneNode(true);
    newCard.setAttribute('data-item-id', nextItemIndex);
    
    const newQtyInput = newCard.querySelector('.qty-input');
    newQtyInput.setAttribute('data-item-index', nextItemIndex);
    newQtyInput.setAttribute('data-item-id', nextItemIndex);
    newQtyInput.value = '1';
    newQtyInput.max = originalQty;
    
    originalQtyInput.value = originalQty - 1;
    originalQtyInput.max = originalQty - 1;
    
    // Clear AGV inputs in new card
    newCard.querySelectorAll('.cascade-input, .grid-input, .slot-input, .total-input').forEach(input => {
        input.value = '';
        input.dataset.itemId = nextItemIndex;
    });
    
    // Reset checkbox
    const newCheckbox = newCard.querySelector('.palletize-checkbox');
    if (newCheckbox) {
        newCheckbox.id = `palletize_${nextItemIndex}`;
        newCheckbox.dataset.itemId = nextItemIndex;
        newCheckbox.checked = false;
        const label = newCard.querySelector(`label[for^="palletize_"]`);
        if (label) label.setAttribute('for', `palletize_${nextItemIndex}`);
    }
    
    originalCard.parentNode.insertBefore(newCard, originalCard.nextSibling);
    
    // Reapply area selection to new card
    if (areaSelect && areaSelect.value === '03') {
        const agvSection = newCard.querySelector('.agv-container-section');
        if (agvSection) agvSection.style.display = 'block';
    }
    
    nextItemIndex++;
};

// ========================================
// ROTATION MODAL FUNCTIONS
// ========================================
window.openRotationModal = function(containerNo, itemId) {
    currentRotationContainer = containerNo;
    currentRotationId = itemId;
    
    const input = document.getElementById('locationPointInput');
    const infoDiv = document.getElementById('rotationInfo');
    
    if (input) input.value = '';
    if (infoDiv) infoDiv.textContent = `Rotating ERP: ${containerNo}`;
    
    const modal = document.getElementById('rotationModal');
    const overlay = document.getElementById('modal-overlay');
    if (modal) modal.style.display = 'flex';
    if (overlay) overlay.style.display = 'block';
    
    setTimeout(() => {
        if (input) input.focus();
    }, 300);
};

window.closeRotationModal = function() {
    const modal = document.getElementById('rotationModal');
    const overlay = document.getElementById('modal-overlay');
    if (modal) modal.style.display = 'none';
    if (overlay) overlay.style.display = 'none';
    
    currentRotationContainer = '';
    currentRotationId = '';
};

window.handleLocationKeyPress = function(event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        submitRotation();
    }
};

window.submitRotation = function() {
    const locationPoint = document.getElementById('locationPointInput')?.value?.trim();
    
    if (!locationPoint) {
        const infoDiv = document.getElementById('rotationInfo');
        if (infoDiv) {
            infoDiv.textContent = '⚠️ Please enter a location point!';
        }
        return;
    }
    
    const infoDiv = document.getElementById('rotationInfo');
    if (infoDiv) {
        infoDiv.textContent = '🔄 Processing rotation...';
    }
    
    const rotationData = { locationPoint: locationPoint };
    
    fetch('sfi_rotation_submit.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer <?php echo $token; ?>'
        },
        body: JSON.stringify(rotationData),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success === true) {
            if (infoDiv) {
                infoDiv.textContent = '✅ ' + (data.message || 'Rotation completed successfully!');
            }
            setTimeout(() => {
                closeRotationModal();
                alert('✅ Rotation successful: ' + locationPoint);
            }, 1500);
        } else {
            if (infoDiv) {
                infoDiv.textContent = '❌ ' + (data.message || 'Rotation failed');
            }
        }
    })
    .catch((error) => {
        console.error('Rotation error:', error);
        if (infoDiv) {
            infoDiv.textContent = '❌ Rotation failed: ' + error.message;
        }
    });
};

// ========================================
// SUBMIT PALLETIZING - DIRECTLY TO inbound_submit.php
// ========================================
window.submitPalletizeDirectly = function() {

    if (!validateSlotSelections()) {
    if (submitBtn) {
        submitBtn.innerHTML = 'Confirm & Submit';
        submitBtn.disabled = false;
    }
    return;
}
    const containerNo = document.getElementById('containerScanInput')?.value?.trim();
    const area = areaSelect?.value || '';
    const erpOrderNo = document.getElementById('erpOrderNo')?.value;
    
    console.log('🚀 Starting submission...');
    console.log('Container:', containerNo);
    console.log('Area:', area);
    console.log('ERP:', erpOrderNo);
    
    if (!erpOrderNo) {
        showError("ERP Order Number is missing!");
        return;
    }
    if (!area) {
        showError("Please select a destination area!");
        return;
    }
    if (!containerNo) {
        showError("Please scan a container barcode!");
        return;
    }
    
    const selectedItems = [];
    const isAGVArea = area === '03';
    
    document.querySelectorAll('.palletize-checkbox:checked').forEach(checkbox => {
        const itemCard = checkbox.closest('.item-card');
        const itemId = itemCard.querySelector('.hidden-item-id').value;
        const partNumber = itemCard.querySelector('.hidden-part-number').value;
        const qtyInput = itemCard.querySelector('.qty-input');
        
        const itemData = {
            item_id: itemId,
            part_number: partNumber,
            qty: qtyInput?.value || ''
        };
        
        // Add AGV fields if AGV area is selected
        if (isAGVArea) {
            const cascadeInput = itemCard.querySelector('.cascade-input');
            const gridInput = itemCard.querySelector('.grid-input');
            const slotInput = itemCard.querySelector('.slot-input');
            const totalInput = itemCard.querySelector('.total-input');
            
            itemData.cascade = cascadeInput?.value || '';
            itemData.grid = gridInput?.value || '';
            itemData.slot = slotInput?.value || '';
            itemData.total = totalInput?.value || '';
        }
        
        selectedItems.push(itemData);
    });
    
    if (selectedItems.length === 0) {
        showError("Please select at least one item for palletizing!");
        return;
    }
    
    console.log('📦 Items to submit:', selectedItems);
    
    const submitBtn = document.getElementById('submitPalletizeBtn');
    if (submitBtn) {
        submitBtn.innerHTML = '⏳ Processing...';
        submitBtn.disabled = true;
    }
    
    const fd = new FormData();
    fd.append('erp_order_no', erpOrderNo);
    fd.append('area', area);
    fd.append('container_no', containerNo);
    
    selectedItems.forEach((item, index) => {
        fd.append(`items[${index}][item_id]`, item.item_id);
        fd.append(`items[${index}][part_number]`, item.part_number);
        fd.append(`items[${index}][qty]`, item.qty);
        
        if (isAGVArea) {
            fd.append(`items[${index}][cascade]`, item.cascade);
            fd.append(`items[${index}][grid]`, item.grid);
            fd.append(`items[${index}][slot]`, item.slot);
            fd.append(`items[${index}][total]`, item.total);
        }
    });
    
    console.log('📤 Sending to inbound_submit.php...');
    
    fetch('inbound_submit.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
        console.log('📥 Response:', d);
        
        if (d.success) {
            alert('✅ ' + d.message);
            closePalletizeModal();
            location.reload(); // Reload to show updated data
        } else {
            showError(d.message);
            if (submitBtn) {
                submitBtn.innerHTML = 'Confirm & Submit';
                submitBtn.disabled = false;
            }
        }
    })
    .catch((error) => {
        console.error('❌ Fetch error:', error);
        showError('System error occurred. Please try again.');
        if (submitBtn) {
            submitBtn.innerHTML = 'Confirm & Submit';
            submitBtn.disabled = false;
        }
    });
};

// ========================================
// ERROR MODAL
// ========================================
window.closeErrorModal = function() {
    const errorModal = document.getElementById('errorModal');
    if (errorModal) errorModal.style.display = 'none';
};

function showError(message) {
    const errorMsg = document.getElementById('errorMessage');
    const errorModal = document.getElementById('errorModal');
    if (errorMsg) errorMsg.textContent = message;
    if (errorModal) errorModal.style.display = 'flex';
}

// ========================================
// DOMContentLoaded - INITIALIZATION
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ DOM Loaded - Initializing...');
    
    areaSelect = document.getElementById('areaSelect');
    
    if (areaSelect) {
        console.log('✅ areaSelect found:', areaSelect);
    } else {
        console.error('❌ areaSelect not found!');
    }
    
    // Test if AGV sections exist
    const agvSections = document.querySelectorAll('.agv-container-section');
    console.log('🔍 Found', agvSections.length, 'AGV container sections');
    
    // ESC key handlers
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closePalletizeModal();
            closeRotationModal();
            closeErrorModal();
        }
    });
    
    console.log('✅ Initialization complete!');
    console.log('📌 To test: Select "RM-AGV Warehouse" from dropdown');
});

// ========================================
// SLOT MEMORY & DYNAMIC GRID FUNCTIONS
// ========================================

// Store slot configurations per item
const slotConfigurations = new Map();

/**
 * Handle when user sets total slots
 */
window.handleTotalSlotsChange = function(input) {
    const itemCard = input.closest('.item-card');
    const itemId = input.dataset.itemId;
    const totalSlots = parseInt(input.value) || 0;
    
    console.log(`🎯 Setting total slots for item ${itemId}: ${totalSlots}`);
    
    if (totalSlots < 1 || totalSlots > 99) {
        console.warn('⚠️ Invalid slot count');
        return;
    }
    
    // Store configuration
    slotConfigurations.set(itemId, {
        totalSlots: totalSlots,
        selectedSlot: null
    });
    
    // Generate slot selection grid
    generateSlotGrid(itemCard, itemId, totalSlots);
    
    // Show slot selection area
    const slotDisplay = itemCard.querySelector('.slot-selection-display');
    if (slotDisplay) slotDisplay.style.display = 'block';
    
    // Update summary
    updateSlotSummary(itemCard, itemId);
};

/**
 * Generate clickable slot grid
 */
function generateSlotGrid(itemCard, itemId, totalSlots) {
    const slotGridContainer = itemCard.querySelector('.slot-grid');
    if (!slotGridContainer) return;
    
    slotGridContainer.innerHTML = '';
    
    for (let i = 1; i <= totalSlots; i++) {
        const slotBtn = document.createElement('button');
        slotBtn.type = 'button';
        slotBtn.textContent = i;
        slotBtn.className = 'slot-btn';
        slotBtn.dataset.slotNumber = i;
        slotBtn.style.cssText = `
            padding: 12px;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
        `;
        
        slotBtn.onmouseover = function() {
            if (!this.classList.contains('selected')) {
                this.style.background = '#e0f2fe';
                this.style.borderColor = '#0ea5e9';
            }
        };
        
        slotBtn.onmouseout = function() {
            if (!this.classList.contains('selected')) {
                this.style.background = 'white';
                this.style.borderColor = '#cbd5e1';
            }
        };
        
        slotBtn.onclick = function() {
            selectSlot(itemCard, itemId, i);
        };
        
        slotGridContainer.appendChild(slotBtn);
    }
}

/**
 * Select a specific slot
 */
function selectSlot(itemCard, itemId, slotNumber) {
    console.log(`✅ Selecting slot ${slotNumber} for item ${itemId}`);
    
    // Update stored configuration
    const config = slotConfigurations.get(itemId);
    if (config) {
        config.selectedSlot = slotNumber;
    }
    
    // Update hidden input
    const slotInput = itemCard.querySelector('.slot-input');
    if (slotInput) {
        slotInput.value = slotNumber;
    }
    
    // Update visual state of all slot buttons
    const slotButtons = itemCard.querySelectorAll('.slot-btn');
    slotButtons.forEach(btn => {
        const btnSlot = parseInt(btn.dataset.slotNumber);
        if (btnSlot === slotNumber) {
            btn.classList.add('selected');
            btn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            btn.style.color = 'white';
            btn.style.borderColor = '#059669';
            btn.style.transform = 'scale(1.05)';
        } else {
            btn.classList.remove('selected');
            btn.style.background = 'white';
            btn.style.color = '#334155';
            btn.style.borderColor = '#cbd5e1';
            btn.style.transform = 'scale(1)';
        }
    });
    
    // Update summary
    updateSlotSummary(itemCard, itemId);
}

/**
 * Update slot summary display
 */
function updateSlotSummary(itemCard, itemId) {
    const summaryDiv = itemCard.querySelector('.slot-summary');
    const summaryText = itemCard.querySelector('.summary-text');
    
    if (!summaryDiv || !summaryText) return;
    
    const config = slotConfigurations.get(itemId);
    
    if (!config || !config.totalSlots) {
        summaryDiv.style.display = 'none';
        return;
    }
    
    summaryDiv.style.display = 'block';
    
    if (config.selectedSlot) {
        summaryText.innerHTML = `📍 <strong>Slot ${config.selectedSlot}</strong> of <strong>${config.totalSlots}</strong> total slots`;
    } else {
        summaryText.innerHTML = `⚠️ Please select a slot (${config.totalSlots} available)`;
    }
}

/**
 * Handle grid input (optional integration with cascade container)
 */
window.handleGridInput = function(input) {
    const itemCard = input.closest('.item-card');
    const cascadeInput = itemCard.querySelector('.cascade-input');
    
    // You can add logic here to auto-populate based on cascade + grid combo
    // For example, load saved slot configurations from localStorage
    const cascadeValue = cascadeInput?.value || '';
    const gridValue = input.value || '';
    
    if (cascadeValue && gridValue) {
        const storageKey = `slots_${cascadeValue}_${gridValue}`;
        const savedSlots = localStorage.getItem(storageKey);
        
        if (savedSlots) {
            const totalInput = itemCard.querySelector('.total-input');
            if (totalInput && !totalInput.value) {
                totalInput.value = savedSlots;
                handleTotalSlotsChange(totalInput);
                console.log(`✅ Restored ${savedSlots} slots from memory for ${cascadeValue}-${gridValue}`);
            }
        }
    }
};

/**
 * Save slot configuration to localStorage when grid + cascade are set
 */
function saveSlotConfiguration(itemCard) {
    const cascadeInput = itemCard.querySelector('.cascade-input');
    const gridInput = itemCard.querySelector('.grid-input');
    const totalInput = itemCard.querySelector('.total-input');
    
    const cascade = cascadeInput?.value?.trim();
    const grid = gridInput?.value?.trim();
    const total = totalInput?.value;
    
    if (cascade && grid && total) {
        const storageKey = `inbound_slots_${cascade}_${grid}`;
        localStorage.setItem(storageKey, total);
        console.log(`💾 Saved inbound slot config: ${cascade}-${grid} = ${total} slots`);
    }
}

/**
 * 处理 Grid 输入时自动加载总槽数 (入库专用)
 */
window.handleGridInput = function(input) {
    const itemCard = input.closest('.item-card');
    const cascadeInput = itemCard.querySelector('.cascade-input');
    
    const cascadeValue = cascadeInput?.value || '';
    const gridValue = input.value || '';
    
    if (cascadeValue && gridValue) {
        const storageKey = `inbound_slots_${cascadeValue}_${gridValue}`;
        const savedTotalSlots = localStorage.getItem(storageKey);
        
        if (savedTotalSlots) {
            const totalInput = itemCard.querySelector('.total-input');
            if (totalInput && !totalInput.value) {
                totalInput.value = savedTotalSlots;
                handleTotalSlotsChange(totalInput);
                console.log(`✅ Restored ${savedTotalSlots} slots from memory for ${cascadeValue}-${gridValue}`);
            }
        }
    }
};

/**
 * Enhanced split function that preserves slot configuration
 */
window.splitItemCardWithSlots = function(button) {
    const originalCard = button.closest('.item-card');
    const originalQtyInput = originalCard.querySelector('.qty-input');
    const originalQty = parseInt(originalQtyInput.value) || 0;
    
    if (originalQty <= 1) {
        showError("Quantity must be greater than 1 to split");
        return;
    }
    
    const newCard = originalCard.cloneNode(true);
    newCard.setAttribute('data-item-id', nextItemIndex);
    
    const newQtyInput = newCard.querySelector('.qty-input');
    newQtyInput.setAttribute('data-item-index', nextItemIndex);
    newQtyInput.setAttribute('data-item-id', nextItemIndex);
    newQtyInput.value = '1';
    newQtyInput.max = originalQty;
    
    originalQtyInput.value = originalQty - 1;
    originalQtyInput.max = originalQty - 1;
    
    // Copy slot configuration to new card
    const originalItemId = originalCard.dataset.itemId;
    const originalConfig = slotConfigurations.get(originalItemId);
    
    if (originalConfig) {
        slotConfigurations.set(nextItemIndex.toString(), {
            totalSlots: originalConfig.totalSlots,
            selectedSlot: originalConfig.selectedSlot
        });
        
        // Regenerate slot grid in new card
        const totalInput = newCard.querySelector('.total-input');
        if (totalInput && originalConfig.totalSlots) {
            totalInput.value = originalConfig.totalSlots;
            setTimeout(() => {
                generateSlotGrid(newCard, nextItemIndex.toString(), originalConfig.totalSlots);
                
                // Re-select the same slot
                if (originalConfig.selectedSlot) {
                    selectSlot(newCard, nextItemIndex.toString(), originalConfig.selectedSlot);
                }
            }, 100);
        }
    }
    
    // Update all data attributes in new card
    newCard.querySelectorAll('.cascade-input, .grid-input, .slot-input, .total-input').forEach(input => {
        input.dataset.itemId = nextItemIndex;
    });
    
    // Reset checkbox
    const newCheckbox = newCard.querySelector('.palletize-checkbox');
    if (newCheckbox) {
        newCheckbox.id = `palletize_${nextItemIndex}`;
        newCheckbox.dataset.itemId = nextItemIndex;
        newCheckbox.checked = false;
        const label = newCard.querySelector(`label[for^="palletize_"]`);
        if (label) label.setAttribute('for', `palletize_${nextItemIndex}`);
    }
    
    originalCard.parentNode.insertBefore(newCard, originalCard.nextSibling);
    
    // Reapply area selection to new card
    if (areaSelect && areaSelect.value === '03') {
        const agvSection = newCard.querySelector('.agv-container-section');
        if (agvSection) agvSection.style.display = 'block';
    }
    
    nextItemIndex++;
    
    console.log(`✂️ Split item with slot config preserved: ${originalConfig?.totalSlots || 0} slots`);
};

/**
 * Enhanced submission validation
 */
window.validateSlotSelections = function() {
    const checkedItems = document.querySelectorAll('.palletize-checkbox:checked');
    const isAGVArea = areaSelect?.value === '03';
    
    if (!isAGVArea) return true; // 非AGV区域不需要验证
    
    let allValid = true;
    let errors = [];
    
    checkedItems.forEach(checkbox => {
        const itemCard = checkbox.closest('.item-card');
        const itemId = itemCard.dataset.itemId;
        const config = slotConfigurations.get(itemId);
        
        // 验证总槽数
        const totalInput = itemCard.querySelector('.total-input');
        const totalSlots = parseInt(totalInput?.value) || 0;
        
        if (!totalSlots || totalSlots < 1) {
            allValid = false;
            errors.push(`Item ${itemId}: Total slots is required (must be ≥ 1)`);
        }
        
        // 验证目标槽位
        const slotInput = itemCard.querySelector('.slot-input');
        const selectedSlot = parseInt(slotInput?.value) || 0;
        
        if (!selectedSlot || selectedSlot < 1) {
            allValid = false;
            errors.push(`Item ${itemId}: Please select a target slot`);
        }
        
        // 验证：目标槽位不能大于总槽数
        if (selectedSlot > totalSlots) {
            allValid = false;
            errors.push(`Item ${itemId}: Target slot (${selectedSlot}) exceeds total slots (${totalSlots})`);
        }
    });
    
    if (!allValid) {
        showError("Slot Configuration Error:\n" + errors.join("\n"));
        return false;
    }
    
    return true;
};

// ========================================
// UPDATE SUBMIT FUNCTION TO INCLUDE VALIDATION
// ========================================
// Modify your submitPalletizeDirectly function to call validateSlotSelections()
// Add this at the beginning of submitPalletizeDirectly:
/*
if (!validateSlotSelections()) {
    if (submitBtn) {
        submitBtn.innerHTML = 'Confirm & Submit';
        submitBtn.disabled = false;
    }
    return;
}
*/
</script>