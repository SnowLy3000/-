<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';

require_auth();
require_role('manage_products');

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* === ОБРАБОТКА ЖИВОГО ПОИСКА (AJAX) === */
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 2) {
        echo json_encode([]);
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT p.name, c.percent 
        FROM products p 
        JOIN salary_categories c ON c.id = p.category_id 
        WHERE p.name LIKE ? 
        LIMIT 10
    ");
    $stmt->execute(['%' . $q . '%']);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

/* === ДОБАВЛЕНИЕ ТОВАРА === */
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $cat  = (int)$_POST['category_id'];

    if ($name !== '' && $cat > 0) {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
        $stmt->execute([$name]);

        if ($stmt->fetch()) {
            $error = '❌ Такой товар уже есть в базе';
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (name, category_id, is_active, created_at) VALUES (?, ?, 1, NOW())");
            $stmt->execute([$name, $cat]);
            echo "<script>window.location.href='?page=products&added=1';</script>";
            exit;
        }
    }
}

/* === СТАТИСТИКА И СПИСКИ === */
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalCats = $pdo->query("SELECT COUNT(*) FROM salary_categories WHERE is_active=1")->fetchColumn();

$cats = $pdo->query("SELECT id, name, percent FROM salary_categories WHERE is_active=1 ORDER BY percent DESC")->fetchAll();
$products = $pdo->query("
    SELECT p.name, c.name as cat, c.percent 
    FROM products p 
    JOIN salary_categories c ON c.id = p.category_id 
    ORDER BY p.id DESC LIMIT 20
")->fetchAll();
?>

<style>
    .products-header { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .stat-mini-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; padding: 20px; display: flex; align-items: center; gap: 15px; }
    .stat-mini-card .icon { width: 45px; height: 45px; border-radius: 12px; background: rgba(120,90,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 20px; color: #785aff; }
    .stat-mini-card b { font-size: 20px; display: block; }
    .stat-mini-card span { font-size: 11px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }

    .search-container { position: relative; }
    .search-input-wrapper { display: flex; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 15px; padding: 5px 15px; align-items: center; transition: 0.3s; }
    .search-input-wrapper:focus-within { border-color: #785aff; background: rgba(120,90,255,0.05); }
    .search-input-wrapper input { background: none; border: none; color: #fff; padding: 12px; width: 100%; outline: none; font-size: 14px; }

    .search-results {
        position: absolute; top: 110%; left: 0; right: 0;
        background: #15192b; border: 1px solid rgba(120,90,255,0.3);
        border-radius: 15px; max-height: 250px; overflow-y: auto;
        z-index: 100; box-shadow: 0 15px 40px rgba(0,0,0,0.6);
    }
    .search-results div { padding: 12px 18px; border-bottom: 1px solid rgba(255,255,255,0.03); transition: 0.2s; font-size: 13px; display: flex; justify-content: space-between; cursor: default; }
    .search-results div:hover { background: rgba(120,90,255,0.15); }

    .add-form-grid { display: grid; grid-template-columns: 2fr 1.2fr auto; gap: 15px; align-items: end; }
    @media (max-width: 768px) { .add-form-grid { grid-template-columns: 1fr; } }
</style>

<div class="dashboard-products">
    
    <div style="margin-bottom: 25px;">
        <h1 style="margin:0; font-size: 24px;">📦 База товаров</h1>
        <p class="muted">Управление номенклатурой и привязкой к бонусным группам</p>
    </div>

    <div class="products-header">
        <div class="stat-mini-card">
            <div class="icon">📦</div>
            <div><span>Всего позиций</span><b><?= number_format($totalProducts) ?></b></div>
        </div>
        <div class="stat-mini-card">
            <div class="icon">💸</div>
            <div><span>Бонусных групп</span><b><?= $totalCats ?></b></div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 25px; border: 1px solid rgba(120,90,255,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <b style="font-size: 15px; color: #b866ff;">🔎 Проверить наличие в базе</b>
            <span style="font-size: 10px; background: #785aff; padding: 2px 8px; border-radius: 5px; color: #fff;">LIVE</span>
        </div>
        <div class="search-container">
            <div class="search-input-wrapper">
                <span style="opacity: 0.5;">🔍</span>
                <input id="searchProduct" placeholder="Введите название товара для проверки..." autocomplete="off">
            </div>
            <div id="results" class="search-results" style="display:none"></div>
        </div>
    </div>

    <div class="card" style="background: linear-gradient(145deg, rgba(120,90,255,0.05) 0%, rgba(20,24,42,1) 100%); border: 1px solid rgba(120,90,255,0.15); box-shadow: 0 10px 30px rgba(0,0,0,0.2); position: relative; overflow: hidden; margin-bottom: 25px;">
        <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: #785aff; filter: blur(60px); opacity: 0.1;"></div>
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 25px;">
            <div style="width: 4px; height: 20px; background: #785aff; border-radius: 10px; box-shadow: 0 0 12px #785aff;"></div>
            <h3 style="margin:0; font-size: 18px; letter-spacing: 0.5px; color: #fff;">Добавить новый товар</h3>
        </div>
        
        <?php if ($error): ?>
            <div style="color:#ff6b6b; background: rgba(255,107,107,0.08); padding: 15px; border-radius: 14px; margin-bottom: 20px; font-size: 13px; border: 1px solid rgba(255,107,107,0.2);">⚠️ <?=h($error)?></div>
        <?php endif; ?>
        <?php if (isset($_GET['added'])): ?>
            <div style="color:#7CFF6B; background: rgba(124, 255, 107, 0.08); padding: 15px; border-radius: 14px; margin-bottom: 20px; font-size: 13px; border: 1px solid rgba(124, 255, 107, 0.2);">✅ Товар успешно добавлен</div>
        <?php endif; ?>

        <form method="post" class="add-form-grid">
            <div>
                <label style="color: rgba(255,255,255,0.4); font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; display: block; font-weight: 700;">Наименование позиции</label>
                <input name="name" class="st-input" required placeholder="Напр: iPhone 15 Case" style="width: 100%; box-sizing: border-box; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 14px 18px; border-radius: 14px; color: #fff; outline: none;">
            </div>
            <div>
                <label style="color: rgba(255,255,255,0.4); font-size: 10px; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 10px; display: block; font-weight: 700;">Группа процентов</label>
                <select name="category_id" class="st-input" required style="width: 100%; box-sizing: border-box; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); padding: 14px 18px; border-radius: 14px; color: #fff; appearance: none; outline: none; cursor: pointer;">
                    <option value="" style="background: #1a1f35;">— Выбрать группу —</option>
                    <?php foreach($cats as $c): ?>
                        <option value="<?=$c['id']?>" style="background: #1a1f35;"><?=h($c['name'])?> (<?=$c['percent']?>%)</option>
                    <?php endforeach ?>
                </select>
            </div>
            <button class="btn" style="height: 52px; padding: 0 35px; font-weight: 800; background: linear-gradient(90deg, #785aff, #b866ff); border: none; border-radius: 14px; color: #fff; box-shadow: 0 4px 15px rgba(120,90,255,0.3); cursor: pointer;">СОХРАНИТЬ</button>
        </form>
    </div>

    <div class="card" style="padding: 0; overflow: hidden; border-radius: 20px;">
        <div style="padding: 20px; display: flex; justify-content: space-between; align-items: center;">
            <b style="font-size: 15px;">📋 Последние позиции</b>
            <a href="?page=import" style="font-size: 12px; color: #785aff; text-decoration: none;">Импорт CSV</a>
        </div>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; opacity: 0.3; font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">
                    <th style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05);">Товар</th>
                    <th style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05);">Категория</th>
                    <th style="padding: 15px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: right;">Бонус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($products as $p): ?>
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                    <td style="padding: 15px 20px;"><b style="font-size: 14px;"><?=h($p['name'])?></b></td>
                    <td style="padding: 15px 20px;"><span style="font-size: 12px; color: rgba(255,255,255,0.5);"><?=h($p['cat'])?></span></td>
                    <td style="padding: 15px 20px; text-align: right;"><span style="color: #7CFF6B; font-weight: 800;"><?=h($p['percent'])?>%</span></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

</div>

<script>
const searchInput = document.getElementById('searchProduct');
const resultsBox = document.getElementById('results');

searchInput.addEventListener('input', function() {
    const q = this.value.trim();
    if (q.length < 2) {
        resultsBox.style.display = 'none';
        return;
    }

    fetch('?page=products&ajax_search=1&q=' + encodeURIComponent(q))
        .then(res => res.json())
        .then(data => {
            resultsBox.innerHTML = '';
            if (data.length > 0) {
                data.forEach(item => {
                    const div = document.createElement('div');
                    div.innerHTML = `<span>${item.name}</span> <b style="color:#7CFF6B">${item.percent}%</b>`;
                    resultsBox.appendChild(div);
                });
                resultsBox.style.display = 'block';
            } else {
                resultsBox.innerHTML = '<div style="padding:15px; opacity:0.5;">Ничего не найдено</div>';
                resultsBox.style.display = 'block';
            }
        });
});

document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target)) resultsBox.style.display = 'none';
});
</script>
