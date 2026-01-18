<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';

if (!has_role('Admin') && !has_role('Owner')) die("No access");

$action = $_REQUEST['action'] ?? '';

// 1. УДАЛЕНИЕ ВОПРОСА
if ($action === 'del_question') {
    $id = (int)$_GET['id'];
    $test_id = (int)$_GET['test_id'];
    
    // Ответы удалятся автоматически, если в БД стоит ON DELETE CASCADE, 
    // но на всякий случай удалим вручную
    $pdo->prepare("DELETE FROM academy_answers WHERE question_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM academy_questions WHERE id = ?")->execute([$id]);
    
    header("Location: /admin/index.php?page=academy_test_manage&id=$test_id");
    exit;
}

// 2. ДОБАВЛЕНИЕ ВОПРОСА
if ($action === 'add_question' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_id = (int)$_POST['test_id'];
    $text = trim($_POST['question_text']);
    $answers = $_POST['answers']; // массив
    $correct_index = (int)$_POST['correct_index'];

    $pdo->prepare("INSERT INTO academy_questions (test_id, question_text) VALUES (?, ?)")
        ->execute([$test_id, $text]);
    $q_id = $pdo->lastInsertId();

    foreach ($answers as $index => $ans_text) {
        $is_correct = ($index === $correct_index) ? 1 : 0;
        $pdo->prepare("INSERT INTO academy_answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)")
            ->execute([$q_id, trim($ans_text), $is_correct]);
    }

    header("Location: /admin/index.php?page=academy_test_manage&id=$test_id");
    exit;
}
