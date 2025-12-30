<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();

// ЗАМЕНЯЕМ: доступ только для тех, у кого есть право редактировать цены
require_role('edit_prices');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// 1. Быстрое создание нового товара через AJAX
if (isset($_GET['create_new_product'])) {
    header('Content-Type: application/json');
    $name = trim($_GET['name'] ?? '');
    if(!empty($name)){
        $stmt = $pdo->prepare("INSERT INTO products (name, price, category_id, is_active) VALUES (?, 0, 1, 1)");
        $stmt->execute([$name]);
        $new_id = $pdo->lastInsertId();
        echo json_encode(['id' => $new_id, 'name' => $name, 'price' => 0]);
    }
    exit;
}

// 2. Живой поиск товаров
if (isset($_GET['search_q'])) {
    header('Content-Type: application/json');
    $q = $_GET['search_q'] ?? '';
    $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE (name LIKE ? OR id = ?) AND is_active = 1 LIMIT 15");
    $stmt->execute(["%$q%", $q]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// 3. Сохранение акта переоценки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reval'])) {
    if (!empty($_POST['items'])) {
        
        // ПОИСК ID СОГЛАСНО ТВОЕМУ AUTH.PHP
        $current_admin_id = $_SESSION['user']['id'] ?? 0;

        if ($current_admin_id === 0) {
            die("<div class='card' style='border:2px solid red; color:red; padding:20px;'>
                <h3>⚠️ Ошибка сессии</h3>
                <p>Ваш ID не найден в \$_SESSION['user']. Пожалуйста, перезайдите в систему.</p>
            </div>");
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO price_revaluations (user_id, created_at) VALUES (?, NOW())");
            $stmt->execute([$current_admin_id]);
            $reval_id = $pdo->lastInsertId();

            foreach ($_POST['items'] as $p_id => $prices) {
                $stmt = $pdo->prepare("INSERT INTO price_revaluation_items (revaluation_id, product_id, old_price, new_price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$reval_id, (int)$p_id, (float)$prices['old'], (float)$prices['new']]);

                $stmt = $pdo->prepare("UPDATE products SET price = ? WHERE id = ?");
                $stmt->execute([(float)$prices['new'], (int)$p_id]);
            }
            $pdo->commit();
            echo "<script>alert('Цены обновлены!'); location.href='?page=price_log';</script>";
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Ошибка базы: " . $e->getMessage());
        }
    }
}


?>

<style>
    .search-wrapper { position: relative; margin-bottom: 30px; z-index: 1001; }
    .search-input { 
        width: 100%; height: 65px; background: rgba(120, 90, 255, 0.05); 
        border: 2px solid #785aff; border-radius: 20px; color: #fff; 
        padding: 0 30px; font-size: 18px; outline: none; transition: 0.3s;
    }
    .search-input:focus { box-shadow: 0 0 25px rgba(120, 90, 255, 0.3); background: rgba(120, 90, 255, 0.1); }
    
    .dropdown-menu { 
        position: absolute; top: 75px; left: 0; width: 100%; 
        background: #1a1f35; border: 1px solid #785aff; border-radius: 15px; 
        box-shadow: 0 20px 50px rgba(0,0,0,0.7); display: none; max-height: 400px; overflow-y: auto; 
    }
    .dropdown-item { padding: 15px 25px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center; }
    .dropdown-item:hover { background: rgba(120, 90, 255, 0.2); }
    
    .create-btn { background: #785aff; color: #fff; padding: 8px 18px; border-radius: 10px; font-weight: bold; border: none; cursor: pointer; }

    .reval-table { width: 100%; border-collapse: collapse; }
    .reval-table th { text-align: left; padding: 18px; color: rgba(255,255,255,0.4); font-size: 11px; text-transform: uppercase; letter-spacing: 1px; }
    .reval-table td { padding: 18px; border-bottom: 1px solid rgba(255,255,255,0.03); }

    .input-new-price { 
        background: rgba(124, 255, 107, 0.05); border: 1px solid #7CFF6B; 
        color: #7CFF6B; padding: 12px; border-radius: 12px; width: 120px; 
        font-weight: 800; text-align: center; font-size: 18px; outline: none;
    }
    .delete-row { color: #ff6b6b; font-size: 28px; cursor: pointer; background: none; border: none; opacity: 0.5; transition: 0.3s; }
    .delete-row:hover { opacity: 1; transform: rotate(90deg); }

    .submit-btn { 
        width: 100%; height: 70px; margin-top: 25px; border: none; border-radius: 20px;
        background: linear-gradient(90deg, #2ecc71, #27ae60); color: #fff; 
        font-size: 20px; font-weight: 800; cursor: pointer; transition: 0.3s; 
        box-shadow: 0 10px 20px rgba(46, 204, 113, 0.3);
    }
    .submit-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(46, 204, 113, 0.4); }
</style>

<div style="margin-bottom: 30px;">
    <h1 style="margin:0; font-size: 28px;">🔄 Новая переоценка</h1>
    <p class="muted">Выберите товары из базы и укажите новые розничные цены</p>
</div>

<div class="card">
    <div class="search-wrapper">
        <input type="text" id="ajaxSearch" class="search-input" placeholder="Начните вводить название товара..." autocomplete="off">
        <div id="ajaxDropdown" class="dropdown-menu"></div>
    </div>

    <form method="POST">
        <div class="card" style="padding:0; overflow:hidden; border-radius: 20px; background: rgba(255,255,255,0.02);">
            <table class="reval-table">
                <thead>
                    <tr>
                        <th>Наименование товара</th>
                        <th>Текущая цена</th>
                        <th>Новая цена (L)</th>
                        <th style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody id="targetTable"></tbody>
            </table>
            <div id="noItems" style="padding: 80px 20px; text-align: center; opacity: 0.3;">
                <div style="font-size: 40px; margin-bottom: 10px;">📦</div>
                <div>Список пуст. Воспользуйтесь поиском выше.</div>
            </div>
        </div>

        <button type="submit" name="submit_reval" id="btnSubmit" class="submit-btn" style="display: none;">
            ✅ ПРИМЕНИТЬ И СОХРАНИТЬ АКТ
        </button>
    </form>
</div>

<script>
const input = document.getElementById('ajaxSearch');
const drop = document.getElementById('ajaxDropdown');
const table = document.getElementById('targetTable');
const placeholder = document.getElementById('noItems');
const saveBtn = document.getElementById('btnSubmit');

input.addEventListener('input', function() {
    let text = this.value.trim();
    if (text.length < 1) { drop.style.display = 'none'; return; }

    fetch('?page=price_revaluation&search_q=' + encodeURIComponent(text))
        .then(r => r.json())
        .then(data => {
            drop.innerHTML = '';
            drop.style.display = 'block';

            if (data.length > 0) {
                data.forEach(p => {
                    let div = document.createElement('div');
                    div.className = 'dropdown-item';
                    div.innerHTML = `<span>${p.name}</span> <b style="color:#785aff">${p.price} L</b>`;
                    div.onclick = () => addToReval(p);
                    drop.appendChild(div);
                });
            }

            let createRow = document.createElement('div');
            createRow.className = 'dropdown-item';
            createRow.style.background = 'rgba(120,90,255,0.05)';
            createRow.innerHTML = `<span>Создать новый: "<b>${text}</b>"</span> <button type="button" class="create-btn">+ В базу</button>`;
            createRow.onclick = (e) => {
                e.stopPropagation();
                createNew(text);
            };
            drop.appendChild(createRow);
        });
});

function createNew(name) {
    fetch('?page=price_revaluation&create_new_product=1&name=' + encodeURIComponent(name))
        .then(r => r.json())
        .then(p => addToReval(p));
}

function addToReval(p) {
    drop.style.display = 'none';
    input.value = '';
    placeholder.style.display = 'none';
    saveBtn.style.display = 'block';

    if (document.getElementById('row-' + p.id)) return;

    let tr = document.createElement('tr');
    tr.id = 'row-' + p.id;
    tr.innerHTML = `
        <td><b style="font-size:15px;">${p.name}</b><input type="hidden" name="items[${p.id}][old]" value="${p.price}"></td>
        <td><span class="muted">${p.price} L</span></td>
        <td><input type="number" step="0.01" name="items[${p.id}][new]" class="input-new-price" required placeholder="0.00"></td>
        <td style="text-align:right;"><button type="button" class="delete-row" onclick="removeRow(${p.id})">&times;</button></td>
    `;
    table.appendChild(tr);
    tr.querySelector('input').focus();
}

function removeRow(id) {
    document.getElementById('row-' + id).remove();
    if (table.children.length === 0) {
        placeholder.style.display = 'block';
        saveBtn.style.display = 'none';
    }
}

document.addEventListener('click', (e) => {
    if (!input.contains(e.target)) drop.style.display = 'none';
});
</script>
