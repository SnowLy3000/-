<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/settings.php';

require_auth();
$userId = (int)($_SESSION['user']['id'] ?? 0);
$saleId = (int)($_GET['sale_id'] ?? 0);
$today = date('Y-m-d'); 

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* ================= АКТИВНАЯ СМЕНА ================= */
$hasActiveShift = true;
$branchId = 0;

if (setting('sales_require_checkin', '1') === '1') {
    $stmt = $pdo->prepare("SELECT branch_id FROM shift_sessions WHERE user_id = ? AND checkout_at IS NULL ORDER BY checkin_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $session = $stmt->fetch();
    if (!$session) $hasActiveShift = false;
    else $branchId = (int)$session['branch_id'];
}

if ($saleId) {
    $stmt = $pdo->prepare("SELECT id, branch_id FROM sales WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$saleId, $userId]);
    $sale = $stmt->fetch();
    if (!$sale) { 
        echo "<script>window.location.href='/cabinet/index.php?page=sales';</script>"; 
        exit; 
    }
}

/* ================= ТОВАРЫ И РАСЧЕТ ================= */
$items = [];
$subtotal = 0; 
$totalSalary = 0; 
$promoItemsTotal = 0; 

if ($saleId) {
    $stmt = $pdo->prepare("
        SELECT si.*, 
        (SELECT COUNT(*) FROM product_promotions pr 
         WHERE pr.product_name = si.product_name 
         AND ? BETWEEN pr.start_date AND pr.end_date) as is_promo
        FROM sale_items si 
        WHERE si.sale_id = ? 
        ORDER BY si.id DESC
    ");
    $stmt->execute([$today, $saleId]);
    $items = $stmt->fetchAll();

    foreach ($items as &$it) {
        $pWithD = ceil($it['price'] - ($it['price'] * $it['discount'] / 100));
        $it['price_with_discount'] = $pWithD;
        $it['row_sum'] = $pWithD * $it['quantity'];
        
        $totalSalary += (float)($it['salary_amount'] ?? 0);
        $subtotal += (float)$it['row_sum'];

        if ($it['is_promo'] > 0) {
            $promoItemsTotal += (float)$it['row_sum'];
        }
    }
    unset($it);
}
?>

<style>
    .sales-layout { display: grid; grid-template-columns: 1fr 380px; gap: 25px; font-family: 'Inter', sans-serif; align-items: start; padding: 10px; }
    
    /* Форма добавления - Чистый стиль */
    .add-item-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.08); border-radius: 24px; padding: 25px; margin-bottom: 25px; }
    
    /* Новая сетка: Название занимает всю верхнюю строку */
    .form-grid { display: grid; gap: 20px; }
    .form-row-main { width: 100%; }
    .form-row-details { display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 15px; }
    
    .input-group { position: relative; }
    .input-group label { display: block; font-size: 10px; color: rgba(255,255,255,0.3); text-transform: uppercase; margin-bottom: 8px; font-weight: 800; letter-spacing: 0.5px; }
    
    .st-input { width: 100%; height: 50px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 0 18px; color: #fff; font-size: 15px; outline: none; transition: 0.2s; box-sizing: border-box; }
    .st-input:focus { border-color: #785aff; background: rgba(120,90,255,0.05); box-shadow: 0 0 0 4px rgba(120,90,255,0.1); }
    
    /* Выпадающий список поиска */
    .autocomplete-results { position: absolute; top: 85px; left: 0; right: 0; background: #161621; border: 1px solid #30304a; border-radius: 16px; z-index: 1000; max-height: 280px; overflow-y: auto; box-shadow: 0 15px 40px rgba(0,0,0,0.6); display: none; }
    .res-item { padding: 14px 20px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.03); display: flex; justify-content: space-between; align-items: center; transition: 0.2s; }
    .res-item:hover { background: #1f1f2e; }
    .res-item b { color: #7CFF6B; font-family: 'JetBrains Mono', monospace; }

    .promo-badge { background: linear-gradient(135deg, #ff416c, #ff4b2b); color: #fff; font-size: 9px; padding: 3px 7px; border-radius: 6px; font-weight: 800; margin-left: 8px; }
    .price-promo-active { color: #7CFF6B !important; border: 2px solid #7CFF6B !important; font-weight: 800 !important; }

    .btn-add-item { background: #785aff; color: #fff; width: 100%; height: 55px; border-radius: 16px; border: none; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 10px; transition: 0.3s; box-shadow: 0 8px 20px rgba(120,90,255,0.2); }
    .btn-add-item:hover { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(120,90,255,0.3); }

    /* Таблица корзины */
    .cart-card { background: rgba(255,255,255,0.01); border-radius: 24px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
    .cart-table { width: 100%; border-collapse: collapse; }
    .cart-table th { background: rgba(255,255,255,0.02); padding: 15px 20px; text-align: left; font-size: 10px; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 1px; }
    .cart-table td { padding: 18px 20px; border-bottom: 1px solid rgba(255,255,255,0.03); }
    
    /* Правая панель завершения */
    .checkout-panel { position: sticky; top: 20px; background: #111118; border-radius: 28px; border: 1px solid rgba(120,90,255,0.2); padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
</style>

<div class="sales-layout">
    <div class="content-side">
        <h2 style="margin: 0 0 25px 0; font-weight: 800; letter-spacing: -0.5px;">🧾 Кассовый модуль</h2>

        <?php if (!$hasActiveShift): ?>
            <div style="background: rgba(255,68,68,0.05); border: 1px solid rgba(255,68,68,0.2); color: #ff6b6b; padding: 30px; border-radius: 20px; text-align: center; font-weight: 600;">
                🔒 Смена закрыта. Перейдите в Check-in для начала работы.
            </div>
        <?php else: ?>
            <div class="add-item-card">
                <form method="post" action="/cabinet/actions/sale_item_add.php" id="saleForm" class="form-grid">
                    <input type="hidden" name="sale_id" value="<?= $saleId ?>">
                    <input type="hidden" name="brand" value="OEM"> <div class="form-row-main">
                        <div class="input-group">
                            <label>Поиск товара по названию</label>
                            <input name="product_name" id="p_name" class="st-input" placeholder="Начните вводить наименование..." required autocomplete="off" style="font-size: 16px;">
                            <div id="p_results" class="autocomplete-results"></div>
                        </div>
                    </div>

                    <div class="form-row-details">
                        <div class="input-group">
                            <label>Цена продажи (L)</label>
                            <input type="number" step="0.01" name="price" id="p_price" class="st-input" placeholder="0.00" required>
                        </div>
                        <div class="input-group">
                            <label id="label_discount">Скидка (%)</label>
                            <input type="number" step="0.01" name="discount" id="p_discount" class="st-input" value="0">
                        </div>
                        <div class="input-group">
                            <label>Кол-во</label>
                            <input type="number" name="quantity" class="st-input" value="1" min="1">
                        </div>
                    </div>
                    <button class="btn-add-item" id="btn_submit_item">➕ Добавить в корзину</button>
                </form>
            </div>

            <div class="cart-card">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Наименование</th>
                            <th>Цена</th>
                            <th>Кол-во</th>
                            <th>Итого</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 60px; color: rgba(255,255,255,0.15); font-size: 14px;">Корзина пуста. Добавьте первый товар.</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; font-size: 15px;">
                                            <?= h($it['product_name']) ?>
                                            <?php if($it['is_promo'] > 0): ?><span class="promo-badge">АКЦИЯ</span><?php endif; ?>
                                        </div>
                                    </td>
                                    <td><span style="opacity: 0.5; font-size: 13px;"><?= number_format($it['price_with_discount'], 0) ?> L</span></td>
                                    <td><span style="font-weight: 700;"><?= (int)$it['quantity'] ?></span></td>
                                    <td><b style="color: #fff; font-size: 15px;"><?= number_format($it['row_sum'], 0) ?> L</b></td>
                                    <td style="text-align: right;">
                                        <form method="post" action="/cabinet/actions/sale_item_delete.php">
                                            <input type="hidden" name="sale_id" value="<?= $saleId ?>">
                                            <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
                                            <button style="background:none; border:none; color:rgba(255,107,107,0.4); cursor:pointer; transition: 0.2s;" onmouseover="this.style.color='#ff6b6b'" onmouseout="this.style.color='rgba(255,107,107,0.4)'">✖</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="checkout-panel">
        <h3 style="margin: 0 0 25px 0; text-align: center; font-weight: 800;">🏁 Завершение</h3>
        
        <div class="client-box">
            <div class="input-group">
                <label>Клиент (телефон)</label>
                <input type="text" id="client_search" class="st-input" placeholder="079..." autocomplete="off">
            </div>
            <div id="client_info">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <b id="c_name" style="color: #b866ff;">-</b>
                    <span id="c_discount" style="background:#785aff; color:#fff; padding:3px 10px; border-radius:8px; font-weight: 800; font-size: 12px;">0%</span>
                </div>
                <button type="button" onclick="resetClient()" style="background:none; border:none; color:#ff4444; font-size:11px; cursor:pointer; padding:0; margin-top:10px; font-weight: 700; opacity: 0.6;">[ Сбросить выбор ]</button>
            </div>
        </div>

        <div class="salary-box">
            <span style="opacity: 0.7;">💰 Заработок:</span>
            <span style="color: #7CFF6B;">+<?= number_format($totalSalary, 2) ?> L</span>
        </div>

        <div id="discount_details" style="display:none; font-size: 14px; color: rgba(255,255,255,0.4); margin-bottom: 15px; background: rgba(255,255,255,0.02); padding: 15px; border-radius: 16px;">
            <div style="display:flex; justify-content:space-between; margin-bottom: 5px;">
                <span>Сумма:</span>
                <span><?= number_format($subtotal, 0) ?> L</span>
            </div>
            <div style="display:flex; justify-content:space-between; color: #ff6b6b; font-weight: 700;">
                <span>Скидка:</span>
                <span id="display_discount_val">0 L</span>
            </div>
            <div id="promo_warning" style="font-size:10px; color:#ffb86c; margin-top:8px; display:none; line-height: 1.4;">
                ⚠️ В чеке есть акционные товары, на них скидка не распространяется.
            </div>
        </div>

        <div class="receipt-total">
            <span style="font-size: 14px; opacity: 0.5; font-weight: 400;">К ОПЛАТЕ:</span>
            <span id="display_final_total" style="color: #785aff;"><?= number_format($subtotal, 0) ?> L</span>
        </div>

        <form method="post" action="/cabinet/actions/sale_finish.php" style="margin-top: 30px;">
            <input type="hidden" name="sale_id" value="<?= $saleId ?>">
            <input type="hidden" name="client_id" id="input_client_id" value="">
            <input type="hidden" name="final_discount_amount" id="input_discount_amount" value="0">
            
            <div class="input-group" style="margin-bottom: 20px;">
                <label>Метод оплаты</label>
                <select name="payment_type" class="st-input" style="cursor: pointer;">
                    <option value="cash">💵 Наличные средства</option>
                    <option value="card">💳 Банковская карта</option>
                </select>
            </div>
            <button class="btn-add-item" style="background: #2ecc71;">💾 ПОДТВЕРДИТЬ ПРОДАЖУ</button>
        </form>
    </div>
</div>

<script>
/* === ЛОГИКА ТОВАРОВ === */
const pInput = document.getElementById('p_name');
const pResults = document.getElementById('p_results');
const pPrice = document.getElementById('p_price');
const pDiscount = document.getElementById('p_discount');
const labelDiscount = document.getElementById('label_discount');

pInput.addEventListener('input', function() {
    let val = this.value;
    if (val.length < 2) { pResults.style.display = 'none'; return; }
    
    fetch('/cabinet/ajax/search_products.php?q=' + encodeURIComponent(val))
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                pResults.innerHTML = '';
                data.forEach(item => {
                    let div = document.createElement('div');
                    div.className = 'res-item';
                    let promoLabel = item.is_promo ? ' <span class="promo-badge">АКЦИЯ</span>' : '';
                    div.innerHTML = `<span>${item.name}${promoLabel}</span> <b>${item.price} L</b>`;
                    
                    div.onclick = () => {
                        pInput.value = item.name;
                        pPrice.value = item.price;
                        
                        if (item.is_promo) {
                            pPrice.classList.add('price-promo-active');
                            pDiscount.value = 0;
                            pDiscount.disabled = true;
                            labelDiscount.innerText = "ФИКСИРОВАННАЯ ЦЕНА";
                            labelDiscount.style.color = "#7CFF6B";
                        } else {
                            pPrice.classList.remove('price-promo-active');
                            pDiscount.disabled = false;
                            labelDiscount.innerText = "СКИДКА (%)";
                            labelDiscount.style.color = "";
                        }
                        pResults.style.display = 'none';
                    };
                    pResults.appendChild(div);
                });
                pResults.style.display = 'block';
            } else { pResults.style.display = 'none'; }
        });
});

/* === ЛОГИКА КЛИЕНТА === */
const cSearch = document.getElementById('client_search');
const cInfo = document.getElementById('client_info');
const subtotal = <?= (float)$subtotal ?>;
const promoItemsTotal = <?= (float)$promoItemsTotal ?>;

cSearch.addEventListener('input', function() {
    let phone = this.value;
    if (phone.length >= 3) {
        fetch('/admin/ajax/find_client.php?phone=' + encodeURIComponent(phone))
            .then(res => res.json())
            .then(data => {
                if (data.success) applyClient(data);
            });
    }
});

function applyClient(data) {
    document.getElementById('input_client_id').value = data.id;
    document.getElementById('c_name').innerText = data.name;
    document.getElementById('c_discount').innerText = data.discount + '%';
    cInfo.style.display = 'block';
    
    let discountableAmount = subtotal - promoItemsTotal;
    let discountAmount = Math.floor(discountableAmount * (data.discount / 100));
    let finalTotal = subtotal - discountAmount;
    
    document.getElementById('discount_details').style.display = 'block';
    document.getElementById('display_discount_val').innerText = '-' + discountAmount + ' L';
    document.getElementById('display_final_total').innerText = finalTotal + ' L';
    document.getElementById('input_discount_amount').value = discountAmount;
    
    if (promoItemsTotal > 0) document.getElementById('promo_warning').style.display = 'block';
}

function resetClient() {
    cSearch.value = '';
    document.getElementById('input_client_id').value = '';
    document.getElementById('input_discount_amount').value = '0';
    cInfo.style.display = 'none';
    document.getElementById('discount_details').style.display = 'none';
    document.getElementById('display_final_total').innerText = subtotal + ' L';
}

document.addEventListener('click', (e) => { if (e.target !== pInput) pResults.style.display = 'none'; });
</script>
