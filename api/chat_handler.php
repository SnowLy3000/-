<?php
error_reporting(0);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/perms.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user']['id'])) {
    echo json_encode(['error' => 'Unauth']); exit;
}

$user = current_user();
$userId = (int)$user['id'];
$isAdmin = has_role('Admin') || has_role('Owner');
$action = $_GET['action'] ?? '';

try {
    // --- ЗАГРУЗКА СООБЩЕНИЙ + СТАТУС КАНАЛА ---
    if ($action === 'load') {
        $channel = $_GET['channel'] ?? 'general';
        $lastId = (int)($_GET['last_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT m.*, u.first_name FROM chat_messages m JOIN users u ON m.user_id = u.id WHERE m.channel = ? AND m.id > ? ORDER BY m.id ASC LIMIT 100");
        $stmt->execute([$channel, $lastId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($messages as &$m) { $m['time'] = date('H:i', strtotime($m['created_at'])); }
        
        $st = $pdo->prepare("SELECT status FROM chat_channels WHERE slug = ?");
        $st->execute([$channel]);
        $status = $st->fetchColumn() ?: 'active';
        
        echo json_encode(['messages' => $messages, 'channel_status' => $status]); exit;
    }

    // --- ОТПРАВКА ---
    if ($action === 'send') {
        $msg = trim($_POST['message'] ?? '');
        $channel = $_POST['channel'] ?? 'general';
        
        $check = $pdo->prepare("SELECT status FROM chat_channels WHERE slug = ?");
        $check->execute([$channel]);
        if ($check->fetchColumn() === 'closed') {
             echo json_encode(['error' => 'closed']); exit;
        }

        if ($msg) {
            $pdo->prepare("INSERT INTO chat_messages (channel, user_id, message) VALUES (?, ?, ?)")->execute([$channel, $userId, $msg]);
        }
        echo json_encode(['status' => 'ok']); exit;
    }

    // --- СПИСОК АКТИВНЫХ ТОВАРНЫХ КАНАЛОВ ---
    if ($action === 'get_active_stock_channels') {
        $stmt = $pdo->query("SELECT name, slug FROM chat_channels WHERE status = 'active' AND slug LIKE 'stock_%' AND slug IN (SELECT CONCAT('stock_', id) FROM stock_requests WHERE expires_at > NOW()) ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
    }

    // --- СПИСОК МОИХ ПРИВАТОВ (ОБНОВЛЕНО: Серверная проверка прочтения) ---
    if ($action === 'get_my_privates') {
        $stmt = $pdo->prepare("SELECT DISTINCT channel FROM chat_messages WHERE channel LIKE 'p_%' AND (channel LIKE ? OR channel LIKE ?)");
        $stmt->execute(["p_{$userId}_%", "%_{$userId}"]);
        $channels = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $result = [];
        foreach ($channels as $ch) {
            $ids = explode('_', $ch);
            $partnerId = ($ids[1] == $userId) ? $ids[2] : $ids[1];
            
            $u = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $u->execute([$partnerId]);
            $userData = $u->fetch(PDO::FETCH_ASSOC);
            $name = trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? ''));
            
            // Последнее сообщение в канале
            $maxStmt = $pdo->prepare("SELECT MAX(id) FROM chat_messages WHERE channel = ?");
            $maxStmt->execute([$ch]);
            $maxId = (int)$maxStmt->fetchColumn();

            // Последнее ПРОЧИТАННОЕ пользователем сообщение из таблицы статусов
            $readStmt = $pdo->prepare("SELECT last_read_id FROM chat_read_status WHERE user_id = ? AND channel_slug = ?");
            $readStmt->execute([$userId, $ch]);
            $lastReadId = (int)$readStmt->fetchColumn();

            $result[] = [
                'id' => $ch, 
                'name' => $name, 
                'last_msg_id' => $maxId, 
                'has_new' => ($maxId > $lastReadId && $maxId > 0) 
            ];
        }
        echo json_encode($result); exit;
    }

    // --- УЧАСТНИКИ НА СМЕНЕ ---
    if ($action === 'get_participants') {
        $requestId = (int)$_GET['request_id'];
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name, 
            (SELECT COUNT(*) FROM stock_responses WHERE request_id = ? AND user_id = u.id) as confirmed,
            (SELECT COUNT(*) FROM stock_declines WHERE request_id = ? AND user_id = u.id) as declined
            FROM users u JOIN shift_sessions ss ON u.id = ss.user_id 
            WHERE ss.checkout_at IS NULL GROUP BY u.id
        ");
        $stmt->execute([$requestId, $requestId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $res = [];
        foreach($rows as $r) {
            $status = 'grey';
            if($r['confirmed'] > 0) $status = 'green';
            elseif($r['declined'] > 0) $status = 'red';
            $res[] = ['full_name' => trim($r['first_name'] . ' ' . $r['last_name']), 'status' => $status];
        }
        echo json_encode($res); exit;
    }

    // --- ЗАКРЫТИЕ ЗАПРОСА ---
    if ($action === 'close_stock') {
        $slug = $_POST['slug'];
        $reqId = (int)str_replace('stock_', '', $slug);
        $st = $pdo->prepare("SELECT user_id FROM stock_requests WHERE id = ?");
        $st->execute([$reqId]);
        $owner = $st->fetchColumn();

        if ($owner == $userId || $isAdmin) {
            $pdo->prepare("UPDATE chat_channels SET status = 'closed' WHERE slug = ?")->execute([$slug]);
            $pdo->prepare("UPDATE stock_requests SET expires_at = NOW() WHERE id = ?")->execute([$reqId]);
            echo json_encode(['status' => 'ok']);
        } else {
            echo json_encode(['error' => 'No perms']);
        }
        exit;
    }

    // --- СОЗДАНИЕ ЗАПРОСА ---
    if ($action === 'create_stock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $product = trim($_POST['product'] ?? '');
        if (!$product) { echo json_encode(['error' => 'empty']); exit; }

        $pdo->prepare("INSERT INTO stock_requests (user_id, product_name, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)")->execute([$userId, $product]);
        $reqId = $pdo->lastInsertId();
        $slug = "stock_" . $reqId;

        $pdo->prepare("INSERT IGNORE INTO chat_channels (name, slug, status) VALUES (?, ?, 'active')")
            ->execute(["📦 " . $product, $slug]);

        echo json_encode(['status' => 'ok', 'slug' => $slug]); exit;
    }

    // --- ПРОВЕРКА ТОВАРОВ ---
    if ($action === 'check_stock') {
        $stmt = $pdo->prepare("SELECT r.*, u.first_name, u.last_name FROM stock_requests r JOIN users u ON r.user_id = u.id WHERE r.expires_at > NOW() AND r.user_id != ? AND r.id NOT IN (SELECT request_id FROM stock_responses WHERE user_id = ?) AND r.id NOT IN (SELECT request_id FROM stock_declines WHERE user_id = ?) ORDER BY r.id DESC");
        $stmt->execute([$userId, $userId, $userId]);
        $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($res as &$r) { 
            $r['is_stock_request'] = true; 
            $r['owner_full_name'] = trim($r['first_name'] . ' ' . $r['last_name']);
            
            // Проверка прочтения для стока
            $readCheck = $pdo->prepare("SELECT 1 FROM chat_read_status WHERE user_id = ? AND channel_slug = ?");
            $readCheck->execute([$userId, "stock_".$r['id']]);
            $r['already_read'] = (bool)$readCheck->fetchColumn();
        }
        echo json_encode($res); exit;
    }

    // --- ПОДТВЕРЖДЕНИЕ ТОВАРА ---
    if ($action === 'confirm_stock' || $action === 'decline_stock') {
        $reqId = (int)$_POST['request_id'];
        $table = ($action === 'confirm_stock') ? 'stock_responses' : 'stock_declines';
        $pdo->prepare("INSERT IGNORE INTO $table (request_id, user_id) VALUES (?, ?)")->execute([$reqId, $userId]);
        echo json_encode(['status' => 'ok']); exit;
    }

    // --- УДАЛЕНИЕ СООБЩЕНИЯ ---
    if ($action === 'delete_message') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM chat_messages WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
        echo json_encode(['status' => 'ok']); exit;
    }

    // --- КОНТАКТЫ ---
    if ($action === 'get_contacts') {
        echo json_encode($pdo->query("SELECT id, first_name, last_name FROM users WHERE status = 'active' AND id != $userId")->fetchAll(PDO::FETCH_ASSOC)); exit;
    }

    // --- СТАТУС КАНАЛА ---
    if ($action === 'get_channel_status') {
        $slug = $_GET['slug'] ?? '';
        $stmt = $pdo->prepare("SELECT status FROM chat_channels WHERE slug = ?");
        $stmt->execute([$slug]);
        $status = $stmt->fetchColumn();
        echo json_encode(['status' => $status ? $status : 'active']); exit;
    }
    
    // --- ПОМЕТИТЬ КАК ПРОЧИТАННОЕ (ОБНОВЛЕНО: Работа с таблицей) ---
    if ($action === 'mark_read') {
        $slug = $_POST['slug'] ?? '';
        if ($slug) {
            // Ищем последний ID сообщения в этом канале
            $st = $pdo->prepare("SELECT MAX(id) FROM chat_messages WHERE channel = ?");
            $st->execute([$slug]);
            $lastMsgId = (int)$st->fetchColumn();
            
            // Если сообщений еще нет (пустой сток), ставим заглушку 1, чтобы пометить просмотренным
            if ($lastMsgId === 0) $lastMsgId = 1;

            $pdo->prepare("REPLACE INTO chat_read_status (user_id, channel_slug, last_read_id) VALUES (?, ?, ?)")
                ->execute([$userId, $slug, $lastMsgId]);
            
            echo json_encode(['status' => 'ok']);
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['error' => 'db_error', 'msg' => $e->getMessage()]);
}