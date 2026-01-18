<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = current_user();
if (!$user) die("Авторизуйтесь");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_id = (int)$_POST['test_id'];
    $user_answers = $_POST['answers'] ?? [];

    // 1. Загружаем данные теста и вопросы
    $stmt = $pdo->prepare("SELECT * FROM academy_tests WHERE id = ?");
    $stmt->execute([$test_id]);
    $test = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT * FROM academy_questions WHERE test_id = ?");
    $stmt->execute([$test_id]);
    $questions = $stmt->fetchAll();

    if (!$test || !$questions) die("Данные не найдены");

    $total_questions = count($questions);
    $correct_count = 0;
    $results_log = [];

    // Проверяем ответы
    foreach ($questions as $q) {
        $u_ans = isset($user_answers[$q['id']]) ? (int)$user_answers[$q['id']] : -1;
        $is_right = ($u_ans === (int)$q['correct_option']);
        
        if ($is_right) $correct_count++;

        $results_log[] = [
            'q' => $q['question_text'],
            'options' => json_decode($q['options'], true),
            'correct' => (int)$q['correct_option'],
            'user' => $u_ans,
            'is_right' => $is_right
        ];
    }

    $percent = round(($correct_count / $total_questions) * 100);
    $is_passed = ($percent >= $test['min_score']);

    // --- ЛОГИКА СОХРАНЕНИЯ РЕЗУЛЬТАТА ---
    $res_stmt = $pdo->prepare("INSERT INTO academy_test_results (user_id, test_id, score, is_passed) VALUES (?, ?, ?, ?)");
    $res_stmt->execute([$user['id'], $test_id, $percent, $is_passed ? 1 : 0]);
    // -------------------------------------

    // Начисление XP
    $xp_added = 0;
    if ($is_passed) {
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_xp_log WHERE user_id = ? AND reason LIKE ?");
        $check_stmt->execute([$user['id'], "%Пройден тест: " . $test['title'] . "%"]);
        if ($check_stmt->fetchColumn() == 0) {
            $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('xp_test_passed', 'xp_exam_passed')");
            $xp_weights = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $xp_key = $test['is_exam'] ? 'xp_exam_passed' : 'xp_test_passed';
            $xp_added = (int)($xp_weights[$xp_key] ?? 50);

            $log_stmt = $pdo->prepare("INSERT INTO user_xp_log (user_id, admin_id, amount, reason) VALUES (?, 1, ?, ?)");
            $log_stmt->execute([$user['id'], $xp_added, "🎓 Пройден тест: " . $test['title'] . " ($percent%)"]);
        }
    }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Результаты</title>
    <style>
        body { background: #0b0b0f; color: #fff; font-family: 'Inter', sans-serif; padding: 40px 20px; line-height: 1.6; }
        .container { max-width: 700px; margin: 0 auto; }
        .result-card { background: #16161a; padding: 40px; border-radius: 30px; border: 1px solid #222; text-align: center; margin-bottom: 30px; }
        .score { font-size: 64px; font-weight: 900; margin: 10px 0; color: <?= $is_passed ? '#00ff88' : '#ff4444' ?>; }
        .q-report { background: #16161a; border-radius: 20px; padding: 20px; margin-bottom: 15px; border: 1px solid #222; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; margin-bottom: 10px; display: inline-block; }
        .correct { background: rgba(0,255,136,0.1); color: #00ff88; }
        .wrong { background: rgba(255,68,68,0.1); color: #ff4444; }
        .btn { display: inline-block; background: #785aff; color: #fff; padding: 15px 30px; border-radius: 14px; text-decoration: none; font-weight: 700; margin-top: 20px; }
        .option { font-size: 14px; opacity: 0.6; padding: 5px 0; }
        .option.correct-opt { color: #00ff88; opacity: 1; font-weight: 700; }
        .option.user-wrong { color: #ff4444; opacity: 1; text-decoration: line-through; }
    </style>
</head>
<body>
<div class="container">
    <div class="result-card">
        <div style="font-size: 50px;"><?= $is_passed ? '🎉' : '❌' ?></div>
        <h2><?= $is_passed ? 'Тест пройден!' : 'Попробуйте еще раз' ?></h2>
        <div class="score"><?= $percent ?>%</div>
        <p>Верно: <?= $correct_count ?> из <?= $total_questions ?> (Порог: <?= $test['min_score'] ?>%)</p>
        <?php if ($xp_added > 0): ?>
            <div style="color:#00ff88; font-weight:900;">🏆 +<?= $xp_added ?> XP начислено!</div>
        <?php endif; ?>
        <br>
        <a href="/cabinet/index.php?page=academy" class="btn">ВЕРНУТЬСЯ В АКАДЕМИЮ</a>
    </div>

    <?php if ($test['show_answers_mode'] == 1): ?>
        <h3 style="margin-bottom: 20px; opacity: 0.5;">Разбор ответов:</h3>
        <?php foreach ($results_log as $idx => $res): ?>
            <div class="q-report">
                <div class="badge <?= $res['is_right'] ? 'correct' : 'wrong' ?>">
                    <?= $res['is_right'] ? 'Верно' : 'Ошибка' ?>
                </div>
                <div style="font-weight: 700; margin-bottom: 10px;"><?= ($idx+1) ?>. <?= htmlspecialchars($res['q']) ?></div>
                <div>
                    <?php foreach ($res['options'] as $oIdx => $oText): 
                        $class = '';
                        if ($oIdx === $res['correct']) $class = 'correct-opt';
                        if (!$res['is_right'] && $oIdx === $res['user']) $class = 'user-wrong';
                    ?>
                        <div class="option <?= $class ?>">
                            <?= $oIdx === $res['correct'] ? '✓ ' : '○ ' ?> <?= htmlspecialchars($oText) ?>
                            <?= ($oIdx === $res['user'] && !$res['is_right']) ? ' (Ваш ответ)' : '' ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
<?php
}