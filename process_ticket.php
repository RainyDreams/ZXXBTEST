<?php
session_start();

// 验证登录状态
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

// 获取操作参数
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$status = $_GET['status'] ?? '';

// 验证ID
if (!is_numeric($id) || $id <= 0) {
    echo json_encode(['success' => false, 'message' => '无效的ID']);
    exit;
}

// 处理不同的操作
if ($action === 'update' && in_array($status, ['open', 'closed'])) {
    updateTicketStatus($id, $status);
} elseif ($action === 'delete') {
    deleteTicket($id);
} else {
    echo json_encode(['success' => false, 'message' => '无效的操作']);
    exit;
}

// 更新工单状态
function updateTicketStatus($id, $status) {
    $filename = 'ticket_status.json';
    $statuses = [];
    
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        $statuses = json_decode($content, true) ?: [];
    }
    
    $statuses[$id] = $status;
    
    file_put_contents($filename, json_encode($statuses, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
}

// 删除工单（现在会同时删除原始记录文件中的条目）
function deleteTicket($id) {
    $filename = 'ticket_status.json';
    $statuses = [];
    
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        $statuses = json_decode($content, true) ?: [];
    }
    
    if (isset($statuses[$id])) {
        unset($statuses[$id]);
        file_put_contents($filename, json_encode($statuses, JSON_PRETTY_PRINT));
    }
    
    // 同时从原始反馈记录文件中删除该项
    deleteFeedbackRecord($id);
    
    echo json_encode(['success' => true]);
}

// 新增：从feedback_records.txt中删除指定记录
function deleteFeedbackRecord($id) {
    $feedbackFile = 'feedback_records.txt';
    
    if (!file_exists($feedbackFile)) {
        return;
    }
    
    $content = file_get_contents($feedbackFile);
    $records = explode('--- 反馈记录 ---', $content);
    
    // 保留第一个空元素
    $newRecords = [$records[0]];
    
    // 从索引1开始（索引0是空）
    for ($i = 1; $i < count($records); $i++) {
        // 跳过要删除的记录
        if (($i - 1) !== ($id - 1)) {
            $newRecords[] = $records[$i];
        }
    }
    
    // 重写文件
    file_put_contents($feedbackFile, implode('--- 反馈记录 ---', $newRecords));
}
?>
