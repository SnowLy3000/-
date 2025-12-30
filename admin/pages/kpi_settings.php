<?php
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/db.php';
require_once __DIR__.'/../../includes/perms.php';

require_auth();

// Ограничение доступа: только для высшего руководства
require_role('kpi_settings');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ─────────────────────────────
   СОХРАНЕНИЕ НАСТРОЕК
───────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] ?? [] as $key => $val) {
        // Используем ON DUPLICATE KEY UPDATE для компактности таблицы settings
        $stmt = $pdo->prepare("
            INSERT INTO settings (skey, svalue)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)
        ");
        $stmt->execute([$key, trim($val)]);
    }

    // Логируем действие (опционально, если есть система логов)
    header('Location: ?page=kpi_settings&saved=1');
    exit;
}

/* ─────────────────────────────
   ЗАГРУЗКА ДАННЫХ
───────────────────────────── */
$settings = [];
$stmt = $pdo->query("SELECT skey, svalue FROM settings WHERE skey LIKE 'kpi_%'");
foreach ($stmt as $row) {
    $settings[$row['skey']] = $row['svalue'];
}

/* ДЕФОЛТНЫЕ ЗНАЧЕНИЯ (если в базе еще пусто) */
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
    .settings-container { max-width: 800px; margin: 0 auto; }
    
    .st-input { 
        width: 100%; height: 48px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); 
        border-radius: 14px; padding: 0 15px; color: #fff; outline: none; font-size: 14px; transition: 0.3s;
    }
    .st-input:focus { border-color: #785aff; background: rgba(120,90,255,0.08); box-shadow: 0 0 15px rgba(120,90,255,0.1); }

    .settings-section { 
        background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.05); 
        border-radius: 24px; padding: 30px; margin-bottom: 25px; position: relative;
    }
    
    .settings-section h4 { margin-top: 0; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; font-size: 18px; color: #fff; }
    .settings-section h4 i { color: #785aff; font-style: normal; }
    
    .form-group label { display: block; font-size: 10px; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-bottom: 10px; font-weight: 800; letter-spacing: 1px; }

    .grid-levels { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; }

    .alert-success { 
        background: rgba(74, 222, 128, 0.1); color: #4ade80; padding: 20px; border-radius: 18px; 
        margin-bottom: 30px; border: 1px solid rgba(74, 222, 128, 0.2); text-align: center; font-weight: 600;
        animation: slideDown 0.5s ease;
    }

    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

    .btn-save { 
        width: 100%; height: 60px; background: linear-gradient(90deg, #785aff, #b866ff); color: #fff; border: none; border-radius: 18px; 
        font-weight: 800; font-size: 16px; cursor: pointer; transition: 0.3s; margin-top: 10px;
        box-shadow: 0 10px 25px rgba(120,90,255,0.3);
    }
    .btn-save:hover { transform: translateY(-3px); box-shadow: 0 15px 35px rgba(120,90,255,0.4); }

    .info-tag { font-size: 12px; background: rgba(120,90,255,0.1); color: #b866ff; padding: 4px 12px; border-radius: 20px; margin-left: auto; }
</style>

<div class="settings-container">
    <div style="margin-bottom: 35px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h1 style="margin:0; font-size: 32px;">⚙️ Настройки KPI</h1>
            <p class="muted" style="margin-top: 5px;">Управление грейдами и правилами начисления бонусов</p>
        </div>
        <div style="text-align: right;">
            <span class="info-tag">v2.1 Stable</span>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert-success">✨ Настройки успешно применены и вступят в силу немедленно</div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        
        <div class="settings-section">
            <h4><i>01.</i> Статус системы</h4>
            <div class="form-group" style="max-width: 300px;">
                <label>Глобальный переключатель</label>
                <select name="settings[kpi_enabled]" class="st-input">
                    <option value="1" <?= $settings['kpi_enabled']=='1'?'selected':'' ?>>🟢 Система активна</option>
                    <option value="0" <?= $settings['kpi_enabled']=='0'?'selected':'' ?>>🔴 Система отключена</option>
                </select>
            </div>
            <p class="muted" style="font-size: 12px; margin-top: 15px;">Если отключено, сотрудники не будут видеть свои планы и прогресс в личном кабинете.</p>
        </div>

        <div class="settings-section">
            <div style="display: flex; align-items: center; margin-bottom: 25px;">
                <h4 style="margin:0;"><i>02.</i> Карьерная лестница</h4>
                <span class="muted" style="font-size: 12px; margin-left: 15px;">(Грейды)</span>
            </div>
            
            <div class="grid-levels">
                <?php foreach ([0, 5, 10, 15, 20, 30] as $min): ?>
                <div class="form-group">
                    <label>От <?= $min ?>% плана</label>
                    <input type="text" class="st-input" name="settings[kpi_level_<?= $min ?>]" value="<?= h($settings['kpi_level_'.$min]) ?>" placeholder="Название ранга">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="settings-section">
            <h4><i>03.</i> Мотивационная сетка</h4>
            <p class="muted" style="font-size: 12px; margin-bottom: 25px;">Укажите % премии, который прибавляется к зарплате при достижении указанных порогов выполнения плана.</p>
            
            <div class="grid-levels">
                <?php foreach ([100, 110, 120, 130] as $perc): ?>
                <div class="form-group">
                    <label>При <?= $perc ?>% выполнения</label>
                    <div style="position: relative;">
                        <input type="number" class="st-input" name="settings[kpi_bonus_<?= $perc ?>]" value="<?= h($settings['kpi_bonus_'.$perc]) ?>" placeholder="0">
                        <span style="position: absolute; right: 15px; top: 14px; color: rgba(255,255,255,0.2); font-weight: 900;">%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <button type="submit" class="btn-save">💾 СОХРАНИТЬ ИЗМЕНЕНИЯ</button>
        <p class="muted" style="text-align: center; font-size: 11px; margin-top: 20px;">Изменения коснутся всех текущих расчетов в реальном времени.</p>
    </form>
</div>
