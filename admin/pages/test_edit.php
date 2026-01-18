<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($test_id <= 0) die("ID не указан");

$stmt = $pdo->prepare("SELECT * FROM academy_tests WHERE id = ?");
$stmt->execute([$test_id]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) die("Тест не найден");

$stmt = $pdo->prepare("SELECT * FROM academy_questions WHERE test_id = ? ORDER BY id ASC");
$stmt->execute([$test_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-container">
    <form action="/admin/actions/save_test.php" method="POST" id="testForm">
        <input type="hidden" name="test_id" value="<?= $test['id'] ?>">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <h1 style="margin: 0; font-size: 28px; font-weight: 900; color:#fff;">✏️ Редактирование</h1>
                <p style="opacity: 0.5;">ID теста: <?= $test['id'] ?></p>
            </div>
            <button type="submit" class="btn" style="background: #785aff; color: #fff; font-weight: 800; padding: 12px 30px; border-radius: 14px; border: none; cursor: pointer;">СОХРАНИТЬ ИЗМЕНЕНИЯ</button>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <div style="background: #16161a; border: 1px solid #222; padding: 25px; border-radius: 24px;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; opacity: 0.4; text-transform: uppercase; margin-bottom: 8px;">Название теста</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($test['title']) ?>" required style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 15px; border-radius: 14px; box-sizing: border-box; font-size: 16px;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; opacity: 0.4; text-transform: uppercase; margin-bottom: 8px;">Описание</label>
                    <textarea name="description" rows="3" style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 15px; border-radius: 14px; box-sizing: border-box; font-family: inherit;"><?= htmlspecialchars($test['description']) ?></textarea>
                </div>
            </div>

            <div style="background: #16161a; border: 1px solid #222; padding: 25px; border-radius: 24px; height: fit-content;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; opacity: 0.4; text-transform: uppercase; margin-bottom: 8px;">Тип аттестации</label>
                    <select name="is_exam" style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 12px; border-radius: 12px;">
                        <option value="0" <?= $test['is_exam'] == 0 ? 'selected' : '' ?>>📖 Обычный тест</option>
                        <option value="1" <?= $test['is_exam'] == 1 ? 'selected' : '' ?>>🏆 Экзамен по теме</option>
                    </select>
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 12px; opacity: 0.4; text-transform: uppercase; margin-bottom: 8px;">Показ ответов</label>
                    <select name="show_answers_mode" style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 12px; border-radius: 12px;">
                        <option value="0" <?= $test['show_answers_mode'] == 0 ? 'selected' : '' ?>>❌ Не показывать ошибки</option>
                        <option value="1" <?= $test['show_answers_mode'] == 1 ? 'selected' : '' ?>>✅ Показать итог в конце</option>
                    </select>
                </div>
                <div style="margin-bottom: 20px;">
    <label style="display: block; font-size: 12px; opacity: 0.4; text-transform: uppercase; margin-bottom: 8px;">Лимит времени (мин)</label>
    <input type="number" name="time_limit" value="<?= $test['time_limit'] ?? 0 ?>" min="0" style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #ffbb33; padding: 12px; border-radius: 12px; font-weight: 800; text-align: center;">
    <small style="display:block; margin-top:5px; opacity:0.3; font-size:10px;">0 — без ограничений</small>
</div>
                <div>
                    <label style="display: block; font-size: 12px; opacity: 0.4; text-transform: uppercase; margin-bottom: 8px;">Проходной балл (%)</label>
                    <input type="number" name="min_score" value="<?= $test['min_score'] ?>" min="1" max="100" style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #00ff88; padding: 12px; border-radius: 12px; font-weight: 800; text-align: center;">
                </div>
            </div>
        </div>

        <div id="questionsContainer" style="margin-top: 30px;">
            <h2 style="font-size: 20px; margin-bottom: 20px; color:#fff;">❓ Вопросы</h2>
            <?php 
            $qCount = 0;
            foreach ($questions as $q): 
                $qCount++;
                $options = json_decode($q['options'], true);
            ?>
                <div class="question-block" id="q_block_<?= $qCount ?>" style="background: #16161a; border: 1px solid #222; padding: 25px; border-radius: 24px; margin-bottom: 20px; position: relative;">
                    <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 15px; right: 15px; background: rgba(255,68,68,0.1); color: #ff4444; border: none; width: 30px; height: 30px; border-radius: 8px; cursor: pointer;">✕</button>
                    <div style="margin-bottom: 20px; padding-right: 40px;">
                        <input type="text" name="questions[<?= $qCount ?>][text]" value="<?= htmlspecialchars($q['question_text']) ?>" required style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 12px; border-radius: 12px; box-sizing: border-box; margin-bottom:15px;">
                        
                        <label style="display: block; font-size: 11px; color: #ffc107; opacity: 0.6; margin-bottom: 8px; text-transform: uppercase;">💡 Подсказка</label>
                        <input type="text" name="questions[<?= $qCount ?>][hint]" value="<?= htmlspecialchars($q['hint'] ?? '') ?>" placeholder="Подсказка для сотрудника..." style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #ffc107; padding: 10px; border-radius: 12px; box-sizing: border-box; font-size: 13px;">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <?php if(is_array($options)) foreach ($options as $i => $opt): ?>
                            <div style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.02); padding: 10px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                                <input type="radio" name="questions[<?= $qCount ?>][correct]" value="<?= $i ?>" <?= (int)$q['correct_option'] === $i ? 'checked' : '' ?>>
                                <input type="text" name="questions[<?= $qCount ?>][options][]" value="<?= htmlspecialchars($opt) ?>" required style="flex: 1; background: transparent; border: none; color: #fff; font-size: 14px; outline: none;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" onclick="addQuestion()" style="width: 100%; background: rgba(120, 90, 255, 0.05); color: #785aff; border: 2px dashed rgba(120, 90, 255, 0.3); padding: 20px; border-radius: 20px; cursor: pointer; font-weight: 700; margin-top:20px;">+ ДОБАВИТЬ ВОПРОС</button>
    </form>
</div>

<script>
let questionCount = <?= $qCount ?>;
function addQuestion() {
    questionCount++;
    const container = document.getElementById('questionsContainer');
    const qDiv = document.createElement('div');
    qDiv.className = 'question-block';
    qDiv.style.cssText = "background: #16161a; border: 1px solid #222; padding: 25px; border-radius: 24px; margin-bottom: 20px; position: relative;";
    
    let opts = '';
    for (let i = 0; i < 4; i++) {
        opts += `
            <div style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.02); padding: 10px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                <input type="radio" name="questions[${questionCount}][correct]" value="${i}" ${i === 0 ? 'checked' : ''}>
                <input type="text" name="questions[${questionCount}][options][]" placeholder="Вариант ${i+1}" required style="flex: 1; background: transparent; border: none; color: #fff; font-size: 14px; outline: none;">
            </div>`;
    }

    qDiv.innerHTML = `
        <button type="button" onclick="this.parentElement.remove()" style="position: absolute; top: 15px; right: 15px; background: rgba(255,68,68,0.1); color: #ff4444; border: none; width: 30px; height: 30px; border-radius: 8px; cursor: pointer; font-weight: bold;">✕</button>
        <div style="margin-bottom: 20px; padding-right: 40px;">
            <input type="text" name="questions[${questionCount}][text]" required placeholder="Введите текст вопроса..." style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 12px; border-radius: 12px; box-sizing: border-box; margin-bottom:15px;">
            <label style="display: block; font-size: 11px; color: #ffc107; opacity: 0.6; margin-bottom: 8px; text-transform: uppercase;">💡 Подсказка</label>
            <input type="text" name="questions[${questionCount}][hint]" placeholder="Подсказка для сотрудника..." style="width: 100%; background: #0b0b12; border: 1px solid #333; color: #ffc107; padding: 10px; border-radius: 12px; box-sizing: border-box; font-size: 13px;">
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">${opts}</div>`;
    container.appendChild(qDiv);
}
</script>