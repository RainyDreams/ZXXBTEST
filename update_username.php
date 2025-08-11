<?php
header("Content-Type: application/json");
$servername = "localhost:3306";
$username = "ser026744627716";
$password = "qQGzVGMWie0H";
$dbname = "ser026744627716";

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "数据库连接失败"]));
}

// 获取JSON数据
$data = json_decode(file_get_contents('php://input'), true);
$userCode = $data['userCode'] ?? '';
$rawPassword = $data['password'] ?? '';
$newUsername = $data['newUsername'] ?? '';

if (empty($userCode) || empty($rawPassword) || empty($newUsername)) {
    echo json_encode(["success" => false, "message" => "参数不完整"]);
    exit;
}

// 密码加密
$secret = 'MTgyMjU2MDU0MjF7c3pvbmV9';
$encryptedPassword = base64_encode($rawPassword . '{' . $secret . '}');

// 验证用户身份
$stmt = $conn->prepare("SELECT id FROM users WHERE account = ? AND password = ?");
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "数据库查询错误"]);
    exit;
}

$stmt->bind_param("ss", $userCode, $rawPassword);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "认证失败"]);
    exit;
}

$user = $result->fetch_assoc();
$userId = $user['id'];

// 更新用户名
$updateStmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
if (!$updateStmt) {
    echo json_encode(["success" => false, "message" => "数据库更新错误"]);
    exit;
}

$updateStmt->bind_param("si", $newUsername, $userId);
if ($updateStmt->execute()) {
    echo json_encode(["success" => true, "username" => $newUsername]);
} else {
    echo json_encode(["success" => false, "message" => "用户名更新失败"]);
}
?>