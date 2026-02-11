<?php
include('config.php');
session_start();
$token = $_SESSION['auth_token'];
//echo $token."<br/>";
$InputPDA=$_REQUEST['InputPDA'];
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
        "batchNo" => "", // 这里替换成你扫码拿到的变量
        "erpNo" => "$InputPDA"
    ]
];

$url="http://192.188.11.100:5091/api/InOrder/GetInOrderDataList";

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
//echo $response."<br/><br/><br/>";
$result = json_decode($response, true);
?>

<?php
if (isset($result['data']) && is_array($result['data'])) {
foreach ($result['data'] as $index => $row) {
if (isset($row['inOrderDetailDtos']) && is_array($row['inOrderDetailDtos'])) {
foreach ($row['inOrderDetailDtos'] as $detail) {
$erpLineNo = $detail['erpLineNo'];
$skuCode = $detail['skuCode'];
$skuNo = $detail['skuNo'];
$skuName = $detail['skuName'];
$planNum = $detail['planNum'];
$groupedNum = $detail['groupedNum'];
$id = $detail['id'];

echo "Id: " . $id . "<br>";
echo "Line No: " . $erpLineNo . "<br>";
echo "skuCode: " . $skuCode . "<br>";
echo "skuNo: " . $skuNo . "<br>";
echo "skuName: " . $skuName . "<br>";
echo "planNum: " . $planNum . "<br>";
echo "groupedNum: " . $groupedNum . "<br><br>";



}
}
}
}
?>