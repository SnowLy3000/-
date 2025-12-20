<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/perms.php';

require_admin();
require_permission('DELETE_APPROVE');

/* =====================
   🧠 ОБРАБОТКА РЕШЕНИЙ
===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id && in_array($action, ['approve', 'reject'], true)) {

        // Получаем запрос
        $stmt = $pdo->prepare("
            SELECT *
            FROM delete_requests
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$id]);
        $req = $stmt->fetch();

        if ($req) {
            $pdo->beginTransaction();

            try {

                if ($action === 'approve') {

                    /* =====================
                       🗑 УДАЛЕНИЕ ОБЪЕКТА
                    ===================== */
                    if ($req['entity_type'] === 'branch') {
                        // МЯГКОЕ удаление (рекомендовано)
                        $pdo->prepare("
                            UPDATE branches
                            SET active = 0
                            WHERE id = ?
                        ")->execute([$req['entity_id']]);
                    }

                    // если в будущем будут другие entity:
                    // elseif ($req['entity_type'] === 'user') { ... }

                }

                /* =====================
                   ✅ ОБНОВЛЯЕМ ЗАЯВКУ
                ===================== */
                $pdo->prepare("
                    UPDATE delete_requests
                    SET status = ?,
                        decided_by = ?,
                        decided_at = NOW()
                    WHERE id = ?
                ")->execute([
                    $action === 'approve' ? 'approved' : 'rejected',
                    $_SESSION['user']['id'],
                    $id
                ]);

                $pdo->commit();

            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
}

/* =====================
   📋 АКТИВНЫЕ ЗАПРОСЫ
===================== */
$requests = $pdo->query("
    SELECT 
        dr.*,
        COALESCE(u.fullname, '—') AS requested_by_name
    FROM delete_requests dr
    LEFT JOIN users u ON u.id = dr.requested_by
    WHERE dr.status = 'pending'
    ORDER BY dr.requested_at
")->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Запросы на удаление</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <a href="/admin/delete_requests.php"><b>🗑 Запросы на удаление</b></a>
    <a href="/admin/logout.php">Выйти</a>
</aside>

<main class="admin-main">

<h1>🗑 Запросы на удаление</h1>

<?php if (!$requests): ?>
    <p style="opacity:.6">Нет активных запросов.</p>
<?php endif; ?>

<?php foreach ($requests as $r): ?>
    <div class="card neon" style="margin-bottom:12px;">
        <b><?= htmlspecialchars((string)$r['entity_type']) ?></b>

        <div>ID: <?= (int)$r['entity_id'] ?></div>
        <div>Запросил: <?= htmlspecialchars((string)$r['requested_by_name']) ?></div>
        <div>Истекает: <?= htmlspecialchars((string)($r['expires_at'] ?? '—')) ?></div>

        <form method="post" style="margin-top:12px;display:flex;gap:10px;">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button name="action" value="approve" class="btn">✅ Подтвердить</button>
            <button name="action" value="reject"  class="btn">❌ Отклонить</button>
        </form>
    </div>
<?php endforeach; ?>

</main>
</div>

</body>
</html>