<?php
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/perms.php';

require_auth();
require_role('kpi_settings');

if (!function_exists('h')) {
    function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/* --- СОХРАНЕНИЕ --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] ?? [] as $key => $val) {
        $stmt = $pdo->prepare("
            INSERT INTO settings (skey, svalue)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)
        ");
        $stmt->execute([$key, trim($val)]);
    }
    
    // ВМЕСТО PHP header() ИСПОЛЬЗУЕМ JS РЕДИРЕКТ
    echo '<script>window.location.href = "?page=kpi_settings&saved=1";</script>';
    exit;
}


/* --- ЗАГРУЗКА --- */
$settings = [];
$stmt = $pdo->query("SELECT skey, svalue FROM settings WHERE skey LIKE 'kpi_%'");
foreach ($stmt as $row) { $settings[$row['skey']] = $row['svalue']; }

$defaults = [
    'kpi_enabled' => '1',
    'kpi_level_0'  => 'Стажёр',
    'kpi_level_5'  => 'Новичок',
    'kpi_level_10' => 'Уверенный',
    'kpi_level_15' => 'Профессионал',
    'kpi_level_20' => 'Эксперт',
    'kpi_level_30' => 'Лидер',
    'kpi_bonus_100' => '0',
    'kpi_bonus_110' => '10',
    'kpi_bonus_120' => '20',
    'kpi_bonus_130' => '30',
];
$settings = array_merge($defaults, $settings);
?>

<style>
    .set-container { max-width: 850px; margin: 0 auto; font-family: 'Inter', sans-serif; color: #fff; padding: 10px; }
    
    .set-section { 
        background: rgba(255, 255, 255, 0.02); 
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 20px; 
        padding: 25px; 
        margin-bottom: 25px;
    }

    .set-title { font-size: 16px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .set-title i { color: #785aff; font-style: normal; opacity: 0.6; }

    /* Исправленная сетка: элементы больше не будут накладываться */
    .set-grid { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
        gap: 20px; 
    }

    .f-group { display: flex; flex-direction: column; gap: 8px; }
    .f-group label { 
        display: block; 
        font-size: 10px; 
        font-weight: 800; 
        color: rgba(255,255,255,0.4); 
        text-transform: uppercase; 
        letter-spacing: 0.5px;
    }
    
    .st-input { 
        width: 100%; 
        height: 44px; 
        background: #0b0b12; 
        border: 1px solid #333; 
        border-radius: 12px; 
        padding: 0 15px; 
        color: #fff; 
        font-size: 14px; 
        outline: none; 
        transition: border-color 0.2s;
        box-sizing: border-box; /* Важно, чтобы padding не расширял блок */
    }
    .st-input:focus { border-color: #785aff; }

    .btn-save { 
        width: 100%; 
        height: 55px; 
        background: #785aff; 
        color: #fff; 
        border: none; 
        border-radius: 15px; 
        font-weight: 800; 
        font-size: 16px; 
        cursor: pointer; 
        transition: 0.2s;
        margin-top: 10px;
    }
    .btn-save:hover { background: #6648df; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(120,90,255,0.2); }

    .alert-saved { 
        background: rgba(124, 255, 107, 0.1); 
        color: #7CFF6B; 
        padding: 15px; 
        border-radius: 12px; 
        border: 1px solid rgba(124, 255, 107, 0.2); 
        margin-bottom: 25px; 
        text-align: center; 
        font-weight: 700; 
    }
</style>

<div class="set-container">
    <div style="margin-bottom: 30px;">
        <h1 style="margin:0; font-size: 26px; font-weight: 900;">⚙️ Настройки KPI</h1>
        <p style="margin:5px 0 0 0; font-size: 14px; opacity: 0.5;">Конфигурация уровней и бонусной сетки</p>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert-saved">✨ Настройки успешно сохранены</div>
    <?php endif; ?>

    <form method="post">
        <div class="set-section">
            <div class="set-title"><i>01</i> Работа системы</div>
            <div class="f-group" style="max-width: 300px;">
                <label>Глобальный статус</label>
                <select name="settings[kpi_enabled]" class="st-input">
                    <option value="1" <?= $settings['kpi_enabled']=='1'?'selected':'' ?>>Включена</option>
                    <option value="0" <?= $settings['kpi_enabled']=='0'?'selected':'' ?>>Выключена</option>
                </select>
            </div>
        </div>

        <div class="set-section">
            <div class="set-title"><i>02</i> Карьерные грейды</div>
            <div class="set-grid">
                <?php foreach ([0, 5, 10, 15, 20, 30] as $min): ?>
                <div class="f-group">
                    <label>От <?= $min ?>% плана</label>
                    <input type="text" class="st-input" name="settings[kpi_level_<?= $min ?>]" value="<?= h($settings['kpi_level_'.$min]) ?>">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="set-section" style="border-color: rgba(124, 255, 107, 0.2);">
            <div class="set-title" style="color: #7CFF6B;"><i>03</i> Сетка премий</div>
            <div class="set-grid">
                <?php foreach ([100, 110, 120, 130] as $perc): ?>
                <div class="f-group">
                    <label>При <?= $perc ?>% KPI</label>
                    <div style="position: relative;">
                        <input type="number" class="st-input" name="settings[kpi_bonus_<?= $perc ?>]" value="<?= h($settings['kpi_bonus_'.$perc]) ?>">
                        <span style="position: absolute; right: 15px; top: 12px; opacity: 0.3; font-weight: 800;">%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn-save">💾 СОХРАНИТЬ ИЗМЕНЕНИЯ</button>
    </form>
</div>