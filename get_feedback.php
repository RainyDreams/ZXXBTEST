<?php
session_start();

// 验证登录状态
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    exit('拒绝访问');
}

// 解析反馈记录文件
function parseFeedbackRecords() {
    $records = [];
    $filename = 'feedback_records.txt';
    
    if (!file_exists($filename)) {
        return $records;
    }
    
    $content = file_get_contents($filename);
    $entries = explode('--- 反馈记录 ---', $content);
    
    // 跳过第一个空元素
    array_shift($entries);
    
    foreach ($entries as $index => $entry) {
        $lines = explode("\n", trim($entry));
        $record = ['id' => $index + 1];
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                
                // 映射键名
                switch ($key) {
                    case '时间': $record['time'] = $value; break;
                    case '姓名': $record['name'] = $value; break;
                    case '邮箱': $record['email'] = $value; break;
                    case '主题': $record['subject'] = $value; break;
                    case '内容': $record['content'] = $value; break;
                    case '订阅': $record['subscribe'] = $value; break;
                }
            }
        }
        
        $records[] = $record;
    }
    
    return $records;
}

// 获取工单状态
function getTicketStatuses() {
    $statuses = [];
    $filename = 'ticket_status.json';
    
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        $statuses = json_decode($content, true) ?: [];
    }
    
    return $statuses;
}

// 合并反馈数据和状态
$feedbacks = parseFeedbackRecords();
$statuses = getTicketStatuses();

foreach ($feedbacks as &$feedback) {
    $id = $feedback['id'];
    $feedback['status'] = isset($statuses[$id]) ? $statuses[$id] : 'open';
}

// 返回JSON数据
header('Content-Type: application/json');
echo json_encode($feedbacks);
?>
