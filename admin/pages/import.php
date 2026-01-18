<?php
// Защита доступа (убедимся, что файл не открыт напрямую)
if (!defined('PDO_DB')) { // Замени на свою константу проверки, если есть
    require_once __DIR__ . '/../../includes/auth.php';
    require_auth();
}
require_role('Admin'); // Или твоя функция проверки прав
?>

<div class="card" style="border-radius: 25px; padding: 30px; background: rgba(255,255,255,0.02);">
    <div style="margin-bottom: 25px;">
        <h2 style="margin:0; font-size: 26px;">📥 Импорт прайс-листа</h2>
        <p class="muted">Загрузите CSV-файл для быстрого обновления цен или добавления новых товаров.</p>
    </div>

    <div style="background: rgba(120, 90, 255, 0.03); border: 2px dashed rgba(120, 90, 255, 0.2); border-radius: 20px; padding: 50px; text-align: center;">
        <input type="file" id="csv_file_input" accept=".csv" style="display: none;" onchange="handleFileSelect()">
        
        <label for="csv_file_input" style="cursor: pointer; display: inline-block;">
            <div style="font-size: 60px; margin-bottom: 15px;">📊</div>
            <div class="btn" style="background: #785aff; padding: 15px 35px; font-weight: 700;">Выбрать файл .csv</div>
        </label>
        
        <div id="file_info" style="margin-top: 20px; font-weight: 600; color: #785aff; display: none;"></div>

        <button type="button" id="btn_start" onclick="startImport()" class="btn" style="display: none; width: 100%; max-width: 400px; margin: 30px auto 0 auto; background: #2ecc71; height: 60px; font-size: 18px; font-weight: 800; border: none; border-radius: 15px; cursor: pointer; transition: 0.3s;">
            🚀 Начать импорт товаров
        </button>
    </div>

    <div style="margin-top: 30px; padding: 20px; background: rgba(255,255,255,0.02); border-radius: 15px; font-size: 13px; line-height: 1.6;">
        <b style="color: #ffbb33;">💡 Требования к файлу:</b><br>
        • Формат файла: <b>CSV</b> (разделитель запятая или точка с запятой).<br>
        • Колонки: <b>1-я — Название товара, 2-я — Розничная цена</b>.<br>
        • Кодировка: UTF-8 или Windows-1251 (система распознает автоматически).<br>
        • Если товар с таким названием уже есть, система просто <b>обновит его цену</b>.
    </div>
</div>

<script>
function handleFileSelect() {
    const input = document.getElementById('csv_file_input');
    const info = document.getElementById('file_info');
    const btn = document.getElementById('btn_start');
    
    if (input.files.length > 0) {
        info.innerText = "Выбран файл: " + input.files[0].name;
        info.style.display = "block";
        btn.style.display = "block";
    }
}

function startImport() {
    const fileInput = document.getElementById('csv_file_input');
    const btn = document.getElementById('btn_start');
    
    if (!fileInput.files[0]) return;

    btn.disabled = true;
    btn.innerText = '⌛ Обработка данных...';

    let formData = new FormData();
    formData.append('file', fileInput.files[0]);

    // Обращаемся к AJAX-обработчику
    fetch('ajax/import_csv.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert('✅ Успешно!\n' + data.message.replace(/<br>/g, '\n'));
            location.reload();
        } else {
            alert('❌ Ошибка: ' + data.message);
            btn.disabled = false;
            btn.innerText = '🚀 Начать импорт товаров';
        }
    })
    .catch(err => {
        console.error(err);
        alert('Критическая ошибка сервера. Убедитесь, что файл ajax/import_csv.php существует.');
        btn.disabled = false;
        btn.innerText = '🚀 Начать импорт товаров';
    });
}
</script>
