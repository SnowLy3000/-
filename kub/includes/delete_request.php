<?php

function createDeleteRequest(PDO $pdo, string $type, int $entityId, int $requestedBy): bool
{
    // Проверяем, нет ли уже активного запроса
    $check = $pdo->prepare("
        SELECT id FROM delete_requests
        WHERE entity_type = ?
          AND entity_id = ?
          AND status = 'pending'
    ");
    $check->execute([$type, $entityId]);

    if ($check->fetch()) {
        return false;
    }

    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));

    $stmt = $pdo->prepare("
        INSERT INTO delete_requests
        (entity_type, entity_id, requested_by, expires_at)
        VALUES (?, ?, ?, ?)
    ");

    return $stmt->execute([
        $type,
        $entityId,
        $requestedBy,
        $expiresAt
    ]);
}