<?php
session_start();
$token = $_SESSION['auth_token'] ?? null;
include('config.php');

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Warehouse Pro Max</title>
    <script src="<?php echo $jquery_path; ?>"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <style>
        :root {
            --primary: #3b82f6;
            --bg-dark: #0f172a;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --danger: #ef4444;
            --success: #22c55e;
            --inbound: #8b5cf6; /* Purple for Inbound */
            --empty-pallet: #64748b; /* Gray for Empty Pallet */
        }

        * {
            margin: 0; padding: 0; box-sizing: border-box;
            font-family: 'Inter', -apple-system, sans-serif;
        }

        body {
            background-color: #f8fafc;
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .top-nav {
            background: var(--bg-dark);
            color: white;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .container {
            max-width: 480px;
            width: 100%;
            margin: 0 auto;
            padding: 20px 16px;
            flex: 1;
        }

        .logo-section {
            text-align: center;
            margin: 20px 0 40px 0;
        }
        
        .logo-section h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--bg-dark);
        }

        /* Menu Grid */
        .menu-grid {
            display: grid;
            gap: 12px;
        }

        .menu-btn {
            display: flex;
            align-items: center;
            padding: 20px;
            background: var(--card-bg);
            text-decoration: none;
            color: var(--text-main);
            border-radius: 16px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .menu-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .menu-btn .icon-box {
            width: 45px;
            height: 45px;
            background: #eff6ff;
            color: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
        }

        .menu-btn.inbound .icon-box {
            background: #f3e8ff;
            color: var(--inbound);
        }

        .menu-btn.empty-pallet .icon-box {
            background: #f1f5f9;
            color: var(--empty-pallet);
        }

        /* PDA Scanning Input with your specific Gradient */
        .input-container {
            background: 
                linear-gradient(#fff, #fff) padding-box,
                linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%) border-box;
            border: 2px solid transparent;
            border-radius: 12px;
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease;
        }

        .input-container:focus-within {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(251, 194, 235, 0.3);
        }

        .pda-input {
            flex: 1;
            border: none;
            padding: 16px;
            font-size: 16px;
            outline: none;
            background: transparent;
        }

        .btn-icon {
            padding: 10px;
            color: var(--text-muted);
            cursor: pointer;
        }

        .flash-success {
            background: #dcfce7 !important;
            border-color: #22c55e !important;
        }

        .bg-logout { 
            background: #fee2e2 !important; 
            color: var(--danger) !important; 
        }
    </style>
</head>
<body>

    <?php if (!empty($token)): ?>
    <div class="top-nav">
        <div style="display:flex; align-items:center; gap:8px;">
            <span class="material-symbols-outlined">inventory_2</span>
            <span style="font-weight:700; font-size: 0.9rem;">WAREHOUSE PRO MAX</span>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
        <?php if (empty($token)): ?>
            <div id="loginContainer"></div>
        <?php else: ?>
            <?php if (!isset($_GET['mode']) || ($_GET['mode'] !== 'sfi' && $_GET['mode'] !== 'inbound' && $_GET['mode'] !== 'empty_inbound' && $_GET['mode'] !== 'empty_outbound')): ?>
                <div class="logo-section">
                    <h1>Main Menu</h1>
                </div>
                <div class="menu-grid">
                    <a href="?mode=sfi" class="menu-btn">
                        <div class="icon-box"><span class="material-symbols-outlined">barcode_scanner</span></div>
                        Order Picking (SFI) 拣选
                    </a>
                    <a href="?mode=inbound" class="menu-btn inbound">
                        <div class="icon-box"><span class="material-symbols-outlined">move_up</span></div>
                        Inbound Palletizing 入库
                    </a>
                    <a href="empty_inbound_scanned.php" class="menu-btn empty-pallet">
                        <div class="icon-box"><span class="material-symbols-outlined">inventory_2</span></div>
                        Empty Pallet Inbound
                    </a>
                    <a href="empty_outbound_scanned.php" class="menu-btn empty-pallet">
                        <div class="icon-box"><span class="material-symbols-outlined">inventory_2</span></div>
                        Empty Pallet Outbound
                    </a>
                    <a href="index.php" class="menu-btn">
                        <div class="icon-box bg-logout"><span class="material-symbols-outlined">logout</span></div>
                        <span style="color:var(--danger)">Logout</span>
                    </a>
                </div>
            <?php else: ?>
                <div style="display:flex; align-items:center; margin-bottom: 20px;">
                    <a href="menu.php" style="color:var(--text-main); text-decoration:none;"><span class="material-symbols-outlined">arrow_back_ios</span></a>
                    <h2 style="flex:1; text-align:center;">
                        <?php 
                        $mode = $_GET['mode'] ?? '';
                        switch($mode) {
                            case 'inbound':
                                echo 'Inbound Palletizing';
                                break;
                            case 'empty_inbound':
                                echo 'Empty Pallet Inbound';
                                break;
                            case 'empty_outbound':
                                echo 'Empty Pallet Outbound';
                                break;
                            default:
                                echo 'Scanning Mode';
                        }
                        ?>
                    </h2>
                </div>

                <div class="input-container" id="inputBox">
                    <input type="text" id="pdaInput" class="pda-input" placeholder="Scan barcode..." autocomplete="off">
                    <span id="keyboardBtn" class="material-symbols-outlined btn-icon">keyboard</span>
                    <span id="clearBtn" class="material-symbols-outlined btn-icon" style="display:none;">close</span>
                </div>

                <div id="scanned_result"></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        $(document).ready(function() {
            const $input = $('#pdaInput');
            const $clearBtn = $('#clearBtn');
            const mode = new URLSearchParams(window.location.search).get('mode') || 'sfi';

            $('#keyboardBtn').on('click', function() { 
                $input.attr('inputmode', 'text').focus(); 
            });
            
            $clearBtn.on('click', function() { 
                $input.val('').focus(); 
                $('#scanned_result').html(''); 
                $(this).hide(); 
            });
            
            $input.on('input', function() { 
                $clearBtn.toggle($(this).val().length > 0); 
            });

            $input.on('keydown', function(e) {
                if (e.key === 'Enter') {
                    const val = $(this).val().trim();
                    if (!val) return;
                    
                    $('#scanned_result').html('Loading...');
                    
                    // Route to different PHP based on mode
                    let targetFile;
                    switch(mode) {
                        case 'inbound':
                            targetFile = 'inbound_scanned.php';
                            break;
                        case 'empty_inbound':
                            targetFile = 'empty_inbound_scanned.php';
                            break;
                        case 'empty_outbound':
                            targetFile = 'empty_outbound_scanned.php';
                            break;
                        default:
                            targetFile = 'sfi_scanned.php';
                    }
                    
                    $.post(targetFile, { pdaInput: val }, function(res) {
                        $('#scanned_result').html(res);
                        $input.val('').attr('inputmode', 'none');
                        $clearBtn.hide();
                    }).fail(function() {
                        $('#scanned_result').html('<div style="color:var(--danger); padding:12px; background:#fee2e2; border-radius:8px;">Error: Failed to process scan</div>');
                    });
                }
            });
        });
    </script>
</body>
</html>