<?php
session_start(); 

// 1. Logic to fetch Captcha from your API
$url = "http://192.188.11.100:5091/api/SysAuth/GetCaptcha";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, "");
curl_setopt($ch, CURLOPT_HTTPHEADER, ['accept: application/json', 'Content-Length: 0']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$captchaId = '';
$imgData = '';

if ($httpCode === 200) {
    $result = json_decode($response, true);
    $captchaId = $result['data']['id'] ?? '';
    $imgData = $result['data']['img'] ?? '';
    
    // Ensure the base64 string has the correct prefix
    if ($imgData && strpos($imgData, 'data:image') === false) {
        $imgData = 'data:image/png;base64,' . $imgData;
    }
} else {
    $errorMsg = "验证码加载失败 (API Error: $httpCode)";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Warehouse Pro Max Login</title>
    <script src="jquery-1.9.1.min.js"></script>
    <style>
        :root {
            --pda-gradient: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 20%, #fbc2eb 40%, #a18cd1 70%, #84fab0 100%);
            --glass: rgba(255, 255, 255, 0.9);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            background: #f1f5f9;
            background-image: 
                radial-gradient(at 0% 0%, rgba(255, 154, 158, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(132, 250, 176, 0.15) 0px, transparent 50%);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 20px;
        }

        .login-card {
            width: 100%; max-width: 420px;
            background: var(--glass);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
            border-radius: 32px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.06);
            padding: 48px 32px;
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .login-header { text-align: center; margin-bottom: 40px; }
        .brand-icon {
            width: 64px; height: 64px; margin: 0 auto 16px;
            background: var(--pda-gradient); border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; color: white;
            box-shadow: 0 8px 16px rgba(255, 154, 158, 0.3);
        }

        .form-group { position: relative; margin-bottom: 24px; text-align: left; }
        .form-group label {
            position: absolute; left: 16px; top: -10px;
            background: white; padding: 0 6px;
            font-size: 0.75rem; font-weight: 700; color: #94a3b8;
            z-index: 2;
        }

        .input-style {
            width: 100%; padding: 16px 20px; border-radius: 16px;
            border: 2px solid #f1f5f9; outline: none; background: #fff;
        }

        .input-style:focus {
            border: 2px solid transparent;
            background: linear-gradient(#fff, #fff) padding-box, var(--pda-gradient) border-box;
        }

        /* Captcha Fix */
        .captcha-row { display: flex; gap: 12px; }
        .captcha-container {
            flex: 0 0 130px; height: 56px; border-radius: 14px;
            overflow: hidden; border: 2px solid #f1f5f9; cursor: pointer;
        }
        .captcha-img { width: 100%; height: 100%; object-fit: cover; }

        .login-btn {
            width: 100%; padding: 18px; background: #0f172a; color: white;
            border: none; border-radius: 16px; font-size: 1.1rem; font-weight: 700;
            cursor: pointer; margin-top: 12px; transition: 0.2s;
        }
        .login-btn:active { transform: scale(0.98); }

        .spinner {
            width: 18px; height: 18px; border: 3px solid rgba(0,0,0,0.1);
            border-top-color: #3b82f6; border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="brand-icon">📦</div>
        <h2 style="font-weight:800;">系统登录</h2>
        <p style="color:#64748b;">Warehouse Management System</p>
    </div>

    <form id="LoginDiv" autocomplete="off">
        <input type="hidden" name="captchaId" id="captchaId" value="<?php echo $captchaId; ?>">

        <div class="form-group">
            <label>账号 Account</label>
            <input type="text" name="loginname" class="input-style" value="wms" required>
        </div>

        <div class="form-group">
            <label>密码 Password</label>
            <input type="password" name="loginpassword" class="input-style" value="123456" required>
        </div>

        <div class="form-group">
            <label>验证码 Captcha</label>
            <div class="captcha-row">
                <div class="captcha-container" onclick="refreshCaptcha()" title="点击刷新">
                    <img src="<?php echo $imgData; ?>" id="captchaImg" class="captcha-img">
                </div>
                <input type="text" name="answer" class="input-style" placeholder="验证码" maxlength="6" required>
            </div>
        </div>

        <button type="button" id="submitBtn" class="login-btn" onclick="onPostLogin()">
            立即登录 Secure Login
        </button>
    </form>

    <div id="loadingAAA" style="margin-top: 20px; display:flex; justify-content:center; align-items:center; gap:8px;"></div>
</div>

<script>
    // Improved refresh: Just reloads the page to get fresh PHP variables
    function refreshCaptcha() {
        $('.captcha-container').css('opacity', '0.5');
        location.reload(); 
    }

    function onPostLogin() {
        const btn = $('#submitBtn');
        const fd = new FormData($('#LoginDiv')[0]);

        btn.prop('disabled', true).text('AUTHENTICATING...');
        $('#loadingAAA').html('<div class="spinner"></div><span style="color:#64748b">正在验证身份...</span>');

        $.ajax({
            url: "save.php",
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.includes('success') || res.includes('成功')) {
                    $('#loadingAAA').html('<span style="color:#10b981">✨ 登录成功</span>');
                    setTimeout(() => { window.location.href = 'menu.php'; }, 800);
                } else {
                    $('#loadingAAA').html('<span style="color:#ef4444">❌ ' + res + '</span>');
                    btn.prop('disabled', false).text('立即登录 Secure Login');
                    $('.login-card').css('animation', 'shake 0.4s');ss
                    setTimeout(() => $('.login-card').css('animation', ''), 400);
                }
            },
            error: function() {
                $('#loadingAAA').html('<span style="color:#ef4444">连接服务器失败</span>');
                btn.prop('disabled', false).text('立即登录');
            }
        });
    }

    $(document).keypress(function(e) { if(e.which == 13) onPostLogin(); });
</script>
</body>
</html>