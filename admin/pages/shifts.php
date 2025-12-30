<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('manage_shifts');

/* ===== ПАРАМЕТРЫ ===== */
$y = (int)($_GET['y'] ?? date('Y'));
$m = (int)($_GET['m'] ?? date('n'));
$branchId = (int)($_GET['branch_id'] ?? 0);

if ($m < 1) $m = 1; if ($m > 12) $m = 12;

$monthStart = sprintf('%04d-%02d-01', $y, $m);
$monthEnd   = date('Y-m-t', strtotime($monthStart));
$monthTitle = (new DateTime($monthStart))->format('F Y');

/* ===== ДАННЫЕ ===== */
$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
$users = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status='active' ORDER BY last_name, first_name")->fetchAll();

$shiftsByDate = [];
if ($branchId > 0) {
    $stmt = $pdo->prepare("SELECT ws.id, ws.shift_date, u.first_name, u.last_name FROM work_shifts ws JOIN users u ON u.id = ws.user_id WHERE ws.branch_id = ? AND ws.shift_date BETWEEN ? AND ? ORDER BY u.last_name");
    $stmt->execute([$branchId, $monthStart, $monthEnd]);
    foreach ($stmt as $row) { $shiftsByDate[$row['shift_date']][] = $row; }
}

// Общая загруженность
$stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, COUNT(ws.id) AS cnt FROM users u LEFT JOIN work_shifts ws ON ws.user_id = u.id AND ws.shift_date BETWEEN ? AND ? WHERE u.status='active' GROUP BY u.id HAVING cnt > 0 ORDER BY cnt DESC");
$stmt->execute([$monthStart, $monthEnd]);
$overallLoad = $stmt->fetchAll();

$branchLoad = []; $branchName = '';
if ($branchId > 0) {
    $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, COUNT(ws.id) AS cnt FROM users u JOIN work_shifts ws ON ws.user_id = u.id WHERE ws.branch_id = ? AND ws.shift_date BETWEEN ? AND ? AND u.status='active' GROUP BY u.id HAVING cnt > 0 ORDER BY cnt DESC");
    $stmt->execute([$branchId, $monthStart, $monthEnd]);
    $branchLoad = $stmt->fetchAll();
    foreach ($branches as $b) { if ((int)$b['id'] === $branchId) { $branchName = $b['name']; break; } }
}

$firstDow = (int)date('N', strtotime($monthStart));
$daysInMonth = (int)date('t', strtotime($monthStart));
$padStart = $firstDow - 1;
$dowNames = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
?>

