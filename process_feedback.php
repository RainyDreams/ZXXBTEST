<?php
/**
 * 处理用户反馈提交的脚本
 * 安全措施：所有用户输入经过严格过滤，文件操作使用绝对路径和锁机制
 */

// 启动会话（在任何输出之前）
session_start();

/**
 * 安全过滤用户输入
 * @param string $data 原始输入数据
 * @return string 过滤后的数据
 */
function sanitize_input($data) {
    // 去除首尾空白字符
    $data = trim($data);
    // 转换HTML特殊字符
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    // 过滤控制字符和不可见字符
    $data = preg_replace('/[\x00-\x1F\x7F]/', '', $data);
    return $data;
}

// 安全检查：仅允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // 非POST请求重定向到反馈页面
    header('Location: feedback.html');
    exit;
}

// 处理POST请求
// 获取并严格过滤所有表单数据
$name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
$email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
$subject = isset($_POST['subject']) ? sanitize_input($_POST['subject']) : '';
$message = isset($_POST['message']) ? sanitize_input($_POST['message']) : '';
$subscribe = isset($_POST['subscribe']) ? 1 : 0;
$created_at = date('Y-m-d H:i:s');

// 初始化错误数组
$errors = [];

// 验证数据

    if (empty($name)) {
        $errors[] = '姓名不能为空';
    } elseif (strlen($name) > 100) {
        $errors[] = '姓名长度不能超过100个字符';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '请输入有效的电子邮箱';
    } elseif (strlen($email) > 255) {
        $errors[] = '邮箱长度不能超过255个字符';
    }

    if (empty($subject)) {
        $errors[] = '请选择反馈主题';
    } elseif (strlen($subject) > 100) {
        $errors[] = '主题长度不能超过100个字符';
    }

    if (empty($message)) {
        $errors[] = '反馈内容不能为空';
    } elseif (strlen($message) > 2000) {
        $errors[] = '反馈内容长度不能超过2000个字符';
    }

    // 如果没有错误，将数据安全地写入TXT文件
if (empty($errors)) {
    try {
        // 使用绝对路径确保文件位置正确且安全
        $file_path = rtrim(__DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'feedback_records.txt';

        // 确保目录可写
        $directory = dirname($file_path);
        if (!is_writable($directory)) {
            throw new Exception('存储目录不可写，请联系管理员');
        }

        // 格式化反馈数据为安全的文本格式
        $feedback_data = "--- 反馈记录 ---
" .
                        "时间: $created_at
" .
                        "姓名: $name
" .
                        "邮箱: $email
" .
                        "主题: $subject
" .
                        "内容: $message
" .
                        "订阅: " . ($subscribe ? '是' : '否') . "

";

        // 安全写入文件 (追加模式 + 排他锁)
        $write_result = file_put_contents($file_path, $feedback_data, FILE_APPEND | LOCK_EX);
        if ($write_result === false) {
            throw new Exception('无法写入反馈数据，请稍后再试');
        }

            // 重定向到成功页面
        header('Location: feedback_success.html');
        exit;
    } catch(Exception $e) {
        $errors[] = '提交失败: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }
}

    // 如果有错误，将错误信息存储到会话中并返回
    $_SESSION['errors'] = $errors;
    // 存储过滤后的表单数据
    $_SESSION['form_data'] = [
        'name' => $name,
        'email' => $email,
        'subject' => $subject,
        'message' => $message,
        'subscribe' => $subscribe
    ];
    header('Location: feedback.html');
    exit;
?>