<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

$test_id = (int)($_GET['id'] ?? 0);
$user = current_user();

$stmt = $pdo->prepare("SELECT * FROM academy_tests WHERE id = ?");
$stmt->execute([$test_id]);
$test = $stmt->fetch();
if (!$test) die("Тест не найден");

$stmt = $pdo->prepare("SELECT * FROM academy_questions WHERE test_id = ?");
$stmt->execute([$test_id]);
$questions = $stmt->fetchAll();
$totalQ = count($questions);
?>

<style>
    .test-wrapper { max-width: 650px; margin: 0 auto; padding: 20px; }
    .q-step { display: none; }
    .q-step.active { display: block; animation: fadeIn 0.4s ease; }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    .progress-container { height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; margin-bottom: 30px; position: relative; }
    .progress-bar { height: 100%; background: linear-gradient(90deg, #785aff, #b866ff); border-radius: 10px; transition: 0.4s; box-shadow: 0 0 15px rgba(120, 90, 255, 0.4); }

    .opt-label { 
        display: flex; align-items: center; gap: 15px; background: rgba(255,255,255,0.02); 
        padding: 18px; border-radius: 18px; border: 1px solid rgba(255,255,255,0.05); 
        cursor: pointer; transition: 0.2s; margin-bottom: 12px;
    }
    .opt-label:hover { background: rgba(255,255,255,0.04); border-color: rgba(120, 90, 255, 0.2); }
    .opt-label.selected { border-color: #785aff !important; background: rgba(120, 90, 255, 0.08) !important; }

    .hint-btn { background: rgba(255, 193, 7, 0.1); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.2); padding: 8px 15px; border-radius: 10px; cursor: pointer; font-size: 12px; font-weight: 700; margin-top: 15px; display: inline-flex; align-items: center; gap: 5px; }
    .hint-text { display: none; background: rgba(255, 193, 7, 0.05); padding: 15px; border-radius: 12px; margin-top: 10px; border-left: 3px solid #ffc107; font-size: 13px; color: #ffc107; }
</style>

<div class="test-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
        <span style="font-size: 12px; font-weight: 800; opacity: 0.5; text-transform: uppercase;">
            Вопрос <span id="currentIdx">1</span> из <?= $totalQ ?>
        </span>
        <span id="percentText" style="font-size: 12px; color: #785aff; font-weight: 800;">0%</span>
    </div>
    <div class="progress-container">
        <div class="progress-bar" id="pBar" style="width: 0%"></div>
    </div>

    <form id="quizForm" action="/cabinet/actions/complete_test.php" method="POST">
        <input type="hidden" name="test_id" value="<?= $test['id'] ?>">

        <?php foreach ($questions as $i => $q): 
            $options = json_decode($q['options'], true);
        ?>
            <div class="q-step <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>">
                <h2 style="font-size: 22px; line-height: 1.4; margin-bottom: 25px; color: #fff;"><?= htmlspecialchars($q['question_text']) ?></h2>

                <div class="options-grid">
                    <?php if(is_array($options)) foreach ($options as $idx => $opt): ?>
                        <label class="opt-label">
                            <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $idx ?>" required style="display: none;" onchange="markSelected(this)">
                            <div class="radio-dot" style="width: 20px; height: 20px; border: 2px solid rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <div class="inner-dot" style="width: 10px; height: 10px; background: #785aff; border-radius: 50%; display: none;"></div>
                            </div>
                            <span style="font-size: 16px; color: #eee;"><?= htmlspecialchars($opt) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($q['hint'])): ?>
                    <div class="hint-btn" onclick="toggleHint(this)">💡 Подсказка</div>
                    <div class="hint-text"><?= htmlspecialchars($q['hint']) ?></div>
                <?php endif; ?>

                <div style="margin-top: 40px; display: flex; gap: 15px;">
                    <?php if ($i > 0): ?>
                        <button type="button" onclick="prevStep()" style="flex: 1; background: rgba(255,255,255,0.05); color: #fff; border: none; padding: 18px; border-radius: 18px; cursor: pointer; font-weight: 700;">Назад</button>
                    <?php endif; ?>
                    
                    <?php if ($i < $totalQ - 1): ?>
                        <button type="button" onclick="nextStep()" style="flex: 2; background: #785aff; color: #fff; border: none; padding: 18px; border-radius: 18px; cursor: pointer; font-weight: 800;">Далее</button>
                    <?php else: ?>
                        <button type="submit" style="flex: 2; background: #00ff88; color: #000; border: none; padding: 18px; border-radius: 18px; cursor: pointer; font-weight: 900;">ЗАВЕРШИТЬ</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </form>
</div>

<script>
let currentStep = 0;
const totalSteps = <?= $totalQ ?>;

// Инициализация прогресса при загрузке
document.addEventListener('DOMContentLoaded', updateProgress);

function updateProgress() {
    const progress = ((currentStep + 1) / totalSteps) * 100;
    document.getElementById('pBar').style.width = progress + '%';
    document.getElementById('percentText').innerText = Math.round(progress) + '%';
    document.getElementById('currentIdx').innerText = currentStep + 1;
}

function nextStep() {
    const currentBlock = document.querySelector('.q-step.active');
    const checked = currentBlock.querySelector('input:checked');

    if (!checked) {
        alert('Выберите ответ!');
        return;
    }

    currentBlock.classList.remove('active');
    currentStep++;
    const nextBlock = document.querySelector(`.q-step[data-index="${currentStep}"]`);
    nextBlock.classList.add('active');
    updateProgress();
}

function prevStep() {
    const currentBlock = document.querySelector('.q-step.active');
    currentBlock.classList.remove('active');
    currentStep--;
    const prevBlock = document.querySelector(`.q-step[data-index="${currentStep}"]`);
    prevBlock.classList.add('active');
    updateProgress();
}

function markSelected(input) {
    const parent = input.closest('.q-step');
    // Снимаем выделение со всех в текущем блоке
    parent.querySelectorAll('.opt-label').forEach(label => {
        label.classList.remove('selected');
        label.querySelector('.inner-dot').style.display = 'none';
        label.querySelector('.radio-dot').style.borderColor = 'rgba(255,255,255,0.1)';
    });
    // Выделяем текущий
    const currentLabel = input.closest('.opt-label');
    currentLabel.classList.add('selected');
    currentLabel.querySelector('.inner-dot').style.display = 'block';
    currentLabel.querySelector('.radio-dot').style.borderColor = '#785aff';
}

function toggleHint(btn) {
    const text = btn.nextElementSibling;
    text.style.display = (text.style.display === 'block') ? 'none' : 'block';
}
</script>