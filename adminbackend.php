<?php
// 会话配置必须在session_start()之前
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // 本地测试用0，生产环境HTTPS请改为1
// 数据库配置
$servername = "localhost:3306";
$username = "ser026744627716";
$dbpassword = "qQGzVGMWie0H";
$dbname = "ser026744627716";
session_start();

// 跨域和响应头设置
header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN'] ?? '*');
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=utf-8");

// 权限验证
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}
function processScores($content) {
    // 这里只是简单返回内容，实际应用中需要实现排名逻辑
    // 解析内容，计算班级排名和全校排名
    return $content;
}
// 空数据默认汇总
function getEmptySummary() {
    return [
        'totalStudents' => 0,
        'averageScore' => 0,
        'highestScore' => 0,
        'lowestScore' => 100,
        'passRate' => 0,
        'examName' => ''
    ];
}

// 解析考试数据文件（适配您提供的txt格式）
function parseExamData($content) {
    $lines = explode("\n", trim($content));
    if (empty($lines)) {
        return ['summary' => getEmptySummary(), 'classData' => [], 'studentScores' => []];
    }

    // 提取考试名称（第一行）
    $examName = trim($lines[0]);
    
    $totalStudents = 0;
    $totalScore = 0.0;
    $highestScore = 0.0;
    $lowestScore = 100.0;
    $passCount = 0;
    $classes = []; // 使用关联数组存储班级数据
    $currentClass = null;
    $lastClassMarker = null; // 跟踪最后遇到的班级标记
    $studentScores = [];

    // 从第二行开始解析
    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;

        // 关键修复1：增强班级标记解析 - 支持各种空格格式
        if (preg_match('/===\s*班级\s*(\d+)\s*===/', $line, $matches)) {
            $detectedClass = trim($matches[1]);
            
            // 关键修复2：确保只添加新班级（避免重复）
            if ($lastClassMarker !== $detectedClass) {
                $currentClass = $detectedClass;
                $lastClassMarker = $detectedClass;
                
                // 初始化新班级数据结构
                if (!isset($classes[$currentClass])) {
                    $classes[$currentClass] = [
                        'students' => [],
                        'highestScore' => 0.0,
                        'lowestScore' => 100.0,
                        'totalScore' => 0.0,
                        'passCount' => 0
                    ];
                }
            }
            continue;
        }

        // 关键修复3：更灵活的学生成绩解析
        if ($currentClass && preg_match('/^(\d+)\s*:\s*([\d.]+)/', $line, $matches)) {
            $studentId = $matches[1];
            $score = floatval($matches[2]);
            
            // 全校统计数据
            $totalStudents++;
            $totalScore += $score;
            $studentScores[] = $score;
            if ($score > $highestScore) $highestScore = $score;
            if ($score < $lowestScore) $lowestScore = $score;
            if ($score >= 60) $passCount++;
            
            // 班级统计数据
            $classes[$currentClass]['students'][] = $score;
            $classes[$currentClass]['totalScore'] += $score;
            if ($score > $classes[$currentClass]['highestScore']) {
                $classes[$currentClass]['highestScore'] = $score;
            }
            if ($score < $classes[$currentClass]['lowestScore']) {
                $classes[$currentClass]['lowestScore'] = $score;
            }
            if ($score >= 60) $classes[$currentClass]['passCount']++;
        }
    }

    // 处理班级数据 - 确保所有班级都被包含
    $classData = [];
    foreach ($classes as $classId => $class) {
        $studentCount = count($class['students']);
        
        $classData[] = [
            'className' => "班级 $classId",
            'averageScore' => $studentCount > 0 ? round($class['totalScore'] / $studentCount, 1) : 0,
            'highestScore' => $studentCount > 0 ? $class['highestScore'] : 0,
            'lowestScore' => $studentCount > 0 ? $class['lowestScore'] : 0,
            'passRate' => $studentCount > 0 ? round(($class['passCount'] / $studentCount) * 100, 1) : 0
        ];
    }

    // 按班级ID排序
    usort($classData, function($a, $b) {
        return intval(str_replace('班级 ', '', $a['className'])) - 
               intval(str_replace('班级 ', '', $b['className']));
    });

    // 计算全校汇总数据
    $summary = [
        'totalStudents' => $totalStudents,
        'averageScore' => $totalStudents > 0 ? round($totalScore / $totalStudents, 1) : 0,
        'highestScore' => $highestScore,
        'lowestScore' => $lowestScore,
        'passRate' => $totalStudents > 0 ? round(($passCount / $totalStudents) * 100, 1) : 0,
        'examName' => $examName
    ];

    return [
        'summary' => $summary,
        'classData' => $classData,
        'studentScores' => $studentScores
    ];
}

