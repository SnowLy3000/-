<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_role('manage_academy');

$topic_id = (int)($_GET['topic_id'] ?? 0);
if (!$topic_id) exit('Topic not found');

$stmt = $pdo->prepare("SELECT title FROM academy_topics WHERE id = ?");
$stmt->execute([$topic_id]);
$topic_title = $stmt->fetchColumn();
?>

<style>
    .lesson-form-wrap { max-width: 800px; margin: 0 auto; color: #fff; font-family: 'Inter', sans-serif; }
    .form-card { background: #16161a; border: 1px solid #222; padding: 30px; border-radius: 24px; }
    .st-label { display: block; margin-bottom: 8px; font-size: 11px; color: #82828e; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; }
    .st-input { width: 100%; background: #0b0b12; border: 1px solid #333; color: #fff; padding: 12px; border-radius: 12px; margin-bottom: 20px; box-sizing: border-box; outline: none; }
    .st-input:focus { border-color: #785aff; }
    
    /* Toolbar & Editor */
    .editor-toolbar { margin-bottom: 10px; display: flex; gap: 5px; background: #0b0b12; padding: 8px; border-radius: 10px; border: 1px solid #222; }
    .tool-btn { background: #222; border: 1px solid #333; color: #fff; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: 0.2s; }
    .tool-btn:hover { background: #785aff; border-color: #785aff; }
    
    #editor-container { background: #0b0b12; border: 1px solid #333; border-radius: 12px; min-height: 350px; color: #fff; padding: 20px; margin-bottom: 20px; outline: none; line-height: 1.6; }
    #editor-container h3 { color: #785aff; margin-top: 0; }

    .btn-save { background: #785aff; color: #fff; border: none; padding: 16px; border-radius: 16px; font-weight: 800; cursor: pointer; width: 100%; font-size: 15px; box-shadow: 0 10px 20px rgba(120, 90, 255, 0.2); }
</style>

<div class="lesson-form-wrap">
    <div style="margin-bottom: 25px;">
        <h2 style="margin: 0;">📖 Создание урока</h2>
        <p style="opacity: 0.4; font-size: 13px;">Тема: <?= htmlspecialchars($topic_title) ?></p>
    </div>

    <form action="/admin/actions/academy_lesson_save.php" method="post" id="lessonForm">
        <input type="hidden" name="topic_id" value="<?= $topic_id ?>">
        
        <div class="form-card">
            <label class="st-label">Название урока</label>
            <input type="text" name="title" class="st-input" placeholder="Введите заголовок..." required>

            <label class="st-label">Видео (YouTube ID или ссылка)</label>
            <input type="text" name="video_url" class="st-input" placeholder="Например: dQw4w9WgXcQ">

            <label class="st-label">Содержание урока</label>
            <div class="editor-toolbar">
                <button type="button" class="tool-btn" onclick="execCmd('bold')"><b>B</b></button>
                <button type="button" class="tool-btn" onclick="execCmd('italic')"><i>I</i></button>
                <button type="button" class="tool-btn" onclick="execCmd('insertUnorderedList')">• Список</button>
                <button type="button" class="tool-btn" onclick="execCmd('formatBlock', 'h3')">Заголовок</button>
            </div>
            <div id="editor-container" contenteditable="true" placeholder="Начните писать здесь..."></div>
            
            <input type="hidden" name="content" id="contentInput">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <label class="st-label">Порядок (Sort)</label>
                    <input type="number" name="sort_order" class="st-input" value="0">
                </div>
            </div>

            <button type="submit" class="btn-save">ОПУБЛИКОВАТЬ УРОК</button>
        </div>
    </form>
</div>

<script>
    function execCmd(cmd, value = null) {
        document.execCommand(cmd, false, value);
    }

    document.getElementById('lessonForm').onsubmit = function() {
        const html = document.getElementById('editor-container').innerHTML;
        if(html.trim() === "") {
            alert("Пожалуйста, заполните содержание урока");
            return false;
        }
        document.getElementById('contentInput').value = html;
    };
</script>