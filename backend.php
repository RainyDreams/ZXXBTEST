<?php
// 数据库配置
$servername = "localhost:3306";
$username = "ser026744627716";
$dbpassword = "qQGzVGMWie0H";
$dbname = "ser026744627716";

// 创建数据库连接
$db = new mysqli($servername, $username, $dbpassword, $dbname);

// 检查连接
if ($db->connect_error) {
    die("数据库连接失败: " . $db->connect_error);
}

// 设置字符集
$db->set_charset("utf8");

// 创建用户表
$createTableSQL = "CREATE TABLE IF NOT EXISTS users (
    id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    account VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$db->query($createTableSQL)) {
    die("创建数据表失败: " . $db->error);
}

// 处理注册请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    // 获取并验证表单数据
    $username = $db->real_escape_string(trim($_POST['username'] ?? ''));
    $account = $db->real_escape_string(trim($_POST['account'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    
    $errors = [];

    // 验证字段是否为空
    if (empty($username)) $errors[] = '用户名不能为空';
    if (empty($account)) $errors[] = '账号不能为空';
    if (empty($password)) $errors[] = '密码不能为空';
    if (empty($confirmPassword)) $errors[] = '确认密码不能为空';

    // 验证用户名格式
    if (!preg_match('/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]{2,20}$/u', $username)) {
        $errors[] = '用户名格式错误 (2-20位中英文/数字/下划线)';
    }

    // 验证账号格式
    if (!preg_match('/^[a-zA-Z0-9_]{6,20}$/', $account)) {
        $errors[] = '账号格式错误 (6-20位英文/数字/下划线)';
    }

    // 验证密码格式
  //  if (strlen($password) < 8 || strlen($password) > 20) {
  //      $errors[] = '密码长度需在8-20位之间';
   // } else if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
   ///     $errors[] = '密码需包含大小写字母和数字';
   // }

    // 验证两次密码是否一致
    if ($password !== $confirmPassword) {
        $errors[] = '两次输入的密码不一致';
    }
    
    // 处理头像上传
    $avatarPath = null;
    $avatarFile = $_FILES['avatar'] ?? null;
    
    if ($avatarFile && $avatarFile['error'] === UPLOAD_ERR_OK) {
        // 验证文件大小 (限制2MB)
        if ($avatarFile['size'] > 2 * 1024 * 1024) {
            $errors[] = '头像文件大小不能超过2MB';
        }

        // 验证文件类型
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
        $fileType = $avatarFile['type'];
        
        if (!array_key_exists($fileType, $allowedTypes)) {
            $errors[] = '只支持JPG、PNG或GIF格式的头像';
        }
    } elseif ($avatarFile && $avatarFile['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors[] = '头像上传失败，错误代码：' . $avatarFile['error'];
    }

    // 如果有错误直接返回
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }

    // 检查账号是否已存在
    $checkAccount = $db->prepare("SELECT id FROM users WHERE account = ?");
    if (!$checkAccount) {
        echo json_encode(['success' => false, 'message' => '数据库查询错误: ' . $db->error]);
        exit;
    }
    
    $checkAccount->bind_param("s", $account);
    $checkAccount->execute();
    $result = $checkAccount->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => '该账号已被注册']);
        exit;
    }

    // 处理头像上传
    if ($avatarFile && $avatarFile['error'] === UPLOAD_ERR_OK) {
        // 创建上传目录
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                echo json_encode(['success' => false, 'message' => '无法创建上传目录']);
                exit;
            }
        }
        
        // 生成安全文件名
        $fileName = 'avatar_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$fileType];
        $uploadPath = $uploadDir . $fileName;
        
        // 移动文件并验证
        if (move_uploaded_file($avatarFile['tmp_name'], $uploadPath)) {
            $avatarPath = $uploadPath;
        } else {
            echo json_encode(['success' => false, 'message' => '头像上传失败']);
            exit;
        }
    }

    // 创建用户（使用预处理语句防止SQL注入）
    $hashedPassword = $password;
    $stmt = $db->prepare("INSERT INTO users (username, account, password, avatar_path) VALUES (?, ?, ?, ?)");
    
    // 修复点：检查prepare是否成功
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => '数据库错误: ' . $db->error]);
        exit;
    }
    
    $stmt->bind_param("ssss", $username, $account, $hashedPassword, $avatarPath);
    
    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        
        // 获取新用户信息
        $stmt = $db->prepare("SELECT id, username, account, avatar_path FROM users WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => '数据库错误: ' . $db->error]);
            exit;
        }
        
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'name' => $user['username'],
                'username' => $user['account'],
                'avatar' => $user['avatar_path'] ?: 'https://via.placeholder.com/150'
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '注册失败: ' . $db->error]);
    }
    exit;
}

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $account = $db->real_escape_string(trim($_POST['account'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    $errors = [];
    
    if (empty($account)) $errors[] = '账号不能为空';
    if (empty($password)) $errors[] = '密码不能为空';
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }
    
    $stmt = $db->prepare("SELECT * FROM users WHERE account = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => '数据库错误: ' . $db->error]);
        exit;
    }
    
    $stmt->bind_param("s", $account);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // 验证密码
        if ($password === $user['password']) {
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['username'],
                    'username' => $user['account'],
                    'avatar' => $user['avatar_path'] ?: 'https://via.placeholder.com/150'
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => '账号或密码错误']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '账号或密码错误']);
    }
    exit;
}

// 其他请求返回404
http_response_code(404);
echo "Not Found";
?>