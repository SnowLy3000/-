<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!function_exists('h')) { function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

$settings_raw = $pdo->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$grades = $pdo->query("SELECT * FROM user_grades ORDER BY min_xp ASC")->fetchAll();

// ПОЛУЧАЕМ ЛОГИ НАЧИСЛЕНИЙ (последние 15 записей)
$xp_logs = $pdo->query("
    SELECT l.*, 
           u.first_name as u_name, u.last_name as u_last,
           a.first_name as a_name, a.last_name as a_last
    FROM user_xp_log l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN users a ON l.admin_id = a.id
    ORDER BY l.created_at DESC 
    LIMIT 15
")->fetchAll();
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
            --warning: #ffc107;
            --dark-bg: #0f0f15;
            --card-bg: #1a1a24;
            --card-border: #2a2a3a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0c0;
            --text-muted: #6c6c7e;
            --hover-bg: #2a2a34;
            --transition: all 0.3s ease;
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
            padding: 20px;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: linear-gradient(135deg, rgba(120, 90, 255, 0.1) 0%, rgba(0, 255, 136, 0.05) 100%);
            border-radius: 20px;
            padding: 30px;
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
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, var(--primary) 0%, transparent 70%);
            opacity: 0.1;
        }

        .header h1 {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(90deg, var(--primary), var(--success));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 16px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 30px;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(120, 90, 255, 0.2);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title::before {
            content: '';
            width: 4px;
            height: 20px;
            background: var(--primary);
            border-radius: 2px;
        }

        .xp-setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            margin-bottom: 12px;
            border: 1px solid transparent;
            transition: var(--transition);
        }

        .xp-setting-item:hover {
            background: rgba(120, 90, 255, 0.05);
            border-color: rgba(120, 90, 255, 0.2);
        }

        .xp-input {
            width: 100px;
            background: rgba(15, 15, 21, 0.8);
            border: 2px solid var(--card-border);
            color: var(--success-light);
            text-align: center;
            font-weight: 700;
            border-radius: 10px;
            padding: 10px;
            font-size: 16px;
            transition: var(--transition);
        }

        .xp-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(120, 90, 255, 0.2);
        }

        .btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(120, 90, 255, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #00cc6f 100%);
        }

        .btn-success:hover {
            box-shadow: 0 8px 25px rgba(0, 255, 136, 0.4);
        }

        .grade-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            margin-bottom: 12px;
            transition: var(--transition);
        }

        .grade-item:hover {
            background: rgba(120, 90, 255, 0.05);
            border-color: var(--primary);
            transform: translateX(5px);
        }

        .grade-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-right: 20px;
        }

        .grade-info {
            flex: 1;
        }

        .grade-title {
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 4px;
        }

        .grade-xp {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .grade-xp span {
            color: var(--success);
            font-weight: 700;
        }

        .grade-actions {
            display: flex;
            gap: 8px;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--hover-bg);
            border: none;
            color: var(--text-primary);
            cursor: pointer;
            transition: var(--transition);
        }

        .icon-btn:hover {
            background: var(--primary);
            transform: rotate(5deg) scale(1.1);
        }

        .icon-btn-danger {
            background: rgba(255, 107, 107, 0.1);
        }

        .icon-btn-danger:hover {
            background: var(--danger);
        }

        .table-container {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 30px;
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .table-title {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        th {
            text-align: left;
            padding: 16px;
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 1px solid var(--card-border);
            font-weight: 600;
            background: rgba(255, 255, 255, 0.02);
        }

        td {
            padding: 20px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            font-size: 14px;
            transition: var(--transition);
        }

        tr:hover td {
            background: rgba(120, 90, 255, 0.05);
        }

        .xp-badge {
            background: rgba(0, 255, 136, 0.1);
            color: var(--success);
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            display: inline-block;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(10px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: linear-gradient(135deg, var(--card-bg) 0%, #1e1e2a 100%);
            border: 1px solid var(--card-border);
            padding: 40px;
            border-radius: 24px;
            width: 100%;
            max-width: 500px;
            position: relative;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
            transition: var(--transition);
        }

        .modal-close:hover {
            color: var(--text-primary);
            transform: rotate(90deg);
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            background: rgba(15, 15, 21, 0.8);
            border: 2px solid var(--card-border);
            color: var(--text-primary);
            padding: 16px;
            border-radius: 12px;
            font-size: 16px;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(120, 90, 255, 0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 800;
            background: linear-gradient(90deg, var(--primary), var(--success));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Заголовок -->
        <div class="header">
            <h1>🎮 Центр Геймификации</h1>
            <p>Управление правилами роста, уровнями и мотивацией сотрудников</p>
        </div>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= count($grades) ?></div>
                <div class="stat-label">Уровней доступа</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">15</div>
                <div class="stat-label">Последних начислений</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">6</div>
                <div class="stat-label">Типов действий</div>
            </div>
        </div>

        <!-- Основной контент -->
        <div class="grid-container">
            <!-- Левая колонка: Вес действий -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">⚡ Вес действий (XP)</div>
                    <div style="color: var(--text-secondary); font-size: 14px;">1 XP = 1 балл опыта</div>
                </div>
                
                <form action="/admin/actions/save_gamification.php" method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <?php 
                    $labels = [
                        'xp_test_passed' => ['📖 Сдача обычного теста', '#785aff'],
                        'xp_exam_passed' => ['🏆 Прохождение экзамена темы', '#00ff88'],
                        'xp_instruction_read' => ['📄 Изучение инструкции', '#9277ff'],
                        'xp_checkin_ontime' => ['📍 Check-in вовремя', '#ffc107'],
                        'xp_perfect_week' => ['📅 Неделя без прогулов', '#00d4ff'],
                        'xp_bug_bounty_base' => ['🤝 Bug Bounty (мин. бонус)', '#ff6b6b']
                    ];
                    
                    foreach($labels as $key => $data): 
                        list($label, $color) = $data;
                    ?>
                        <div class="xp-setting-item">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="color: <?= $color ?>; font-size: 20px;"><?= substr($label, 0, 2) ?></div>
                                <div>
                                    <div style="font-weight: 600; font-size: 15px;"><?= substr($label, 3) ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                        Баллы за выполнение
                                    </div>
                                </div>
                            </div>
                            <input 
                                type="number" 
                                name="<?= $key ?>" 
                                value="<?= h($settings_raw[$key] ?? 0) ?>" 
                                class="xp-input"
                                min="0"
                                max="1000"
                                style="color: <?= $color ?>;"
                            >
                        </div>
                    <?php endforeach; ?>
                    
                    <button type="submit" class="btn" style="width: 100%; margin-top: 20px;">
                        💾 Обновить правила начисления
                    </button>
                </form>
            </div>

            <!-- Правая колонка: Уровни доступа -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📈 Уровни доступа</div>
                    <button onclick="openGradeModal()" class="btn btn-success">
                        <span style="font-size: 18px;">+</span> Добавить звание
                    </button>
                </div>

                <div style="max-height: 500px; overflow-y: auto; padding-right: 10px;">
                    <?php if ($grades): ?>
                        <?php foreach ($grades as $index => $g): 
                            $colors = ['#785aff', '#00ff88', '#ff6b6b', '#ffc107', '#00d4ff', '#9277ff'];
                            $color = $colors[$index % count($colors)];
                        ?>
                            <div class="grade-item">
                                <div style="display: flex; align-items: center; flex: 1;">
                                    <div class="grade-icon" style="background: linear-gradient(135deg, <?= $color ?> 0%, <?= $color ?>80 100%);">
                                        <?= h($g['icon']) ?>
                                    </div>
                                    <div class="grade-info">
                                        <div class="grade-title"><?= h($g['title']) ?></div>
                                        <div class="grade-xp">Минимум: <span><?= number_format($g['min_xp']) ?> XP</span></div>
                                    </div>
                                </div>
                                <div class="grade-actions">
                                    <button onclick='openGradeModal(<?= json_encode($g) ?>)' class="icon-btn">
                                        ✏️
                                    </button>
                                    <a href="/admin/actions/save_gamification.php?delete_grade=<?= $g['id'] ?>" 
                                       onclick="return confirm('Удалить звание \"<?= addslashes($g['title']) ?>\"?')" 
                                       class="icon-btn icon-btn-danger">
                                        🗑️
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;">🎖️</div>
                            <div style="font-size: 16px; color: var(--text-secondary); margin-bottom: 24px;">
                                Уровни еще не созданы
                            </div>
                            <button onclick="openGradeModal()" class="btn">
                                Создать первый уровень
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- История начислений -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">📜 История начислений XP</div>
                <div style="color: var(--text-secondary); font-size: 14px;">Последние 15 записей</div>
            </div>

            <?php if ($xp_logs): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Дата</th>
                            <th>Сотрудник</th>
                            <th>XP</th>
                            <th>Причина</th>
                            <th>Администратор</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($xp_logs as $l): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?= date('d.m.y', strtotime($l['created_at'])) ?></div>
                                    <div style="font-size: 12px; color: var(--text-muted);"><?= date('H:i', strtotime($l['created_at'])) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700;"><?= h($l['u_last']) ?></div>
                                    <div style="font-size: 13px; color: var(--text-secondary);"><?= h($l['u_name']) ?></div>
                                </td>
                                <td>
                                    <span class="xp-badge">+<?= $l['amount'] ?></span>
                                </td>
                                <td>
                                    <div style="font-weight: 500;"><?= h($l['reason']) ?></div>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                                        <div style="font-weight: 600; color: var(--primary);">
                                            <?= h($l['a_last'] ?: 'System') ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <div style="font-size: 48px; margin-bottom: 16px; opacity: 0.3;">📝</div>
                    <div style="font-size: 16px; color: var(--text-secondary);">
                        История начислений пуста
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Модальное окно для добавления/редактирования уровня -->
    <div id="gradeModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeGradeModal()">×</button>
            
            <h2 id="modalTitle" style="margin-bottom: 30px; font-size: 24px; font-weight: 800; color: var(--text-primary);">
                ✨ Создать новый уровень
            </h2>
            
            <form action="/admin/actions/save_gamification.php" method="POST">
                <input type="hidden" name="action" value="save_grade">
                <input type="hidden" name="grade_id" id="grade_id" value="">
                
                <div class="form-group">
                    <label class="form-label">Название звания</label>
                    <input type="text" 
                           name="title" 
                           id="grade_title" 
                           required 
                           placeholder="Например: Эксперт" 
                           class="form-input">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Иконка</label>
                        <input type="text" 
                               name="icon" 
                               id="grade_icon" 
                               required 
                               class="form-input"
                               style="text-align: center; font-size: 20px;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Порог XP</label>
                        <input type="number" 
                               name="min_xp" 
                               id="grade_xp" 
                               required 
                               placeholder="1000" 
                               class="form-input">
                    </div>
                </div>
                
                <div style="display: flex; gap: 16px; margin-top: 32px;">
                    <button type="button" 
                            onclick="closeGradeModal()" 
                            style="flex: 1; background: var(--hover-bg); color: var(--text-primary); border: none; padding: 16px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: var(--transition);">
                        Отмена
                    </button>
                    <button type="submit" 
                            style="flex: 2; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white; border: none; padding: 16px; border-radius: 12px; font-weight: 700; cursor: pointer; transition: var(--transition);">
                        💾 Сохранить уровень
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openGradeModal(data = null) {
            const modal = document.getElementById('gradeModal');
            if (data) {
                document.getElementById('modalTitle').innerHTML = "✏️ Редактировать уровень";
                document.getElementById('grade_id').value = data.id;
                document.getElementById('grade_title').value = data.title;
                document.getElementById('grade_icon').value = data.icon;
                document.getElementById('grade_xp').value = data.min_xp;
            } else {
                document.getElementById('modalTitle').innerHTML = "✨ Создать новый уровень";
                document.getElementById('grade_id').value = "";
                document.getElementById('grade_title').value = "";
                document.getElementById('grade_icon').value = "🎖️";
                document.getElementById('grade_xp').value = "";
            }
            modal.style.display = 'flex';
        }

        function closeGradeModal() { 
            document.getElementById('gradeModal').style.display = 'none';
        }

        // Закрытие модального окна при клике вне его
        document.getElementById('gradeModal').addEventListener('click', function(e) {
            if (e.target === this) closeGradeModal();
        });

        // Добавляем интерактивность для инпутов
        document.querySelectorAll('.xp-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.style.transform = 'scale(1.05)';
            });
            
            input.addEventListener('blur', function() {
                this.style.transform = 'scale(1)';
            });
        });

        // Анимация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>