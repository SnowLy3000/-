<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_admin();

/**
 * =========================
 * ОБЩЕЕ КОЛ-ВО ИНСТРУКЦИЙ
 * =========================
 */
$totalKnowledge = (int)$pdo->query("
    SELECT COUNT(*) FROM subthemes
")->fetchColumn();

/**
 * =========================
 * КОМУ РАЗРЕШЁН АНТИ-ТОП
 * (через 3 дня после регистрации)
 * =========================
 */
$antiTopAllowedUsers = $pdo->query("
    SELECT id
    FROM users
    WHERE created_at <= DATE_SUB(NOW(), INTERVAL 3 DAY)
")->fetchAll(PDO::FETCH_COLUMN);

/**
 * =========================
 * ПОЛЬЗОВАТЕЛИ + ФИЛИАЛЫ
 * =========================
 */
$users = $pdo->query("
    SELECT 
        u.id,
        u.username,
        b.title AS branch
    FROM users u
    LEFT JOIN branches b ON b.id = u.branch_id
")->fetchAll();

$stats = [];

/**
 * =========================
 * СТАТИСТИКА ПО КАЖДОМУ
 * =========================
 */
foreach ($users as $u) {

    // сколько прочитал
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT subtheme_id)
        FROM knowledge_views
        WHERE user_id = ?
    ");
    $stmt->execute([$u['id']]);
    $read = (int)$stmt->fetchColumn();

    // процент (НИКОГДА > 100)
    $percent = $totalKnowledge > 0
        ? min(100, round(($read / $totalKnowledge) * 100))
        : 100;

    $stats[] = [
        'id'       => (int)$u['id'],
        'username' => (string)$u['username'],
        'branch'   => $u['branch'] ?: '—',
        'read'     => $read,
        'total'    => $totalKnowledge,
        'percent'  => $percent,
        'allowAnti'=> in_array($u['id'], $antiTopAllowedUsers, true)
    ];
}

/**
 * =========================
 * ТОП-5
 * =========================
 */
$top = $stats;
usort($top, fn($a, $b) => $b['percent'] <=> $a['percent']);
$top = array_slice($top, 0, 5);

/**
 * =========================
 * АНТИ-ТОП (если можно)
 * =========================
 */
$anti = array_filter($stats, function ($u) {
    return $u['allowAnti'] && $u['percent'] < 100;
});
usort($anti, fn($a, $b) => $a['percent'] <=> $b['percent']);
$anti = array_slice($anti, 0, 5);
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>🏆 Рейтинг сотрудников</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">

<style>
.grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:20px;
}
.row {
    padding:6px 0;
    border-bottom:1px solid rgba(255,255,255,.1);
}
.good { color:#8bc34a; }
.bad { color:#ff6666; }
.small { opacity:.7;font-size:13px; }
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/knowledge_rating.php"><b>🏆 Рейтинг сотрудников</b></a>
    <a href="/admin/knowledge_rating_branches.php">🏢 По филиалам</a>
    <a href="/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>🏆 Рейтинг сотрудников</h1>

<div class="grid">

<!-- ТОП -->
<div class="card neon">
    <h3>🏆 ТОП-5</h3>

    <?php if (!$top): ?>
        <p class="small">Нет данных</p>
    <?php endif; ?>

    <?php foreach ($top as $u): ?>
        <div class="row good">
            <?= htmlspecialchars($u['username']) ?>
            <span class="small">
                (<?= $u['percent'] ?>%, <?= htmlspecialchars($u['branch']) ?>)
            </span>
        </div>
    <?php endforeach; ?>
</div>

<!-- АНТИ-ТОП -->
<div class="card neon">
    <h3>⚠️ АНТИ-ТОП</h3>

    <?php if (!$anti): ?>
        <p class="small">
            Анти-топ появится через 3 дня после регистрации
            или все уже выполнили инструкции.
        </p>
    <?php endif; ?>

    <?php foreach ($anti as $u): ?>
        <div class="row bad">
            <?= htmlspecialchars($u['username']) ?>
            <span class="small">
                (<?= $u['percent'] ?>%, <?= htmlspecialchars($u['branch']) ?>)
            </span>
        </div>
    <?php endforeach; ?>
</div>

</div>

</main>
</div>

</body>
</html>