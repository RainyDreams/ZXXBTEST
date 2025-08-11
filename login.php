<?php
session_start();

// 设置固定管理员凭证
$admin_username = 'admin';
$admin_password = 'securePassword123!'; // 实际应用中应使用密码哈希

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // 简单的输入过滤
    $username = htmlspecialchars(trim($username));
    $password = trim($password);
    
    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['loggedin'] = true;
        header('Location: feedbackresponse.php');
        exit;
    } else {
        $_SESSION['login_error'] = '用户名或密码不正确';
        header('Location: feedbackresponse.php');
        exit;
    }
} else {
    header('Location: feedbackresponse.php');
    exit;
}
?>