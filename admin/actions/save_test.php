<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $test_id = isset($_POST['test_id']) ? (int)$_POST['test_id'] : null;
        $title = $_POST['title'];
        $description = $_POST['description'];
        $min_score = (int)$_POST['min_score'];
        $is_exam = (int)$_POST['is_exam'];
        $show_mode = (int)$_POST['show_answers_mode']; // НОВОЕ

        if ($test_id) {
            // === РЕДАКТИРОВАНИЕ ===
            $stmt = $pdo->prepare("UPDATE academy_tests SET title = ?, description = ?, min_score = ?, is_exam = ?, show_answers_mode = ? WHERE id = ?");
            $stmt->execute([$title, $description, $min_score, $is_exam, $show_mode, $test_id]);

            $stmt = $pdo->prepare("DELETE FROM academy_questions WHERE test_id = ?");
            $stmt->execute([$test_id]);
        } else {
            // === СОЗДАНИЕ НОВОГО ===
            $stmt = $pdo->prepare("INSERT INTO academy_tests (title, description, min_score, is_exam, show_answers_mode) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $min_score, $is_exam, $show_mode]);
            $test_id = $pdo->lastInsertId();
        }

        // 3. Сохраняем вопросы (с подсказками)
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            $qStmt = $pdo->prepare("INSERT INTO academy_questions (test_id, question_text, options, correct_option, hint) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($_POST['questions'] as $q) {
                if (empty($q['text'])) continue;

                $qStmt->execute([
                    $test_id,
                    $q['text'],
                    json_encode($q['options'], JSON_UNESCAPED_UNICODE),
                    (int)$q['correct'],
                    $q['hint'] ?? null // НОВОЕ
                ]);
            }
        }

        $pdo->commit();
        header("Location: /admin/index.php?page=academy_manage&success=1");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Ошибка сохранения: " . $e->getMessage());
    }
} else {
    header("Location: /admin/index.php?page=academy_manage");
    exit;
}