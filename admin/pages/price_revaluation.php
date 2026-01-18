<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('edit_prices');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// 1. AJAX: Создание товара
if (isset($_GET['create_new_product'])) {
    header('Content-Type: application/json');
    $name = trim($_GET['name'] ?? '');
    if(!empty($name)){
        $stmt = $pdo->prepare("INSERT INTO products (name, price, category_id, is_active) VALUES (?, 0, 1, 1)");
        $stmt->execute([$name]);
        echo json_encode(['id' => $pdo->lastInsertId(), 'name' => $name, 'price' => 0]);
    }
    exit;
}

// 2. AJAX: Живой поиск
if (isset($_GET['search_q'])) {
    header('Content-Type: application/json');
    $q = $_GET['search_q'] ?? '';
    $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE (name LIKE ? OR id = ?) AND is_active = 1 LIMIT 10");
    $stmt->execute(["%$q%", $q]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// 3. Сохранение
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reval'])) {
    if (!empty($_POST['items'])) {
        $current_admin_id = $_SESSION['user']['id'] ?? 0;
        $target_positions = !empty($_POST['target_positions']) ? json_encode($_POST['target_positions']) : null;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO price_revaluations (user_id, target_positions, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$current_admin_id, $target_positions]);
            $reval_id = $pdo->lastInsertId();

            foreach ($_POST['items'] as $p_id => $prices) {
                $stmt = $pdo->prepare("INSERT INTO price_revaluation_items (revaluation_id, product_id, old_price, new_price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$reval_id, (int)$p_id, (float)$prices['old'], (float)$prices['new']]);

                $stmt = $pdo->prepare("UPDATE products SET price = ? WHERE id = ?");
                $stmt->execute([(float)$prices['new'], (int)$p_id]);
            }
            $pdo->commit();
            echo "<script>window.location.href='/admin/index.php?page=price_log';</script>";
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Ошибка: " . $e->getMessage());
        }
    }
}

$all_positions = $pdo->query("SELECT * FROM positions ORDER BY name ASC")->fetchAll();
?>

