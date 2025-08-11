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

// 获取表单数据
$userCode = $_POST['userCode'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($userCode) || empty($password)) {
    echo json_encode(["success" => false, "message" => "参数不完整"]);
    exit;
}

// 验证用户身份
$secret = 'MTgyMjU2MDU0MjF7c3pvbmV9';
$encryptedPassword = base64_encode($password . '{' . $secret . '}');

$stmt = $conn->prepare("SELECT id FROM users WHERE account = ? AND password = ?");
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "数据库查询错误"]);
    exit;
}

$stmt->bind_param("ss", $userCode, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "认证失败"]);
    exit;
}

$user = $result->fetch_assoc();
$userId = $user['id'];

// 处理头像上传
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['avatar'];
    
    // 验证文件类型
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(["success" => false, "message" => "只支持JPG、PNG或GIF格式"]);
        exit;
    }
    
    // 验证文件大小 (最大2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        echo json_encode(["success" => false, "message" => "文件大小不能超过2MB"]);
        exit;
    }
    
    // 创建上传目录
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // 生成唯一文件名
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'avatar_' . uniqid() . '.' . $extension;
    $uploadPath = $uploadDir . $fileName;
    
    // 移动文件
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // 更新数据库
        $updateStmt = $conn->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
        $updateStmt->bind_param("si", $uploadPath, $userId);
        
        if ($updateStmt->execute()) {
            echo json_encode(["success" => true, "avatar" => $uploadPath]);
        } else {
            echo json_encode(["success" => false, "message" => "数据库更新失败"]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "文件上传失败"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "未接收到头像文件"]);
}
?>