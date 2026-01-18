<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
// Проверка прав, если нужно (например, только для админа)
if (!has_role('Admin') && !has_role('Owner')) {
    exit('Нет доступа');
}

// 1. СОХРАНЕНИЕ ВРЕМЕНИ ОТКРЫТИЯ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_schedule'])) {
    foreach ($_POST['times'] as $branch_id => $time) {
        $stmt = $pdo->prepare("
            INSERT INTO branch_schedules (branch_id, opening_time) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE opening_time = ?
        ");
        $stmt->execute([(int)$branch_id, $time, $time]);
    }
    $success = "Графики успешно обновлены!";
}

// 2. ПОЛУЧАЕМ ВСЕ ФИЛИАЛЫ И ИХ ВРЕМЯ
$branches = $pdo->query("
    SELECT b.id, b.name, bs.opening_time 
    FROM branches b 
    LEFT JOIN branch_schedules bs ON b.id = bs.branch_id 
    ORDER BY b.name
")->fetchAll();
?>

<div style="margin-bottom: 25px;">
    <h1 style="font-size: 28px; font-weight: 800; margin: 0;">🕘 Графики работы филиалов</h1>
    <p style="opacity: 0.5;">Установите время открытия для контроля опозданий</p>
</div>

<?php if (isset($success)): ?>
    <div style="background: rgba(46, 204, 113, 0.2); color: #2ecc71; padding: 15px; border-radius: 15px; margin-bottom: 20px;">
        ✅ <?= $success ?>
    </div>
<?php endif; ?>

<div class="card" style="background: rgba(255,255,255,0.02); border-radius: 24px; padding: 30px; border: 1px solid rgba(255,255,255,0.05);">
    <form method="post">
        <input type="hidden" name="save_schedule" value="1">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; opacity: 0.3; font-size: 11px; text-transform: uppercase;">
                    <th style="padding-bottom: 15px;">Название филиала</th>
                    <th style="padding-bottom: 15px; text-align: right;">Официальное время открытия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $b): ?>
                <tr style="border-top: 1px solid rgba(255,255,255,0.05);">
                    <td style="padding: 20px 0;">
                        <b style="font-size: 16px;"><?= htmlspecialchars($b['name']) ?></b>
                    </td>
                    <td style="padding: 20px 0; text-align: right;">
                        <input type="time" name="times[<?= $b['id'] ?>]" 
                               value="<?= $b['opening_time'] ? date('H:i', strtotime($b['opening_time'])) : '09:00' ?>" 
                               style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 10px; border-radius: 10px; font-family: inherit; font-size: 16px; width: 120px; text-align: center;">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <button type="submit" class="btn" style="width: 100%; margin-top: 25px; height: 50px; justify-content: center;">
            💾 Сохранить настройки графиков
        </button>
    </form>
</div>
