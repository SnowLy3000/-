<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$test_id = (int)($_GET['id'] ?? 0);
$lesson_id = (int)($_GET['lesson_id'] ?? 0);

// Авто-создание теста, если зашли через урок
if ($lesson_id > 0 && $test_id == 0) {
    $stmt = $pdo->prepare("SELECT id FROM academy_tests WHERE lesson_id = ?");
    $stmt->execute([$lesson_id]);
    $test_id = $stmt->fetchColumn();
    
    if (!$test_id) {
        $stmt = $pdo->prepare("INSERT INTO academy_tests (lesson_id, title, min_score, status) VALUES (?, 'Тест к уроку', 80, 'active')");
        $stmt->execute([$lesson_id]);
        $test_id = $pdo->lastInsertId();
    }
}

$stmt = $pdo->prepare("SELECT * FROM academy_tests WHERE id = ?");
$stmt->execute([$test_id]);
$test = $stmt->fetch();

if (!$test) { echo "<div class='badge' style='background:red;'>Тест не найден</div>"; return; }

$stmt = $pdo->prepare("SELECT * FROM academy_questions WHERE test_id = ? ORDER BY id ASC");
$stmt->execute([$test_id]);
$questions = $stmt->fetchAll();
?>

<style>
    /* Стили в стиле твоего Sidebar и Dashboard */
    .constructor-wrap { width: 100%; max-width: 900px; margin: 0 auto; }
    
    .glass-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 24px;
        padding: 30px;
        margin-bottom: 30px;
        backdrop-filter: blur(10px);
    }

    .form-group { margin-bottom: 20px; }
    .form-group label { 
        display: block; font-size: 10px; color: #785aff; 
        text-transform: uppercase; font-weight: 800; margin-bottom: 8px; letter-spacing: 1.5px;
    }

    .cyber-input {
        width: 100%; background: #0d0d12; border: 1px solid rgba(255,255,255,0.1);
        color: #fff; padding: 14px 18px; border-radius: 14px; font-size: 14px;
        transition: 0.3s; box-sizing: border-box;
    }
    .cyber-input:focus { border-color: #785aff; outline: none; box-shadow: 0 0 15px rgba(120, 90, 255, 0.2); }

    /* Варианты ответов */
    .answer-row {
        display: flex; align-items: center; gap: 15px; 
        background: rgba(0,0,0,0.2); padding: 10px; border-radius: 14px; margin-bottom: 10px;
        border: 1px solid transparent; transition: 0.3s;
    }
    .answer-row:hover { border-color: rgba(120, 90, 255, 0.3); }

    /* Переключатель правильного ответа */
    .correct-checker {
        appearance: none; width: 20px; height: 20px; border: 2px solid #333;
        border-radius: 50%; cursor: pointer; position: relative; transition: 0.3s;
    }
    .correct-checker:checked { border-color: #00ff88; background: rgba(0, 255, 136, 0.1); }
    .correct-checker:checked::after {
        content: ''; position: absolute; top: 4px; left: 4px; width: 8px; height: 8px;
        background: #00ff88; border-radius: 50%; box-shadow: 0 0 10px #00ff88;
    }

    /* Карточки вопросов */
    .q-item {
        background: #0d0d12; border-left: 4px solid #785aff;
        padding: 20px; border-radius: 0 18px 18px 0; margin-bottom: 15px;
        position: relative; transition: 0.3s;
    }
    .q-item:hover { transform: translateX(5px); background: #12121a; }
    
    .ans-pill {
        display: inline-flex; align-items: center; padding: 6px 12px;
        border-radius: 8px; font-size: 12px; margin-right: 8px; margin-top: 8px;
        background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
    }
    .ans-pill.is-correct { border-color: #00ff88; color: #00ff88; background: rgba(0, 255, 136, 0.05); }

    .del-btn { color: #ff4b2b; opacity: 0.3; transition: 0.3s; cursor: pointer; text-decoration: none; }
    .del-btn:hover { opacity: 1; }
</style>



<div class="constructor-wrap">
    <div style="margin-bottom: 40px;">
        <a href="?page=academy_manage" style="color: rgba(255,255,255,0.3); font-size: 12px; text-decoration: none;">← К СПИСКУ ТЕМ</a>
        <h1 style="margin: 10px 0; font-size: 28px; font-weight: 900; letter-spacing: -1px;">
            <span style="color: #785aff;">⚙️</span> Конструктор теста
        </h1>
        <div class="badge"><?= htmlspecialchars($test['title']) ?></div>
    </div>

    <div class="glass-card" style="border: 1px solid rgba(120, 90, 255, 0.2); box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <h3 style="margin-top: 0; margin-bottom: 25px; font-size: 16px;">➕ Создать вопрос</h3>
        <form action="/admin/actions/academy_test_save.php" method="POST">
            <input type="hidden" name="test_id" value="<?= $test_id ?>">
            <input type="hidden" name="action" value="add_question">
            
            <div class="form-group">
                <label>Суть вопроса</label>
                <input type="text" name="question_text" class="cyber-input" placeholder="Введите текст вопроса..." required>
            </div>
            
            <div class="form-group">
                <label>Варианты ответов (отметьте правильный)</label>
                <div id="ans_fields">
                    <div class="answer-row">
                        <input type="radio" name="correct_index" value="0" class="correct-checker" checked title="Верный ответ">
                        <input type="text" name="answers[]" class="cyber-input" placeholder="Вариант 1" required>
                    </div>
                    <div class="answer-row">
                        <input type="radio" name="correct_index" value="1" class="correct-checker" title="Верный ответ">
                        <input type="text" name="answers[]" class="cyber-input" placeholder="Вариант 2" required>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn" style="width: 100%; height: 50px; font-size: 14px; letter-spacing: 1px;">
                СОХРАНИТЬ ВОПРОС
            </button>
        </form>
    </div>

    <div style="margin-top: 50px;">
        <h3 style="font-size: 14px; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-bottom: 20px;">
            Существующие вопросы (<?= count($questions) ?>)
        </h3>
        
        <?php foreach ($questions as $q): ?>
            <div class="q-item">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div style="font-size: 15px; font-weight: 600; line-height: 1.5; color: #fff;">
                        <?= htmlspecialchars($q['question_text']) ?>
                    </div>
                    <a href="/admin/actions/academy_test_save.php?action=del_question&id=<?= $q['id'] ?>&test_id=<?= $test_id ?>" 
                       class="del-btn" onclick="return confirm('Удалить?')">✕</a>
                </div>
                
                <div style="margin-top: 10px;">
                    <?php
                    $stmt = $pdo->prepare("SELECT * FROM academy_answers WHERE question_id = ?");
                    $stmt->execute([$q['id']]);
                    foreach ($stmt->fetchAll() as $ans):
                    ?>
                        <div class="ans-pill <?= $ans['is_correct'] ? 'is-correct' : '' ?>">
                            <?= $ans['is_correct'] ? '✓' : '○' ?> <?= htmlspecialchars($ans['answer_text']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
