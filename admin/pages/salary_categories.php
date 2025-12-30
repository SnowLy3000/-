<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// ЗАМЕНЯЕМ: Теперь доступ регулируется через админку ролей
require_role('manage_salary');

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* ================= ЛОГИКА ДЕЙСТВИЙ ================= */

if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM salary_categories WHERE id = ?");
    $stmt->execute([(int)$_GET['delete_id']]);
    echo "<script>window.location.href='?page=salary_categories';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $percent = (float)($_POST['percent'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $active = isset($_POST['is_active']) ? 1 : 0;

    if ($name !== '') {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE salary_categories SET name=?, percent=?, description=?, is_active=? WHERE id=?");
            $stmt->execute([$name, $percent, $desc, $active, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO salary_categories (name, percent, description, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $percent, $desc, $active]);
        }
        echo "<script>window.location.href='?page=salary_categories';</script>";
        exit;
    }
}

$cats = $pdo->query("SELECT * FROM salary_categories ORDER BY percent DESC")->fetchAll();
$edit = null;
if (isset($_GET['edit_id'])) {
    foreach ($cats as $c) { if ($c['id'] == (int)$_GET['edit_id']) $edit = $c; }
}
?>

<style>
    .cat-container { font-family: 'Inter', sans-serif; max-width: 1000px; margin: 0 auto; color: #fff; }
    
    .form-card {
        background: linear-gradient(145deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 24px; padding: 30px; margin-bottom: 30px;
    }

    .form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
    .input-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.4); margin-bottom: 8px; display: block; font-weight: 700; }
    
    .st-input {
        width: 100%; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1);
        border-radius: 14px; padding: 12px 16px; color: #fff; font-size: 15px; transition: 0.3s; outline: none; box-sizing: border-box;
    }
    .st-input:focus { border-color: #785aff; background: rgba(120,90,255,0.05); }

    .styled-table { width: 100%; border-collapse: separate; border-spacing: 0 10px; }
    .styled-table tr { background: rgba(255,255,255,0.02); transition: 0.3s; }
    .styled-table tr:hover { background: rgba(255,255,255,0.05); }
    .styled-table td { padding: 18px; }
    .styled-table td:first-child { border-radius: 15px 0 0 15px; }
    .styled-table td:last-child { border-radius: 0 15px 15px 0; }
    
    .perc-badge {
        background: rgba(124, 255, 107, 0.1); color: #7CFF6B;
        padding: 6px 14px; border-radius: 10px; font-weight: 800; font-size: 14px; border: 1px solid rgba(124, 255, 107, 0.2);
    }
    
    .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 8px; }
    
    .btn-save { 
        background: #785aff; color: #fff; border: none; padding: 14px 25px; border-radius: 14px; 
        font-weight: 700; cursor: pointer; transition: 0.3s;
    }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(120,90,255,0.3); }

    .action-btn { text-decoration: none; font-size: 18px; margin-left: 10px; opacity: 0.6; transition: 0.2s; }
    .action-btn:hover { opacity: 1; transform: scale(1.2); }
</style>

<div class="cat-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <div>
            <h1 style="margin:0; font-size: 24px;">💸 Категории процентов</h1>
            <p class="muted" style="margin:5px 0 0 0;">Настройка бонусных ставок для различных групп товаров</p>
        </div>
    </div>

    <div class="form-card">
        <form method="post">
            <input type="hidden" name="id" value="<?= $edit ? $edit['id'] : 0 ?>">
            
            <div class="form-grid">
                <div>
                    <label class="input-label">Название группы</label>
                    <input name="name" class="st-input" value="<?= $edit ? h($edit['name']) : '' ?>" required placeholder="Напр: Аксессуары Premium">
                </div>
                <div>
                    <label class="input-label">Ставка %</label>
                    <input name="percent" type="number" step="0.01" class="st-input" value="<?= $edit ? $edit['percent'] : '' ?>" required placeholder="0.00">
                </div>
            </div>

            <div style="margin-top: 20px;">
                <label class="input-label">Описание (для информации)</label>
                <textarea name="description" class="st-input" style="height: 70px; resize: none;"><?= $edit ? h($edit['description']) : '' ?></textarea>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px;">
                    <input type="checkbox" name="is_active" style="width:18px; height:18px; accent-color: #785aff;" <?= (!$edit || $edit['is_active']) ? 'checked' : '' ?>>
                    Категория активна
                </label>
                
                <div style="display: flex; gap: 12px;">
                    <?php if ($edit): ?>
                        <a href="?page=salary_categories" class="st-input" style="text-decoration:none; background: rgba(255,255,255,0.1); display: flex; align-items: center;">Отмена</a>
                    <?php endif; ?>
                    <button class="btn-save"><?= $edit ? '💾 Сохранить изменения' : '🚀 Создать категорию' ?></button>
                </div>
            </div>
        </form>
    </div>

    <table class="styled-table">
        <thead>
            <tr style="text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.3);">
                <th style="padding: 10px 20px;">Название группы</th>
                <th style="padding: 10px 20px;">Описание</th>
                <th style="padding: 10px 20px;">Процент</th>
                <th style="padding: 10px 20px;">Статус</th>
                <th style="padding: 10px 20px; text-align: right;">Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$cats): ?>
                <tr><td colspan="5" style="text-align: center; opacity: 0.3; padding: 40px;">Категории еще не созданы</td></tr>
            <?php endif; ?>
            <?php foreach ($cats as $c): ?>
            <tr>
                <td style="font-weight: 700;"><?= h($c['name']) ?></td>
                <td class="muted" style="font-size: 13px; max-width: 250px;"><?= h($c['description']) ?></td>
                <td><span class="perc-badge"><?= (float)$c['percent'] ?>%</span></td>
                <td>
                    <span class="status-dot" style="background: <?= $c['is_active'] ? '#00c851' : '#ff4444' ?>;"></span>
                    <span style="font-size: 12px; color: <?= $c['is_active'] ? '#00c851' : '#ff4444' ?>;">
                        <?= $c['is_active'] ? 'Активна' : 'Отключена' ?>
                    </span>
                </td>
                <td style="text-align: right;">
                    <a href="?page=salary_categories&edit_id=<?= $c['id'] ?>" class="action-btn" title="Редактировать">✏️</a>
                    <a href="javascript:void(0)" onclick="confirmDelete(<?= $c['id'] ?>)" class="action-btn" style="color: #ff4444;" title="Удалить">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function confirmDelete(id) {
    if (confirm('Внимание! При удалении категории, привязанные к ней товары могут перестать рассчитываться в ЗП. Продолжить?')) {
        window.location.href = '?page=salary_categories&delete_id=' + id;
    }
}
</script>