<style>
    .shift-calendar { width: 100%; border-collapse: separate; border-spacing: 6px; table-layout: fixed; }
    .shift-calendar th { padding: 10px; color: rgba(255,255,255,0.4); font-size: 11px; text-transform: uppercase; font-weight: 800; }
    
    .day-cell { 
        background: rgba(255,255,255,0.03); 
        border: 1px solid rgba(255,255,255,0.08); 
        border-radius: 12px; 
        padding: 8px; 
        min-height: 140px; /* Увеличили минимальную высоту */
        height: auto; 
        vertical-align: top; 
        transition: 0.2s;
    }
    .day-cell.weekend { background: rgba(0,0,0,0.1); }
    .day-cell.today { border: 1px solid #785aff; background: rgba(120, 90, 255, 0.05); }
    
    .day-num { font-size: 15px; font-weight: 800; margin-bottom: 8px; display: block; opacity: 0.6; }
    
    /* Исправленная плашка смены - теперь имя видно полностью */
    .shift-badge { 
        background: #785aff; 
        color: #fff; 
        padding: 6px 28px 6px 10px; /* Увеличили внутренние отступы для удобства */
        border-radius: 8px; 
        font-size: 11px; 
        margin-bottom: 6px; 
        position: relative;
        /* УБРАЛИ nowrap, чтобы текст переносился */
        white-space: normal; 
        word-wrap: break-word; /* Перенос длинных слов */
        line-height: 1.3;
        display: block;
        transition: 0.3s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Кнопка удаления */
    .btn-del-ajax { 
        position: absolute;
        right: 0; top: 0; bottom: 0;
        width: 26px;
        border: none;
        background: rgba(0,0,0,0.1);
        color: #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        border-radius: 0 8px 8px 0; /* Скругляем только правые углы */
    }
    .btn-del-ajax:hover { background: #ff4444; }

    .add-shift-select { 
        width: 100%; 
        background: rgba(255,255,255,0.05); 
        border: 1px solid rgba(255,255,255,0.1); 
        color: #fff; 
        font-size: 11px; 
        padding: 6px; 
        border-radius: 8px; 
        margin-top: 8px;
        outline: none;
        cursor: pointer;
    }
    
    .load-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
    .load-item { background: rgba(255,255,255,0.03); padding: 10px 15px; border-radius: 10px; font-size: 13px; border: 1px solid rgba(255,255,255,0.05); }
    .load-item b { color: #785aff; font-size: 15px; }

    .month-nav { display: flex; align-items: center; gap: 15px; background: rgba(255,255,255,0.03); padding: 10px 20px; border-radius: 12px; }
</style>


<div class="card">
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        <h2 style="margin:0;">📅 График смен</h2>
        <form method="get" action="/admin/index.php" style="display:flex;gap:10px;align-items:center;">
            <input type="hidden" name="page" value="shifts">
            <select name="branch_id" class="st-input" onchange="this.form.submit()" style="min-width: 200px;">
                <option value="0">— Выберите филиал —</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId===$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="month-nav">
                <a href="?page=shifts&branch_id=<?= $branchId ?>&y=<?= $m==1?$y-1:$y ?>&m=<?= $m==1?12:$m-1 ?>" style="text-decoration:none;">◀</a>
                <b style="min-width: 100px; text-align:center;"><?= h($monthTitle) ?></b>
                <a href="?page=shifts&branch_id=<?= $branchId ?>&y=<?= $m==12?$y+1:$y ?>&m=<?= $m==12?1:$m+1 ?>" style="text-decoration:none;">▶</a>
            </div>
        </form>
    </div>
</div>

<div class="card" style="padding: 10px;">
    <?php if ($branchId === 0): ?>
        <div style="text-align:center; padding: 50px; opacity: 0.5;">
            <div style="font-size: 40px; margin-bottom: 10px;">🏢</div>
            <p>Выберите филиал для расписания</p>
        </div>
    <?php else: ?>
        <table class="shift-calendar">
            <thead>
                <tr><?php foreach ($dowNames as $d): ?><th><?= h($d) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
                <tr>
                <?php
                $day = 1;
                for ($i=0; $i<($padStart+$daysInMonth); $i++):
                    if ($i % 7 === 0 && $i > 0) echo '</tr><tr>';
                    if ($i < $padStart): echo '<td></td>'; continue; endif;
                    $dateKey = sprintf('%04d-%02d-%02d', $y, $m, $day);
                    $isWeekend = ($i % 7 === 5 || $i % 7 === 6);
                    $isToday = ($dateKey === date('Y-m-d'));
                ?>
                <td class="day-cell <?= $isWeekend?'weekend':'' ?> <?= $isToday?'today':'' ?>">
                    <span class="day-num"><?= $day ?></span>
                    <div class="shifts-list">
                        <?php if (!empty($shiftsByDate[$dateKey])): ?>
                            <?php foreach ($shiftsByDate[$dateKey] as $s): ?>
                                <div class="shift-badge" id="shift-<?= (int)$s['id'] ?>" title="<?= h($s['last_name'].' '.$s['first_name']) ?>">
                                    <span><?= h($s['last_name'].' '.mb_substr($s['first_name'],0,1).'.') ?></span>
                                    <button class="btn-del-ajax" onclick="ajaxDeleteShift(<?= (int)$s['id'] ?>)">✕</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="/admin/ajax/shift_add.php" class="ajax-shift-add">
                        <input type="hidden" name="date" value="<?= h($dateKey) ?>">
                        <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
                        <select name="user_id" class="add-shift-select" onchange="if(this.value) this.form.dispatchEvent(new Event('submit'))">
                            <option value="">+ сотрудник</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= h($u['last_name'].' '.$u['first_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <?php $day++; endfor; ?>
                </tr>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
    <?php if ($branchId > 0): ?>
    <div class="card">
        <h3 style="margin-top:0;">📊 <?= h($branchName) ?></h3>
        <div class="load-list" id="branch-load-list">
            <?php foreach ($branchLoad as $u): ?>
                <div class="load-item" data-fullname="<?= h($u['last_name'].' '.$u['first_name']) ?>">
                    <b><?= (int)$u['cnt'] ?></b> — <?= h($u['last_name'].' '.$u['first_name']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="card">
        <h3 style="margin-top:0;">🌎 Все филиалы</h3>
        <div class="load-list" id="overall-load-list">
            <?php foreach ($overallLoad as $u): ?>
                <div class="load-item" data-fullname="<?= h($u['last_name'].' '.$u['first_name']) ?>">
                    <b><?= (int)$u['cnt'] ?></b> — <?= h($u['last_name'].' '.$u['first_name']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function updateStats(fullName, mode) {
    document.querySelectorAll('.load-item').forEach(item => {
        if (item.getAttribute('data-fullname') === fullName) {
            let b = item.querySelector('b');
            let val = parseInt(b.innerText) + mode;
            b.innerText = val >= 0 ? val : 0;
            item.style.background = mode > 0 ? 'rgba(124,255,107,0.1)' : 'rgba(255,107,107,0.1)';
            setTimeout(() => { item.style.background = ''; }, 600);
        }
    });
}

async function ajaxDeleteShift(shiftId) {
    if (!confirm('Удалить смену?')) return;
    const badge = document.getElementById('shift-' + shiftId);
    if (!badge) return;
    const fullName = badge.getAttribute('title');
    badge.style.opacity = '0.3';
    try {
        const formData = new FormData();
        formData.append('shift_id', shiftId);
        const res = await fetch('/admin/ajax/shift_delete.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.ok) {
            badge.style.transform = 'scale(0)';
            setTimeout(() => { badge.remove(); updateStats(fullName, -1); }, 300);
        } else { alert(data.message); badge.style.opacity = '1'; }
    } catch (e) { alert('Ошибка связи'); badge.style.opacity = '1'; }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ajax-shift-add').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const select = form.querySelector('select');
            const shiftsList = form.closest('.day-cell').querySelector('.shifts-list');
            try {
                const res = await fetch(form.action, { method: 'POST', body: new FormData(form) });
                const data = await res.json();
                if (data.ok) {
                    const newBadge = `<div class="shift-badge" id="shift-${data.id}" title="${data.full_name}" style="transform: scale(0); transition: 0.3s;">
                        <span>${data.name}</span><button class="btn-del-ajax" onclick="ajaxDeleteShift(${data.id})">✕</button></div>`;
                    shiftsList.insertAdjacentHTML('beforeend', newBadge);
                    setTimeout(() => { document.getElementById('shift-'+data.id).style.transform = 'scale(1)'; }, 10);
                    updateStats(data.full_name, 1);
                    select.value = '';
                } else { alert(data.message); select.value = ''; }
            } catch (e) { alert('Ошибка. Проверьте консоль.'); select.value = ''; }
        });
    });
});
</script>
