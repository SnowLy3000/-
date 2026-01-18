<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/perms.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/settings.php';

require_auth();
require_role('settings_checkin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    setting_set('checkin_enabled', isset($_POST['checkin_enabled']) ? '1' : '0');
    setting_set('shift_start_time', $_POST['shift_start_time'] ?? '09:00');
    setting_set('checkin_grace_minutes', (int)($_POST['checkin_grace_minutes'] ?? 0));
    setting_set('sales_require_checkin', isset($_POST['sales_require_checkin']) ? '1' : '0');
    setting_set('checkin_limit_per_day', (int)($_POST['checkin_limit_per_day'] ?? 1));
    setting_set('shift_transfer_enabled', isset($_POST['shift_transfer_enabled']) ? '1' : '0');
    setting_set('late_fine_show_money', isset($_POST['late_fine_show_money']) ? '1' : '0');
    setting_set('late_fine_enabled', isset($_POST['late_fine_enabled']) ? '1' : '0');
    setting_set('late_fine_per_minute', (float)($_POST['late_fine_per_minute'] ?? 0));
    setting_set('show_late_on_dashboard', isset($_POST['show_late_on_dashboard']) ? '1' : '0');

    $_SESSION['success'] = 'Настройки обновлены';
    echo "<script>window.location.href='?page=settings_checkin';</script>";
    exit;
}

