<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Picking</title>
    <!-- JQUERY REQUIRED FOR YOUR AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- MATERIAL ICONS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-25..0" />
    <style>
        .pda-input {
            width:100%; padding:14px; font-size:14px;
            border-radius:1px; border:2px solid #fff;
            outline:none;
            background: 
                linear-gradient(#fff, #fff) padding-box,
                linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%) border-box;
            border: 2px solid transparent;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .pda-input:focus { 
            background-color: #fff; 
            box-shadow: 0 0 12px rgba(251, 194, 235, 0.5);
            transform: translateY(-1px);
        }
        .pda-input::placeholder {
            color: #bbb;
            font-size: 14px;
        }
        .input-wrapper { 
            max-width:450px; 
            margin:0 auto; 
            position:relative;
            padding:0px 10px; 
        }
        .action-btns {
            position:absolute; 
            right:18px; 
            top:50%; 
            transform:translateY(-50%);
            display:flex; 
            gap:6px;
        }
        .bton {
            width:34px; 
            height:34px; 
            border-radius:6px;
            background:#eee; 
            display:flex; 
            align-items:center; 
            justify-content:center;
            cursor:pointer; 
            font-size:18px;
        }
        .flash-success { 
            background:#d4edda !important; 
            transition:.2s; 
        }
        #fullscreen {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        #divA {
            flex-shrink: 0;
        }
        #divB {
            flex-grow: 1;
            overflow-y: auto;
            padding-bottom:100px;
        }
    </style>
</head>
<body>
<div id="fullscreen">
    <div id="divA">
        <div style="display:grid;grid-template-columns:30px 1fr 30px;grid-gap: 0px;align-items:center;padding:5px 10px;">
            <!-- FIXED BACK BUTTON -->
            <a href="javascript:history.back()" style="text-decoration:none;">
                <span class="material-symbols-outlined" style="font-weight:600;color:#000;font-size:24px;">arrow_back_ios</span>
            </a>
            <div style="margin:0 auto;margin-bottom:8px;font-weight:600;text-align:center;font-size:20px;">Order Picking</div>
            <div>&nbsp;</div>
        </div>

        <div class="input-wrapper">
            <input type="text" id="pdaInput" class="pda-input" inputmode="text" placeholder="Scan barcode..." autocomplete="off">
            <div class="action-btns">
                <div id="keyboardBtn" class="bton" title="Manual Keyboard">⌨</div>
                <div id="clearBtn" class="bton" title="Clear" style="">✕</div>
            </div>
        </div>
    </div>

    <div id="divB">
        <div id="scanned_result" style="margin:0 auto;padding:0;padding-bottom:10px;"></div>
    </div>
</div>

<script type="text/javascript">
const input = document.getElementById('pdaInput');
const keyboardBtn = document.getElementById('keyboardBtn');
const clearBtn = document.getElementById('clearBtn');

function lockScanFocus() {
    if (document.activeElement !== input) {
        input.focus({ preventScroll: true });
    }
}

function backToScanMode() {
    input.setAttribute('inputmode', 'none');
    lockScanFocus();
}

function activateKeyboard() {
    input.setAttribute('inputmode', 'text');
    setTimeout(() => { input.focus(); }, 50);
}

function toggleClearBtn() {
    clearBtn.style.display = input.value.length > 0 ? "flex" : "none";
}

/* Keyboard button */
keyboardBtn.addEventListener('click', e => {
    e.stopPropagation();
    activateKeyboard();
});

/* Clear button */
clearBtn.addEventListener('click', e => {
    e.stopPropagation();
    input.value = '';
    $("#scanned_result").html("");
    input.setAttribute('inputmode', 'text');
    input.focus();
    setTimeout(() => { input.setAttribute('inputmode', 'none'); }, 300);
});

/* Input monitoring */
input.addEventListener('input', toggleClearBtn);

input.addEventListener('touchstart', e => {
    if (input.getAttribute('inputmode') === 'none') {
        e.preventDefault();
        lockScanFocus();
        input.setAttribute('inputmode', 'text');
        input.focus();
        setTimeout(() => { input.setAttribute('inputmode', 'none'); }, 300);
    }
});

document.addEventListener('click', e => {
    if (!e.target.closest('.input-wrapper')) {
        lockScanFocus();
    }
});

/* Handle Enter key */
input.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
        e.preventDefault();
        const data = input.value.trim();
        if (!data) return;
        
        $("#scanned_result").html('<div style="text-align:center;padding:20px;">Loading...</div>');
        
        $.ajax({
            url: "sfi_scanned.php",
            method: "POST",
            data: { pdaInput: data },
            success: function(response) {
                $("#scanned_result").html(response);
                input.classList.add('flash-success');
                setTimeout(() => input.classList.remove('flash-success'), 300);
                input.value = '';
                toggleClearBtn();
                backToScanMode();
            },
            error: function(xhr, status, error) {
                $("#scanned_result").html('<div style="color:red;padding:20px;text-align:center;">❌ Error: ' + error + '</div>');
                input.value = '';
                toggleClearBtn();
                backToScanMode();
            }
        });
    }
});

// Initialize
input.focus();
input.setAttribute('inputmode', 'none');
</script>
</body>
</html>