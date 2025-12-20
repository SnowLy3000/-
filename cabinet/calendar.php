<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();
date_default_timezone_set('Europe/Chisinau');

$userId = (int)($_SESSION['user']['id'] ?? 0);
if ($userId <= 0) {
    http_response_code(403);
    exit('Access denied');
}

/* =========================
   helpers
========================= */
function column_exists(PDO $pdo, string $table, string $col): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$col]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function ru_month_year(int $year, int $month): string {
    $months = [
        1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',5=>'Май',6=>'Июнь',
        7=>'Июль',8=>'Август',9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь'
    ];
    $m = $months[$month] ?? 'Месяц';
    return $m . ' ' . $year;
}

/* =========================
   month params
========================= */
$year  = (int)($_GET['year'] ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$daysInMonth   = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstWeekDayN = (int)date('N', strtotime(sprintf('%04d-%02d-01', $year, $month))); // 1=Mon..7=Sun

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

/* =========================
   columns availability
========================= */
$hasBranchColor = column_exists($pdo, 'branches', 'color');
$hasBranchRate  = column_exists($pdo, 'branches', 'rate'); // если нет — просто будет 0

/* =========================
   user info (чтобы не было "Сотрудник")
========================= */
$stmt = $pdo->prepare("SELECT fullname, username, phone FROM users WHERE id=? LIMIT 1");
$stmt->execute([$userId]);
$u = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$fullName = trim((string)($u['fullname'] ?? ''));
if ($fullName === '') {
    $fullName = trim((string)($u['username'] ?? ''));
}
if ($fullName === '') {
    $fullName = 'Сотрудник';
}

/* =========================
   schedule for month
========================= */
$selectRate  = $hasBranchRate  ? "b.rate AS rate" : "0 AS rate";
$selectColor = $hasBranchColor ? "b.color AS color" : "'#2C3E50' AS color";

$stmt = $pdo->prepare("
    SELECT
        ws.work_date,
        ws.branch_id,
        b.title AS branch_title,
        $selectColor,
        $selectRate
    FROM work_schedule ws
    JOIN branches b ON b.id = ws.branch_id
    WHERE ws.user_id = ?
      AND YEAR(ws.work_date) = ?
      AND MONTH(ws.work_date) = ?
    ORDER BY ws.work_date ASC
");
$stmt->execute([$userId, $year, $month]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* map by date */
$byDate = [];  // 'YYYY-mm-dd' => [ {branch_id, title, color, rate}, ...]
foreach ($rows as $r) {
    $d = (string)$r['work_date'];
    if (!isset($byDate[$d])) $byDate[$d] = [];
    $byDate[$d][] = [
        'branch_id' => (int)$r['branch_id'],
        'title'     => (string)$r['branch_title'],
        'color'     => (string)($r['color'] ?? '#2C3E50'),
        'rate'      => (int)($r['rate'] ?? 0),
    ];
}

/* =========================
   stats
========================= */
$totalShifts = 0;
$doneShifts  = 0; // прошедшие дни (строго < today)
$leftShifts  = 0; // будущие + сегодня (>= today)

$earned = 0;
$forecastTotal = 0;

/* per-branch breakdown */
$perBranch = []; // branch_id => ['title'=>..., 'color'=>..., 'rate'=>..., 'total'=>..., 'done'=>..., 'left'=>...]

for ($day=1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $items = $byDate[$date] ?? [];
    foreach ($items as $it) {
        $totalShifts++;
        $forecastTotal += (int)$it['rate'];

        $bid = (int)$it['branch_id'];
        if (!isset($perBranch[$bid])) {
            $perBranch[$bid] = [
                'title' => $it['title'],
                'color' => $it['color'],
                'rate'  => (int)$it['rate'],
                'total' => 0,
                'done'  => 0,
                'left'  => 0,
            ];
        }
        $perBranch[$bid]['total']++;

        if ($date < $today) {
            $doneShifts++;
            $earned += (int)$it['rate'];
            $perBranch[$bid]['done']++;
        } else {
            $leftShifts++;
            $perBranch[$bid]['left']++;
        }
    }
}

/* tomorrow notification */
$tomorrowItems = $byDate[$tomorrow] ?? [];

/* =========================
   render helpers (weekdays)
========================= */
$weekDays = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Мой график</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">

<style>
/* ===== layout ===== */
.wrap {
    max-width: 1100px;
    margin: 20px auto;
    padding: 0 14px;
}

.topbar {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:14px;
}

.titleline {
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
}
.user {
    font-weight:800;
    font-size:18px;
}
.small-muted {opacity:.75; font-size:13px;}

.nav {
    display:flex;
    align-items:center;
    gap:10px;
}
.btn-nav {
    display:inline-block;
    padding:10px 12px;
    border-radius:10px;
    text-decoration:none;
    background:rgba(255,255,255,.08);
    border:1px solid rgba(255,255,255,.12);
    color:#fff;
}
.btn-nav:hover {transform: translateY(-1px);}

.month {
    font-weight:800;
    letter-spacing:.2px;
    min-width: 160px;
    text-align:center;
}

.card {
    border-radius:14px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.10);
    padding: 14px;
    margin-top: 14px;
}

.toast {
    border-radius:14px;
    padding: 14px;
    border:1px solid rgba(120, 200, 255, .35);
    background: rgba(120, 200, 255, .10);
    margin-bottom: 14px;
    position: relative;
    overflow:hidden;
}
.toast strong {display:block; margin-bottom:4px;}
.toast .x {
    position:absolute;
    right:10px; top:10px;
    cursor:pointer;
    opacity:.7;
    user-select:none;
}
.toast .x:hover {opacity:1;}
.toastGlow {
    position:absolute;
    inset:-50px;
    background: radial-gradient(circle at 20% 20%, rgba(120,200,255,.18), transparent 55%);
    filter: blur(2px);
    pointer-events:none;
    animation: floatGlow 6s ease-in-out infinite;
}
@keyframes floatGlow {
    0% {transform: translate(0,0);}
    50% {transform: translate(20px,10px);}
    100% {transform: translate(0,0);}
}

/* ===== calendar ===== */
.calendar {
    display:grid;
    grid-template-columns: repeat(7, 1fr);
    gap:10px;
    margin-top: 10px;
}

.wd {
    text-align:center;
    opacity:.75;
    font-weight:700;
}

.day {
    background:#fff;
    color:#111;
    border-radius:14px;
    min-height: 96px;
    padding: 10px 10px 12px;
    position:relative;
    box-shadow: 0 8px 20px rgba(0,0,0,.18);
    overflow:hidden;
    transition: transform .15s ease, box-shadow .15s ease;
}

.day:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 24px rgba(0,0,0,.24);
}

.day.past {
    background: #f1f1f1;
    color:#666;
    box-shadow: none;
}
.day.today {
    outline: 2px solid rgba(120, 200, 255, .8);
    animation: todayPulse 2.2s ease-in-out infinite;
}
@keyframes todayPulse {
    0%   {outline-color: rgba(120, 200, 255, .35);}
    50%  {outline-color: rgba(120, 200, 255, .95);}
    100% {outline-color: rgba(120, 200, 255, .35);}
}

.daynum {
    font-weight:900;
    font-size:14px;
    opacity:.8;
}

.badgeShift {
    margin-top: 8px;
    display:flex;
    flex-direction:column;
    gap:6px;
}

.shiftChip {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    border-radius:12px;
    padding:8px 10px;
    color:#fff;
    font-weight:700;
    font-size:12px;
    background: #2C3E50;
    position: relative;
}
.shiftChip .branch {
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    max-width: 100%;
}
.shiftChip .dot {
    width:10px;
    height:10px;
    border-radius:99px;
    background: rgba(255,255,255,.9);
    flex: 0 0 auto;
    box-shadow: 0 0 0 2px rgba(255,255,255,.25);
}

/* subtle animation for future shift chips */
.day:not(.past) .shiftChip {
    animation: chipFadeIn .25s ease;
}
@keyframes chipFadeIn {
    from {opacity:0; transform: translateY(4px);}
    to   {opacity:1; transform: translateY(0);}
}

/* ===== summary below calendar ===== */
.statsRow {
    display:grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap:10px;
}
.statBox {
    border-radius:14px;
    padding:12px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.10);
}
.statBox .k {opacity:.75; font-size:12px;}
.statBox .v {font-size:20px; font-weight:900; margin-top:3px;}

