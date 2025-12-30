<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/settings.php';

require_auth();

// Проверка прав
require_role('manage_settings_checkin');

/* ===== ЛОГИКА СОХРАНЕНИЯ ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    setting_set('checkin_enabled', isset($_POST['checkin_enabled']) ? '1' : '0');
    setting_set('shift_start_time', $_POST['shift_start_time'] ?? '09:00');
    setting_set('checkin_grace_minutes', (int)($_POST['checkin_grace_minutes'] ?? 0));
    setting_set('sales_require_checkin', isset($_POST['sales_require_checkin']) ? '1' : '0');
    setting_set('checkin_limit_per_day', (int)($_POST['checkin_limit_per_day'] ?? 1));
    setting_set('shift_transfer_enabled', isset($_POST['shift_transfer_enabled']) ? '1' : '0');

    $_SESSION['success'] = 'Настройки check-in сохранены';
    header('Location: /admin/index.php?page=settings_checkin');
    exit;
}

/* ===== ПОЛУЧЕНИЕ ТЕКУЩИХ ЗНАЧЕНИЙ ===== */
// Эти переменные ДОЛЖНЫ быть определены здесь, чтобы не было ошибки "Undefined variable"
$checkin_enabled        = setting('checkin_enabled', '1');
$shift_start_time       = setting('shift_start_time', '09:00');
$checkin_grace_minutes  = setting('checkin_grace_minutes', '10');
$sales_require_checkin  = setting('sales_require_checkin', '1');
$checkin_limit_per_day  = setting('checkin_limit_per_day', '1');
$shift_transfer_enabled = setting('shift_transfer_enabled', '1');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<style>
    .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
    .settings-card { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 20px; padding: 25px; backdrop-filter: blur(10px); transition: 0.3s; }
    .settings-card:hover { border-color: rgba(120, 90, 255, 0.4); background: rgba(255, 255, 255, 0.05); }
    .settings-card h3 { margin-top: 0; margin-bottom: 20px; font-size: 18px; display: flex; align-items: center; gap: 10px; color: #b866ff; }
    .input-group { margin-bottom: 15px; }
    .input-group label { display: block; font-size: 13px; color: rgba(255, 255, 255, 0.5); margin-bottom: 8px; }
    .st-input { width: 100%; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 12px; color: #fff; outline: none; transition: 0.3s; }
    .st-input:focus { border-color: #785aff; background: rgba(120, 90, 255, 0.1); }
    .switch-group { display: flex; align-items: center; justify-content: space-between; padding: 12px; background: rgba(255, 255, 255, 0.02); border-radius: 12px; cursor: pointer; transition: 0.2s; }
    .switch-group:hover { background: rgba(255, 255, 255, 0.05); }
    
    .toggle { position: relative; width: 40px; height: 20px; appearance: none; background: rgba(255,255,255,0.1); outline: none; border-radius: 20px; transition: 0.3s; cursor: pointer; }
    .toggle:checked { background: #785aff; }
    .toggle::before { content: ''; position: absolute; width: 16px; height: 16px; border-radius: 50%; top: 2px; left: 2px; background: #fff; transition: 0.3s; }
    .toggle:checked::before { left: 22px; }

    .btn-save { background: #785aff; color: #fff; border: none; padding: 15px 40px; border-radius: 14px; font-weight: bold; cursor: pointer; transition: 0.3s; width: 100%; max-width: 300px; }
    .btn-save:hover { background: #6344d4; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(120, 90, 255, 0.4); }
    
    .success-msg { background: rgba(0, 200, 81, 0.1); color: #00c851; padding: 15px; border-radius: 15px; margin-bottom: 20px; border: 1px solid rgba(0, 200, 81, 0.2); text-align: center; }
</style>

<div style="margin-bottom: 30px;">
    <h1 style="margin:0; font-size: 28px;">⚙️ Настройки Check-in</h1>
    <p style="color: rgba(255,255,255,0.5); margin: 5px 0 0 0;">Конфигурация дисциплины и правил работы со сменами</p>
</div>

<?php if (!empty($_SESSION['success'])): ?>
    <div class="success-msg">✅ <?= h($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<form method="post">
    <div class="settings-grid">
        
        <div class="settings-card">
            <h3><span>🔌</span> Работа модуля</h3>
            <label class="switch-group">
                <span>Включить систему Check-in</span>
                <input type="checkbox" name="checkin_enabled" class="toggle" <?= $checkin_enabled==='1'?'checked':'' ?>>
            </label>
            <p style="color: rgba(255,255,255,0.3); font-size: 11px; margin-top: 10px;">Если выключено, сотрудники смогут открывать продажи без отметки о прибытии.</p>
        </div>

        <div class="settings-card">
            <h3><span>⏰</span> Регламент времени</h3>
            <div class="input-group">
                <label>Начало рабочей смены</label>
                <input type="time" name="shift_start_time" class="st-input" value="<?= h($shift_start_time) ?>">
            </div>
            <div class="input-group">
                <label>Лимит опоздания (мин)</label>
                <input type="number" min="0" name="checkin_grace_minutes" class="st-input" value="<?= h($checkin_grace_minutes) ?>">
            </div>
        </div>

        <div class="settings-card">
            <h3><span>🚫</span> Дисциплина</h3>
            <label class="switch-group" style="margin-bottom: 15px;">
                <span>Блокировать продажи без Check-in</span>
                <input type="checkbox" name="sales_require_checkin" class="toggle" <?= $sales_require_checkin==='1'?'checked':'' ?>>
            </label>
            <div class="input-group">
                <label>Макс. кол-во Check-in в сутки</label>
                <input type="number" min="1" name="checkin_limit_per_day" class="st-input" value="<?= h($checkin_limit_per_day) ?>">
            </div>
        </div>

        <div class="settings-card">
            <h3><span>🤝</span> Пересменка</h3>
            <label class="switch-group">
                <span>Разрешить передачу смен</span>
                <input type="checkbox" name="shift_transfer_enabled" class="toggle" <?= $shift_transfer_enabled==='1'?'checked':'' ?>>
            </label>
            <p style="color: rgba(255,255,255,0.3); font-size: 11px; margin-top: 10px;">Сотрудники смогут передавать текущую активную смену коллегам.</p>
        </div>

    </div>

    <div style="margin-top: 40px; text-align: center;">
        <button type="submit" class="btn-save">💾 Сохранить настройки</button>
    </div>
</form>
