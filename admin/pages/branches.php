<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

require_auth();

$branches = $pdo->query("SELECT * FROM branches ORDER BY name")->fetchAll();
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<style>
    .branch-manager { 
        display: grid; 
        grid-template-columns: 1fr 380px; 
        gap: 24px; 
        align-items: start;
    }

    /* Анимация появления */
    @keyframes slideIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .st-input { 
        width: 100%; height: 48px; background: rgba(255,255,255,0.03); 
        border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; 
        padding: 0 16px; color: #fff; margin-bottom: 16px; outline: none;
        transition: 0.3s; box-sizing: border-box;
    }
    .st-input:focus { border-color: #785aff; background: rgba(120,90,255,0.06); }

    .branch-list { display: flex; flex-direction: column; gap: 16px; }

    .branch-item { 
        background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); 
        border-radius: 22px; padding: 24px; display: flex; justify-content: space-between; 
        align-items: center; transition: 0.3s ease; animation: slideIn 0.4s ease forwards;
    }
    .branch-item:hover { border-color: rgba(120,90,255,0.3); background: rgba(255,255,255,0.04); }
    
    /* Состояние редактирования */
    .branch-item.edit-mode-active { border-color: #785aff; background: rgba(120,90,255,0.08); }

    .branch-name { font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 8px; display: block; }
    .branch-meta { font-size: 13px; color: rgba(255,255,255,0.4); margin-bottom: 6px; display: flex; align-items: center; gap: 10px; }
    
    .time-badge { 
        background: rgba(120,90,255,0.1); color: #b866ff; padding: 6px 14px; 
        border-radius: 10px; font-size: 12px; font-weight: 800; display: inline-flex; align-items: center; gap: 8px;
    }

    .form-card { 
        position: sticky; top: 20px; background: rgba(255,255,255,0.02); 
        border: 1px solid rgba(255,255,255,0.08); border-radius: 28px; padding: 30px; 
        transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .form-card.edit-active { border-color: #785aff; box-shadow: 0 0 30px rgba(120,90,255,0.15); }

    .btn-submit { 
        width: 100%; height: 55px; background: linear-gradient(90deg, #785aff, #b866ff); 
        color: #fff; border: none; border-radius: 16px; font-weight: 800; font-size: 15px; 
        cursor: pointer; transition: 0.3s; margin-top: 10px;
    }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(120,90,255,0.3); }

    @media (max-width: 1100px) { .branch-manager { grid-template-columns: 1fr; } .form-card { position: static; } }
</style>

<div style="margin-bottom: 35px;">
    <h1 style="margin:0; font-size: 32px;">🏢 Управление филиалами</h1>
    <p class="muted">Настройка локаций, графиков работы и контактных данных</p>
</div>

<div class="branch-manager">
    <div class="branch-list">
        <?php if (!$branches): ?>
            <div class="card" style="text-align: center; padding: 80px; opacity: 0.5;">
                <div style="font-size: 50px; margin-bottom: 20px;">🏢</div>
                У вас пока нет созданных филиалов.
            </div>
        <?php else: ?>
            <?php foreach ($branches as $b): ?>
                <div class="branch-item" id="branch-row-<?= $b['id'] ?>">
                    <div>
                        <span class="branch-name"><?= h($b['name']) ?></span>
                        <div class="branch-meta">📍 <?= h($b['address'] ?: 'Адрес не указан') ?></div>
                        <div class="branch-meta">📞 <?= h($b['phone'] ?: '—') ?></div>
                        <div style="margin-top: 12px;">
                            <span class="time-badge">🕒 <?= substr($b['shift_start_time'],0,5) ?> — <?= substr($b['shift_end_time'],0,5) ?: '23:59' ?></span>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px;">
                        <button class="btn-icon" onclick='editBranch(<?= json_encode($b) ?>)' style="background: rgba(120,90,255,0.1); color: #b866ff; width: 40px; height: 40px; border-radius: 12px; border:none; cursor:pointer;">✏️</button>
                        <form method="post" action="/admin/actions/branch_delete.php" onsubmit="return confirm('Удалить филиал? Это может повлиять на отчеты сотрудников.')">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <button type="submit" style="background: rgba(255,68,68,0.1); color: #ff4444; width: 40px; height: 40px; border-radius: 12px; border:none; cursor:pointer;">🗑️</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="form-card" id="formContainer">
        <h3 id="formTitle" style="margin-top: 0; margin-bottom: 25px; font-size: 22px;">➕ Новый филиал</h3>
        <form method="post" action="/admin/actions/branch_save.php" id="branchForm">
            <input type="hidden" name="id" id="branchId" value="">
            
            <label class="form-label">Название филиала</label>
            <input type="text" name="name" id="f_name" class="st-input" placeholder="Напр: Центр, ТЦ 'Молл'..." required>

            <label class="form-label">Адрес</label>
            <input type="text" name="address" id="f_addr" class="st-input" placeholder="Улица, номер дома">

            <label class="form-label">Телефон</label>
            <input type="text" name="phone" id="f_phone" class="st-input" placeholder="+373 ...">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <label class="form-label">Начало смены</label>
                    <input type="time" name="shift_start_time" id="f_start" class="st-input" value="09:00" required>
                </div>
                <div>
                    <label class="form-label">Конец смены</label>
                    <input type="time" name="shift_end_time" id="f_end" class="st-input" value="18:00">
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">💾 СОХРАНИТЬ ФИЛИАЛ</button>
            
            <button type="button" id="cancelBtn" onclick="resetForm()" style="display:none; width: 100%; background: none; border: none; color: #ff4444; margin-top: 15px; cursor: pointer; font-weight: 700; font-size: 13px;">❌ ОТМЕНИТЬ РЕДАКТИРОВАНИЕ</button>
        </form>
    </div>
</div>

<script>
function editBranch(data) {
    const form = document.getElementById('formContainer');
    form.classList.add('edit-active');
    document.getElementById('formTitle').innerText = '✏️ Редактирование';
    document.getElementById('submitBtn').innerText = '💾 СОХРАНИТЬ ИЗМЕНЕНИЯ';
    document.getElementById('cancelBtn').style.display = 'block';

    document.getElementById('branchId').value = data.id;
    document.getElementById('f_name').value = data.name;
    document.getElementById('f_addr').value = data.address || '';
    document.getElementById('f_phone').value = data.phone || '';
    document.getElementById('f_start').value = data.shift_start_time.substring(0,5);
    document.getElementById('f_end').value = data.shift_end_time ? data.shift_end_time.substring(0,5) : '';

    // Подсветка строки в списке
    document.querySelectorAll('.branch-item').forEach(i => i.classList.remove('edit-mode-active'));
    document.getElementById('branch-row-' + data.id).classList.add('edit-mode-active');
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    const form = document.getElementById('formContainer');
    form.classList.remove('edit-active');
    document.getElementById('formTitle').innerText = '➕ Новый филиал';
    document.getElementById('submitBtn').innerText = '💾 СОХРАНИТЬ ФИЛИАЛ';
    document.getElementById('cancelBtn').style.display = 'none';
    document.getElementById('branchForm').reset();
    document.getElementById('branchId').value = '';
    document.querySelectorAll('.branch-item').forEach(i => i.classList.remove('edit-mode-active'));
}
</script>
