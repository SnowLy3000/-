<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/db.php';

require_admin();

/* =========================
   📊 БАЗА ЗНАНИЙ — СВОДКА
========================= */
$totalKnowledge = (int)$pdo->query("
    SELECT COUNT(*) FROM subthemes
")->fetchColumn();

$atRiskPreview = [];
$totalEmployees = 0;
$doneEmployees = 0;
$inProgressEmployees = 0;
$riskEmployees = 0;

if ($totalKnowledge > 0) {
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.username,
            u.created_at,
            COALESCE(b.title, '—') AS branch,
            COUNT(DISTINCT kv.subtheme_id) AS read_cnt
        FROM users u
        LEFT JOIN branches b ON b.id = u.branch_id
        LEFT JOIN knowledge_views kv ON kv.user_id = u.id
        WHERE u.role NOT IN ('owner','admin')
        GROUP BY u.id
    ");

    foreach ($stmt->fetchAll() as $r) {
        $totalEmployees++;
        $read = (int)$r['read_cnt'];

        if ($read >= $totalKnowledge) {
            $doneEmployees++;
        } else {
            $days = (time() - strtotime($r['created_at'])) / 86400;
            if ($days >= 3) {
                $riskEmployees++;
                if (count($atRiskPreview) < 5) {
                    $atRiskPreview[] = [
                        'username' => $r['username'],
                        'branch'   => $r['branch'],
                        'read'     => $read
                    ];
                }
            } else {
                $inProgressEmployees++;
            }
        }
    }
}

/* =========================
   🟢 ОТМЕТКИ / ОПОЗДАНИЯ СЕГОДНЯ
========================= */
$stmt = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(late_minutes > 0) AS late
    FROM work_checkins
    WHERE work_date = CURDATE()
");
$todayStats = $stmt->fetch();

$checkedToday = (int)($todayStats['total'] ?? 0);
$lateToday    = (int)($todayStats['late'] ?? 0);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Admin Dashboard</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<style>
.notice{padding:14px;border-radius:12px;margin-bottom:20px}
.notice-ok{border:1px solid rgba(120,255,160,.35);background:rgba(120,255,160,.08)}
.notice-warn{border:1px solid rgba(255,120,120,.35);background:rgba(255,120,120,.08)}
.notice-title{font-weight:700;margin-bottom:6px}
.notice-small{opacity:.8;font-size:13px}

.menu-alert{
    animation:pulse 1.4s infinite;
    color:#ff5555 !important;
    font-weight:bold;
}
.badge-count{margin-left:4px}
@keyframes pulse{
    0%{opacity:1}
    50%{opacity:.5}
    100%{opacity:1}
}
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <b style="display:block;margin-bottom:15px;">ADMIN PANEL</b>

    <a href="/admin/dashboard.php"><b>Dashboard</b></a>

    <?php if (user_has('USER_APPROVE')): ?>
        <a href="/admin/users.php">👥 Сотрудники</a>
        <a href="/admin/users_pending.php">👥 Пользователи (ожидают)</a>
    <?php endif; ?>

    <?php if (user_has('TEST_MANAGE')): ?>
        <a href="/admin/themes.php">Темы</a>
        <a href="/admin/subthemes.php">Подтемы</a>
        <a href="/admin/questions.php">Тестовые вопросы</a>
        <a href="/admin/tests.php">Тесты</a>
        <a href="/admin/surveys.php">Анкеты</a>
        <a href="/admin/stats.php">📊 Статистика</a>
        <a href="/admin/knowledge_stats.php">📚 База знаний</a>

        <a class="btn" href="/admin/export_users_surveys.php">
            📤 Экспорт анкет (CSV)
        </a>
        <a class="btn" href="/admin/export_knowledge.php">
            📤 Экспорт базы знаний (CSV)
        </a>
    <?php endif; ?>

    <?php if (user_has('BRANCH_MANAGE')): ?>
        <a href="/admin/branches.php">🏬 Филиалы</a>
    <?php endif; ?>

    <?php if (user_has('SCHEDULE_MANAGE')): ?>
        <a href="/admin/schedule.php">📅 График работы</a>
    <?php endif; ?>

    <?php if (user_has('CHECKIN_VIEW')): ?>
        <a href="/admin/attendance.php">🟢 Отметки</a>
    <?php endif; ?>

    <?php if (user_has('LATE_VIEW')): ?>
        <a href="/admin/late_stats.php"
           class="<?= $lateToday > 0 ? 'menu-alert' : '' ?>">
            ⏰ Опоздания
            <?php if ($lateToday > 0): ?>
                <span class="badge-count">(<?= $lateToday ?>)</span>
            <?php endif; ?>
        </a>
    <?php endif; ?>

    <?php if (user_has('LATE_MANAGE') || user_has('PENALTY_MANAGE')): ?>
        <a href="/admin/attendance_settings.php">⚙️ Настройки отметок</a>
    <?php endif; ?>

    <?php if (($_SESSION['user']['role'] ?? '') === 'owner'): ?>
        <a href="/admin/admins.php">Администраторы</a>
    <?php endif; ?>

    <?php if (user_has('DELETE_APPROVE')): ?>
        <a href="/admin/delete_requests.php">🗑 Запросы на удаление</a>
    <?php endif; ?>

    <hr style="opacity:.2">
    <a href="/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>Панель управления</h1>

<?php if ($checkedToday > 0): ?>
<div class="notice notice-ok neon">
    <div class="notice-title">🟢 Сегодня отметились</div>
    <div class="notice-small">
        <?= $checkedToday ?> сотрудников
        <?php if ($lateToday > 0): ?>
            , ⏰ опоздали: <b><?= $lateToday ?></b>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($totalKnowledge > 0): ?>
<div class="card neon">
    <h3>📊 Сводка по базе знаний</h3>
    <ul style="line-height:1.8;margin-top:10px;">
        <li>👥 Всего сотрудников: <b><?= $totalEmployees ?></b></li>
        <li>✅ Выполнили всё: <b><?= $doneEmployees ?></b></li>
        <li>🕓 В процессе: <b><?= $inProgressEmployees ?></b></li>
        <li>⚠️ В зоне риска: <b><?= $riskEmployees ?></b></li>
    </ul>
    <div style="opacity:.7;font-size:13px;">* правило 3 дней</div>
</div>
<?php endif; ?>

</main>
</div>

</body>
</html>