<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('manage_academy');

// Получаем статистику
$stats_query = "
    SELECT 
        COUNT(*) as total_attempts,
        AVG(score) as avg_score,
        SUM(is_passed) as passed_count,
        COUNT(DISTINCT user_id) as unique_users
    FROM academy_test_results
";
$stats = $pdo->query($stats_query)->fetch();

// Получаем последние результаты тестов всех сотрудников
$query = "
    SELECT 
        tr.*, 
        u.first_name, u.last_name,
        t.title as test_title,
        t.is_exam as test_type
    FROM academy_test_results tr
    JOIN users u ON tr.user_id = u.id
    JOIN academy_tests t ON tr.test_id = t.id
    ORDER BY tr.created_at DESC
    LIMIT 50
";
$results = $pdo->query($query)->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary: #785aff;
            --primary-light: #9277ff;
            --success: #00ff88;
            --success-light: #7dffc3;
            --danger: #ff6b6b;
            --danger-light: #ff8c8c;
            --warning: #ffc107;
            --warning-light: #ffd666;
            --info: #00d4ff;
            --dark-bg: #0f0f15;
            --card-bg: #1a1a24;
            --card-border: #2a2a3a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0c0;
            --text-muted: #6c6c7e;
            --hover-bg: #2a2a34;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --gradient-primary: linear-gradient(135deg, #785aff 0%, #b866ff 100%);
            --gradient-success: linear-gradient(135deg, #00ff88 0%, #00d4ff 100%);
            --gradient-danger: linear-gradient(135deg, #ff6b6b 0%, #ff8c00 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0a0a12 0%, #151520 100%);
            color: var(--text-primary);
            min-height: 100vh;
        }

        .page-wrapper {
            padding: 20px;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, rgba(120, 90, 255, 0.1) 0%, rgba(0, 255, 136, 0.05) 100%);
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 30px;
            border: 1px solid var(--card-border);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, var(--primary) 0%, transparent 70%);
            opacity: 0.1;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .header-title {
            font-size: 40px;
            font-weight: 800;
            background: linear-gradient(90deg, #785aff, #00ff88);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .header-subtitle {
            color: var(--text-secondary);
            font-size: 16px;
            max-width: 600px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 25px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(120, 90, 255, 0.05), transparent);
            transform: translateX(-100%);
            transition: 0.6s;
        }

        .stat-card:hover::before {
            transform: translateX(100%);
        }

        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(120, 90, 255, 0.2);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, rgba(120, 90, 255, 0.1) 0%, rgba(184, 102, 255, 0.1) 100%);
            border: 2px solid rgba(120, 90, 255, 0.2);
        }

        .stat-value {
            font-size: 40px;
            font-weight: 800;
            margin-bottom: 8px;
            line-height: 1;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
        }

        .table-container {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .table-title {
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            background: rgba(15, 15, 21, 0.8);
            border: 2px solid var(--card-border);
            border-radius: 12px;
            padding: 12px 20px;
            color: var(--text-primary);
            font-size: 14px;
            min-width: 200px;
            transition: var(--transition);
        }

        .search-box:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(120, 90, 255, 0.2);
        }

        .filter-btn {
            background: var(--hover-bg);
            border: 2px solid var(--card-border);
            border-radius: 12px;
            padding: 12px 20px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            background: var(--card-border);
        }

        .results-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .results-table th {
            text-align: left;
            padding: 20px;
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--card-border);
            font-weight: 600;
            background: rgba(255, 255, 255, 0.02);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .results-table td {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            font-size: 14px;
            transition: var(--transition);
        }

        .results-table tr:hover td {
            background: rgba(120, 90, 255, 0.05);
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            color: white;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 700;
            font-size: 15px;
        }

        .user-department {
            font-size: 12px;
            color: var(--text-muted);
        }

        .test-badge {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-exam {
            background: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.2);
        }

        .badge-test {
            background: rgba(120, 90, 255, 0.1);
            color: #785aff;
            border: 1px solid rgba(120, 90, 255, 0.2);
        }

        .score-badge {
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .score-excellent {
            background: rgba(0, 255, 136, 0.1);
            color: #00ff88;
            border: 1px solid rgba(0, 255, 136, 0.2);
        }

        .score-good {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }

        .score-poor {
            background: rgba(255, 107, 107, 0.1);
            color: #ff6b6b;
            border: 1px solid rgba(255, 107, 107, 0.2);
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-passed {
            background: rgba(0, 255, 136, 0.15);
            color: #00ff88;
        }

        .status-failed {
            background: rgba(255, 107, 107, 0.15);
            color: #ff6b6b;
        }

        .time-cell {
            text-align: right;
        }

        .time-date {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .time-time {
            font-size: 12px;
            color: var(--text-muted);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
            display: inline-block;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .empty-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-primary);
            opacity: 0.8;
        }

        .empty-subtitle {
            color: var(--text-secondary);
            font-size: 16px;
            max-width: 400px;
            margin: 0 auto 30px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .pagination-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--hover-bg);
            border: 1px solid var(--card-border);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .pagination-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .pagination-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .export-btn {
            background: linear-gradient(135deg, var(--success) 0%, var(--info) 100%);
            color: #000;
            font-weight: 700;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 5px 20px rgba(0, 255, 136, 0.2);
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 255, 136, 0.3);
        }

        @media (max-width: 1024px) {
            .page-wrapper {
                padding: 15px;
            }
            
            .header {
                padding: 30px;
            }
            
            .header-title {
                font-size: 32px;
            }
            
            .table-container {
                padding: 20px;
            }
            
            .results-table {
                display: block;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .results-table th,
            .results-table td {
                padding: 15px;
                white-space: nowrap;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header {
                padding: 24px;
                border-radius: 20px;
            }
            
            .header-title {
                font-size: 28px;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table-controls {
                width: 100%;
                justify-content: space-between;
            }
            
            .search-box {
                flex: 1;
                min-width: 0;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .stat-value {
                font-size: 32px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-wrapper {
                padding: 10px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header-title {
                font-size: 24px;
            }
            
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
            
            .results-table th,
            .results-table td {
                padding: 12px 8px;
                font-size: 13px;
            }
            
            .user-cell {
                min-width: 150px;
            }
        }

        /* Стили для скроллбара */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-light);
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="page-container">
            <!-- Заголовок -->
            <div class="header">
                <div class="header-content">
                    <h1 class="header-title">📊 Успеваемость сотрудников</h1>
                    <p class="header-subtitle">Мониторинг прохождения тестов, результаты аттестации и статистика обучения</p>
                </div>
            </div>

            <!-- Статистика -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-value"><?= $stats['total_attempts'] ?? 0 ?></div>
                    <div class="stat-label">Всего попыток</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-value"><?= isset($stats['avg_score']) ? round($stats['avg_score'], 1) : '0.0' ?>%</div>
                    <div class="stat-label">Средний балл</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?= $stats['passed_count'] ?? 0 ?></div>
                    <div class="stat-label">Успешных сдач</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-value"><?= $stats['unique_users'] ?? 0 ?></div>
                    <div class="stat-label">Активных сотрудников</div>
                </div>
            </div>

            <!-- Таблица результатов -->
            <div class="table-container">
                <div class="table-header">
                    <h2 class="table-title">📋 История прохождения тестов</h2>
                    <div class="table-controls">
                        <input type="text" 
                               id="searchInput" 
                               class="search-box" 
                               placeholder="Поиск по сотрудникам или тестам..." 
                               onkeyup="searchTable()">
                        <button class="filter-btn" onclick="toggleFilters()">
                            <span>⚙️</span> Фильтры
                        </button>
                        <button class="export-btn" onclick="exportData()">
                            <span>📥</span> Экспорт
                        </button>
                    </div>
                </div>

                <?php if (empty($results)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">🏜️</div>
                        <h3 class="empty-title">Данные отсутствуют</h3>
                        <p class="empty-subtitle">
                            Пока никто из сотрудников не проходил тесты. Результаты появятся здесь после начала обучения.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="scroll-container">
                        <table class="results-table" id="resultsTable">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Сотрудник</th>
                                    <th style="width: 25%;">Название теста</th>
                                    <th style="width: 15%; text-align: center;">Результат</th>
                                    <th style="width: 15%; text-align: center;">Статус</th>
                                    <th style="width: 25%; text-align: right;">Дата и время</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $r): 
                                    $user_initials = mb_substr($r['first_name'], 0, 1, 'UTF-8') . mb_substr($r['last_name'], 0, 1, 'UTF-8');
                                    $score = $r['score'];
                                    $score_class = $score >= 90 ? 'score-excellent' : ($score >= 70 ? 'score-good' : 'score-poor');
                                    $status_class = $r['is_passed'] ? 'status-passed' : 'status-failed';
                                    $test_type_class = $r['test_type'] ? 'badge-exam' : 'badge-test';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <div class="user-avatar">
                                                    <?= htmlspecialchars($user_initials) ?>
                                                </div>
                                                <div class="user-info">
                                                    <div class="user-name">
                                                        <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?>
                                                    </div>
                                                    <div class="user-department">
                                                        ID: <?= $r['user_id'] ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="margin-bottom: 6px; font-weight: 600;">
                                                <?= htmlspecialchars($r['test_title']) ?>
                                            </div>
                                            <span class="test-badge <?= $test_type_class ?>">
                                                <?= $r['test_type'] ? '🏆 Экзамен' : '📝 Тест' ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <div class="score-badge <?= $score_class ?>">
                                                <span><?= $score ?>%</span>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <div class="status-badge <?= $status_class ?>">
                                                <span><?= $r['is_passed'] ? '✅ ПРОЙДЕНО' : '❌ ПРОВАЛЕНО' ?></span>
                                            </div>
                                        </td>
                                        <td class="time-cell">
                                            <div class="time-date">
                                                <?= date('d.m.Y', strtotime($r['created_at'])) ?>
                                            </div>
                                            <div class="time-time">
                                                <?= date('H:i', strtotime($r['created_at'])) ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Пагинация -->
                    <div class="pagination">
                        <button class="pagination-btn">←</button>
                        <button class="pagination-btn active">1</button>
                        <button class="pagination-btn">2</button>
                        <button class="pagination-btn">3</button>
                        <button class="pagination-btn">→</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Функция поиска в таблице
        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('resultsTable');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                const td1 = tr[i].getElementsByTagName('td')[0];
                const td2 = tr[i].getElementsByTagName('td')[1];
                let found = false;
                
                if (td1 || td2) {
                    const text1 = td1.textContent || td1.innerText;
                    const text2 = td2.textContent || td2.innerText;
                    
                    if (text1.toUpperCase().indexOf(filter) > -1 || 
                        text2.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                    }
                }
                
                tr[i].style.display = found ? '' : 'none';
            }
        }
        
        // Функция переключения фильтров
        function toggleFilters() {
            alert('Функция фильтрации находится в разработке. Скоро будет доступна!');
        }
        
        // Функция экспорта данных
        function exportData() {
            alert('Экспорт данных в CSV. Функция в разработке.');
        }
        
        // Сортировка таблицы
        document.querySelectorAll('.results-table th').forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                alert(`Сортировка по столбцу ${index + 1}. Функция в разработке.`);
            });
        });
        
        // Анимация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            const tableRows = document.querySelectorAll('.results-table tbody tr');
            
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);
            });
            
            tableRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                
                setTimeout(() => {
                    row.style.transition = 'all 0.5s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, 600 + (index * 50));
            });
        });
        
        // Подсветка строк при наведении
        document.querySelectorAll('.results-table tbody tr').forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.01)';
                this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.2)';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
                this.style.boxShadow = 'none';
            });
        });
    </script>
</body>
</html>