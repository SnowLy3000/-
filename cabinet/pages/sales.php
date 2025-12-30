<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/settings.php';

require_auth();
$userId = (int)($_SESSION['user']['id'] ?? 0);
$saleId = (int)($_GET['sale_id'] ?? 0);

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

/* ================= СОЗДАНИЕ ЧЕКА ================= */
if ($hasActiveShift && !$saleId) {
    $stmt = $pdo->prepare("INSERT INTO sales (user_id, branch_id, payment_type, total_amount, created_at) VALUES (?, ?, 'cash', 0.00, NOW())");
    $stmt->execute([$userId, $branchId]);
    $newId = $pdo->lastInsertId();
    echo "<script>window.location.href='/cabinet/index.php?page=sales&sale_id=$newId';</script>";
    exit;
}

if ($saleId) {
    $stmt = $pdo->prepare("SELECT id, branch_id FROM sales WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$saleId, $userId]);
    $sale = $stmt->fetch();
    if (!$sale) { echo "⛔ Нет доступа"; exit; }
}

/* ================= ТОВАРЫ И РАСЧЕТ ================= */
$items = [];
$total = 0; 
$totalSalary = 0; 

if ($saleId) {
    $stmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id DESC");
    $stmt->execute([$saleId]);
    $items = $stmt->fetchAll();

    foreach ($items as &$it) {
        $pWithD = ceil($it['price'] - ($it['price'] * $it['discount'] / 100));
        $it['price_with_discount'] = $pWithD;
        $it['row_sum'] = $pWithD * $it['quantity'];
        $totalSalary += (float)($it['salary_amount'] ?? 0);
        $total += (float)$it['row_sum'];
    }
    unset($it);
}
?>

<style>
    .sales-layout { display: grid; grid-template-columns: 1fr 380px; gap: 20px; font-family: 'Inter', sans-serif; align-items: start; }
    .add-item-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 25px; margin-bottom: 20px; }
    .form-row-top { display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px; }
    .form-row-bottom { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
    .input-group { position: relative; }
    .input-group label { display: block; font-size: 11px; color: rgba(255,255,255,0.4); text-transform: uppercase; margin-bottom: 8px; font-weight: 600; }
    .st-input { width: 100%; height: 45px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; padding: 0 15px; color: #fff; font-size: 14px; outline: none; box-sizing: border-box; }
    .st-input:focus { border-color: #785aff; background: rgba(120,90,255,0.08); }
    
    /* Стили Автокомплита */
    .autocomplete-results { position: absolute; top: 70px; left: 0; right: 0; background: #1e1e2d; border: 1px solid #785aff; border-radius: 12px; z-index: 100; max-height: 200px; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: none; }
    .res-item { padding: 12px 15px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; font-size: 13px; }
    .res-item:hover { background: rgba(120,90,255,0.1); }
    .res-item b { color: #7CFF6B; }

    .btn-add-item { background: #785aff; color: #fff; width: 100%; height: 50px; border-radius: 15px; border: none; font-size: 15px; font-weight: 700; cursor: pointer; margin-top: 20px; transition: 0.3s; }
    .cart-card { background: rgba(255,255,255,0.02); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); overflow: hidden; }
    .cart-table { width: 100%; border-collapse: collapse; }
    .cart-table th { background: rgba(255,255,255,0.03); padding: 12px 15px; text-align: left; font-size: 11px; color: rgba(255,255,255,0.3); text-transform: uppercase; }
    .cart-table td { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.03); font-size: 14px; }
    .checkout-panel { position: sticky; top: 20px; background: #15151e; border-radius: 24px; border: 1px solid rgba(120,90,255,0.3); padding: 25px; }
    .receipt-total { border-top: 1px dashed rgba(255,255,255,0.1); margin-top: 15px; padding-top: 15px; font-size: 24px; font-weight: 800; display: flex; justify-content: space-between; }
    .salary-box { margin-bottom: 12px; font-size: 14px; color: #7CFF6B; font-weight: 700; padding: 12px; background: rgba(124,255,107,0.05); border-radius: 12px; border: 1px dashed rgba(124,255,107,0.2); display: flex; justify-content: space-between; }
</style>

<div class="sales-layout">
    <div class="content-side">
        <h2 style="margin: 0 0 20px 0;">🧾 Продажа товаров</h2>

        <?php if (!$hasActiveShift): ?>
            <div style="background: rgba(255,68,68,0.1); border: 1px solid rgba(255,68,68,0.3); color: #ff6b6b; padding: 20px; border-radius: 16px; text-align: center;">
                🔒 <b>Внимание:</b> Сначала откройте смену в разделе Check-in.
            </div>
        <?php else: ?>

            <div class="add-item-card">
                <form method="post" action="/cabinet/actions/sale_item_add.php" id="saleForm">
                    <input type="hidden" name="sale_id" value="<?= $saleId ?>">
                    <div class="form-row-top">
                        <div class="input-group">
                            <label>Название товара</label>
                            <input name="product_name" id="p_name" class="st-input" placeholder="Введите название..." required autocomplete="off">
                            <div id="p_results" class="autocomplete-results"></div>
                        </div>
                        <div class="input-group">
                            <label>Бренд</label>
                            <select name="brand" id="p_brand" class="st-input" required>
                                <option value="OEM">OEM</option>
                                <?php foreach (['Borofone','Hoco','Mietubl','Moldcell','Orange','Screen Geeks','Wekome'] as $b): ?>
                                    <option value="<?= $b ?>"><?= $b ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row-bottom">
                        <div class="input-group">
                            <label>Цена (L)</label>
                            <input type="number" step="0.01" name="price" id="p_price" class="st-input" placeholder="0.00" required>
                        </div>
                        <div class="input-group">
                            <label>Скидка (%)</label>
                            <input type="number" step="0.01" name="discount" class="st-input" value="0">
                        </div>
                        <div class="input-group">
                            <label>Количество</label>
                            <input type="number" name="quantity" class="st-input" value="1" min="1">
                        </div>
                    </div>
                    <button class="btn-add-item">➕ Добавить в чек</button>
                </form>
            </div>

            <div class="cart-card">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Цена</th>
                            <th>Кол-во</th>
                            <th>Сумма</th>
                            <th>ЗП</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="6" style="text-align: center; padding: 40px; color: rgba(255,255,255,0.2);">Корзина пуста.</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?= h($it['product_name']) ?></div>
                                        <div style="font-size: 11px; opacity: 0.3;"><?= h($it['brand']) ?></div>
                                    </td>
                                    <td><b><?= number_format($it['price_with_discount'], 0) ?> L</b></td>
                                    <td><?= (int)$it['quantity'] ?></td>
                                    <td><b><?= number_format($it['row_sum'], 0) ?> L</b></td>
                                    <td style="color:#7CFF6B">+<?= number_format($it['salary_amount'], 2) ?></td>
                                    <td style="text-align: right;">
                                        <form method="post" action="/cabinet/actions/sale_item_delete.php">
                                            <input type="hidden" name="sale_id" value="<?= $saleId ?>">
                                            <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
                                            <button style="background:none; border:none; color:#ff6b6b; cursor:pointer;">✖</button>
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
        <h3 style="margin: 0 0 20px 0; text-align: center;">🏁 Завершение</h3>
        <div class="salary-box">
            <span>💰 Заработок:</span>
            <span>+<?= number_format($totalSalary, 2) ?> L</span>
        </div>
        <div class="receipt-total">
            <span style="font-size: 16px; opacity: 0.8;">ИТОГО:</span>
            <span><?= number_format($total, 0) ?> L</span>
        </div>
        <form method="post" action="/cabinet/actions/sale_finish.php" style="margin-top: 25px;">
            <input type="hidden" name="sale_id" value="<?= $saleId ?>">
            <div class="input-group" style="margin-bottom: 15px;">
                <label>Тип оплаты</label>
                <select name="payment_type" class="st-input">
                    <option value="cash">💵 Наличные</option>
                    <option value="card">💳 Карта</option>
                </select>
            </div>
            <button class="btn-add-item">💾 Пробить чек</button>
        </form>
    </div>
</div>

<script>
const pInput = document.getElementById('p_name');
const pResults = document.getElementById('p_results');
const pPrice = document.getElementById('p_price');

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
                    div.innerHTML = `<span>${item.name}</span> <b>${item.price} L</b>`;
                    div.onclick = () => {
                        pInput.value = item.name;
                        pPrice.value = item.price;
                        pResults.style.display = 'none';
                    };
                    pResults.appendChild(div);
                });
                pResults.style.display = 'block';
            } else {
                pResults.style.display = 'none';
            }
        });
});

document.addEventListener('click', (e) => {
    if (e.target !== pInput) pResults.style.display = 'none';
});
</script>
