<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/db.php';

require_admin();

if (!user_has('LATE_MANAGE') && !user_has('PENALTY_MANAGE')) {
    http_response_code(403);
    exit('Нет доступа');
}

$message = null;

/* =====================
   💾 СОХРАНЕНИЕ
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $work_start           = trim($_POST['work_start'] ?? '09:00');
    $allowed_late_minutes = (int)($_POST['allowed_late_minutes'] ?? 0);
    $block_after_minutes  = (int)($_POST['block_after_minutes'] ?? 0);

    $enable_penalties     = isset($_POST['enable_penalties']) ? 1 : 0;
    $penalty_per_minute   = (float)($_POST['penalty_per_minute'] ?? 0);
    $max_penalty_per_day  = (float)($_POST['max_penalty_per_day'] ?? 0);

    $allow_manual         = isset($_POST['allow_manual']) ? 1 : 0;

    // нормализация времени
    if (preg_match('/^\d{2}:\d{2}$/', $work_start)) {
        $work_start .= ':00';
    }

    if ($allowed_late_minutes < 0 || $block_after_minutes < 0) {
        $message = 'Минуты не могут быть отрицательными';
    } elseif ($penalty_per_minute < 0 || $max_penalty_per_day < 0) {
        $message = 'Штрафы не могут быть отрицательными';
    } else {
        $pdo->prepare("
            UPDATE attendance_settings SET
                work_start = ?,
                allowed_late_minutes = ?,
                block_after_minutes = ?,
                enable_penalties = ?,
                penalty_per_minute = ?,
                max_penalty_per_day = ?,
                allow_manual = ?
            WHERE id = 1
        ")->execute([
            $work_start,
            $allowed_late_minutes,
            $block_after_minutes,
            $enable_penalties,
            $penalty_per_minute,
            $max_penalty_per_day,
            $allow_manual
        ]);

        $message = 'Настройки сохранены ✅';
    }
}

/* =====================
   ⚙️ ЗАГРУЗКА
===================== */
$settings = $pdo->query("
    SELECT * FROM attendance_settings WHERE id = 1
")->fetch();

$work_start_hm = substr($settings['work_start'] ?? '09:00:00', 0, 5);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Настройки отметок</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<style>
.form{max-width:640px}
.field{margin-bottom:18px}
label{display:block;margin-bottom:6px;opacity:.85}
input[type=time],
input[type=number]{width:100%}
.checkbox{display:flex;gap:10px;align-items:center}
.hint{font-size:13px;opacity:.65;margin-top:4px}
.success{padding:14px;border-radius:10px;background:rgba(120,255,160,.15);border:1px solid rgba(120,255,160,.35)}
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
</aside>

<main class="admin-main">

<h1>⚙️ Настройки отметок и опозданий</h1>

<?php if ($message): ?>
    <div class="success neon" style="margin-bottom:20px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<form method="post" class="form card neon">

<h3>⏰ Отметка</h3>

<div class="field">
    <label>Время начала смены</label>
    <input type="time" name="work_start" value="<?= htmlspecialchars($work_start_hm) ?>">
    <div class="hint">После этого времени считается опоздание</div>
</div>

<div class="field">
    <label>Допустимое опоздание (минут)</label>
    <input type="number" name="allowed_late_minutes" min="0"
           value="<?= (int)$settings['allowed_late_minutes'] ?>">
</div>

<div class="field">
    <label>Блокировать отметку через (минут)</label>
    <input type="number" name="block_after_minutes" min="0"
           value="<?= (int)$settings['block_after_minutes'] ?>">
    <div class="hint">После этого времени отметка будет запрещена</div>
</div>

<div class="field checkbox">
    <input type="checkbox" name="allow_manual"
        <?= !empty($settings['allow_manual']) ? 'checked' : '' ?>>
    <label>Можно отмечаться без смены в графике</label>
</div>

<h3 style="margin-top:30px;">💸 Штрафы</h3>

<div class="field checkbox">
    <input type="checkbox" name="enable_penalties"
        <?= !empty($settings['enable_penalties']) ? 'checked' : '' ?>>
    <label>Включить авто-штрафы</label>
</div>

<div class="field">
    <label>Штраф за 1 минуту (лей)</label>
    <input type="number" step="0.01" min="0"
           name="penalty_per_minute"
           value="<?= htmlspecialchars($settings['penalty_per_minute']) ?>">
</div>

<div class="field">
    <label>Максимальный штраф за день (лей)</label>
    <input type="number" step="0.01" min="0"
           name="max_penalty_per_day"
           value="<?= htmlspecialchars($settings['max_penalty_per_day']) ?>">
</div>

<button class="btn">💾 Сохранить</button>

</form>

</main>
</div>

</body>
</html>