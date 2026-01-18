<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Проверяем, авторизован ли пользователь, и получаем его данные
$user = current_user(); 
$uid = $user['id'] ?? null;

if (!$uid) {
    die("<div style='padding:20px; color:white;'>Ошибка: Пользователь не авторизован.</div>");
}

// Получаем все тесты и проверяем, проходил ли их уже этот пользователь
$stmt = $pdo->prepare("
    SELECT t.*, 
           (SELECT COUNT(*) FROM user_xp_log WHERE user_id = ? AND reason LIKE CONCAT('%', t.title, '%')) as is_passed,
           (SELECT COUNT(*) FROM academy_questions WHERE test_id = t.id) as q_count
    FROM academy_tests t
    ORDER BY t.is_exam DESC, t.id ASC
");
$stmt->execute([$uid]);
$tests = $stmt->fetchAll();
?>

<div class="page-container" style="max-width: 900px;">
    <div style="margin-bottom: 35px;">
        <h1 style="margin: 0; font-size: 32px; font-weight: 900; letter-spacing: -1px;">🎓 Академия KUB</h1>
        <p style="opacity: 0.5; margin-top: 5px;">Повышай квалификацию и получай новые звания</p>
    </div>

    <div style="display: grid; gap: 20px;">
        <?php foreach ($tests as $t): ?>
            <div style="background: #16161a; border: 1px solid <?= $t['is_passed'] ? 'rgba(0,255,136,0.2)' : 'rgba(255,255,255,0.05)' ?>; padding: 25px; border-radius: 24px; display: flex; justify-content: space-between; align-items: center; position: relative; overflow: hidden;">
                
                <?php if ($t['is_passed']): ?>
                    <div style="position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #00ff88;"></div>
                <?php endif; ?>

                <div style="display: flex; align-items: center; gap: 20px;">
                    <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.02); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 28px;">
                        <?= $t['is_exam'] ? '🏆' : '📖' ?>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                            <span style="font-size: 9px; font-weight: 900; padding: 3px 8px; border-radius: 5px; color: #fff; background: <?= $t['is_exam'] ? '#ff4444' : '#785aff' ?>;">
                                <?= $t['is_exam'] ? 'ЭКЗАМЕН' : 'ТЕСТ' ?>
                            </span>
                            <span style="font-size: 11px; opacity: 0.4; font-weight: 600;"><?= $t['q_count'] ?> вопросов</span>
                        </div>
                        <h3 style="margin: 0; font-size: 18px; font-weight: 800;"><?= htmlspecialchars($t['title']) ?></h3>
                        <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.4;"><?= htmlspecialchars($t['description']) ?></p>
                    </div>
                </div>

                <div style="text-align: right;">
                    <?php if ($t['is_passed']): ?>
                        <div style="color: #00ff88; font-weight: 900; font-size: 14px; display: flex; align-items: center; gap: 5px;">
                            <span>✅ ПРОЙДЕНО</span>
                        </div>
                    <?php else: ?>
                        <a href="?page=test_view&id=<?= $t['id'] ?>" style="background: #fff; color: #000; padding: 12px 25px; border-radius: 14px; text-decoration: none; font-weight: 800; font-size: 13px; transition: 0.3s; display: inline-block;">
                            НАЧАТЬ
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!$tests): ?>
            <div style="text-align: center; padding: 50px; opacity: 0.3;">
                <p>Доступных тестов пока нет...</p>
            </div>
        <?php endif; ?>
    </div>
</div>