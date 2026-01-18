<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/settings.php';

require_auth();

// Проверка права на доступ к странице
if (!can_user('clients')) {
    echo "<div style='padding:100px; text-align:center; color:#ff4444; font-family:Inter;'><h2>🚫 Доступ ограничен</h2></div>";
    exit;
}

$isAdmin = can_user('roles') || has_role('Owner');

// --- ОБРАБОТКА ДЕЙСТВИЙ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Удаление клиента (Только для админов)
    if (isset($_POST['delete_client']) && $isAdmin) {
        $id = (int)$_POST['delete_client'];
        $pdo->prepare("DELETE FROM clients WHERE id = ?")->execute([$id]);
        
        // Логируем удаление
        $pdo->prepare("INSERT INTO client_logs (user_id, action_type, new_data) VALUES (?, 'delete', ?)")
            ->execute([$_SESSION['user']['id'], "Удален клиент ID: $id"]);
            
        echo "<script>window.location.href='?page=clients&success=deleted';</script>";
        exit;
    }

    // Сохранение/Редактирование
    if (isset($_POST['save_client'])) {
        $id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : null;
        $name = trim($_POST['name']);
        $phone = trim($_POST['phone']);
        $discount = (int)$_POST['discount'];
        $user_id = $_SESSION['user']['id'];

        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE clients SET name = ?, phone = ?, discount_percent = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $discount, $id]);
                
                $pdo->prepare("INSERT INTO client_logs (client_id, user_id, action_type, new_data) VALUES (?, ?, 'edit', ?)")
                    ->execute([$id, $user_id, "Изменение: $name, $phone, $discount%"]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO clients (name, phone, discount_percent, added_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $phone, $discount, $user_id]);
                $new_id = $pdo->lastInsertId();
                
                $pdo->prepare("INSERT INTO client_logs (client_id, user_id, action_type, new_data) VALUES (?, ?, 'add', ?)")
                    ->execute([$new_id, $user_id, "Новый клиент: $name ($phone)"]);
            }
            echo "<script>window.location.href='?page=clients&success=1';</script>";
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo "<script>alert('Ошибка: Клиент с таким номером телефона уже существует!'); window.history.back();</script>";
                exit;
            }
            die("Ошибка базы данных");
        }
    }
}

// --- ЗАГРУЗКА ДАННЫХ ---
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$query = "SELECT c.*, u.username as creator FROM clients c LEFT JOIN users u ON c.added_by = u.id";
if ($search) { $query .= " WHERE c.name LIKE :q OR c.phone LIKE :q"; }
$query .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($query);
if ($search) { $stmt->bindValue(':q', "%$search%"); }
$stmt->execute();
$clients = $stmt->fetchAll();

// Статистика для карточек
$total_count = count($clients);
$avg_discount = $total_count > 0 ? array_sum(array_column($clients, 'discount_percent')) / $total_count : 0;
?>