.progressBar {
    height: 12px;
    background: rgba(255,255,255,.10);
    border-radius: 999px;
    overflow:hidden;
    margin-top: 10px;
}
.progressBar > div {
    height:100%;
    width:0%;
    background: linear-gradient(90deg, rgba(120,200,255,.85), rgba(180,255,200,.85));
    transition: width .35s ease;
}

.branchList {
    display:flex;
    flex-direction:column;
    gap:10px;
    margin-top: 12px;
}
.branchItem {
    border-radius:14px;
    padding:12px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.10);
}
.branchHead {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom: 8px;
}
.branchTitle {
    font-weight:900;
    display:flex;
    align-items:center;
    gap:8px;
}
.branchColorDot {
    width:12px; height:12px;
    border-radius:99px;
    background:#2C3E50;
    box-shadow: 0 0 0 2px rgba(255,255,255,.18);
}
.branchMeta {opacity:.8; font-size:12px;}
.branchRow {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    opacity:.9;
    font-size:13px;
}

/* ===== mobile ===== */
@media (max-width: 820px) {
    .month {min-width: 120px;}
    .calendar {gap:8px;}
    .day {min-height: 84px; padding: 9px;}
    .statsRow {grid-template-columns: repeat(2, minmax(0, 1fr));}
}
@media (max-width: 520px) {
    .wrap {padding: 0 10px;}
    .user {font-size:16px;}
    .btn-nav {padding:9px 10px;}
    .wd {font-size:12px;}
    .day {border-radius:12px; min-height: 78px;}
    .shiftChip {padding:7px 9px; font-size:12px;}
}
</style>
</head>
<body>