// 主逻辑处理
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'login':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // 实际应用中请替换为数据库验证
        $validUser = 'admin';
        $validPass = 'admin123';
        
        if ($username === $validUser && $password === $validPass) {
            $_SESSION['admin_logged_in'] = true;
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => '用户名或密码错误']);
        }
        break;

    case 'logout':
        $_SESSION['admin_logged_in'] = false;
        session_destroy();
        echo json_encode(['status' => 'success']);
        break;

    case 'getDashboardData':
        if (!isLoggedIn()) {
            echo json_encode(['status' => 'error', 'message' => '未授权访问']);
            break;
        }

        // 从考试文件中获取仪表板数据
        $examFiles = glob('exams/*.txt');
        $totalExams = count($examFiles);
        $totalStudents = 0;
        $totalAvgScore = 0;
        $totalPassRate = 0;
        $examCount = 0;

        $subjectDistribution = [];
        $subjectScores = [];

        foreach ($examFiles as $file) {
            $content = file_get_contents($file);
            if ($content) {
                $examData = parseExamData($content);
                $summary = $examData['summary'];
                
                if ($summary['totalStudents'] > 0) {
                    $totalStudents += $summary['totalStudents'];
                    $totalAvgScore += $summary['averageScore'];
                    $totalPassRate += $summary['passRate'];
                    $examCount++;
                }

                // 提取科目信息（从考试名称中）
                if (preg_match('/(语文|数学|英语|物理|化学|生物|历史|地理|政治)/', $summary['examName'], $matches)) {
                    $subject = $matches[1];
                    $subjectDistribution[$subject] = ($subjectDistribution[$subject] ?? 0) + 1;
                    $subjectScores[$subject][] = $summary['averageScore'];
                }
            }
        }

        // 计算平均数据
        $avgScore = $examCount > 0 ? round($totalAvgScore / $examCount, 1) : 0;
        $avgPassRate = $examCount > 0 ? round($totalPassRate / $examCount, 1) : 0;

        // 处理科目数据
        $subjectLabels = array_keys($subjectDistribution);
        $subjectValues = array_values($subjectDistribution);
        
        $avgSubjectScores = [];
        foreach ($subjectScores as $subject => $scores) {
            $avgSubjectScores[$subject] = round(array_sum($scores) / count($scores), 1);
        }

        echo json_encode([
            'status' => 'success',
            'data' => [
                'totalExams' => $totalExams,
                'totalStudents' => $totalStudents,
                'averageScore' => $avgScore,
                'averagePassRate' => $avgPassRate,
                'examsTrend' => ['change' => 0, 'comparison' => '上月'], // 实际应用中可从历史数据计算
                'studentsTrend' => ['change' => 0, 'comparison' => '上月'],
                'passRateTrend' => ['change' => 0, 'comparison' => '上月'],
                'scoreTrend' => ['change' => 0, 'comparison' => '上月'],
                'subjectDistribution' => [
                    'labels' => $subjectLabels,
                    'values' => $subjectValues
                ],
                'subjectScores' => [
                    'labels' => array_keys($avgSubjectScores),
                    'values' => array_values($avgSubjectScores)
                ]
            ]
        ]);
        break;

    case 'getExamList':
        if (!isLoggedIn()) {
            echo json_encode(['status' => 'error', 'message' => '未授权访问']);
            break;
        }

        $examListUrl = 'http://1.92.76.138:6687/examlist.txt';
        $examListContent = file_get_contents($examListUrl);
        if ($examListContent === false) {
            echo json_encode(['status' => 'error', 'message' => '无法获取考试列表']);
            break;
        }
    
    // 解析考试列表
        $lines = explode("\n", $examListContent);
        $exams = [];
    
        foreach ($lines as $line) {
              $line = trim($line);
             if (empty($line)) continue;
        
              $parts = explode(',', $line, 2);
            if (count($parts) === 2) {
                $exams[] = [
                    'name' => $parts[0],
                    'guid' => $parts[1]
                 ];
            }
        }   
    
        echo json_encode(['status' => 'success', 'data' => $exams]);
        break;
    case 'getExamData':
        if (!isLoggedIn()) {
            echo json_encode(['status' => 'error', 'message' => '未授权访问']);
            break;
        }

        $guid = $_GET['guid'] ?? '';
        $file = "exams/{$guid}.txt";
        
        if (!file_exists($file)) {
            echo json_encode([
                'status' => 'error',
                'message' => '考试数据不存在'
            ]);
            break;
        }

        $content = file_get_contents($file);
        $examData = parseExamData($content);
        
        echo json_encode([
            'status' => 'success',
            'data' => $examData
        ]);
        break;

    case 'fetchScores':
        if (!isLoggedIn()) {
            echo json_encode(['status' => 'error', 'message' => '未授权访问']);
            break;
        }

        if (!file_exists('exams')) {
        mkdir('exams', 0755, true);
    }
    
    // 发送GET请求
    $url1 = 'http://1.92.76.138:9842';
    file_get_contents($url1); // 无视响应
    
    // 获取考试列表
    $examListUrl = 'http://1.92.76.138:6687/examlist.txt';
    $examListContent = file_get_contents($examListUrl);
    
    if ($examListContent === false) {
        echo json_encode(['status' => 'error', 'message' => '无法获取考试列表']);
        exit;
    }
    
    // 解析考试列表
    $lines = explode("\n", $examListContent);
    $exams = [];
    $missingExams = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $parts = explode(',', $line, 2);
        if (count($parts) === 2) {
            $examName = $parts[0];
            $examGuid = $parts[1];
            $exams[] = ['name' => $examName, 'guid' => $examGuid];
            
            // 检查文件是否存在
            $filename = 'exams/' . $examGuid . '.txt';
            if (!file_exists($filename)) {
                $missingExams[] = $examGuid;
            }
        }
    }
    
    // 获取缺失的考试成绩
    $fetchedCount = 0;
    foreach ($missingExams as $guid) {
        $scoreUrl = 'http://1.92.76.138:5555/exams/' . $guid . '.txt';
        $scoreContent = file_get_contents($scoreUrl);
        
        if ($scoreContent !== false) {
            // 处理成绩并添加排名
            $processedContent = processScores($scoreContent);
            
            // 保存文件
            file_put_contents('exams/' . $guid . '.txt', $processedContent);
            $fetchedCount++;
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => "成功获取 $fetchedCount 个缺失的考试成绩",
        'totalMissing' => count($missingExams)
    ]);
        break;

    case 'getUsers':
        if (!isLoggedIn()) {
            echo json_encode(['status' => 'error', 'message' => '未授权访问']);
            break;
        }

        // 创建连接
    $conn = new mysqli($servername, $username, $dbpassword, $dbname);
    
    // 检查连接
    if ($conn->connect_error) {
        echo json_encode(['status' => 'error', 'message' => '数据库连接失败: ' . $conn->connect_error]);
        exit;
    }
    
    $sql = "SELECT * FROM users";
    $result = $conn->query($sql);
    
    $users = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    
    $conn->close();
    
        // 分页处理
        $page = intval($_GET['page'] ?? 1);
        $perPage = intval($_GET['perPage'] ?? 10);
        $total = count($users);
        $offset = ($page - 1) * $perPage;
        $paginatedUsers = array_slice($users, $offset, $perPage);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'users' => $paginatedUsers,
                'total' => $total
            ]
        ]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => '无效的请求']);
        break;
}