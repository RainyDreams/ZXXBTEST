<?php
header("Content-Type: application/json");
$servername = "localhost:3306";
$username = "ser026744627716";
$password = "qQGzVGMWie0H";
$dbname = "ser026744627716";

// 创建数据库连接
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => 500, "message" => "数据库连接失败"]));
}

// 获取前端数据
$data = json_decode(file_get_contents('php://input'), true);
$userCode = $data['userCode'] ?? '';
$rawPassword = $data['password'] ?? '';

if (empty($userCode) || empty($rawPassword)) {
    echo json_encode(["status" => 400, "message" => "参数不完整"]);
    exit;
}

// 密码加密
$secret = 'MTgyMjU2MDU0MjF7c3pvbmV9';
$encryptedPassword = base64_encode($rawPassword . '{' . $secret . '}');

// 查询用户
$stmt = $conn->prepare("SELECT * FROM users WHERE account = ? AND password = ?");
if (!$stmt) {
    echo json_encode(["status" => 500, "message" => "数据库查询错误: " . $conn->error]);
    exit;
}

$stmt->bind_param("ss", $userCode, $rawPassword);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => 401, "message" => "认证失败"]);
    exit;
}

$user = $result->fetch_assoc();

// 检查是否需要第三方数据
$needThirdPartyData = empty($user['province']) || empty($user['school']) || 
                     empty($user['real_name']) || empty($user['student_guid']);

if ($needThirdPartyData) {
    // 获取第三方token - 增强调试功能
    $tokenResult = getThirdPartyToken($userCode, $rawPassword);
    if (!$tokenResult['success']) {
        echo json_encode([
            "status" => 503,
            "message" => "第三方服务不可用",
            "debug_info" => $tokenResult['debug']
        ]);
        exit;
    }
    $token = $tokenResult['token'];
    
    // 获取第三方用户数据 - 增强调试功能
    $userInfoResult = getThirdPartyUserInfo($token);
    if (!$userInfoResult['success']) {
        echo json_encode([
            "status" => 503,
            "message" => "获取用户信息失败",
            "debug_info" => $userInfoResult['debug']
        ]);
        exit;
    }
    $userInfo = $userInfoResult['data'];
    
    // 更新数据库
    $updateStmt = $conn->prepare("UPDATE users SET province=?, school=?, real_name=?, 
                                  student_guid=?, school_guid=? WHERE id=?");
    if (!$updateStmt) {
        echo json_encode(["status" => 500, "message" => "数据库更新错误: " . $conn->error]);
        exit;
    }
    
    $updateStmt->bind_param("sssssi", 
        $userInfo['province'],
        $userInfo['school'],
        $userInfo['real_name'],
        $userInfo['student_guid'],
        $userInfo['school_guid'],
        $user['id']
    );
    $updateStmt->execute();
    
    // 合并数据
    $user = array_merge($user, $userInfo);
}

// 处理手机号
$maskedPhone = substr($user['account'], 0, 3) . '******' . substr($user['account'], -2);

// 返回用户数据
echo json_encode([
    "status" => 200,
    "data" => [
        "avatar" => $user['avatar_path'] ?: 'https://via.placeholder.com/150',
        "username" => $user['username'],
        "phone" => $maskedPhone,
        "province" => $user['province'],
        "school" => $user['school'],
        "real_name" => $user['real_name'],
        "student_guid" => $user['student_guid'],
        "school_guid" => $user['school_guid']
    ]
]);

// 增强的获取第三方token函数 - 返回详细调试信息
function getThirdPartyToken($phone, $password) {
    $secret = 'MTgyMjU2MDU0MjF7c3pvbmV9';
    $encPassword = base64_encode($password . '{' . $secret . '}');
    
    $url = "https://szone-my.7net.cc/login";
    $postData = "password=" . urlencode($encPassword) . "&userCode=" . $phone;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'User-Agent: Mozilla/5.0 (Linux; Android 12; GOA-AL80 Build/HUAWEIGOA-AL80; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Mobile Safari/537.36'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 临时禁用SSL验证
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 临时禁用主机验证
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 设置超时时间
    curl_setopt($ch, CURLOPT_VERBOSE, true); // 启用详细输出
    
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose); // 捕获cURL详细日志
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);
    
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    fclose($verbose);
    
    // 构建完整调试信息
    $debugInfo = [
        'timestamp' => date('Y-m-d H:i:s'),
        'request_url' => $url,
        'request_payload' => $postData,
        'request_headers' => [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'User-Agent: Mozilla/5.0 (Linux; Android 12; GOA-AL80 Build/HUAWEIGOA-AL80; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/114.0.5735.196 Mobile Safari/537.36'
        ],
        'response_http_code' => $httpCode,
        'response_body' => $response,
        'curl_error' => $curlError,
        'verbose_log' => $verboseLog,
        'total_time' => $curlInfo['total_time'] ?? 0
    ];
    
    if ($response === false) {
        return [
            'success' => false,
            'token' => null,
            'debug' => $debugInfo
        ];
    }
    
    $data = json_decode($response, true);
    
    // 检查token是否存在
    $token = $data['data']['token'] ?? null;
    $success = ($httpCode == 200 && $token !== null);
    
    return [
        'success' => $success,
        'token' => $token,
        'debug' => $debugInfo
    ];
}

// 增强的获取第三方用户信息函数 - 返回详细调试信息
function getThirdPartyUserInfo($token) {
    $url = "https://szone-public.7net.cc/publicszoneui.service/UserService/info";
    $postData = "token=" . urlencode($token) . "&version=4.5.1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);
    
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    fclose($verbose);
    
    // 构建完整调试信息
    $debugInfo = [
        'timestamp' => date('Y-m-d H:i:s'),
        'request_url' => $url,
        'request_payload' => $postData,
        'request_headers' => [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'
        ],
        'response_http_code' => $httpCode,
        'response_body' => $response,
        'curl_error' => $curlError,
        'verbose_log' => $verboseLog,
        'total_time' => $curlInfo['total_time'] ?? 0
    ];
    
    if ($response === false) {
        return [
            'success' => false,
            'data' => null,
            'debug' => $debugInfo
        ];
    }
    
    $data = json_decode($response, true);
    
    // 检查数据是否有效
    $success = ($httpCode == 200 && !empty($data['data']));
    
    $userInfo = [
        "province" => $data['data']['proviceName'] ?? '',
        "school" => $data['data']['schoolName'] ?? '',
        "real_name" => $data['data']['studentName'] ?? '',
        "student_guid" => $data['data']['studentGuid'] ?? '',
        "school_guid" => $data['data']['schoolGuid'] ?? ''
    ];
    
    return [
        'success' => $success,
        'data' => $userInfo,
        'debug' => $debugInfo
    ];
}