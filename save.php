<?php
session_start();
if(isset($_POST['captchaId'])){
$captchaId=$_POST['captchaId'];
$loginname=$_POST['loginname'];
$loginpassword=$_POST['loginpassword'];
$answer=$_POST['answer'];

$loginData = [
        "account" => $loginname,
        "password" => $loginpassword,
        "codeId" => $captchaId,
        "code" => $answer
    ];

$url="http://192.188.11.100:5091/api/SysAuth/Login";
$json_data = json_encode($loginData);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_data)
]);

$response = curl_exec($ch);

if (curl_errno($ch)){
echo 'cURL 错误: ' . curl_error($ch);
} else {
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$result = json_decode($response, true);

if (is_array($result)) {
if (isset($result['success']) && $result['success'] === true) {
$_SESSION['auth_token'] = $result['data']['accessToken'];
session_write_close(); // Ensure session is written

// Redirect to menu.php after successful login
echo '<!DOCTYPE html><html><head>';
echo '<meta http-equiv="refresh" content="0;url=menu.php">';
echo '</head><body><p>Redirecting to main page...</p></body></html>';
exit();
} else {
echo "<pre>Error: 交易失败或状态未知。</pre>";
}
} else {
echo "<pre>Fatal Error: API 返回的数据无法解析为 JSON。</pre>". htmlspecialchars($response);
}
}

curl_close($ch);
}
?>