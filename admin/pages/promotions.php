<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
if (!can_user('promotions') && !has_role('Owner')) {
    echo "<div style='padding:100px; text-align:center; color:#ff4444;'><h2>🚫 Доступ ограничен</h2></div>";
    exit;
}

// 1. ЖИВОЙ ПОИСК
if (isset($_GET['search_q'])) {
    header('Content-Type: application/json');
    $q = $_GET['search_q'] ?? '';
    // Ищем только по имени
    $stmt = $pdo->prepare("SELECT name, price FROM products WHERE name LIKE ? AND is_active = 1 LIMIT 15");
    $stmt->execute(["%$q%"]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// 2. СОХРАНЕНИЕ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['launch_bulk_promo'])) {
    if (!empty($_POST['items'])) {
        $start = $_POST['s_date'];
        $end = $_POST['e_date'];
        // Поле brand оставляем пустым или убираем из запроса, если в БД оно не обязательно
        $stmt = $pdo->prepare("INSERT INTO product_promotions (product_name, brand, promo_price, start_date, end_date) VALUES (?, '', ?, ?, ?)");
        foreach ($_POST['items'] as $item) {
            $stmt->execute([$item['name'], (float)$item['promo_price'], $start, $end]);
        }
        echo "<script>window.location.href='?page=promotions&success=1';</script>";
        exit;
    }
}

// 3. УДАЛЕНИЕ
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM product_promotions WHERE id = ?")->execute([$_GET['delete']]);
    echo "<script>window.location.href='?page=promotions';</script>";
    exit;
}

$promos = $pdo->query("SELECT * FROM product_promotions ORDER BY id DESC LIMIT 50")->fetchAll();
?>