<style>
    .reval-container { max-width: 900px; margin: 0 auto; font-family: 'Inter', sans-serif; }
    
    .search-input { 
        width: 100%; height: 50px; background: rgba(120, 90, 255, 0.05); 
        border: 1px solid #785aff; border-radius: 15px; color: #fff; 
        padding: 0 20px; font-size: 15px; outline: none; transition: 0.2s;
    }
    .search-input:focus { background: rgba(120, 90, 255, 0.1); box-shadow: 0 0 15px rgba(120,90,255,0.2); }

    .drop-menu { 
        position: absolute; width: 100%; background: #0b0b12; border: 1px solid #333; 
        border-radius: 12px; margin-top: 5px; z-index: 100; max-height: 300px; overflow-y: auto; display: none;
    }
    .drop-item { padding: 10px 15px; cursor: pointer; border-bottom: 1px solid #1a1a1a; display: flex; justify-content: space-between; font-size: 13px; }
    .drop-item:hover { background: #1a1a1a; }

    .compact-table { width: 100%; border-collapse: collapse; }
    .compact-table th { text-align: left; padding: 10px; font-size: 9px; color: rgba(255,255,255,0.3); text-transform: uppercase; border-bottom: 1px solid #333; }
    .compact-table td { padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 13px; }

    .price-input-mini { 
        background: rgba(124, 255, 107, 0.05); border: 1px solid #2ecc71; 
        color: #2ecc71; padding: 5px; border-radius: 8px; width: 80px; 
        text-align: center; font-weight: 800; font-size: 14px; outline: none;
    }

    .pos-section { background: rgba(255,255,255,0.02); border-radius: 15px; padding: 15px; margin-top: 20px; border: 1px solid rgba(255,255,255,0.05); }
    .chip-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .chip { font-size: 11px; background: rgba(255,255,255,0.05); padding: 5px 12px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; }
    .chip input { accent-color: #785aff; }

    .btn-submit-fixed { width: 100%; height: 50px; background: #2ecc71; color: #fff; border: none; border-radius: 12px; font-weight: 800; cursor: pointer; margin-top: 20px; }
</style>

<div class="reval-container">
    <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <div style="font-size: 20px;">🔄</div>
        <h1 style="margin:0; font-size: 20px; font-weight: 800;">Новая переоценка</h1>
    </div>

    <div style="position: relative; margin-bottom: 20px;">
        <input type="text" id="ajaxSearch" class="search-input" placeholder="Поиск товара по названию или ID..." autocomplete="off">
        <div id="ajaxDropdown" class="drop-menu"></div>
    </div>

    <form method="POST">
        <div class="card" style="padding:0; overflow:hidden; border-radius: 15px; border: 1px solid #333;">
            <table class="compact-table">
                <thead>
                    <tr>
                        <th>Товар</th>
                        <th>Текущая</th>
                        <th>Новая (L)</th>
                        <th style="width: 30px;"></th>
                    </tr>
                </thead>
                <tbody id="targetTable"></tbody>
            </table>
            <div id="noItems" style="padding: 40px; text-align: center; opacity: 0.2;">
                <div style="font-size: 30px;">📦</div>
                <div style="font-size: 12px;">Добавьте товары через поиск</div>
            </div>
        </div>

        <div id="posSection" class="pos-section" style="display: none;">
            <span style="font-size: 10px; font-weight: 800; color: #b866ff; text-transform: uppercase;">Направить уведомление:</span>
            <div class="chip-grid">
                <?php foreach($all_positions as $pos): ?>
                    <label class="chip">
                        <input type="checkbox" name="target_positions[]" value="<?= $pos['id'] ?>" checked>
                        <?= h($pos['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" name="submit_reval" id="btnSubmit" class="btn-submit-fixed" style="display: none;">ПРИМЕНИТЬ ЦЕНЫ</button>
    </form>
</div>

<script>
const input = document.getElementById('ajaxSearch');
const drop = document.getElementById('ajaxDropdown');
const table = document.getElementById('targetTable');
const placeholder = document.getElementById('noItems');
const saveBtn = document.getElementById('btnSubmit');
const posSection = document.getElementById('posSection');

input.addEventListener('input', function() {
    let text = this.value.trim();
    if (text.length < 1) { drop.style.display = 'none'; return; }

    fetch('?page=price_revaluation&search_q=' + encodeURIComponent(text))
        .then(r => r.json())
        .then(data => {
            drop.innerHTML = '';
            drop.style.display = 'block';
            data.forEach(p => {
                let div = document.createElement('div');
                div.className = 'drop-item';
                div.innerHTML = `<span>${p.name}</span> <b style="color:#785aff">${p.price} L</b>`;
                div.onclick = () => addToReval(p);
                drop.appendChild(div);
            });
            let createRow = document.createElement('div');
            createRow.className = 'drop-item';
            createRow.style.background = 'rgba(120,90,255,0.05)';
            createRow.innerHTML = `<span>+ Создать "<b>${text}</b>"</span>`;
            createRow.onclick = () => createNew(text);
            drop.appendChild(createRow);
        });
});

function createNew(name) {
    fetch('?page=price_revaluation&create_new_product=1&name=' + encodeURIComponent(name))
        .then(r => r.json()).then(p => addToReval(p));
}

function addToReval(p) {
    drop.style.display = 'none';
    input.value = '';
    placeholder.style.display = 'none';
    saveBtn.style.display = 'block';
    posSection.style.display = 'block';
    if (document.getElementById('row-' + p.id)) return;
    let tr = document.createElement('tr');
    tr.id = 'row-' + p.id;
    tr.innerHTML = `
        <td><b>${p.name}</b><input type="hidden" name="items[${p.id}][old]" value="${p.price}"></td>
        <td style="opacity:0.5">${p.price} L</td>
        <td><input type="number" step="0.01" name="items[${p.id}][new]" class="price-input-mini" required></td>
        <td style="text-align:right;"><button type="button" style="background:none; border:none; color:#ff6b6b; cursor:pointer;" onclick="removeRow(${p.id})">&times;</button></td>
    `;
    table.appendChild(tr);
}

function removeRow(id) {
    document.getElementById('row-' + id).remove();
    if (table.children.length === 0) {
        placeholder.style.display = 'block';
        saveBtn.style.display = 'none';
        posSection.style.display = 'none';
    }
}
</script>