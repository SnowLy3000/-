<?php

function notif_unread_count(PDO $pdo, int $userId): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

function notif_create_bulk(PDO $pdo, array $userIds, string $title, string $body = '', string $link = '', string $entityType = null, int $entityId = null): void {
    if (!$userIds) return;

    $pdo->beginTransaction();
    $st = $pdo->prepare("
        INSERT INTO notifications (user_id, entity_type, entity_id, title, body, link)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($userIds as $uid) {
        $st->execute([(int)$uid, $entityType, $entityId, $title, $body, $link]);
    }
    $pdo->commit();
}

function notif_mark_read(PDO $pdo, int $userId, int $notifId): void {
    $st = $pdo->prepare("
        UPDATE notifications
        SET is_read=1, read_at=NOW()
        WHERE id=? AND user_id=?
    ");
    $st->execute([$notifId, $userId]);
}

function notif_mark_read_by_entity(PDO $pdo, int $userId, string $entityType, int $entityId): void {
    $st = $pdo->prepare("
        UPDATE notifications
        SET is_read=1, read_at=NOW()
        WHERE user_id=? AND entity_type=? AND entity_id=? AND is_read=0
    ");
    $st->execute([$userId, $entityType, $entityId]);
}