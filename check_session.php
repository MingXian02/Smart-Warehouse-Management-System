<?php
session_start();
$token = isset($_SESSION['auth_token']) ? $_SESSION['auth_token'] : null;
echo json_encode(['token_exists' => !empty($token), 'has_token' => !!$token]);
?>