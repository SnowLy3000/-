<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

// Тянем расширенные данные: связку ролей и должностей через запятую
$stmt = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.phone, u.telegram, u.status,
           GROUP_CONCAT(DISTINCT r.name SEPARATOR ', ') AS roles,
           GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') AS positions
    FROM users u
    LEFT JOIN user_roles ur ON ur.user_id = u.id
    LEFT JOIN roles r ON r.id = ur.role_id
    LEFT JOIN user_positions up ON up.user_id = u.id
    LEFT JOIN positions p ON p.id = up.position_id
    GROUP BY u.id
    ORDER BY u.last_name ASC
");
$rows = $stmt->fetchAll();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
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
            --danger: #ff6b6b;
            --warning: #ffc107;
            --dark-bg: #0f0f15;
            --card-bg: #1a1a24;
            --card-border: #2a2a3a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0c0;
            --text-muted: #6c6c7e;
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
            padding: 15px;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            padding: 20px 0;
            margin-bottom: 20px;
        }

        .header-title {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(90deg, #785aff, #9277ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }

        .header-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .search-box {
            width: 100%;
            padding: 12px 20px 12px 45px;
            background: var(--card-bg);
            border: 2px solid var(--card-border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 14px;
            margin-bottom: 15px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236c6c7e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 15px center;
            background-size: 16px;
        }

        .search-box:focus {
            outline: none;
            border-color: var(--primary);
        }

        .contacts-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .contacts-table th {
            text-align: left;
            padding: 12px 10px;
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--card-border);
            background: var(--card-bg);
        }

        .contacts-table td {
            padding: 12px 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            vertical-align: top;
        }

        .contacts-table tr:hover td {
            background: rgba(120, 90, 255, 0.05);
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: white;
            flex-shrink: 0;
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .user-id {
            font-size: 11px;
            color: var(--text-muted);
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .phone-link {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .phone-link:hover {
            color: var(--primary);
        }

        .copy-btn {
            background: rgba(120, 90, 255, 0.1);
            border: 1px solid rgba(120, 90, 255, 0.2);
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 10px;
            color: var(--primary);
            cursor: pointer;
            margin-top: 4px;
        }

        .telegram-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .badges-container {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            max-width: 200px;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-role {
            background: rgba(120, 90, 255, 0.15);
            color: #9277ff;
            border: 1px solid rgba(120, 90, 255, 0.3);
        }

        .badge-position {
            background: rgba(0, 255, 136, 0.15);
            color: #00ff88;
            border: 1px solid rgba(0, 255, 136, 0.3);
        }

        .status-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot-active {
            background: var(--success);
        }

        .dot-pending {
            background: var(--warning);
        }

        .status-text {
            font-size: 12px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--card-border);
        }

        .empty-icon {
            font-size: 40px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .empty-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
            opacity: 0.8;
        }

        .empty-subtitle {
            color: var(--text-secondary);
            font-size: 14px;
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            .page-wrapper {
                padding: 10px;
            }
            
            .hide-mobile {
                display: none;
            }
            
            .contacts-table {
                font-size: 12px;
            }
            
            .contacts-table th,
            .contacts-table td {
                padding: 10px 8px;
            }
            
            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: 12px;
            }
            
            .user-name {
                font-size: 13px;
            }
            
            .badges-container {
                max-width: 150px;
            }
            
            .badge {
                padding: 3px 6px;
                font-size: 10px;
            }
        }

        @media (max-width: 480px) {
            .header-title {
                font-size: 20px;
            }
            
            .contacts-table th,
            .contacts-table td {
                padding: 8px 6px;
            }
            
            .user-cell {
                gap: 8px;
            }
        }

        /* Улучшенный скроллбар */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="page-container">
            <!-- Компактный заголовок -->
            <div class="header">
                <h1 class="header-title">👥 Контакты</h1>
                <p class="header-subtitle">Телефонный справочник сотрудников</p>
            </div>

            <!-- Поиск -->
            <input type="text" 
                   id="contactSearch" 
                   class="search-box" 
                   placeholder="Поиск по имени, телефону или должности..." 
                   onkeyup="searchContacts()">

            <!-- Таблица контактов -->
            <div style="overflow-x: auto;">
                <table class="contacts-table" id="contactsTable">
                    <thead>
                        <tr>
                            <th>Сотрудник</th>
                            <th>Телефон</th>
                            <th class="hide-mobile">Telegram</th>
                            <th>Роли / Должность</th>
                            <th>Статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-icon">📇</div>
                                        <h3 class="empty-title">Контакты не найдены</h3>
                                        <p class="empty-subtitle">В системе пока нет зарегистрированных сотрудников</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): 
                                $user_initials = mb_substr($r['first_name'], 0, 1, 'UTF-8') . mb_substr($r['last_name'], 0, 1, 'UTF-8');
                                $status_class = $r['status'] === 'active' ? 'dot-active' : 'dot-pending';
                                $status_text = $r['status'] === 'active' ? 'АКТИВЕН' : 'ОЖИДАНИЕ';
                            ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar">
                                                <?= h($user_initials) ?>
                                            </div>
                                            <div class="user-info">
                                                <div class="user-name">
                                                    <?= h($r['first_name'] . ' ' . $r['last_name']) ?>
                                                </div>
                                                <div class="user-id">
                                                    ID: <?= $r['id'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-info">
                                            <?php if ($r['phone']): ?>
                                                <a href="tel:<?= preg_replace('/[^0-9+]/', '', $r['phone']) ?>" 
                                                   class="phone-link">
                                                    📱 <?= h($r['phone']) ?>
                                                </a>
                                                <button class="copy-btn" 
                                                        onclick="copyToClipboard('<?= h($r['phone']) ?>')">
                                                    Копировать
                                                </button>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 12px;">Не указан</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="hide-mobile">
                                        <?php if ($r['telegram']): ?>
                                            <a href="https://t.me/<?= str_replace('@', '', $r['telegram']) ?>" 
                                               target="_blank" 
                                               class="telegram-link">
                                                ✈️ @<?= h(str_replace('@', '', $r['telegram'])) ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 12px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="badges-container">
                                            <?php if ($r['roles']): ?>
                                                <?php foreach (explode(', ', $r['roles']) as $role): 
                                                    if (trim($role)): ?>
                                                        <span class="badge badge-role"><?= h(trim($role)) ?></span>
                                                    <?php endif;
                                                endforeach; ?>
                                            <?php endif; ?>
                                            <?php if ($r['positions']): ?>
                                                <?php foreach (explode(', ', $r['positions']) as $pos): 
                                                    if (trim($pos)): ?>
                                                        <span class="badge badge-position"><?= h(trim($pos)) ?></span>
                                                    <?php endif;
                                                endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="status-container">
                                            <span class="status-dot <?= $status_class ?>"></span>
                                            <span class="status-text"><?= $status_text ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Функция поиска в таблице
        function searchContacts() {
            const input = document.getElementById('contactSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('contactsTable');
            const rows = table.getElementsByTagName('tr');
            
            // Пропускаем заголовок и строку с empty-state (если есть)
            const startIndex = table.querySelector('tbody tr:first-child td[colspan]') ? 2 : 1;
            
            for (let i = startIndex; i < rows.length; i++) {
                const row = rows[i];
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            }
        }
        
        // Функция копирования в буфер обмена
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showNotification('✓ Номер скопирован');
            }).catch(err => {
                // Fallback для старых браузеров
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showNotification('✓ Номер скопирован');
            });
        }
        
        // Функция показа уведомления
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--primary);
                color: white;
                padding: 10px 20px;
                border-radius: 8px;
                font-weight: 600;
                font-size: 13px;
                z-index: 10000;
                animation: slideIn 0.2s ease-out;
                box-shadow: 0 4px 12px rgba(120, 90, 255, 0.3);
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.2s ease-in';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 200);
            }, 1500);
        }
        
        // Добавляем стили для анимаций уведомлений
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // Фокус на поиск при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('contactSearch').focus();
        });
    </script>
</body>
</html>