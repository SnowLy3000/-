<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('salary_categories'); // Соответствует slug из прав доступа

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
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 24px; padding: 30px; margin-bottom: 30px;
    }

    .form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
    .input-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.3); margin-bottom: 8px; display: block; font-weight: 800; }
    
    .st-input {
        width: 100%; background: #0b0b12; border: 1px solid #333;
        border-radius: 12px; padding: 12px 16px; color: #fff; font-size: 15px; transition: 0.2s; outline: none; box-sizing: border-box;
    }
    .st-input:focus { border-color: #785aff; }

    .styled-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    .styled-table tr { background: rgba(255,255,255,0.02); transition: 0.2s; }
    .styled-table td { padding: 15px 20px; border-top: 1px solid rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.05); }
    .styled-table td:first-child { border-left: 1px solid rgba(255,255,255,0.05); border-radius: 12px 0 0 12px; }
    .styled-table td:last-child { border-right: 1px solid rgba(255,255,255,0.05); border-radius: 0 12px 12px 0; }
    
    .perc-badge {
        background: rgba(124, 255, 107, 0.1); color: #7CFF6B;
        padding: 5px 12px; border-radius: 8px; font-weight: 900; font-size: 14px; border: 1px solid rgba(124, 255, 107, 0.2);
    }
    
    .status-pill { padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
    .sp-active { background: rgba(0, 200, 81, 0.1); color: #00c851; }
    .sp-off { background: rgba(255, 68, 68, 0.1); color: #ff4444; }
    
    .btn-save { 
        background: #785aff; color: #fff; border: none; padding: 12px 25px; border-radius: 12px; 
        font-weight: 800; cursor: pointer; transition: 0.2s;
    }
    .btn-save:hover { background: #6648df; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(120,90,255,0.3); }

    .action-btn { text-decoration: none; opacity: 0.5; transition: 0.2s; margin-left: 10px; font-size: 16px; }
    .action-btn:hover { opacity: 1; }
</style>

<div class="cat-container">
    <div style="margin-bottom: 30px;">
        <h1 style="margin:0; font-size: 26px; font-weight: 900;">💸 Процентные ставки</h1>
        <p style="margin:5px 0 0 0; font-size: 14px; opacity: 0.4;">Управление категориями начислений для товаров</p>
    </div>

    <div class="form-card">
        <form method="post">
            <input type="hidden" name="id" value="<?= $edit ? $edit['id'] : 0 ?>">
            
            <div class="form-grid">
                <div>
                    <label class="input-label">Название категории</label>
                    <input name="name" class="st-input" value="<?= $edit ? h($edit['name']) : '' ?>" required placeholder="Напр: Аксессуары Premium">
                </div>
                <div>
                    <label class="input-label">Бонус %</label>
                    <input name="percent" type="number" step="0.01" class="st-input" value="<?= $edit ? $edit['percent'] : '' ?>" required placeholder="0.00">
                </div>
            </div>

            <div style="margin-top: 20px;">
                <label class="input-label">Внутреннее примечание</label>
                <textarea name="description" class="st-input" style="height: 60px; resize: none;"><?= $edit ? h($edit['description']) : '' ?></textarea>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 25px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 13px; font-weight: 700;">
                    <input type="checkbox" name="is_active" style="width:18px; height:18px; accent-color: #785aff;" <?= (!$edit || $edit['is_active']) ? 'checked' : '' ?>>
                    Категория активна (используется в расчетах)
                </label>
                
                <div style="display: flex; gap: 12px;">
                    <?php if ($edit): ?>
                        <a href="?page=salary_categories" style="text-decoration:none; color:rgba(255,255,255,0.4); padding: 12px 20px; font-size: 13px; font-weight: 800;">Отмена</a>
                    <?php endif; ?>
                    <button class="btn-save"><?= $edit ? '💾 СОХРАНИТЬ' : '🚀 СОЗДАТЬ КАТЕГОРИЮ' ?></button>
                </div>
            </div>
        </form>
    </div>

    <table class="styled-table">
        <thead>
            <tr style="text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: rgba(255,255,255,0.3);">
                <th style="padding: 10px 20px;">Категория</th>
                <th style="padding: 10px 20px;">Примечание</th>
                <th style="padding: 10px 20px;">Процент</th>
                <th style="padding: 10px 20px;">Статус</th>
                <th style="padding: 10px 20px; text-align: right;">Управление</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$cats): ?>
                <tr><td colspan="5" style="text-align: center; opacity: 0.2; padding: 60px;">Категории еще не определены</td></tr>
            <?php endif; ?>
            <?php foreach ($cats as $c): ?>
            <tr>
                <td><b style="font-size: 15px;"><?= h($c['name']) ?></b></td>
                <td style="font-size: 12px; opacity: 0.5; max-width: 200px;"><?= h($c['description']) ?></td>
                <td><span class="perc-badge"><?= (float)$c['percent'] ?>%</span></td>
                <td>
                    <span class="status-pill <?= $c['is_active'] ? 'sp-active' : 'sp-off' ?>">
                        <?= $c['is_active'] ? '● Активна' : '○ Отключена' ?>
                    </span>
                </td>
                <td style="text-align: right;">
                    <a href="?page=salary_categories&edit_id=<?= $c['id'] ?>" class="action-btn">✏️</a>
                    <a href="javascript:void(0)" onclick="confirmDelete(<?= $c['id'] ?>)" class="action-btn" style="color: #ff4444;">🗑️</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
function confirmDelete(id) {
    if (confirm('Внимание! При удалении категории, товары, привязанные к ней, могут перестать учитываться в расчете зарплаты. Вы уверены?')) {
        window.location.href = '?page=salary_categories&delete_id=' + id;
    }
}
</script>