<?php
session_start();
$token = $_SESSION['auth_token'] ?? null;
include('config.php');

if (!$token) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Empty Pallet Inbound</title>
    <!-- JQUERY REQUIRED FOR YOUR AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- MATERIAL ICONS -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-25..0" />
    <style>
        :root {
            --primary: #3b82f6;
            --bg-dark: #0f172a;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --danger: #ef4444;
            --success: #22c55e;
            --inbound: #8b5cf6;
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

        .form-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        select, input[type="text"] {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            font-size: 1rem;
            margin-bottom: 15px;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>
<body>

<!-- GLOBAL NAVBAR LIKE YOUR MENU.PHP -->
<div class="top-nav">
    <div style="display:flex; align-items:center; gap:8px;">
        <span class="material-symbols-outlined">inventory_2</span>
        <span style="font-weight:700; font-size: 0.9rem;">WAREHOUSE PRO MAX</span>
    </div>
</div>

<div class="container">
    <div style="display:flex; align-items:center; margin-bottom: 20px;">
        <a href="javascript:history.back()" style="color:var(--text-main); text-decoration:none;">
            <span class="material-symbols-outlined">arrow_back_ios</span>
        </a>
        <h2 style="flex:1; text-align:center;">Empty Pallet Inbound</h2>
    </div>

    <div class="form-card">
        <div style="text-align:center; margin-bottom:20px;">
            <div style="font-size:2rem; margin-bottom:10px;">📦</div>
            <h3 style="font-weight:700; color:#1e293b;">Empty Pallet Inbound</h3>
        </div>
        
        <div style="margin-bottom:20px;">
            <label style="display:block; font-weight:600; margin-bottom:8px; color:#1e293b;">Destination Area</label>
            <select id="areaSelect">
                <option value="">Select Area</option>
                <option value="RM Warehouse">RM Warehouse</option>
                <option value="Finished Goods Warehouse">Finished Goods Warehouse</option>
                <option value="RM-AGV Warehouse">RM-AGV Warehouse</option>
            </select>
        </div>
        
        <div style="margin-bottom:20px;">
            <label style="display:block; font-weight:600; margin-bottom:8px; color:#1e293b;">Container Number</label>
            <input type="text" 
                   id="containerInput"
                   placeholder="Scan or enter container number"
                   style="background:#f8fafc;">
        </div>
        
        <button onclick="processInbound()" class="btn">
            Process Inbound
        </button>
    </div>
</div>

<script>
function processInbound() {
    const area = document.getElementById("areaSelect").value;
    const container = document.getElementById("containerInput").value;
    
    if (!area) {
        alert("Please select a destination area");
        return;
    }
    if (!container) {
        alert("Please enter container number");
        return;
    }
    
    // Add your actual API call here
    fetch('empty_inbound_process.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            area: area,
            container: container
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Error processing request');
    });
}
</script>
</body>
</html>