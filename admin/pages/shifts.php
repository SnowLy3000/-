<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('manage_shifts');

$y = (int)($_GET['y'] ?? date('Y'));
$m = (int)($_GET['m'] ?? date('n'));
$branchId = (int)($_GET['branch_id'] ?? 0);

if ($m < 1) $m = 1; if ($m > 12) $m = 12;

$monthStart = sprintf('%04d-%02d-01', $y, $m);
$monthEnd   = date('Y-m-t', strtotime($monthStart));
$monthTitle = (new DateTime($monthStart))->format('F Y');

$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
$users = $pdo->query("SELECT id, first_name, last_name FROM users WHERE status='active' ORDER BY last_name, first_name")->fetchAll();

$shiftsByDate = [];
if ($branchId > 0) {
    $stmt = $pdo->prepare("SELECT ws.id, ws.shift_date, ws.user_id, u.first_name, u.last_name FROM work_shifts ws JOIN users u ON u.id = ws.user_id WHERE ws.branch_id = ? AND ws.shift_date BETWEEN ? AND ? ORDER BY u.last_name");
    $stmt->execute([$branchId, $monthStart, $monthEnd]);
    foreach ($stmt as $row) { $shiftsByDate[$row['shift_date']][] = $row; }
}

// Загруженность (Общая)
$stmt = $pdo->prepare("SELECT u.id, u.last_name, COUNT(ws.id) AS cnt FROM users u LEFT JOIN work_shifts ws ON ws.user_id = u.id AND ws.shift_date BETWEEN ? AND ? WHERE u.status='active' GROUP BY u.id HAVING cnt > 0 ORDER BY cnt DESC");
$stmt->execute([$monthStart, $monthEnd]);
$overallLoad = $stmt->fetchAll();

// Загруженность (Филиал)
$branchLoad = []; $branchName = 'Филиал не выбран';
if ($branchId > 0) {
    $stmt = $pdo->prepare("SELECT u.id, u.last_name, COUNT(ws.id) AS cnt FROM users u JOIN work_shifts ws ON ws.user_id = u.id WHERE ws.branch_id = ? AND ws.shift_date BETWEEN ? AND ? AND u.status='active' GROUP BY u.id HAVING cnt > 0 ORDER BY cnt DESC");
    $stmt->execute([$branchId, $monthStart, $monthEnd]);
    $branchLoad = $stmt->fetchAll();
    foreach ($branches as $b) { if ((int)$b['id'] === $branchId) { $branchName = $b['name']; break; } }
}

