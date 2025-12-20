<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/perms.php';
require __DIR__ . '/../includes/db.php';

require_admin();
require_permission('SCHEDULE_MANAGE');
$load = [];

/* =====================
   📅 МЕСЯЦ
===================== */
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));
if ($month < 1 || $month > 12) $month = (int)date('m');

$dt = DateTime::createFromFormat('Y-n-j', $year.'-'.$month.'-1') ?: new DateTime('first day of this month');
$year  = (int)$dt->format('Y');
$month = (int)$dt->format('n');

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

/* =====================
   🏬 ФИЛИАЛЫ
===================== */
$branches = $pdo->query("
    SELECT id, title
    FROM branches
    WHERE active=1
    ORDER BY title
")->fetchAll();

$branchId = (int)($_GET['branch_id'] ?? 0);

/* =====================
   ⚙️ СКОЛЬКО ЛЮДЕЙ В ДЕНЬ (ручная настройка)
===================== */
$maxPerDay = (int)($_GET['max'] ?? 1);
if ($maxPerDay < 1) $maxPerDay = 1;
if ($maxPerDay > 10) $maxPerDay = 10;

/* =====================
   👥 СОТРУДНИКИ
===================== */
$users = [];
if ($branchId) {
    $users = $pdo->query("
        SELECT id, fullname
        FROM users
        WHERE role='employee' AND status='active'
        ORDER BY fullname
    ")->fetchAll();
}

/* =====================
   📅 ГРАФИК НА МЕСЯЦ (все филиалы, чтобы ловить конфликты)
===================== */
$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

$stmt = $pdo->prepare("
    SELECT ws.id, ws.branch_id, ws.user_id, ws.work_date,
           COALESCE(u.fullname, u.username, CONCAT('ID#', u.id)) AS uname,
           COALESCE(b.title, '—') AS bname
    FROM work_schedule ws
    JOIN users u     ON u.id = ws.user_id
    JOIN branches b  ON b.id = ws.branch_id
    WHERE ws.work_date BETWEEN ? AND ?
");
$stmt->execute([$monthStart, $monthEnd]);
$rows = $stmt->fetchAll();

/**
 * $byDateAll[date] = [ ['user_id'=>..,'branch_id'=>..,'uname'=>..,'bname'=>..], ... ]
 * $byDateBranch[date] = assignments ONLY for selected branch
 */
$byDateAll = [];
$byDateBranch = [];
$branchUserCounts = []; // user_id => shifts count in selected branch

foreach ($rows as $r) {
    $d = $r['work_date'];
    $byDateAll[$d][] = $r;

    if ($branchId && (int)$r['branch_id'] === $branchId) {
        $byDateBranch[$d][] = $r;
        $uid = (int)$r['user_id'];
        $branchUserCounts[$uid] = ($branchUserCounts[$uid] ?? 0) + 1;
    }
}

/* =====================
   📆 ДНИ НЕДЕЛИ (ПН–ВС)
===================== */
$weekDays = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];

/* =====================
   RU название месяца (strftime deprecated)
===================== */
$ruMonths = [
    1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',
    7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь'
];
$monthTitle = ($ruMonths[$month] ?? $dt->format('F')) . ' ' . $year;

/* prev/next */
$prev = (clone $dt)->modify('-1 month');
$next = (clone $dt)->modify('+1 month');

/* =====================
   📊 ЗАГРУЖЕННОСТЬ СОТРУДНИКОВ (по всем филиалам)
===================== */
$load = [];

$stmt = $pdo->prepare("
    SELECT 
        u.fullname,
        COUNT(ws.id) AS shifts_cnt
    FROM users u
    LEFT JOIN work_schedule ws
        ON ws.user_id = u.id
       AND ws.work_date BETWEEN ? AND ?
    WHERE u.role='employee'
      AND u.status='active'
    GROUP BY u.id, u.fullname
    HAVING shifts_cnt > 0
");
$stmt->execute([$monthStart, $monthEnd]);
$loadRows = $stmt->fetchAll();

foreach ($loadRows as $r) {
    $cnt = (int)$r['shifts_cnt'];
    $load[$cnt][] = $r['fullname'];
}
krsort($load);



?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>График работы</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">
<link rel="stylesheet" href="/assets/css/admin.css">

<style>
.top-row{
    display:flex; gap:10px; align-items:center; flex-wrap:wrap;
    margin-bottom:12px;
}
.calendar{
    display:grid;
    grid-template-columns:repeat(7,1fr);
    gap:10px;
    margin-top:14px;
}
.wd{
    text-align:center;
    opacity:.7;
    font-weight:600;
}
.day{
    background:#fff;
    color:#000;
    border-radius:14px;
    padding:10px 10px 12px;
    min-height:110px;
    box-shadow:0 6px 18px rgba(0,0,0,.18);
    position:relative;
}
.day-number{
    font-weight:800;
    font-size:14px;
    opacity:.75;
}
.assigned{
    margin-top:8px;
    display:flex;
    flex-direction:column;
    gap:6px;
}
.pill{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    background:rgba(0,0,0,.06);
    border:1px solid rgba(0,0,0,.08);
    padding:6px 8px;
    border-radius:999px;
    font-size:13px;
}
.pill b{font-weight:700}
.delbtn{
    border:0;
    background:rgba(255,0,0,.10);
    color:#b30000;
    border-radius:999px;
    padding:4px 8px;
    cursor:pointer;
    font-weight:800;
}
.delbtn:hover{background:rgba(255,0,0,.18)}
.addsel{
    width:100%;
    margin-top:8px;
    border-radius:10px;
}
.blocked{
    background:#111 !important;
    color:#777 !important;
    box-shadow:none;
}
.blocked .pill{background:rgba(255,255,255,.04); border-color:rgba(255,255,255,.08)}
.blocked .delbtn{opacity:.6}
.fulltag{
    position:absolute;
    top:10px; right:10px;
    font-size:11px;
    padding:4px 8px;
    border-radius:999px;
    background:#111;
    color:#fff;
    opacity:.8;
}
.hint{
    margin-top:16px;
    opacity:.75;
    font-size:13px;
    line-height:1.5;
}
.rank{
    margin-top:22px;
}
.rank .row{
    display:flex; gap:10px; flex-wrap:wrap;
    margin:8px 0;
}
.rank .count{
    min-width:88px;
    font-weight:900;
    opacity:.85;
}
.rank .names{
    opacity:.95;
}
.small{
    opacity:.7;
    font-size:13px;
}
</style>
</head>
<body>

<div class="admin-wrap">

<aside class="admin-menu neon">
    <a href="/admin/dashboard.php">← Dashboard</a>
    <b style="margin-top:10px;display:block">📅 График работы</b>
</aside>

<main class="admin-main">

<h1>📅 График работы</h1>

<form method="get" class="top-row">
    <select name="branch_id" onchange="this.form.submit()">
        <option value="">— Выбери филиал —</option>
        <?php foreach ($branches as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= (int)$b['id']===$branchId?'selected':'' ?>>
                <?= htmlspecialchars($b['title']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label class="small">
        Сколько людей в день:
        <input type="number" name="max" value="<?= (int)$maxPerDay ?>" min="1" max="10" style="width:80px;">
    </label>

    <input type="hidden" name="year" value="<?= (int)$year ?>">
    <input type="hidden" name="month" value="<?= (int)$month ?>">

    <a class="btn" href="?branch_id=<?= $branchId ?>&max=<?= $maxPerDay ?>&year=<?= (int)$prev->format('Y') ?>&month=<?= (int)$prev->format('n') ?>">←</a>
    <b style="min-width:160px;text-align:center"><?= htmlspecialchars($monthTitle) ?></b>
    <a class="btn" href="?branch_id=<?= $branchId ?>&max=<?= $maxPerDay ?>&year=<?= (int)$next->format('Y') ?>&month=<?= (int)$next->format('n') ?>">→</a>
</form>

<?php if (!$branchId): ?>
    <div class="card neon" style="margin-top:14px;">
        Выбери филиал — и календарь станет активным.
    </div>
<?php endif; ?>

<div class="calendar">
    <?php foreach ($weekDays as $wd): ?>
        <div class="wd"><?= $wd ?></div>
    <?php endforeach; ?>

    <?php
    $firstWeekDay = (int)date('N', strtotime(sprintf('%04d-%02d-01', $year, $month)));
    for ($i=1; $i<$firstWeekDay; $i++) echo '<div></div>';

    for ($day=1; $day<=$daysInMonth; $day++):
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

        $all = $byDateAll[$date] ?? [];
        $mine = $byDateBranch[$date] ?? [];

        // если в этот день есть записи, но не в выбранном филиале — день блокируем (чёрный),
        // потому что ты сейчас редактируешь КОНКРЕТНЫЙ филиал, а сотрудники в другом филиале в этот день заняты
        // (добавлять всё равно можно, но конфликт поймает schedule_day.php; блок делаем “визуально”)
        $hasOtherBranch = false;
        if ($branchId) {
            foreach ($all as $a) {
                if ((int)$a['branch_id'] !== $branchId) { $hasOtherBranch = true; break; }
            }
        }

        // “полный” = в выбранном филиале достигли лимита
        $isFull = $branchId ? (count($mine) >= $maxPerDay) : false;

        $cls = [];
        if ($hasOtherBranch && $branchId && count($mine)===0) $cls[] = 'blocked';
        ?>
        <div class="day <?= implode(' ', $cls) ?>">
            <div class="day-number"><?= $day ?></div>

            <?php if ($branchId && $isFull): ?>
                <div class="fulltag">полный</div>
            <?php endif; ?>

            <?php if ($branchId): ?>
                <div class="assigned">
                    <?php foreach ($mine as $m): ?>
                        <div class="pill">
                            <b><?= htmlspecialchars($m['uname']) ?></b>
                            <button class="delbtn"
                                data-action="delete"
                                data-date="<?= htmlspecialchars($date) ?>"
                                data-branch="<?= (int)$branchId ?>"
                                data-user="<?= (int)$m['user_id'] ?>"
                                type="button"
                                title="Удалить смену">✖</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!$isFull): ?>
                    <select class="addsel" data-action="add" data-date="<?= htmlspecialchars($date) ?>">
                        <option value="">+ Добавить сотрудника…</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['fullname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endfor; ?>
</div>

<div class="card neon" style="margin-top:30px;">
    <h3>📊 Загруженность сотрудников (<?= sprintf('%02d.%d', $month, $year) ?>)</h3>

    <?php if (!$load): ?>
        <div style="opacity:.6">Нет данных</div>
    <?php else: ?>
        <?php foreach ($load as $cnt => $names):

            // 🎨 цвет по загруженности
            if ($cnt >= 20)      $color = '#ff4d4d';
            elseif ($cnt >= 15)  $color = '#ff9f1a';
            elseif ($cnt >= 10)  $color = '#f1c40f';
            else                 $color = '#2ecc71';

            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        ?>
            <div style="
                padding:10px 14px;
                border-radius:10px;
                margin-bottom:8px;
                background:rgba(0,0,0,.25);
                border-left:6px solid <?= $color ?>;
            ">
                <b style="color:<?= $color ?>">
                    <?= (int)$cnt ?> смен
                </b>
                —
                <?= htmlspecialchars(implode(', ', $names)) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($branchId): ?>
    <div class="rank">
    <h3 style="margin-top:18px;">👥 Загруженность сотрудников за месяц</h3>
    <div class="small">
        Одна строка — одинаковое количество смен
    </div>

    <?php
    $cntToNames = [];

    if ($branchUserCounts) {
        $ids = array_keys($branchUserCounts);
        $in  = implode(',', array_fill(0, count($ids), '?'));

        $stN = $pdo->prepare("SELECT id, fullname FROM users WHERE id IN ($in)");
        $stN->execute($ids);

        $nameMap = [];
        foreach ($stN->fetchAll() as $nr) {
            $nameMap[(int)$nr['id']] = $nr['fullname'];
        }

        foreach ($branchUserCounts as $uid => $cnt) {
            $cntToNames[(int)$cnt][] = $nameMap[$uid] ?? ('ID#'.$uid);
        }

        krsort($cntToNames);
    }
    ?>

    <?php if (!$cntToNames): ?>
        <div class="card neon" style="margin-top:10px;">
            Пока нет смен в этом месяце.
        </div>
    <?php else: ?>
        <?php foreach ($cntToNames as $cnt => $names):

            // 🎨 цвет по загруженности
            if ($cnt >= 20)      $color = '#ff4d4d';
            elseif ($cnt >= 15)  $color = '#ff9f1a';
            elseif ($cnt >= 10)  $color = '#f1c40f';
            else                 $color = '#2ecc71';

            sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        ?>
            <div class="row" style="
                padding:10px 14px;
                border-radius:12px;
                background:rgba(0,0,0,.25);
                border-left:6px solid <?= $color ?>;
                margin-bottom:8px;
            ">
                <div class="count" style="color:<?= $color ?>">
                    <?= (int)$cnt ?> смен
                </div>
                <div class="names">
                    <?= htmlspecialchars(implode(', ', $names)) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

    <div class="hint">
        <b>Подсказка:</b><br>
        • Добавление: выбери сотрудника в дне.<br>
        • Удаление: нажми <b>✖</b> возле имени (будет подтверждение).<br>
        • Если сотрудник уже занят в другой точке — сервер вернёт ошибку, и ты увидишь <b>alert</b>.
    </div>
<?php endif; ?>

</main>
</div>

<script>
function postSchedule(body){
    return fetch('/admin/schedule_day.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body
    }).then(async r => {
        if(!r.ok){
            const t = await r.text();
            throw new Error(t || ('HTTP ' + r.status));
        }
        return r.text();
    });
}

document.querySelectorAll('select[data-action="add"]').forEach(sel=>{
    sel.addEventListener('change', async ()=>{
        if(!sel.value) return;

        const date = encodeURIComponent(sel.dataset.date);
        const user = encodeURIComponent(sel.value);

        try{
            await postSchedule(
                'do=add' +
                '&date=' + date +
                '&user_id=' + user +
                '&branch_id=<?= (int)$branchId ?>' +
                '&max=<?= (int)$maxPerDay ?>'
            );
            location.reload();
        }catch(e){
            alert(e.message);
            sel.value = '';
        }
    });
});

document.querySelectorAll('button.delbtn').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
        if(!confirm('Удалить смену?')) return;

        try{
            await postSchedule(
                'do=delete' +
                '&date=' + encodeURIComponent(btn.dataset.date) +
                '&user_id=' + encodeURIComponent(btn.dataset.user) +
                '&branch_id=' + encodeURIComponent(btn.dataset.branch)
            );
            location.reload();
        }catch(e){
            alert(e.message);
        }
    });
});
</script>

</body>
</html>