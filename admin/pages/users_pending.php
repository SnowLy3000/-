<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

// Доступ только тем, у кого есть право на просмотр заявок
require_role('view_pending');

$stmt = $pdo->query("
    SELECT id, first_name, last_name, phone, telegram, created_at
    FROM users
    WHERE status = 'pending'
    ORDER BY created_at ASC
");
$users = $stmt->fetchAll();

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
?>

<style>
    .pending-container { font-family: 'Inter', sans-serif; color: #fff; }
    
    /* Заголовочная карточка */
    .pending-header {
        background: rgba(255, 187, 51, 0.05);
        border: 1px solid rgba(255, 187, 51, 0.15);
        padding: 25px;
        border-radius: 24px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .counter-circle {
        background: #ffbb33;
        color: #000;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 14px;
        box-shadow: 0 0 15px rgba(255, 187, 51, 0.3);
    }

    /* Таблица заявок */
    .pending-box {
        background: #111118;
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 24px;
        overflow: hidden;
    }

    .pending-table { width: 100%; border-collapse: collapse; }
    .pending-table th { 
        text-align: left; padding: 15px 20px; font-size: 10px; 
        text-transform: uppercase; color: rgba(255, 255, 255, 0.3); 
        letter-spacing: 1px; border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    .pending-table td { padding: 18px 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.02); }
    .pending-table tr:hover td { background: rgba(255, 255, 255, 0.01); }

    .avatar-wait {
        width: 40px; height: 40px;
        background: rgba(255, 187, 51, 0.1);
        color: #ffbb33;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 12px; border: 1px solid rgba(255, 187, 51, 0.2);
    }

    .btn-approve {
        background: #785aff;
        color: #fff;
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 700;
        transition: 0.3s;
        display: inline-block;
    }
    .btn-approve:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(120, 90, 255, 0.3); }

    .empty-state { text-align: center; padding: 80px 20px; }
</style>

<div class="pending-container">
    <div class="pending-header">
        <div>
            <h1 style="margin:0; font-size: 24px; font-weight: 900;">⏳ Заявки на вход</h1>
            <p style="margin:5px 0 0 0; opacity: 0.5; font-size: 14px;">Новые сотрудники ожидают подтверждения и настройки ролей</p>
        </div>
        <?php if (count($users) > 0): ?>
            <div class="counter-circle"><?= count($users) ?></div>
        <?php endif; ?>
    </div>

    <div class="pending-box">
        <?php if (!$users): ?>
            <div class="empty-state">
                <div style="font-size: 50px; margin-bottom: 20px;">🎉</div>
                <h3 style="margin:0; opacity: 0.8;">Очередь пуста</h3>
                <p style="opacity: 0.4; font-size: 14px;">Все регистрации обработаны вовремя.</p>
            </div>
        <?php else: ?>
            <table class="pending-table">
                <thead>
                    <tr>
                        <th>Сотрудник</th>
                        <th>Контакты</th>
                        <th>Дата регистрации</th>
                        <th style="text-align: right;">Управление</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <div class="avatar-wait">
                                    <?= mb_substr($u['first_name'], 0, 1) . mb_substr($u['last_name'], 0, 1) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; font-size: 15px;"><?= h($u['first_name'].' '.$u['last_name']) ?></div>
                                    <div style="font-size: 11px; opacity: 0.4;">ID пользователя: #<?= $u['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 13px; font-weight: 600;"><?= h($u['phone']) ?></div>
                            <?php if ($u['telegram']): ?>
                                <a href="https://t.me/<?= str_replace('@', '', $u['telegram']) ?>" target="_blank" style="color: #785aff; text-decoration: none; font-size: 12px;">
                                    @<?= h(str_replace('@', '', $u['telegram'])) ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size: 13px; font-weight: 600;"><?= date('d.m.Y', strtotime($u['created_at'])) ?></div>
                            <div style="font-size: 11px; opacity: 0.3;"><?= date('H:i', strtotime($u['created_at'])) ?></div>
                        </td>
                        <td style="text-align: right;">
                            <a class="btn-approve" href="index.php?page=user_edit&id=<?= $u['id'] ?>">
                                Настроить доступ
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>