$firstDow = (int)date('N', strtotime($monthStart));
$daysInMonth = (int)date('t', strtotime($monthStart));
$padStart = $firstDow - 1;
$dowNames = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<style>
    .sh-shell { font-family: 'Inter', sans-serif; color: #fff; max-width: 1100px; margin: 0 auto; font-size: 12px; }
    
    /* Навигация */
    .sh-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; background: #111; padding: 8px 15px; border-radius: 12px; border: 1px solid #222; }
    .st-select { background: #000; border: 1px solid #333; color: #fff; padding: 4px 8px; border-radius: 6px; font-size: 11px; outline: none; }
    
    /* Календарь (Максимально компактный) */
    .sh-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
    .sh-dow { text-align: center; font-size: 9px; font-weight: 900; color: #444; text-transform: uppercase; padding-bottom: 3px; }
    
    .sh-day { 
        background: #0d0d0d; border: 1px solid #1a1a1a; border-radius: 8px; 
        min-height: 75px; padding: 5px; transition: 0.2s; position: relative;
    }
    .sh-day.today { border-color: #785aff; background: #110d1a; }
    .sh-day.weekend { background: #080808; }
    
    .sh-num { font-size: 10px; font-weight: 900; opacity: 0.2; margin-bottom: 3px; display: block; }
    .today .sh-num { opacity: 1; color: #785aff; }

    /* Бейджи */
    .sh-badge { 
        background: #785aff; color: #fff; padding: 2px 16px 2px 5px; border-radius: 4px; 
        font-size: 9px; font-weight: 700; margin-bottom: 2px; position: relative;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;
    }
    .sh-del { 
        position: absolute; right: 0; top: 0; bottom: 0; width: 14px; border: none; 
        background: rgba(0,0,0,0.2); color: #fff; cursor: pointer; font-size: 7px; 
    }

    .sh-add { width: 100%; background: transparent; border: 1px dashed #222; color: #333; font-size: 8px; padding: 1px; border-radius: 3px; cursor: pointer; }

    /* Аналитика */
    .sh-footer { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
    .sh-stats-card { background: #0a0a0a; border: 1px solid #1a1a1a; border-radius: 10px; padding: 10px; }
    .sh-stats-card h4 { margin: 0 0 8px 0; font-size: 9px; text-transform: uppercase; color: #444; }
    
    .sh-pill-list { display: flex; flex-wrap: wrap; gap: 5px; }
    .sh-pill { 
        background: #111; border: 1px solid #222; padding: 3px 7px; border-radius: 6px; 
        display: flex; align-items: center; gap: 5px; transition: 0.3s;
    }
    .sh-pill b { color: #785aff; font-size: 11px; }
    .sh-pill span { font-size: 10px; color: #777; }
    .sh-pill.highlight { border-color: #785aff; background: #221a33; transform: scale(1.1); }
</style>

<div class="sh-shell">
    <div class="sh-nav">
        <h2 style="margin:0; font-size: 14px;">📅 <?= h($branchName) ?></h2>
        <form method="get" style="display:flex; gap:8px;">
            <input type="hidden" name="page" value="shifts">
            <select name="branch_id" class="st-select" onchange="this.form.submit()">
                <option value="0">Филиал...</option>
                <?php foreach ($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $branchId===$b['id']?'selected':'' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div style="display:flex; align-items:center; background:#000; border-radius:6px; border:1px solid #333; font-size: 10px;">
                <a href="?page=shifts&branch_id=<?= $branchId ?>&y=<?= $m==1?$y-1:$y ?>&m=<?= $m==1?12:$m-1 ?>" style="padding:3px 8px; color:#785aff; text-decoration:none;">◀</a>
                <span style="font-weight:800;"><?= h($monthTitle) ?></span>
                <a href="?page=shifts&branch_id=<?= $branchId ?>&y=<?= $m==12?$y+1:$y ?>&m=<?= $m==12?1:$m+1 ?>" style="padding:3px 8px; color:#785aff; text-decoration:none;">▶</a>
            </div>
        </form>
    </div>

    <?php if ($branchId > 0): ?>
        <div class="sh-grid">
            <?php foreach ($dowNames as $d): ?><div class="sh-dow"><?= $d ?></div><?php endforeach; ?>
            <?php
            $day = 1;
            for ($i=0; $i<($padStart+$daysInMonth); $i++):
                if ($i < $padStart): echo '<div></div>'; continue; endif;
                $dateKey = sprintf('%04d-%02d-%02d', $y, $m, $day);
                $isWeekend = ($i % 7 === 5 || $i % 7 === 6);
                $isToday = ($dateKey === date('Y-m-d'));
            ?>
            <div class="sh-day <?= $isWeekend?'weekend':'' ?> <?= $isToday?'today':'' ?>">
                <span class="sh-num"><?= $day ?></span>
                <div class="sh-list" id="list-<?= $dateKey ?>">
                    <?php if (!empty($shiftsByDate[$dateKey])): ?>
                        <?php foreach ($shiftsByDate[$dateKey] as $s): ?>
                            <div class="sh-badge" id="sh-<?= (int)$s['id'] ?>" data-uid="<?= $s['user_id'] ?>">
                                <?= h($s['last_name'].' '.mb_substr($s['first_name'],0,1).'.') ?>
                                <button class="sh-del" onclick="ajaxDeleteShift(<?= (int)$s['id'] ?>, <?= $s['user_id'] ?>)">✕</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <form method="post" action="/admin/ajax/shift_add.php" class="ajax-add">
                    <input type="hidden" name="date" value="<?= h($dateKey) ?>">
                    <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
                    <select name="user_id" class="sh-add" onchange="if(this.value) handleShiftAdd(this)">
                        <option value="">+</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" data-lname="<?= h($u['last_name']) ?>"><?= h($u['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <?php $day++; endfor; ?>
        </div>

        <div class="sh-footer">
            <div class="sh-stats-card">
                <h4>Локально (<?= h($branchName) ?>)</h4>
                <div class="sh-pill-list" id="local-stats">
                    <?php foreach ($branchLoad as $u): ?>
                        <div class="sh-pill" data-stat-uid="<?= $u['id'] ?>">
                            <span><?= h($u['last_name']) ?></span><b><?= (int)$u['cnt'] ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="sh-stats-card">
                <h4>Глобально (Вся сеть)</h4>
                <div class="sh-pill-list" id="global-stats">
                    <?php foreach ($overallLoad as $u): ?>
                        <div class="sh-pill" data-stat-uid="<?= $u['id'] ?>">
                            <span><?= h($u['last_name']) ?></span><b><?= (int)$u['cnt'] ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function updateLoadDisplay(userId, lastName, delta) {
    const containers = ['#local-stats', '#global-stats'];
    
    containers.forEach(selector => {
        const container = document.querySelector(selector);
        let pill = container.querySelector(`[data-stat-uid="${userId}"]`);
        
        if (!pill && delta > 0) {
            // Если пилюли нет (новый сотрудник в списке), создаем её
            const html = `
                <div class="sh-pill" data-stat-uid="${userId}">
                    <span>${lastName}</span><b>0</b>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
            pill = container.querySelector(`[data-stat-uid="${userId}"]`);
        }
        
        if (pill) {
            let b = pill.querySelector('b');
            let current = parseInt(b.innerText);
            b.innerText = Math.max(0, current + delta);
            
            pill.classList.add('highlight');
            setTimeout(() => pill.classList.remove('highlight'), 800);
            
            // Если смен стало 0, можно либо оставить, либо скрыть. Оставим для наглядности.
        }
    });
}

async function handleShiftAdd(select) {
    const form = select.closest('form');
    const list = form.previousElementSibling;
    const uid = select.value;
    const lastName = select.options[select.selectedIndex].getAttribute('data-lname');
    
    try {
        const res = await fetch(form.action, { method: 'POST', body: new FormData(form) });
        const data = await res.json();
        
        if(data.ok) {
            const html = `<div class="sh-badge" id="sh-${data.id}" data-uid="${uid}">
                ${lastName}<button class="sh-del" onclick="ajaxDeleteShift(${data.id}, ${uid}, '${lastName}')">✕</button></div>`;
            list.insertAdjacentHTML('beforeend', html);
            updateLoadDisplay(uid, lastName, 1);
            select.value = '';
        } else { alert(data.message); select.value = ''; }
    } catch(e) { select.value = ''; }
}

async function ajaxDeleteShift(id, uid, lastName) {
    if(!confirm('Удалить?')) return;
    const badge = document.getElementById('sh-'+id);
    try {
        const res = await fetch('/admin/ajax/shift_delete.php', { 
            method: 'POST', 
            body: new URLSearchParams({'shift_id': id}) 
        });
        const data = await res.json();
        if(data.ok) {
            badge.remove();
            updateLoadDisplay(uid, lastName, -1);
        }
    } catch(e) { }
}
</script>