<style>
    .pr-wrapper { font-family: 'Inter', sans-serif; color: #fff; padding: 15px; }
    .pr-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .btn-main { background: #785aff; color: #fff; border: none; padding: 14px 28px; border-radius: 14px; font-weight: 700; cursor: pointer; transition: 0.3s; }

    .glass-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; overflow-x: auto; }
    .base-table { width: 100%; border-collapse: collapse; min-width: 600px; }
    .base-table th { background: rgba(255,255,255,0.03); padding: 15px; text-align: left; font-size: 11px; opacity: 0.4; }
    .base-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.03); }

    .modal-full { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; backdrop-filter: blur(10px); align-items: center; justify-content: center; }
    .modal-container { background: #111118; width: 95%; max-width: 800px; border-radius: 24px; padding: 25px; border: 1px solid #333; max-height: 90vh; overflow-y: auto; }

    .search-box { position: relative; margin-bottom: 20px; }
    .search-ctrl { width: 100%; height: 55px; background: #000; border: 2px solid #785aff; border-radius: 15px; color: #fff; padding: 0 20px; outline: none; box-sizing: border-box; font-size: 16px; }
    .search-drop { position: absolute; top: 60px; left: 0; width: 100%; background: #1c1c26; border-radius: 12px; z-index: 100; display: none; border: 1px solid #444; }
    .search-row { padding: 12px 20px; cursor: pointer; border-bottom: 1px solid #333; display: flex; justify-content: space-between; }

    .date-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
    .date-item label { display: block; font-size: 10px; opacity: 0.5; margin-bottom: 5px; text-transform: uppercase; }
    .date-input { width: 100%; background: #000; border: 1px solid #333; color: #fff; padding: 10px; border-radius: 10px; box-sizing: border-box; }

    /* ГРИД БЕЗ БРЕНДА (3 колонки + кнопка) */
    .draft-header { display: grid; grid-template-columns: 1fr 100px 120px 40px; gap: 15px; padding: 10px; opacity: 0.3; font-size: 10px; text-transform: uppercase; border-bottom: 1px solid #333; }
    .draft-row { display: grid; grid-template-columns: 1fr 100px 120px 40px; gap: 15px; padding: 12px 0; border-bottom: 1px solid #222; align-items: center; }
    
    .input-inline { background: none; border: none; color: #fff; width: 100%; font-size: 15px; outline: none; font-weight: 600; }
    .input-price { background: rgba(124, 255, 107, 0.1); border: 1px solid #7CFF6B; color: #7CFF6B; padding: 10px; border-radius: 10px; width: 100%; text-align: center; font-weight: 800; box-sizing: border-box; font-size: 16px; }

    .btn-save { background: #2ecc71; color: #fff; border: none; width: 100%; padding: 18px; border-radius: 15px; font-weight: 800; cursor: pointer; margin-top: 20px; font-size: 16px; }
</style>

<div class="pr-wrapper">
    <div class="pr-header">
        <h1>🔥 Акции</h1>
        <button onclick="document.getElementById('pModal').style.display='flex'" class="btn-main">+ Добавить товары</button>
    </div>

    <div class="glass-card">
        <table class="base-table">
            <thead>
                <tr>
                    <th>Наименование товара</th>
                    <th>Акционная цена</th>
                    <th>Период</th>
                    <th style="text-align:right;">Удалить</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($promos as $p): 
                    $is_active = (date('Y-m-d') >= $p['start_date'] && date('Y-m-d') <= $p['end_date']);
                ?>
                <tr style="<?= !$is_active ? 'opacity:0.4' : '' ?>">
                    <td><b style="font-size:15px;"><?= htmlspecialchars($p['product_name']) ?></b></td>
                    <td><b style="color:#7CFF6B; font-size:16px;"><?= number_format($p['promo_price'], 2) ?> L</b></td>
                    <td style="font-size:12px; opacity:0.6;">📅 <?= date('d.m', strtotime($p['start_date'])) ?> - <?= date('d.m.y', strtotime($p['end_date'])) ?></td>
                    <td align="right"><a href="?page=promotions&delete=<?= $p['id'] ?>" style="color:#ff4444; text-decoration:none; font-weight:800; font-size:20px;">&times;</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="pModal" class="modal-full">
    <div class="modal-container">
        <h2 style="margin-top:0">🚀 Список новых акций</h2>
        
        <div class="search-box">
            <input type="text" id="pSearch" class="search-ctrl" placeholder="Поиск товара по названию..." autocomplete="off">
            <div id="pDrop" class="search-drop"></div>
        </div>

        <form method="POST">
            <div class="date-row">
                <div class="date-item">
                    <label>Начало акции</label>
                    <input type="date" name="s_date" value="<?= date('Y-m-d') ?>" class="date-input">
                </div>
                <div class="date-item">
                    <label>Окончание акции</label>
                    <input type="date" name="e_date" required class="date-input">
                </div>
            </div>

            <div class="draft-header">
                <div>Товар</div>
                <div>Базовая</div>
                <div>Акция (L)</div>
                <div></div>
            </div>
            
            <div id="pDraftBody"></div>

            <div id="pEmpty" style="padding:50px; text-align:center; opacity:0.2; border:2px dashed #444; border-radius:15px; margin:15px 0;">
                Список пуст. Найдите товар через поиск.
            </div>

            <button type="submit" name="launch_bulk_promo" id="pBtnSave" class="btn-save" style="display:none;">✅ ЗАПУСТИТЬ АКЦИИ</button>
            <button type="button" onclick="document.getElementById('pModal').style.display='none'" style="width:100%; background:none; border:none; color:#ff4444; margin-top:15px; cursor:pointer; font-weight:700;">Отмена</button>
        </form>
    </div>
</div>

<script>
const pSearch = document.getElementById('pSearch');
const pDrop = document.getElementById('pDrop');
const pDraft = document.getElementById('pDraftBody');
const pEmpty = document.getElementById('pEmpty');
const pBtn = document.getElementById('pBtnSave');

pSearch.addEventListener('input', function() {
    let val = this.value.trim();
    if (val.length < 1) { pDrop.style.display = 'none'; return; }

    fetch('?page=promotions&search_q=' + encodeURIComponent(val))
        .then(r => r.json())
        .then(data => {
            pDrop.innerHTML = '';
            pDrop.style.display = 'block';
            data.forEach(item => {
                let div = document.createElement('div');
                div.className = 'search-row';
                div.innerHTML = `<span>${item.name}</span> <b style="color:#785aff">${item.price} L</b>`;
                div.onclick = () => {
                    addToDraft(item);
                    pDrop.style.display = 'none';
                    pSearch.value = '';
                };
                pDrop.appendChild(div);
            });
        });
});

function addToDraft(item) {
    pEmpty.style.display = 'none';
    pBtn.style.display = 'block';

    let id = 'row_' + Date.now();
    let div = document.createElement('div');
    div.className = 'draft-row';
    div.innerHTML = `
        <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; padding-left:10px;">
            <input type="text" name="items[${id}][name]" value="${item.name}" class="input-inline" readonly title="${item.name}">
        </div>
        <div style="opacity:0.5; font-size:14px; text-align:center;">${item.price} L</div>
        <div>
            <input type="number" step="0.01" name="items[${id}][promo_price]" class="input-price" required placeholder="0.00">
        </div>
        <div style="text-align:right;">
            <button type="button" onclick="this.parentElement.parentElement.remove(); checkEmpty();" style="background:none; border:none; color:#ff4444; font-size:24px; cursor:pointer;">&times;</button>
        </div>
    `;
    pDraft.appendChild(div);
    div.querySelector('.input-price').focus();
}

function checkEmpty() {
    if (pDraft.children.length === 0) {
        pEmpty.style.display = 'block';
        pBtn.style.display = 'none';
    }
}

document.addEventListener('click', (e) => {
    if (!pSearch.contains(e.target)) pDrop.style.display = 'none';
});
</script>
