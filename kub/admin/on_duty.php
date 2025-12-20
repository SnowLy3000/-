<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/db.php';

require_admin();
require_permission('SCHEDULE_VIEW');

/* =====================
   📅 ВЫБОР ДАТЫ
===================== */
$selectedDate = $_GET['date'] ?? date('Y-m-d');
try {
    $date = new DateTime($selectedDate);
} catch (Exception $e) {
    $date = new DateTime('today');
}
$dateStr = $date->format('Y-m-d');

/* =====================
   🔎 ФИЛЬТРЫ
===================== */
$branchFilter = (int)($_GET['branch_id'] ?? 0);
$q = trim($_GET['q'] ?? '');

/* =====================
   🏬 ФИЛИАЛЫ
===================== */
$branches = $pdo->query("
    SELECT id, title, phone
    FROM branches
    WHERE active = 1
    ORDER BY title
")->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   ☎️ ФУНКЦИЯ НОРМАЛИЗАЦИИ ТЕЛЕФОНА
===================== */
function normalizePhone($phone) {
    $digits = preg_replace('/\D+/', '', $phone);
    if (!$digits) return null;

    // если номер без кода страны → Молдова +373
    if (strlen($digits) === 8) {
        $digits = '373' . $digits;
    }

    return '+' . $digits;
}

/* =====================
   🟢 РАБОТАЮТ В ЭТОТ ДЕНЬ
===================== */
$sql = "
    SELECT 
        u.id,
        u.fullname,
        u.phone AS user_phone,
        b.title AS branch_title,
        b.phone AS branch_phone
    FROM work_schedule ws
    JOIN users u    ON u.id = ws.user_id
    JOIN branches b ON b.id = ws.branch_id
    WHERE ws.work_date = ?
      AND u.status = 'active'
";

$params = [$dateStr];

if ($branchFilter) {
    $sql .= " AND b.id = ? ";
    $params[] = $branchFilter;
}

if ($q !== '') {
    $sql .= " AND u.fullname LIKE ? ";
    $params[] = "%$q%";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$working = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   🔴 ВЫХОДНЫЕ
===================== */
$workingIds = array_column($working, 'id');

$sqlOff = "
    SELECT id, fullname, phone
    FROM users
    WHERE role = 'employee'
      AND status = 'active'
";

$paramsOff = [];

if ($workingIds) {
    $sqlOff .= " AND id NOT IN (" . implode(',', array_fill(0, count($workingIds), '?')) . ")";
    $paramsOff = $workingIds;
}

if ($q !== '') {
    $sqlOff .= " AND fullname LIKE ? ";
    $paramsOff[] = "%$q%";
}

$stmt = $pdo->prepare($sqlOff);
$stmt->execute($paramsOff);
$off = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   📤 ЭКСПОРТ CSV
===================== */
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=on_duty_'.$dateStr.'.csv');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Имя', 'Телефон', 'Филиал', 'Телефон филиала']);

    foreach ($working as $w) {
        fputcsv($out, [
            $w['fullname'],
            normalizePhone($w['user_phone']),
            $w['branch_title'],
            normalizePhone($w['branch_phone'])
        ]);
    }
    fclose($out);
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>Кто на смене</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<style>
.filters{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    margin-bottom:20px;
}
.person{
    padding:12px 0;
    border-bottom:1px solid rgba(255,255,255,.08);
}
.person:last-child{border-bottom:0}
.green{color:#2ecc71}
.red{color:#ff5f5f}
.small{opacity:.75;font-size:13px}
.links a{
    margin-right:10px;
    font-weight:600;
}
</style>
</head>

<body>
<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <b>🕒 Кто на смене</b>
</aside>

<main class="admin-main">

<h1>🕒 Кто на смене</h1>

<form class="filters" method="get">

    <input type="date"
           name="date"
           value="<?= htmlspecialchars($dateStr) ?>">

    <select name="branch_id">
        <option value="">Все филиалы</option>
        <?php foreach ($branches as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= $branchFilter===(int)$b['id']?'selected':'' ?>>
                <?= htmlspecialchars($b['title']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input name="q"
           placeholder="Поиск по имени"
           value="<?= htmlspecialchars($q) ?>">

    <button class="btn">Фильтр</button>

    <a class="btn"
       href="?export=1&date=<?= htmlspecialchars($dateStr) ?>&branch_id=<?= (int)$branchFilter ?>&q=<?= urlencode($q) ?>">
       📤 Экспорт
    </a>
</form>

<!-- 🟢 РАБОТАЮТ -->
<div class="card neon">
<h3 class="green">🟢 Работают (<?= count($working) ?>)</h3>

<?php foreach ($working as $w): 
    $userPhone = normalizePhone($w['user_phone']);
    $branchPhone = normalizePhone($w['branch_phone']);
?>
<div class="person">
    <b><?= htmlspecialchars($w['fullname']) ?></b><br>

    <?php if ($userPhone): ?>
    <div class="links">
        <a href="tel:<?= $userPhone ?>">📞 Позвонить</a>
        <a href="https://wa.me/<?= ltrim($userPhone,'+') ?>" target="_blank">WhatsApp</a>
        <a href="viber://chat?number=<?= $userPhone ?>">Viber</a>
        <a href="https://t.me/<?= ltrim($userPhone,'+') ?>" target="_blank">Telegram</a>
    </div>
    <?php else: ?>
        <div class="small">Телефон не указан</div>
    <?php endif; ?>

    <div class="small">
        📍 <?= htmlspecialchars($w['branch_title']) ?>
        <?php if ($branchPhone): ?>
            — 📞 <?= htmlspecialchars($branchPhone) ?>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if (!$working): ?>
    <div class="small">Никто не работает</div>
<?php endif; ?>
</div>

<!-- 🔴 ВЫХОДНЫЕ -->
<div class="card neon" style="margin-top:22px;">
<h3 class="red">🔴 Выходные (<?= count($off) ?>)</h3>

<?php foreach ($off as $o): ?>
<div class="person">
    <b><?= htmlspecialchars($o['fullname']) ?></b><br>
    <?php if ($o['phone']): ?>
        📞 <?= htmlspecialchars(normalizePhone($o['phone'])) ?>
    <?php else: ?>
        <span class="small">Телефон не указан</span>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if (!$off): ?>
    <div class="small">Все работают</div>
<?php endif; ?>
</div>

</main>
</div>
</body>
</html>