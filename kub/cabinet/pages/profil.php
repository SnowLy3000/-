<?php
if (!isset($_SESSION['user'])) {
    header('Location: /public/index.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];

$stmt = $pdo->prepare("
    SELECT 
        u.phone,
        u.fullname,
        u.telegram_username,
        u.gender,
        u.status,
        b.title AS branch_title
    FROM users u
    LEFT JOIN branches b ON b.id = u.branch_id
    WHERE u.id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo '<div class="card">Профиль не найден</div>';
    return;
}

function badge_status(string $status): string {
    return match ($status) {
        'active'  => '<span class="badge green">Активен</span>',
        'pending' => '<span class="badge orange">Ожидает подтверждения</span>',
        'blocked' => '<span class="badge red">Заблокирован</span>',
        default   => ''
    };
}
?>

<h1>👤 Профиль</h1>

<div class="card">
    <div class="profile-grid">

        <div class="profile-field">
            <label>ФИО</label>
            <input value="<?= htmlspecialchars($user['fullname']) ?>" disabled>
        </div>

        <div class="profile-field">
            <label>Телефон</label>
            <input value="<?= htmlspecialchars($user['phone']) ?>" disabled>
        </div>

        <div class="profile-field">
            <label>Telegram</label>
            <input value="<?= htmlspecialchars($user['telegram_username'] ?: '—') ?>" disabled>
        </div>

        <div class="profile-field">
            <label>Пол</label>
            <input value="<?= $user['gender']==='male'?'Мужской':'Женский' ?>" disabled>
        </div>

        <div class="profile-field">
            <label>Филиал</label>
            <input value="<?= htmlspecialchars($user['branch_title'] ?: 'Не назначен') ?>" disabled>
        </div>

        <div class="profile-field">
            <label>Статус</label>
            <?= badge_status($user['status']) ?>
        </div>

    </div>
</div>