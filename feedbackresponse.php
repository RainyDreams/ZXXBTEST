<?php
session_start();
$is_logged_in = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>反馈管理系统</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>反馈管理系统</h1>
            <?php if ($is_logged_in): ?>
                <div class="user-info">
                    <span>管理员已登录</span>
                    <a href="logout.php" class="logout-btn">退出</a>
                </div>
            <?php endif; ?>
        </header>

        <?php if (!$is_logged_in): ?>
            <div class="login-form">
                <h2>管理员登录</h2>
                <form action="login.php" method="post">
                    <div class="form-group">
                        <label for="username">用户名:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">密码:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" class="login-btn">登录</button>
                </form>
            </div>
        <?php else: ?>
            <div class="feedback-section">
                <h2>反馈列表</h2>
                <div class="feedback-filters">
                    <select id="status-filter">
                        <option value="all">全部状态</option>
                        <option value="open">待处理</option>
                        <option value="closed">已解决</option>
                    </select>
                    <input type="text" id="search-box" placeholder="搜索反馈内容...">
                </div>
                
                <div id="feedback-list">
                    <!-- 反馈列表将通过JavaScript动态加载 -->
                    <div class="loading">加载反馈数据中...</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($is_logged_in): ?>
        <script>
            // 仅当用户登录后才加载功能脚本
            document.addEventListener('DOMContentLoaded', function() {
                // 从服务器获取反馈数据
                fetchFeedbackData();
                
                // 设置事件监听器
                document.getElementById('status-filter').addEventListener('change', filterFeedback);
                document.getElementById('search-box').addEventListener('input', filterFeedback);
                
                // 从服务器获取反馈数据
                function fetchFeedbackData() {
                    fetch('get_feedback.php')
                        .then(response => response.json())
                        .then(data => {
                            renderFeedbackList(data);
                        })
                        .catch(error => {
                            console.error('获取反馈数据失败:', error);
                            document.getElementById('feedback-list').innerHTML = 
                                '<div class="error">无法加载反馈数据，请稍后再试。</div>';
                        });
                }
                
                // 渲染反馈列表
                function renderFeedbackList(feedbacks) {
                    const container = document.getElementById('feedback-list');
                    if (!feedbacks || feedbacks.length === 0) {
                        container.innerHTML = '<div class="no-results">没有找到反馈记录</div>';
                        return;
                    }
                    
                    let html = '';
                    feedbacks.forEach(feedback => {
                        const statusClass = feedback.status === 'closed' ? 'status-closed' : 'status-open';
                        const statusText = feedback.status === 'closed' ? '已解决' : '待处理';
                        
                        html += `
                        <div class="feedback-item ${statusClass}" data-id="${feedback.id}">
                            <div class="feedback-header">
                                <span class="feedback-id">#${feedback.id}</span>
                                <span class="feedback-time">${feedback.time}</span>
                                <span class="feedback-status">${statusText}</span>
                            </div>
                            <div class="feedback-content">
                                <div class="feedback-user">
                                    <strong>${feedback.name}</strong> (${feedback.email})
                                </div>
                                <div class="feedback-subject">主题: ${feedback.subject}</div>
                                <div class="feedback-message">${feedback.content}</div>
                                <div class="feedback-subscribe">订阅通知: ${feedback.subscribe === '是' ? '是' : '否'}</div>
                            </div>
                            <div class="feedback-actions">
                                ${feedback.status !== 'closed' ? 
                                    `<button class="resolve-btn" onclick="resolveTicket('${feedback.id}')">标记为已解决</button>` : 
                                    `<button class="reopen-btn" onclick="reopenTicket('${feedback.id}')">重新打开</button>`
                                }
                                <button class="delete-btn" onclick="deleteTicket('${feedback.id}')">删除</button>
                            </div>
                        </div>
                        `;
                    });
                    
                    container.innerHTML = html;
                }
                
                // 过滤反馈
                function filterFeedback() {
                    const statusFilter = document.getElementById('status-filter').value;
                    const searchTerm = document.getElementById('search-box').value.toLowerCase();
                    
                    document.querySelectorAll('.feedback-item').forEach(item => {
                        const status = item.classList.contains('status-closed') ? 'closed' : 'open';
                        const content = item.querySelector('.feedback-message').textContent.toLowerCase();
                        const subject = item.querySelector('.feedback-subject').textContent.toLowerCase();
                        
                        const statusMatch = statusFilter === 'all' || status === statusFilter;
                        const searchMatch = content.includes(searchTerm) || subject.includes(searchTerm);
                        
                        item.style.display = (statusMatch && searchMatch) ? 'block' : 'none';
                    });
                }
                
                // 解决工单
                window.resolveTicket = function(id) {
                    updateTicketStatus(id, 'closed');
                }
                
                // 重新打开工单
                window.reopenTicket = function(id) {
                    updateTicketStatus(id, 'open');
                }
                
                // 删除工单
                window.deleteTicket = function(id) {
                    if (confirm('确定要删除这条反馈吗？此操作不可撤销。')) {
                        fetch(`process_ticket.php?action=delete&id=${id}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    document.querySelector(`.feedback-item[data-id="${id}"]`).remove();
                                } else {
                                    alert('删除失败: ' + data.message);
                                }
                            });
                    }
                }
                
                // 更新工单状态
                function updateTicketStatus(id, status) {
                    fetch(`process_ticket.php?action=update&id=${id}&status=${status}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const item = document.querySelector(`.feedback-item[data-id="${id}"]`);
                                item.classList.remove('status-open', 'status-closed');
                                item.classList.add(status === 'closed' ? 'status-closed' : 'status-open');
                                
                                const statusSpan = item.querySelector('.feedback-status');
                                statusSpan.textContent = status === 'closed' ? '已解决' : '待处理';
                                
                                // 更新操作按钮
                                const actionsDiv = item.querySelector('.feedback-actions');
                                actionsDiv.innerHTML = status === 'closed' ? 
                                    `<button class="reopen-btn" onclick="reopenTicket('${id}')">重新打开</button>
                                     <button class="delete-btn" onclick="deleteTicket('${id}')">删除</button>` :
                                    `<button class="resolve-btn" onclick="resolveTicket('${id}')">标记为已解决</button>
                                     <button class="delete-btn" onclick="deleteTicket('${id}')">删除</button>`;
                            } else {
                                alert('操作失败: ' + data.message);
                            }
                        });
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>