$checkin_enabled        = setting('checkin_enabled', '1');
$shift_start_time       = setting('shift_start_time', '09:00');
$checkin_grace_minutes  = setting('checkin_grace_minutes', '10');
$sales_require_checkin  = setting('sales_require_checkin', '1');
$checkin_limit_per_day  = setting('checkin_limit_per_day', '1');
$shift_transfer_enabled = setting('shift_transfer_enabled', '1');
$late_fine_enabled      = setting('late_fine_enabled', '0');
$late_fine_per_minute   = setting('late_fine_per_minute', '0');
$show_late_on_dashboard = setting('show_late_on_dashboard', '0');
$late_fine_show_money   = setting('late_fine_show_money', '0');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<style>
    .set-wrapper { 
        max-width: 960px; 
        margin: 0 auto; 
        font-family: 'Inter', sans-serif; 
        color: #eee;
        box-sizing: border-box;
    }
    
    /* Сетка 3 колонки с защитой от переполнения */
    .set-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); 
        gap: 15px; 
        margin-top: 20px; 
    }
    
    .set-card { 
        background: rgba(255, 255, 255, 0.02); 
        border: 1px solid rgba(255, 255, 255, 0.06); 
        border-radius: 16px; 
        padding: 16px;
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
    }
    
    .set-card h3 { 
        margin: 0 0 15px 0; 
        font-size: 12px; 
        font-weight: 900; 
        color: #b866ff; 
        text-transform: uppercase; 
        letter-spacing: 1px; 
    }
    
    .f-group { margin-bottom: 12px; width: 100%; box-sizing: border-box; }
    .f-group label { 
        display: block; 
        font-size: 10px; 
        color: rgba(255, 255, 255, 0.3); 
        margin-bottom: 5px; 
        font-weight: 700; 
    }
    
    .st-input { 
        width: 100%; 
        height: 36px; 
        background: #0b0b12; 
        border: 1px solid #222; 
        border-radius: 8px; 
        padding: 0 12px; 
        color: #fff; 
        font-size: 13px; 
        outline: none;
        box-sizing: border-box; /* Важно: паддинг не увеличивает ширину */
        display: block;
    }
    .st-input:focus { border-color: #785aff; }

    .switch-row { 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        padding: 8px 0; 
        cursor: pointer; 
        border-bottom: 1px solid rgba(255,255,255,0.03);
    }
    .switch-row:last-child { border-bottom: none; }
    .switch-row span { font-size: 12px; font-weight: 500; color: #ccc; }

    .toggle { 
        position: relative; 
        width: 36px; 
        height: 18px; 
        appearance: none; 
        background: #222; 
        border-radius: 18px; 
        cursor: pointer; 
        transition: 0.2s; 
        flex-shrink: 0; /* Чтобы не сжимался */
    }
    .toggle:checked { background: #785aff; }
    .toggle::before { 
        content: ""; 
        position: absolute; 
        width: 14px; 
        height: 14px; 
        background: #fff; 
        border-radius: 50%; 
        top: 2px; 
        left: 2px; 
        transition: 0.2s; 
    }
    .toggle:checked::before { left: 20px; }

    .btn-save-main { 
        background: #785aff; 
        color: #fff; 
        border: none; 
        padding: 12px 40px; 
        border-radius: 12px; 
        font-weight: 800; 
        cursor: pointer; 
        font-size: 14px;
        transition: 0.2s;
        box-shadow: 0 5px 15px rgba(120,90,255,0.2);
    }
    .btn-save-main:hover { background: #6648df; transform: translateY(-1px); }

    .toast-success { 
        font-size: 13px; 
        color: #00c851; 
        font-weight: 700; 
        background: rgba(0,200,81,0.05);
        padding: 5px 15px;
        border-radius: 8px;
    }
</style>

<div class="set-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
        <h1 style="margin:0; font-size: 20px; font-weight: 900;">⚙️ Настройки дисциплины</h1>
        <?php if (!empty($_SESSION['success'])): ?>
            <div class="toast-success">✓ <?= h($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
    </div>

    <form method="post">
        <div class="set-grid">
            <div class="set-card">
                <h3>Активность</h3>
                <label class="switch-row">
                    <span>Система Check-in</span>
                    <input type="checkbox" name="checkin_enabled" class="toggle" <?= $checkin_enabled==='1'?'checked':'' ?>>
                </label>
                <label class="switch-row">
                    <span>Блокировка продаж</span>
                    <input type="checkbox" name="sales_require_checkin" class="toggle" <?= $sales_require_checkin==='1'?'checked':'' ?>>
                </label>
            </div>

            <div class="set-card">
                <h3>Тайминг</h3>
                <div class="f-group">
                    <label>Начало смены</label>
                    <input type="time" name="shift_start_time" class="st-input" value="<?= h($shift_start_time) ?>">
                </div>
                <div class="f-group">
                    <label>Опоздание (мин)</label>
                    <input type="number" name="checkin_grace_minutes" class="st-input" value="<?= h($checkin_grace_minutes) ?>">
                </div>
            </div>

            <div class="set-card" style="border-color: rgba(255,75,43,0.15);">
                <h3 style="color: #ff4b2b;">Штрафы</h3>
                <label class="switch-row">
                    <span>Включить штраф</span>
                    <input type="checkbox" name="late_fine_enabled" class="toggle" <?= $late_fine_enabled==='1'?'checked':'' ?>>
                </label>
        <label class="switch-row">
        <span>Показывать сумму (деньги)</span>
        <input type="checkbox" name="late_fine_show_money" class="toggle" value="1" <?= $late_fine_show_money === '1' ? 'checked' : '' ?>>
    </label>
                <div class="f-group">
                    <label>1 мин (MDL)</label>
                    <input type="number" step="0.1" name="late_fine_per_minute" class="st-input" value="<?= h($late_fine_per_minute) ?>">
                </div>
            </div>

            <div class="set-card">
                <h3>Лимиты</h3>
                <div class="f-group">
                    <label>Check-in в сутки</label>
                    <input type="number" name="checkin_limit_per_day" class="st-input" value="<?= h($checkin_limit_per_day) ?>">
                </div>
                <label class="switch-row">
                    <span>Передача смены</span>
                    <input type="checkbox" name="shift_transfer_enabled" class="toggle" <?= $shift_transfer_enabled==='1'?'checked':'' ?>>
                </label>
            </div>

            
            <div class="set-card">
    <h3>Отображение</h3>
    <label class="switch-row">
        <span>Штраф на Dashboard</span>
        <input type="checkbox" name="show_late_on_dashboard" class="toggle" <?= setting('show_late_on_dashboard', '0')==='1'?'checked':'' ?>>
    </label>
    <label class="switch-row">
        <span>Показывать блок опозданий</span>
        <input type="checkbox" name="show_late_on_dashboard" class="toggle" value="1" <?= $show_late_on_dashboard === '1' ? 'checked' : '' ?>>
</div>

            <div style="display: flex; align-items: center; justify-content: center;">
                <button type="submit" class="btn-save-main">СОХРАНИТЬ ВСЁ</button>
            </div>
        </div>
    </form>
</div>