<?php

function getPendingDeleteRequestsCount(PDO $pdo): int
{
    return (int)$pdo->query("
        SELECT COUNT(*)
        FROM delete_requests
        WHERE status = 'pending'
    ")->fetchColumn();
}