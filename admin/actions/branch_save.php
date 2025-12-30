<?php
// admin/actions/branch_save.php

// 1. Подключаем всё необходимое
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';

// 2. Проверяем авторизацию и ПРАВО (вместо жесткой роли)
require_auth();
require_role('branch_save'); 

// 3. Сбор данных из формы
$id         = (int)($_POST['id'] ?? 0);
$name       = trim($_POST['name'] ?? '');
$address    = trim($_POST['address'] ?? '');
$phone      = trim($_POST['phone'] ?? '');
$shiftStart = trim($_POST['shift_start_time'] ?? '09:00');
$shiftEnd   = trim($_POST['shift_end_time'] ?? '');

// Простая валидация
if ($name === '') {
    die('Ошибка: Название филиала обязательно для заполнения');
}

// Проверка формата времени (ЧЧ:ММ)
if (!preg_match('/^\d{2}:\d{2}$/', $shiftStart)) $shiftStart = '09:00';
// Если конечное время пустое — ставим null, иначе проверяем формат
$finalShiftEnd = (empty($shiftEnd)) ? null : (preg_match('/^\d{2}:\d{2}$/', $shiftEnd) ? $shiftEnd : null);

try {
    if ($id > 0) {
        // ОБНОВЛЕНИЕ СУЩЕСТВУЮЩЕГО
        $stmt = $pdo->prepare("
            UPDATE branches 
            SET name = ?, address = ?, phone = ?, shift_start_time = ?, shift_end_time = ? 
            WHERE id = ?
        ");
        $stmt->execute([$name, $address, $phone, $shiftStart, $finalShiftEnd, $id]);
    } else {
        // СОЗДАНИЕ НОВОГО
        $stmt = $pdo->prepare("
            INSERT INTO branches (name, address, phone, shift_start_time, shift_end_time)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $address, $phone, $shiftStart, $finalShiftEnd]);
    }

    // Возврат к списку филиалов
    header('Location: /admin/index.php?page=branches');
    exit;

} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}