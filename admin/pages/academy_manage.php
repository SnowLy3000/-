<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Получаем тесты вместе с количеством вопросов для каждого
$stmt = $pdo->query("
    SELECT t.*, 
    (SELECT COUNT(*) FROM academy_questions WHERE test_id = t.id) as q_count 
    FROM academy_tests t 
    ORDER BY t.id DESC
");
$tests = $stmt->fetchAll();

// Статистика для карточек
$stats = [
    'total' => count($tests),
    'exams' => count(array_filter($tests, fn($t) => isset($t['is_exam']) && $t['is_exam'])),
    'regular' => count(array_filter($tests, fn($t) => isset($t['is_exam']) && !$t['is_exam'])),
];
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
            overflow-x: hidden;
        }

        .page-wrapper {
            padding: 20px;
            max-width: 100%;
            width: 100%;
        }

        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .header {
            background: linear-gradient(135deg, rgba(120, 90, 255, 0.1) 0%, rgba(184, 102, 255, 0.05) 100%);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--card-border);
            position: relative;
            overflow: hidden;
            width: 100%;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-left {
            flex: 1;
            min-width: 300px;
        }

        .header-title {
            font-size: clamp(28px, 4vw, 40px);
            font-weight: 800;
            background: linear-gradient(90deg, #785aff, #b866ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .header-subtitle {
            color: var(--text-secondary);
            font-size: clamp(14px, 2vw, 16px);
            max-width: 500px;
            line-height: 1.5;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 24px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            min-width: 0;
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

        .stat-value {
            font-size: clamp(32px, 4vw, 40px);
            font-weight: 800;
            margin-bottom: 8px;
            line-height: 1;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: clamp(12px, 1.5vw, 14px);
            font-weight: 500;
        }

        .tests-grid {
            display: grid;
            gap: 20px;
            width: 100%;
        }

        .test-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 24px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            width: 100%;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
        }

        .test-card::before {
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

        .test-card:hover::before {
            transform: translateX(100%);
        }

        .test-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(120, 90, 255, 0.2);
        }

        .test-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            flex-shrink: 0;
        }

        .test-icon-exam {
            background: linear-gradient(135deg, rgba(255, 107, 107, 0.1) 0%, rgba(255, 140, 0, 0.1) 100%);
            border: 2px solid rgba(255, 107, 107, 0.2);
        }

        .test-icon-regular {
            background: linear-gradient(135deg, rgba(120, 90, 255, 0.1) 0%, rgba(184, 102, 255, 0.1) 100%);
            border: 2px solid rgba(120, 90, 255, 0.2);
        }

        .test-content {
            min-width: 0;
            overflow: hidden;
        }

        .test-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }

        .test-badge {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .badge-exam {
            background: var(--gradient-danger);
            color: white;
        }

        .badge-regular {
            background: var(--gradient-primary);
            color: white;
        }

        .test-title {
            font-size: clamp(16px, 2vw, 22px);
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            line-height: 1.3;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .test-description {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 16px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .test-meta {
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
        }

        .meta-value {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 14px;
        }

        .threshold-badge {
            background: rgba(0, 255, 136, 0.1);
            color: var(--success);
            padding: 6px 12px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .test-actions {
            display: flex;
            gap: 10px;
            flex-shrink: 0;
        }

        .action-btn {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .action-btn:hover {
            transform: translateY(-3px);
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
        }

        .btn-edit:hover {
            box-shadow: 0 8px 25px rgba(120, 90, 255, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--danger) 0%, var(--warning) 100%);
            color: white;
        }

        .btn-delete:hover {
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
        }

        .btn-create {
            background: linear-gradient(135deg, var(--success) 0%, var(--info) 100%);
            color: #000;
            font-weight: 700;
            padding: 16px 28px;
            border-radius: 16px;
            text-decoration: none;
            font-size: 14px;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(0, 255, 136, 0.3);
            white-space: nowrap;
        }

        .btn-create:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(0, 255, 136, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: linear-gradient(135deg, rgba(120, 90, 255, 0.03) 0%, rgba(184, 102, 255, 0.03) 100%);
            border: 2px dashed var(--card-border);
            border-radius: 24px;
            margin-top: 20px;
            width: 100%;
        }

        .empty-icon {
            font-size: 60px;
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
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text-primary);
            opacity: 0.8;
        }

        .empty-subtitle {
            color: var(--text-secondary);
            font-size: 15px;
            max-width: 400px;
            margin: 0 auto 30px;
            line-height: 1.5;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(10px);
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            position: relative;
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

        /* Адаптивные стили */
        @media (max-width: 1024px) {
            .test-card {
                grid-template-columns: auto 1fr;
                grid-template-rows: auto auto;
                gap: 15px;
            }
            
            .test-actions {
                grid-column: 1 / -1;
                justify-self: end;
                margin-top: 10px;
            }
        }

        @media (max-width: 768px) {
            .page-wrapper {
                padding: 15px;
            }
            
            .header {
                padding: 24px;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-left {
                min-width: 100%;
            }
            
            .btn-create {
                width: 100%;
                justify-content: center;
            }
            
            .stat-card {
                padding: 20px;
            }
            
            .test-card {
                padding: 20px;
                grid-template-columns: auto 1fr;
            }
            
            .test-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
            
            .test-meta {
                gap: 12px;
            }
            
            .meta-item {
                font-size: 12px;
            }
            
            .threshold-badge {
                padding: 5px 10px;
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .page-wrapper {
                padding: 10px;
            }
            
            .header {
                padding: 20px;
                border-radius: 20px;
            }
            
            .test-card {
                grid-template-columns: 1fr;
                gap: 15px;
                text-align: center;
            }
            
            .test-icon {
                margin: 0 auto;
            }
            
            .test-header {
                justify-content: center;
            }
            
            .test-meta {
                justify-content: center;
            }
            
            .test-actions {
                grid-column: 1;
                justify-self: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .action-btn {
                width: 42px;
                height: 42px;
            }
        }

        @media (max-width: 360px) {
            .header {
                padding: 16px;
            }
            
            .test-card {
                padding: 16px;
            }
            
            .test-meta {
                flex-direction: column;
                align-items: center;
                gap: 8px;
            }
            
            .meta-item {
                flex-direction: column;
                text-align: center;
                gap: 2px;
            }
        }

        /* Фикс для очень длинных заголовков */
        .text-truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .scroll-container {
            max-width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Улучшенный скроллбар */
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
                    <div class="header-left">
                        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                            <div style="width: 50px; height: 50px; background: rgba(120, 90, 255, 0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center; font-size: 24px; border: 1px solid rgba(120, 90, 255, 0.2);">
                                📦
                            </div>
                            <h1 class="header-title">Склад тестов</h1>
                        </div>
                        <p class="header-subtitle">Конструктор образовательных модулей и аттестаций для эффективного обучения команды</p>
                    </div>
                    <a href="?page=test_create" class="btn-create">
                        <span style="font-size: 18px;">+</span> СОЗДАТЬ ТЕСТ
                    </a>
                </div>
            </div>

            <!-- Статистика -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value" style="color: #785aff;"><?= $stats['total'] ?></div>
                    <div class="stat-label">Всего тестов</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #ff6b6b;"><?= $stats['exams'] ?></div>
                    <div class="stat-label">Экзаменов</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #00ff88;"><?= $stats['regular'] ?></div>
                    <div class="stat-label">Обычных тестов</div>
                </div>
            </div>

            <!-- Список тестов -->
            <div class="tests-grid">
                <?php if ($tests): ?>
                    <?php foreach ($tests as $t): 
                        $is_exam = isset($t['is_exam']) ? $t['is_exam'] : false;
                        $q_count = isset($t['q_count']) ? $t['q_count'] : 0;
                        $title = isset($t['title']) ? htmlspecialchars($t['title']) : 'Без названия';
                        $description = isset($t['description']) ? htmlspecialchars($t['description']) : 'Описание отсутствует...';
                        $min_score = isset($t['min_score']) ? $t['min_score'] : 70;
                        $time_limit = isset($t['time_limit']) ? $t['time_limit'] : null;
                        $xp_reward = isset($t['xp_reward']) ? $t['xp_reward'] : null;
                        $id = isset($t['id']) ? $t['id'] : 0;
                    ?>
                        <div class="test-card">
                            <div class="test-icon <?= $is_exam ? 'test-icon-exam' : 'test-icon-regular' ?>">
                                <?= $is_exam ? '🏆' : '📝' ?>
                            </div>
                            
                            <div class="test-content">
                                <div class="test-header">
                                    <span class="test-badge <?= $is_exam ? 'badge-exam' : 'badge-regular' ?>">
                                        <?= $is_exam ? '⚡ Экзамен' : '📚 Тест' ?>
                                    </span>
                                    <div class="meta-item">
                                        <span>❓</span>
                                        <span class="meta-value"><?= $q_count ?> вопросов</span>
                                    </div>
                                </div>
                                
                                <h3 class="test-title text-truncate"><?= $title ?></h3>
                                
                                <p class="test-description">
                                    <?= $description ?>
                                </p>
                                
                                <div class="test-meta">
                                    <div class="threshold-badge">
                                        <span>🎯</span>
                                        Порог: <?= $min_score ?>%
                                    </div>
                                    <?php if ($time_limit && $time_limit > 0): ?>
                                        <div class="meta-item">
                                            <span>⏱️</span>
                                            <span class="meta-value"><?= $time_limit ?> мин.</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($xp_reward && $xp_reward > 0): ?>
                                        <div class="meta-item">
                                            <span>⚡</span>
                                            <span class="meta-value" style="color: #00ff88;">+<?= $xp_reward ?> XP</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="test-actions">
                                <a href="?page=test_edit&id=<?= $id ?>" class="action-btn btn-edit" title="Редактировать тест">
                                    ✏️
                                </a>
                                <a href="/admin/actions/delete_test.php?id=<?= $id ?>" 
                                   onclick="return confirmDelete('<?= addslashes($title) ?>')" 
                                   class="action-btn btn-delete" title="Удалить тест">
                                    🗑️
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <h3 class="empty-title">На складе пока пусто</h3>
                        <p class="empty-subtitle">
                            Создайте первый обучающий тест или экзамен для начала обучения команды
                        </p>
                        <a href="?page=test_create" class="btn-create" style="margin-top: 20px;">
                            <span style="font-size: 18px;">+</span> СОЗДАТЬ ПЕРВЫЙ ТЕСТ
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Модальное окно подтверждения удаления -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">×</button>
            <div style="text-align: center; padding: 20px;">
                <div style="font-size: 50px; margin-bottom: 20px; color: #ff6b6b;">⚠️</div>
                <h3 style="margin-bottom: 10px; font-size: 22px;">Подтверждение удаления</h3>
                <p id="modalMessage" style="color: var(--text-secondary); margin-bottom: 30px; line-height: 1.5;">
                    Вы уверены, что хотите удалить этот тест?
                </p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <button onclick="closeModal()" style="padding: 12px 24px; background: var(--hover-bg); color: var(--text-primary); border: none; border-radius: 12px; font-weight: 600; cursor: pointer; transition: var(--transition); min-width: 100px;">
                        Отмена
                    </button>
                    <a id="confirmDeleteBtn" href="#" style="padding: 12px 24px; background: linear-gradient(135deg, #ff6b6b 0%, #ff8c00 100%); color: white; border-radius: 12px; text-decoration: none; font-weight: 700; transition: var(--transition); min-width: 100px;">
                        Удалить
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(testTitle) {
            const modal = document.getElementById('confirmModal');
            const message = document.getElementById('modalMessage');
            const deleteBtn = document.getElementById('confirmDeleteBtn');
            
            message.innerHTML = `Вы уверены, что хотите удалить тест <strong>"${testTitle}"</strong>?<br><small style="color: #ff6b6b;">Это действие нельзя отменить!</small>`;
            
            modal.style.display = 'flex';
            return false; // Предотвращаем переход по ссылке
        }

        function closeModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }

        // Закрытие модального окна при клике вне его
        document.getElementById('confirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // Анимация при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.test-card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Анимация для пустого состояния
            const emptyIcon = document.querySelector('.empty-icon');
            if (emptyIcon) {
                emptyIcon.style.animation = 'float 3s ease-in-out infinite';
            }
        });

        // Фикс для очень маленьких экранов
        function adjustLayout() {
            const width = window.innerWidth;
            const cards = document.querySelectorAll('.test-card');
            
            if (width < 480) {
                cards.forEach(card => {
                    const meta = card.querySelector('.test-meta');
                    if (meta) {
                        meta.style.flexDirection = 'column';
                        meta.style.alignItems = 'center';
                    }
                });
            }
        }
        
        window.addEventListener('resize', adjustLayout);
        window.addEventListener('load', adjustLayout);
    </script>
</body>
</html>