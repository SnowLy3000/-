<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image'])) {
    $userId = $_SESSION['user']['id'];
    $data = $_POST['image'];

    // Декодируем base64 из Croppie
    if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
        $data = substr($data, strpos($data, ',') + 1);
        $data = base64_decode($data);
    } else {
        die("Invalid data");
    }

    $uploadDir = __DIR__ . '/../../uploads/avatars/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    // Удаляем старую аватарку
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldAvatar = $stmt->fetchColumn();

    if ($oldAvatar && file_exists(__DIR__ . '/../../' . $oldAvatar)) {
        // Удаляем только если это файл в папке uploads (не системный)
        if (strpos($oldAvatar, 'uploads/avatars/') !== false) {
            unlink(__DIR__ . '/../../' . $oldAvatar);
        }
    }

    // Сохраняем новую
    $fileName = $userId . '_' . time() . '.jpg';
    file_put_contents($uploadDir . $fileName, $data);

    $dbPath = 'uploads/avatars/' . $fileName;
    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->execute([$dbPath, $userId]);

    // Обновляем сессию
    $_SESSION['user']['avatar'] = $dbPath;
    
    echo json_encode(['status' => 'success']);
    exit;
}
header("Location: /cabinet/index.php?page=profile");