<div class="wrap">

  <div class="topbar">
    <div class="titleline">
      <div class="user"><?= htmlspecialchars($fullName) ?></div>
      <div class="small-muted">Мой календарь смен</div>
    </div>

    <div class="nav">
      <?php
        $prevY = $year; $prevM = $month - 1;
        if ($prevM < 1) { $prevM = 12; $prevY--; }

        $nextY = $year; $nextM = $month + 1;
        if ($nextM > 12) { $nextM = 1; $nextY++; }
      ?>
      <a class="btn-nav" href="?year=<?= $prevY ?>&month=<?= $prevM ?>">←</a>
      <div class="month"><?= htmlspecialchars(ru_month_year($year, $month)) ?></div>
      <a class="btn-nav" href="?year=<?= $nextY ?>&month=<?= $nextM ?>">→</a>
      <a class="btn-nav" href="/cabinet/index.php" title="Назад в кабинет">⟵</a>
    </div>
  </div>

  <?php if (!empty($tomorrowItems)): ?>
    <div class="toast" id="tomorrowToast">
      <div class="toastGlow"></div>
      <div class="x" onclick="document.getElementById('tomorrowToast').style.display='none'">✖</div>
      <strong>🔔 Завтра смена</strong>
      <div class="small-muted">
        <?= htmlspecialchars(date('d.m.Y', strtotime($tomorrow))) ?> —
        <?php
          $names = array_map(fn($x)=>$x['title'], $tomorrowItems);
          echo htmlspecialchars(implode(' / ', $names));
        ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="calendar">

      <?php foreach ($weekDays as $wd): ?>
        <div class="wd"><?= $wd ?></div>
      <?php endforeach; ?>

      <?php
        for ($i=1; $i<$firstWeekDayN; $i++) echo '<div></div>';

        for ($day=1; $day <= $daysInMonth; $day++):
          $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
          $isPast = ($date < $today);
          $isToday = ($date === $today);

          $items = $byDate[$date] ?? [];
      ?>
        <div class="day <?= $isPast?'past':'' ?> <?= $isToday?'today':'' ?>">
          <div class="daynum"><?= $day ?></div>

          <?php if (!empty($items)): ?>
            <div class="badgeShift">
              <?php foreach ($items as $it): ?>
                <div class="shiftChip" style="background: <?= htmlspecialchars($it['color']) ?>;">
                  <span class="branch" title="<?= htmlspecialchars($it['title']) ?>">
                    <?= htmlspecialchars($it['title']) ?>
                  </span>
                  <span class="dot" aria-hidden="true"></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

        </div>
      <?php endfor; ?>

    </div>
  </div>

  <!-- ===== summary UNDER calendar ===== -->
  <div class="card">
    <?php
      $progressPct = ($totalShifts > 0) ? (int)round(($doneShifts / $totalShifts) * 100) : 0;
      if ($progressPct < 0) $progressPct = 0;
      if ($progressPct > 100) $progressPct = 100;
    ?>
    <div style="font-weight:900;margin-bottom:10px;">Смены в этом месяце</div>

    <div class="statsRow">
      <div class="statBox">
        <div class="k">Общая смены</div>
        <div class="v"><?= (int)$totalShifts ?></div>
      </div>
      <div class="statBox">
        <div class="k">Выполнено смен</div>
        <div class="v"><?= (int)$doneShifts ?></div>
      </div>
      <div class="statBox">
        <div class="k">Осталось дней</div>
        <div class="v"><?= (int)$leftShifts ?></div>
      </div>
      <div class="statBox">
        <div class="k">Прогресс</div>
        <div class="v"><?= $progressPct ?>%</div>
      </div>
    </div>

    <div class="progressBar" aria-label="progress">
      <div id="pb"></div>
    </div>

    <div style="margin-top:14px;font-weight:900;">По филиалам</div>

    <?php if (empty($perBranch)): ?>
      <div class="small-muted" style="margin-top:8px;">В этом месяце смен пока нет.</div>
    <?php else: ?>
      <div class="branchList">
        <?php foreach ($perBranch as $pb): ?>
          <?php
            $rate = (int)($pb['rate'] ?? 0);
            $earnedB = $rate * (int)$pb['done'];
            $forecastB = $rate * (int)$pb['total'];
          ?>
          <div class="branchItem">
            <div class="branchHead">
              <div class="branchTitle">
                <span class="branchColorDot" style="background: <?= htmlspecialchars($pb['color']) ?>;"></span>
                <?= htmlspecialchars($pb['title']) ?>
              </div>
              <div class="branchMeta">
                <?php if ($hasBranchRate): ?>
                  💰 Ставка: <b><?= $rate ?></b> лей/смена
                <?php else: ?>
                  💰 Ставка: <b>0</b> лей/смена
                <?php endif; ?>
              </div>
            </div>

            <div class="branchRow">
              <div>📌 Смен: <b><?= (int)$pb['total'] ?></b></div>
              <div>✅ Прошло: <b><?= (int)$pb['done'] ?></b></div>
              <div>⏳ Осталось: <b><?= (int)$pb['left'] ?></b></div>
            </div>

            <div class="branchRow" style="margin-top:6px;">
              <div>💵 Заработано≈ <b><?= $earnedB ?></b> / <b><?= $forecastB ?></b> лей</div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

</div>

<script>
/* progress bar */
(function(){
  var pct = <?= (int)$progressPct ?>;
  var el = document.getElementById('pb');
  if(el) el.style.width = pct + '%';
})();
</script>

</body>
</html>