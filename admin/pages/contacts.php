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

<style>
    .contacts-container { max-width: 1200px; margin: 0 auto; }
    .contacts-card { background: rgba(255, 255, 255, 0.02); border-radius: 24px; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.05); }
    
    .search-box { 
        width: 100%; height: 50px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); 
        border-radius: 15px; padding: 0 20px; color: #fff; outline: none; margin-bottom: 20px; font-size: 15px;
    }
    .search-box:focus { border-color: #785aff; }

    .contacts-table { width: 100%; border-collapse: collapse; }
    .contacts-table th { 
        padding: 16px; text-align: left; font-size: 10px; color: rgba(255, 255, 255, 0.3); 
        text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    .contacts-table td { padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.03); font-size: 14px; color: #eee; }
    .contacts-table tr:hover td { background: rgba(120, 90, 255, 0.04); }

    .user-avatar { 
        width: 38px; height: 38px; background: linear-gradient(135deg, #785aff, #b866ff); 
        color: #fff; border-radius: 12px; display: flex; align-items: center; justify-content: center; 
        font-weight: 800; font-size: 14px;
    }

    .badge { font-size: 10px; padding: 3px 10px; border-radius: 8px; background: rgba(255, 255, 255, 0.05); color: rgba(255, 255, 255, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); }
    .badge-role { border-color: rgba(120, 90, 255, 0.5); color: #b866ff; background: rgba(120, 90, 255, 0.05); }

    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 8px; }
    .dot-active { background: #00c851; box-shadow: 0 0 10px rgba(0, 200, 81, 0.4); }
    .dot-pending { background: #ffbb33; box-shadow: 0 0 10px rgba(255, 187, 51, 0.4); }

    @media (max-width: 768px) {
        .hide-mobile { display: none; }
    }
</style>

<div class="contacts-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="margin:0; font-size: 28px;">👥 Контакты</h1>
            <p class="muted">Телефонный справочник команды</p>
        </div>
    </div>

    <input type="text" id="contactSearch" class="search-box" placeholder="🔍 Быстрый поиск по имени, телефону или должности...">

    <div class="contacts-card">
        <div style="overflow-x: auto;">
            <table class="contacts-table" id="contactsTable">
                <thead>
                    <tr>
                        <th>Сотрудник</th>
                        <th>Телефон</th>
                        <th class="hide-mobile">Telegram</th>
                        <th>Грейд / Должность</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div class="user-avatar">
                                    <?= mb_substr($r['first_name'], 0, 1) . mb_substr($r['last_name'], 0, 1) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; color: #fff;"><?= h($r['last_name'] . ' ' . $r['first_name']) ?></div>
                                    <div class="muted" style="font-size: 11px;">#<?= $r['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($r['phone']): ?>
                                <a href="tel:<?= preg_replace('/[^0-9+]/', '', $r['phone']) ?>" style="color: #fff; text-decoration: none; font-weight: 600;">
                                    <?= h($r['phone']) ?>
                                </a>
                            <?php else: ?>
                                <span class="muted">не указан</span>
                            <?php endif; ?>
                        </td>
                        <td class="hide-mobile">
                            <?php if ($r['telegram']): ?>
                                <a href="https://t.me/<?= str_replace('@', '', $r['telegram']) ?>" target="_blank" style="color: #785aff; text-decoration: none;">
                                    @<?= h(str_replace('@', '', $r['telegram'])) ?>
                                </a>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                                <?php if ($r['roles']): ?>
                                    <?php foreach (explode(', ', $r['roles']) as $role): ?>
                                        <span class="badge badge-role"><?= h($role) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                <?php if ($r['positions']): ?>
                                    <?php foreach (explode(', ', $r['positions']) as $pos): ?>
                                        <span class="badge"><?= h($pos) ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="status-dot <?= $r['status'] === 'active' ? 'dot-active' : 'dot-pending' ?>"></span>
                            <span style="font-size: 12px; font-weight: 600;"><?= strtoupper(h($r['status'])) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Простой поиск по таблице без перезагрузки
document.getElementById('contactSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#contactsTable tbody tr');
    
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>
