<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Функция безопасности вывода текста
if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$user = current_user();
$found_sales = [];
$search_performed = false;

// 1. ЛОГИКА ПОИСКА ЧЕКА ДЛЯ ОФОРМЛЕНИЯ
if (isset($_GET['search_return'])) {
    $search_performed = true;
    $date = $_GET['sale_date'];
    $branch = (int)$_GET['branch_id'];
    $amount = (float)$_GET['total_amount'];

    $stmt = $pdo->prepare("
        SELECT s.*, u.first_name as seller_name, b.name as branch_name
        FROM sales s
        JOIN users u ON s.user_id = u.id
        JOIN branches b ON s.branch_id = b.id
        WHERE DATE(s.created_at) = ? 
        AND s.branch_id = ? 
        AND s.total_amount BETWEEN ? AND ?
        AND s.is_returned = 0
    ");
    $stmt->execute([$date, $branch, $amount - 0.5, $amount + 0.5]);
    $found_sales = $stmt->fetchAll();
}

// 2. ЛОГИКА ИСТОРИИ (ПОСЛЕДНИЕ 10 ВОЗВРАТОВ)
$stmt_history = $pdo->prepare("
    SELECT r.sale_id, r.created_at, r.reason, s.total_amount, u.first_name as staff_name, b.name as branch_name
    FROM returns r
    JOIN sales s ON r.sale_id = s.id
    JOIN users u ON r.staff_id = u.id
    JOIN branches b ON s.branch_id = b.id
    ORDER BY r.created_at DESC
    LIMIT 10
");
$stmt_history->execute();
$returns_history = $stmt_history->fetchAll();

$branches = $pdo->query("SELECT id, name FROM branches ORDER BY name")->fetchAll();
?>

<style>
    .returns-page { font-family: 'Inter', sans-serif; max-width: 1000px; margin: 0 auto; color: #e8eefc; padding: 20px; }
    .search-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px; background: rgba(120, 90, 255, 0.05); border: 1px solid rgba(120, 90, 255, 0.2);
        padding: 20px; border-radius: 20px; align-items: end; margin-bottom: 30px;
    }
    .st-input { background: #0f1629; border: 1px solid rgba(255,255,255,0.1); padding: 10px 15px; border-radius: 12px; color: #fff; width: 100%; box-sizing: border-box; }
    
    .sale-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 24px; padding: 20px; margin-bottom: 20px; }
    
    .items-selection-list { background: rgba(0,0,0,0.3); border-radius: 15px; padding: 10px; margin: 10px 0; border: 1px solid rgba(255,255,255,0.05); }
    .item-checkbox-row { display: flex; align-items: center; gap: 15px; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); cursor: pointer; }
    .custom-cb { width: 22px; height: 22px; accent-color: #785aff; }

    .preview-container { display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px; margin-top: 15px; }
    .preview-item { width: 80px; height: 80px; border-radius: 10px; overflow: hidden; border: 1px solid #785aff; position: relative; }
    .preview-item img { width: 100%; height: 100%; object-fit: cover; }
    .btn-action { background: #785aff; color: #fff; border: none; padding: 12px; border-radius: 12px; font-weight: 700; cursor: pointer; width: 100%; transition: 0.2s; }
    .btn-action:hover { opacity: 0.9; transform: translateY(-1px); }

    .history-item { background: rgba(255,107,107,0.05); border: 1px solid rgba(255,107,107,0.1); padding: 15px 20px; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
</style>

<div class="returns-page">
    <div style="text-align: center; margin-bottom: 25px;">
        <img src="https://kub.md/image/catalog/logo_new.png" style="height: 30px; filter: brightness(0) invert(1);">
        <h2 style="margin:5px 0 0 0;">Оформление возврата</h2>
    </div>

    <form class="search-grid" method="GET">
        <input type="hidden" name="page" value="returns">
        <div class="form-group">
            <label style="font-size:10px; color:#785aff; font-weight:800; text-transform:uppercase;">Дата</label>
            <input type="date" name="sale_date" class="st-input" required value="<?= h($_GET['sale_date'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="form-group">
            <label style="font-size:10px; color:#785aff; font-weight:800; text-transform:uppercase;">Филиал</label>
            <select name="branch_id" class="st-input" required>
                <?php foreach($branches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= (isset($_GET['branch_id']) && $_GET['branch_id'] == $b['id']) ? 'selected' : '' ?>><?= h($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label style="font-size:10px; color:#785aff; font-weight:800; text-transform:uppercase;">Сумма</label>
            <input type="number" step="0.01" name="total_amount" class="st-input" required placeholder="0.00" value="<?= h($_GET['total_amount'] ?? '') ?>">
        </div>
        <button name="search_return" class="btn-action" style="height: 44px;">Найти</button>
    </form>

    <?php if ($search_performed): ?>
        <div id="searchResults">
            <?php if ($found_sales): ?>
                <?php foreach ($found_sales as $sale): 
                    $days = (new DateTime())->diff(new DateTime($sale['created_at']))->days;
                    $stmtItems = $pdo->prepare("SELECT si.id, si.product_name as name, si.price FROM sale_items si WHERE si.sale_id = ?");
                    $stmtItems->execute([$sale['id']]);
                    $saleItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="sale-card" style="border-left: 5px solid <?= ($days > 14) ? '#ff6b6b' : '#2ecc71' ?>;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <b style="font-size:18px;">Чек #<?= h($sale['id']) ?></b>
                            <div style="font-size:12px; color:<?= ($days > 14) ? '#ff6b6b' : '#2ecc71' ?>;">
                                <?= ($days > 14) ? '⛔ Срок истек' : '✅ В срок' ?> (<?= $days ?> дн.)
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span style="font-size:20px; font-weight:900;"><?= number_format($sale['total_amount'], 2) ?> L</span>
                            <div style="opacity:0.5; font-size:11px;"><?= date('d.m.Y', strtotime($sale['created_at'])) ?></div>
                        </div>
                    </div>
                    <div style="margin-top:15px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="opacity:0.6; font-size:12px;">Продавец: <?= h($sale['seller_name']) ?></span>
                        <button onclick='openReturnModal(<?= h(json_encode($sale)) ?>, <?= h(json_encode($saleItems)) ?>)' class="btn-action" style="width:auto; padding:8px 20px; background:#ff6b6b;">Выбрать товары</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; opacity:0.5; padding: 20px;">Чеки не найдены. Проверьте дату и сумму.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div style="margin-top: 50px;">
        <h3 style="margin-bottom: 20px;">📋 Последние 10 возвратов (нажмите для деталей)</h3>
        <?php if (!$returns_history): ?>
            <p style="opacity:0.4; text-align:center;">История пуста</p>
        <?php else: ?>
            <?php foreach ($returns_history as $rh): ?>
                <a href="index.php?page=sale_view&id=<?= $rh['sale_id'] ?>" style="text-decoration: none; color: inherit;">
                    <div class="history-item" style="cursor: pointer; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.01)'" onmouseout="this.style.transform='scale(1)'">
                        <div>
                            <div style="font-weight: 800; font-size: 14px;">Чек #<?= $rh['sale_id'] ?></div>
                            <div style="font-size: 12px; opacity: 0.6;">
                                Принял: <?= h($rh['staff_name']) ?> | <?= date('d.m H:i', strtotime($rh['created_at'])) ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="color: #ff6b6b; font-weight: 900;"><?= number_format($rh['total_amount'], 2) ?> L</div>
                            <div style="font-size: 10px; opacity:0.5;"><?= h($rh['branch_name']) ?></div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div id="modalReturn" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.95); z-index:10000; backdrop-filter:blur(10px);">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:#161b2e; padding:30px; border-radius:25px; border:1px solid #785aff; width:95%; max-width:550px; max-height:90vh; overflow-y:auto;">
        <h3 id="m_title" style="text-align:center; margin-top:0;">Оформление возврата</h3>
        
        <form action="ajax/confirm_return.php" method="POST" enctype="multipart/form-data" id="returnForm">
            <input type="hidden" name="sale_id" id="m_sale_id">
            
            <label style="font-size:11px; color:#785aff; font-weight:800; display:block;">ВЫБЕРИТЕ ТОВАРЫ ДЛЯ ВОЗВРАТА:</label>
            <div id="m_items_list" class="items-selection-list"></div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                <div><label style="font-size:10px; opacity:0.5;">ID 1С</label>
                <input type="text" name="1c_return_id" required class="st-input"></div>
                <div><label style="font-size:10px; opacity:0.5;">ПРИЧИНА</label>
                <select name="reason_type" class="st-input" onchange="checkDefect(this.value)">
                    <option value="not_liked">Не подошел</option>
                    <option value="defect">❌ БРАК / ГАРАНТИЯ</option>
                </select></div>
            </div>

            <div id="defect_fields" style="display:none; margin-bottom:15px;">
                <textarea name="defect_desc" class="st-input" style="height: 80px; resize: none;" placeholder="Описание неисправности..."></textarea>
            </div>

            <div style="background:rgba(255,255,255,0.03); border:2px dashed #785aff; border-radius:15px; padding:15px; text-align:center;">
                <input type="file" id="photo_input" name="return_photos[]" multiple accept="image/*" style="display:none;" onchange="handleFiles(this.files)">
                <button type="button" onclick="document.getElementById('photo_input').click()" style="background:none; border:none; color:#785aff; font-weight:800; cursor:pointer;">➕ ДОБАВИТЬ ФОТО</button>
                <div id="gallery" class="preview-container"></div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:20px;">
                <button type="submit" class="btn-action" style="background:#2ecc71;">ЗАВЕРШИТЬ</button>
                <button type="button" onclick="document.getElementById('modalReturn').style.display='none'" style="background:none; border:none; color:#ff6b6b; cursor:pointer;">ОТМЕНА</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReturnModal(sale, items) {
    try {
        document.getElementById('m_sale_id').value = sale.id;
        document.getElementById('m_title').innerText = "Оформление возврата #" + sale.id;
        
        const itemsList = document.getElementById('m_items_list');
        itemsList.innerHTML = '';
        
        const itemsArray = Array.isArray(items) ? items : [items];
        
        if (itemsArray.length === 0) {
            itemsList.innerHTML = '<div style="padding:10px; opacity:0.5;">Товары не найдены</div>';
        } else {
            let html = '';
            itemsArray.forEach(it => {
                // it.quantity — это сколько было куплено всего
                const maxQty = parseInt(it.quantity) || 1;
                
                html += `
                <div class="item-checkbox-row" style="display: grid; grid-template-columns: 30px 1fr 80px; align-items: center; gap: 10px; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <input type="checkbox" name="return_items[]" value="${it.id}" class="custom-cb" checked onchange="toggleQty(this, ${it.id})">
                    <div style="flex:1;">
                        <div style="font-size:13px; font-weight:700; color:#fff;">${it.name}</div>
                        <div style="font-size:11px; opacity:0.6;">Цена: ${it.price} L | Всего: ${maxQty} шт.</div>
                    </div>
                    <div>
                        <input type="number" name="return_qty[${it.id}]" id="qty_${it.id}" 
                               value="${maxQty}" min="1" max="${maxQty}" 
                               class="st-input" style="padding: 5px; text-align: center; height: 35px;">
                    </div>
                </div>`;
            });
            itemsList.innerHTML = html;
        }
        
        document.getElementById('modalReturn').style.display = 'block';
    } catch (e) {
        console.error(e);
        alert("Ошибка загрузки товаров");
    }
}

// Функция для блокировки ввода количества, если чекбокс снят
function toggleQty(cb, itemId) {
    const qtyInput = document.getElementById('qty_' + itemId);
    if (qtyInput) {
        qtyInput.disabled = !cb.checked;
        qtyInput.style.opacity = cb.checked ? "1" : "0.3";
    }
}


// ПРОВЕРКА ПЕРЕД ОТПРАВКОЙ ФОРМЫ
document.getElementById('returnForm').onsubmit = function(e) {
    const checkboxes = document.querySelectorAll('input[name="return_items[]"]:checked');
    if (checkboxes.length === 0) {
        alert("Пожалуйста, выберите хотя бы один товар для возврата.");
        e.preventDefault();
        return false;
    }
    return true;
};

function handleFiles(files) {
    const gallery = document.getElementById('gallery');
    gallery.innerHTML = '';
    Array.from(files).forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.innerHTML = `<img src="${e.target.result}">`;
            gallery.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

function checkDefect(val) {
    const fields = document.getElementById('defect_fields');
    if (fields) fields.style.display = (val === 'defect') ? 'block' : 'none';
}
</script>