<style>
    .cl-page { font-family: 'Inter', sans-serif; color: #fff; }
    
    /* Карточки статистики как в "Дисциплине" */
    .summary-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-card { 
        background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); 
        padding: 25px; border-radius: 24px; position: relative; overflow: hidden;
    }
    .stat-card label { display: block; font-size: 11px; text-transform: uppercase; opacity: 0.4; letter-spacing: 1px; margin-bottom: 10px; font-weight: 800; }
    .stat-card b { font-size: 28px; font-weight: 900; }
    .stat-card .icon { position: absolute; right: -10px; bottom: -10px; font-size: 60px; opacity: 0.05; transform: rotate(-15deg); }

    .cl-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 20px; }
    
    /* Поиск и кнопки */
    .search-input-wrap { position: relative; }
    .search-input-wrap input { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 12px 15px 12px 40px; border-radius: 14px; color: #fff; width: 280px; outline: none; transition: 0.3s; }
    .search-input-wrap::before { content: '🔍'; position: absolute; left: 14px; top: 12px; opacity: 0.4; }
    
    .btn-primary { background: #785aff; color: #fff; border: none; padding: 12px 24px; border-radius: 14px; font-weight: 700; cursor: pointer; transition: 0.3s; box-shadow: 0 4px 15px rgba(120,90,255,0.3); }
    .btn-primary:hover { transform: translateY(-2px); }

    /* Таблица */
    .table-card { background: rgba(255, 255, 255, 0.02); border-radius: 30px; border: 1px solid rgba(255, 255, 255, 0.05); overflow: hidden; }
    .cl-main-table { width: 100%; border-collapse: collapse; }
    .cl-main-table th { background: rgba(255,255,255,0.03); padding: 18px; text-align: left; font-size: 11px; text-transform: uppercase; opacity: 0.4; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .cl-main-table td { padding: 16px 18px; border-bottom: 1px solid rgba(255,255,255,0.03); }
    .cl-main-table tr:hover td { background: rgba(120, 90, 255, 0.03); }

    .cl-user { display: flex; align-items: center; gap: 12px; }
    .cl-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, #785aff, #b866ff); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 14px; }
    .cl-discount { background: rgba(124, 255, 107, 0.1); color: #7CFF6B; padding: 5px 12px; border-radius: 8px; font-weight: 800; font-size: 12px; border: 1px solid rgba(124, 255, 107, 0.2); }
    
    .btn-edit-link { color: #785aff; text-decoration: none; font-weight: 700; font-size: 13px; }
    .btn-del-link { background: none; border: none; color: #ff4444; cursor: pointer; font-weight: 700; font-size: 13px; opacity: 0.6; }
    .btn-del-link:hover { opacity: 1; }

    /* Модалка */
    .modal-backdrop { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:2000; backdrop-filter: blur(10px); align-items: center; justify-content: center; }
    .modal-box { background: #15151e; border: 1px solid rgba(255,255,255,0.1); width: 100%; max-width: 400px; border-radius: 28px; padding: 35px; }
</style>

<div class="cl-page">

    <div style="margin-bottom: 30px;">
        <h1 style="font-size: 32px; font-weight: 900; margin: 0; letter-spacing: -1px;">👥 Клиенты</h1>
        <p style="opacity: 0.5; margin-top: 5px;">Управление базой лояльности и скидками</p>
    </div>

    <div class="summary-row">
        <div class="stat-card">
            <label>Всего клиентов</label>
            <b><?= $total_count ?></b>
            <div class="icon">👥</div>
        </div>
        <div class="stat-card">
            <label>Средняя скидка</label>
            <b style="color: #7CFF6B;"><?= round($avg_discount, 1) ?>%</b>
            <div class="icon">🏷️</div>
        </div>
        <div class="stat-card">
            <label>История</label>
            <a href="?page=client_history" style="color:#785aff; text-decoration:none; display:block; margin-top:5px; font-weight:800; font-size:14px;">Посмотреть логи →</a>
            <div class="icon">📜</div>
        </div>
    </div>

    <div class="cl-top-bar">
        <div class="search-input-wrap">
            <form method="GET">
                <input type="hidden" name="page" value="clients">
                <input type="text" name="q" placeholder="Поиск по имени или телефону..." value="<?= htmlspecialchars($search) ?>">
            </form>
        </div>
        <div class="cl-actions">
            <button onclick="openModal()" class="btn-primary">+ Новый клиент</button>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div style="background: rgba(46, 204, 113, 0.1); color: #2ecc71; padding: 15px; border-radius: 14px; margin-bottom: 20px; border: 1px solid rgba(46, 204, 113, 0.2); font-size: 14px; text-align: center;">
            ✅ <?= $_GET['success'] === 'deleted' ? 'Клиент успешно удален' : 'Данные успешно сохранены' ?>
        </div>
    <?php endif; ?>

    <div class="table-card">
        <table class="cl-main-table">
            <thead>
                <tr>
                    <th>ФИО Клиента</th>
                    <th>Телефон</th>
                    <th>Скидка</th>
                    <th>Кто добавил</th>
                    <th>Дата</th>
                    <th style="text-align:right;">Действие</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($clients as $c): ?>
                <tr>
                    <td>
                        <div class="cl-user">
                            <div class="cl-avatar"><?= mb_substr($c['name'], 0, 1, 'UTF-8') ?></div>
                            <b><?= htmlspecialchars($c['name']) ?></b>
                        </div>
                    </td>
                    <td style="opacity: 0.7; font-family: monospace;"><?= htmlspecialchars($c['phone']) ?></td>
                    <td><span class="cl-discount"><?= $c['discount_percent'] ?>%</span></td>
                    <td><span style="font-size: 12px; opacity: 0.5;">👤 <?= htmlspecialchars($c['creator'] ?? 'Система') ?></span></td>
                    <td style="font-size: 12px; opacity: 0.4;"><?= date('d.m.Y', strtotime($c['created_at'])) ?></td>
                    <td style="text-align:right;">
                        <div style="display: flex; justify-content: flex-end; align-items: center; gap: 15px;">
                            <a href="javascript:void(0)" onclick='editClient(<?= json_encode($c) ?>)' class="btn-edit-link">ИЗМЕНИТЬ</a>
                            <?php if ($isAdmin): ?>
                            <form method="POST" onsubmit="return confirm('Удалить клиента навсегда?')" style="margin:0;">
                                <input type="hidden" name="delete_client" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn-del-link">УДАЛИТЬ</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($clients)): ?>
                <tr><td colspan="6" style="text-align:center; padding:50px; opacity:0.3;">Клиенты не найдены</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="clModal" class="modal-backdrop">
    <div class="modal-box">
        <h2 id="modalT" style="margin-top:0; font-size: 24px; font-weight: 800; margin-bottom: 25px;">Новый клиент</h2>
        <form method="POST">
            <input type="hidden" name="client_id" id="f_id">
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:11px; text-transform:uppercase; opacity:0.4; margin-bottom:8px; font-weight:700;">Имя и Фамилия</label>
                <input type="text" name="name" id="f_name" required style="width:100%; height:48px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:14px; color:#fff; padding:0 15px; outline:none; transition:0.3s;" onfocus="this.style.borderColor='#785aff'">
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:11px; text-transform:uppercase; opacity:0.4; margin-bottom:8px; font-weight:700;">Телефон</label>
                <input type="text" name="phone" id="f_phone" required style="width:100%; height:48px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:14px; color:#fff; padding:0 15px; outline:none;" onfocus="this.style.borderColor='#785aff'">
            </div>
            <div style="margin-bottom:30px;">
                <label style="display:block; font-size:11px; text-transform:uppercase; opacity:0.4; margin-bottom:8px; font-weight:700;">Скидка %</label>
                <input type="number" name="discount" id="f_discount" value="10" style="width:100%; height:48px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:14px; color:#fff; padding:0 15px; outline:none;" onfocus="this.style.borderColor='#785aff'">
            </div>
            <button type="submit" name="save_client" class="btn-primary" style="width:100%; padding:16px; font-size:16px;">💾 Сохранить клиента</button>
            <button type="button" onclick="closeModal()" style="width:100%; background:none; border:none; color:#ff4444; margin-top:15px; cursor:pointer; font-weight:700; opacity:0.6;">Отмена</button>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalT').innerText = "Новый клиент";
    document.getElementById('f_id').value = "";
    document.getElementById('f_name').value = "";
    document.getElementById('f_phone').value = "";
    document.getElementById('f_discount').value = "10";
    document.getElementById('clModal').style.display = 'flex';
}
function editClient(d) {
    document.getElementById('modalT').innerText = "Правка клиента";
    document.getElementById('f_id').value = d.id;
    document.getElementById('f_name').value = d.name;
    document.getElementById('f_phone').value = d.phone;
    document.getElementById('f_discount').value = d.discount_percent;
    document.getElementById('clModal').style.display = 'flex';
}
function closeModal() { document.getElementById('clModal').style.display = 'none'; }
</script>