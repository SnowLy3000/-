<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/notifications.php';

require_admin();
require_permission('TEST_MANAGE');

// =======================
// АКЦИИ
// =======================
$actions = $pdo->query("
    SELECT id, title
    FROM actions
    WHERE active = 1
    ORDER BY created_at DESC
")->fetchAll();

$actionId = (int)($_GET['action'] ?? 0);
$branchesStats = [];

// =======================
// СТАТИСТИКА ПО ФИЛИАЛАМ
// =======================
if ($actionId) {
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.title,
            COUNT(u.id) AS total,
            SUM(aus.status IS NULL) AS not_viewed,
            SUM(aus.status = 'viewed') AS viewed,
            SUM(aus.status = 'done') AS done
        FROM action_branches ab
        JOIN branches b ON b.id = ab.branch_id
        JOIN users u ON u.branch_id = b.id
            AND u.role = 'employee'
            AND u.status = 'active'
        LEFT JOIN action_user_status aus
            ON aus.action_id = ab.action_id
           AND aus.user_id = u.id
        WHERE ab.action_id = ?
        GROUP BY b.id
        ORDER BY b.title
    ");
    $stmt->execute([$actionId]);
    $branchesStats = $stmt->fetchAll();

    // =======================
    // 🔔 УВЕДОМЛЕНИЕ АДМИНУ
    // =======================
    $problemBranches = [];

    foreach ($branchesStats as $b) {
        if ($b['not_viewed'] > 0) {
            $problemBranches[] = $b['title'];
        }
    }

    if ($problemBranches) {

        // админы + owner
        $admins = $pdo->query("
            SELECT id
            FROM users
            WHERE role IN ('admin','owner')
              AND status='active'
        ")->fetchAll(PDO::FETCH_COLUMN);

        if ($admins) {

            // не дублируем уведомление
            $check = $pdo->prepare("
                SELECT COUNT(*)
                FROM notifications
                WHERE entity_type='action'
                  AND entity_id=?
                  AND title LIKE '🚨%'
            ");
            $check->execute([$actionId]);

            if ((int)$check->fetchColumn() === 0) {

                notif_create_bulk(
                    $pdo,
                    $admins,
                    '🚨 Проблема по акции',
                    'Есть филиалы без ознакомления: ' . implode(', ', $problemBranches),
                    '/admin/actions_stats.php?action=' . $actionId,
                    'action',
                    $actionId
                );
            }
        }
    }
}

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Статистика по акциям</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<style>
.branch-card {
    padding:15px;
    margin-bottom:12px;
    border-radius:8px;
}
.branch-red {
    background:#2a0f0f;
    border:2px solid #ff4444;
}
.branch-yellow {
    background:#2a230f;
    border:2px solid #ffcc00;
}
.branch-green {
    background:#0f2a1c;
    border:2px solid #44ff99;
}
.branch-card a {
    color:#fff;
    font-size:18px;
    text-decoration:none;
}
.branch-card a:hover {
    text-decoration:underline;
}
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/actions.php">📢 Акции и инструкции</a>
    <a href="/admin/actions_stats.php"><b>📊 Статистика</b></a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>📊 Статистика по акциям</h1>

<form method="get" class="card neon">
    <select name="action" required>
        <option value="">— Выберите акцию —</option>
        <?php foreach ($actions as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $a['id']===$actionId?'selected':'' ?>>
                <?= htmlspecialchars($a['title']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button class="btn">Показать</button>
</form>

<?php if ($branchesStats): ?>
    <h3 style="margin-top:25px;">Филиалы</h3>

    <?php foreach ($branchesStats as $b):

        if ($b['not_viewed'] > 0) {
            $cls = 'branch-red';
        } elseif ($b['done'] < $b['total']) {
            $cls = 'branch-yellow';
        } else {
            $cls = 'branch-green';
        }

    ?>
        <div class="branch-card <?= $cls ?>">
            <a href="/admin/actions_branch.php?action=<?= $actionId ?>&branch=<?= $b['id'] ?>">
                📍 <?= htmlspecialchars($b['title']) ?>
            </a>

            <div>Всего сотрудников: <?= $b['total'] ?></div>
            <div>❌ Не ознакомились: <?= $b['not_viewed'] ?></div>
            <div>👀 Ознакомились: <?= $b['viewed'] ?></div>
            <div>✔ Выполнили: <?= $b['done'] ?></div>
        </div>
    <?php endforeach; ?>

<?php elseif ($actionId): ?>
    <p style="margin-top:20px;">Нет данных по выбранной акции.</p>
<?php endif; ?>

</main>
</div>

</body>
</html>
