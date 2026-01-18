<?php
// Полный мапинг всех твоих разделов
$descriptions = [
    // --- УПРАВЛЕНИЕ ---
    'adm_dash' => ['text' => 'Главная панель администратора: сводка ключевых метрик системы.', 'link' => '?page=dashboard'],
    'users'    => ['text' => 'Управление командой: роли, доступы и персональные данные сотрудников.', 'link' => '?page=users'],
    'checkin'  => ['text' => 'Дисциплина: настройка штрафов, лимитов и параметров авторизации.', 'link' => '?page=settings_checkin'],
    'branches' => ['text' => 'Торговые точки: управление списком филиалов и привязка персонала.', 'link' => '?page=branches'],
    'pending'  => ['text' => 'Заявки: активация новых аккаунтов и назначение прав доступа.', 'link' => '?page=users_pending'],
    'shifts'   => ['text' => 'График: планирование смен и управление выходами персонала.', 'link' => '?page=shifts'],
    'late_log' => ['text' => 'Опоздания: детальный журнал нарушений временного регламента.', 'link' => '?page=late_control'],
    'roles'    => ['text' => 'Безопасность: настройка уровней доступа и прав в системе.', 'link' => '?page=roles'],
    'monitor'  => ['text' => 'Live: мониторинг текущей активности сотрудников в системе.', 'link' => '?page=staff_monitor'],
    'work_chart'=> ['text' => 'Графики: визуализация рабочего времени и выработки часов.', 'link' => '?page=work_charts'],

    // --- КЛИЕНТЫ ---
    'clients_list' => ['text' => 'CRM: полная база клиентов сети с историей их активности.', 'link' => '?page=contacts'],
    'clients_log'  => ['text' => 'Аудит: история изменений данных клиентов и их статусов.', 'link' => '?page=contacts_log'],

    // --- ЦЕНЫ ---
    'reval'     => ['text' => 'Переоценка: создание актов изменения цен на товары.', 'link' => '?page=price_revaluation'],
    'price_log' => ['text' => 'История: архив всех изменений стоимости в прайс-листе.', 'link' => '?page=price_log'],
    'price_conf'=> ['text' => 'Контроль: мониторинг подтверждения ценников сотрудниками.', 'link' => '?page=price_confirm'],
    'promos'    => ['text' => 'Маркетинг: управление акциями, скидками и спецпредложениями.', 'link' => '?page=promo_const'],

    // --- ПРОДАЖИ ---
    'sales_all' => ['text' => 'Чеки: полный реестр всех пробитых операций в реальном времени.', 'link' => '?page=sales_all'],
    'kpi_table' => ['text' => 'КПЭ: сводная таблица эффективности продаж по всей сети.', 'link' => '?page=report_sales'],
    'sales_det' => ['text' => 'Анализ: глубокая детализация по конкретным товарам в чеках.', 'link' => '?page=report_sales_checks'],
    'returns'   => ['text' => 'Возвраты: аудит отмен, контроль брака и фото-фиксация.', 'link' => '?page=returns_control'],

    // --- KPI ---
    'analytics' => ['text' => 'Центр: графики выручки, маржи и прогноз выполнения планов.', 'link' => '?page=kpi'],
    'net_chart' => ['text' => 'Сеть: сравнение эффективности филиалов на одном графике.', 'link' => '?page=report_sales_chart'],
    'br_stats'  => ['text' => 'Филиалы: рейтинг точек по выполнению плановых показателей.', 'link' => '?page=kpi_branch'],
    'usr_stats' => ['text' => 'Сотрудники: личный KPI, бонусы и % выполнения плана.', 'link' => '?page=kpi_user'],
    'rating'    => ['text' => 'Лидеры: соревновательная таблица лучших продавцов сети.', 'link' => '?page=kpi_chart'],
    'usr_charts'=> ['text' => 'Динамика: графики личных продаж каждого сотрудника.', 'link' => '?page=report_sales_user_chart'],

    // --- ФИНАНСЫ ---
    'salary'    => ['text' => 'Ведомость: расчет текущей зарплаты и бонусов персонала.', 'link' => '?page=kpi_bonus'],
    'sal_arch'  => ['text' => 'Архив: история всех выплат за прошлые периоды.', 'link' => '?page=kpi_bonuses'],
    'plans'     => ['text' => 'Планы: установка целевых показателей на следующий месяц.', 'link' => '?page=kpi_plans'],
    'fix_month' => ['text' => 'Закрытие: фиксация финансовых итогов текущего месяца.', 'link' => '?page=kpi_fix'],
    'kpi_set'   => ['text' => 'Настройки: управление формулами расчета и весами KPI.', 'link' => '?page=kpi_settings'],
    'sal_cats'  => ['text' => 'Категории: привязка процента бонуса к группам товаров.', 'link' => '?page=salary_categories'],
    'products'  => ['text' => 'Товары: каталог номенклатуры и привязка категорий KPI.', 'link' => '?page=products'],
    'import'    => ['text' => 'Импорт: массовая загрузка данных из Excel файлов.', 'link' => '?page=import'],
];
?>

<style>
    .dash-wrapper { font-family: 'Inter', sans-serif; color: #fff; max-width: 1200px; margin: 0 auto; }
    
    /* Заголовок */
    .dash-head { margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; }
    .dash-head h1 { margin: 0; font-size: 24px; font-weight: 900; }
    .dash-head p { margin: 5px 0 0 0; opacity: 0.4; font-size: 13px; }

    /* Плотная сетка категорий */
    .cat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 20px; }
    .cat-card { 
        background: #111118; border: 1px solid #1f1f23; padding: 15px 10px; border-radius: 18px; 
        text-align: center; cursor: pointer; transition: 0.2s;
    }
    .cat-card:hover { border-color: #785aff; background: #16161a; transform: translateY(-3px); }
    .cat-card i { font-size: 24px; display: block; margin-bottom: 8px; }
    .cat-card b { font-size: 11px; text-transform: uppercase; color: #555; letter-spacing: 0.5px; }
    .cat-card.active { border-color: #785aff; background: rgba(120, 90, 255, 0.05); }
    .cat-card.active b { color: #785aff; }

    /* Компактное подменю */
    .sub-box { 
        background: rgba(0,0,0,0.2); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05);
        max-height: 0; overflow: hidden; transition: 0.3s ease-out;
        display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;
    }
    .sub-box.active { padding: 15px; max-height: 500px; margin-bottom: 20px; }

    .sub-item { 
        padding: 6px 14px; background: #16161a; border-radius: 10px; 
        font-size: 11px; color: #ccc; cursor: pointer; border: 1px solid transparent; transition: 0.2s;
    }
    .sub-item:hover { border-color: #785aff; color: #fff; background: rgba(120, 90, 255, 0.1); }

    /* Инфо-панель */
    .info-pane { 
        background: #0b0b12; border: 1px solid #1f1f23; padding: 20px; 
        border-radius: 24px; display: flex; align-items: center; gap: 20px;
    }
    #info-text { font-size: 13px; color: #82828e; flex: 1; margin: 0; line-height: 1.4; }
    #info-title { color: #fff; font-weight: 900; font-size: 15px; text-transform: uppercase; margin-bottom: 5px; display: block; }
    
    .go-btn {
        background: #785aff; color: #fff; padding: 10px 25px; border-radius: 12px; 
        font-weight: 800; text-decoration: none; display: none; font-size: 12px;
        box-shadow: 0 5px 15px rgba(120, 90, 255, 0.3); transition: 0.2s;
    }
    .go-btn:hover { background: #6648df; transform: scale(1.05); }
</style>

<div class="dash-wrapper">
    <div class="dash-head">
        <div>
            <h1>Command Center KUB</h1>
            <p>Единый интерфейс управления всеми бизнес-процессами</p>
        </div>
        <div style="font-size: 11px; font-weight: 800; color: #785aff; background: rgba(120,90,255,0.1); padding: 5px 12px; border-radius: 8px;">
            STABLE 3.0
        </div>
    </div>

    <div class="cat-grid">
        <div class="cat-card" id="b-mgmt" onclick="openSection('mgmt')"><i>🛡️</i><b>Управление</b></div>
        <div class="cat-card" id="b-clients" onclick="openSection('clients')"><i>👥</i><b>Клиенты</b></div>
        <div class="cat-card" id="b-price" onclick="openSection('price')"><i>🔄</i><b>Цены</b></div>
        <div class="cat-card" id="b-sales" onclick="openSection('sales')"><i>🧾</i><b>Продажи</b></div>
        <div class="cat-card" id="b-kpi" onclick="openSection('kpi')"><i>🎯</i><b>KPI</b></div>
        <div class="cat-card" id="b-fin" onclick="openSection('fin')"><i>💵</i><b>Финансы</b></div>
    </div>

    <div id="mgmt" class="sub-box">
        <div class="sub-item" onclick="sh('adm_dash','📊 Главная')">📊 Главная</div>
        <div class="sub-item" onclick="sh('monitor','🟢 Online')">🟢 Online</div>
        <div class="sub-item" onclick="sh('users','🛡️ Сотрудники')">🛡️ Сотрудники</div>
        <div class="sub-item" onclick="sh('checkin','🔧 Check-in')">🔧 Check-in</div>
        <div class="sub-item" onclick="sh('branches','🏢 Филиалы')">🏢 Филиалы</div>
        <div class="sub-item" onclick="sh('pending','⏳ Заявки')">⏳ Заявки</div>
        <div class="sub-item" onclick="sh('shifts','🗓️ График')">🗓️ График</div>
        <div class="sub-item" onclick="sh('late_log','⏰ Опоздания')">⏰ Опоздания</div>
        <div class="sub-item" onclick="sh('work_chart','🕘 Рабочие часы')">🕘 Рабочие часы</div>
        <div class="sub-item" onclick="sh('roles','🔑 Доступ')">🔑 Доступ</div>
    </div>

    <div id="clients" class="sub-box">
        <div class="sub-item" onclick="sh('clients_list','📋 База клиентов')">📋 Список клиентов</div>
        <div class="sub-item" onclick="sh('clients_log','📜 История базы')">📜 История изменений</div>
    </div>

    <div id="price" class="sub-box">
        <div class="sub-item" onclick="sh('reval','🔄 Переоценка')">🔄 Новая переоценка</div>
        <div class="sub-item" onclick="sh('price_log','📜 Журнал цен')">📜 Журнал изменений</div>
        <div class="sub-item" onclick="sh('price_conf','✅ Контроль цен')">✅ Подтверждение</div>
        <div class="sub-item" onclick="sh('promos','🔥 Скидки')">🔥 Акции и скидки</div>
    </div>

    <div id="sales" class="sub-box">
        <div class="sub-item" onclick="sh('sales_all','🧾 Журнал чеков')">🧾 Все чеки</div>
        <div class="sub-item" onclick="sh('kpi_table','📋 КПЭ')">📋 Таблица КПЭ</div>
        <div class="sub-item" onclick="sh('sales_det','🔍 Детализация')">🔍 Детализация</div>
        <div class="sub-item" onclick="sh('returns','🔙 Возвраты')">🔙 Возвраты</div>
    </div>

    <div id="kpi" class="sub-box">
        <div class="sub-item" onclick="sh('analytics','🎯 Аналитика')">🎯 Аналитика</div>
        <div class="sub-item" onclick="sh('net_chart','📈 График сети')">📈 График сети</div>
        <div class="sub-item" onclick="sh('br_stats','🏢 По филиалам')">🏢 По филиалам</div>
        <div class="sub-item" onclick="sh('usr_stats','👤 По сотрудникам')">👤 По сотрудникам</div>
        <div class="sub-item" onclick="sh('rating','📊 Рейтинг')">📊 Рейтинг</div>
        <div class="sub-item" onclick="sh('usr_charts','📊 Графики')">📊 Графики продаж</div>
    </div>

    <div id="fin" class="sub-box">
        <div class="sub-item" onclick="sh('salary','💵 Ведомость')">💵 Ведомость (Тек)</div>
        <div class="sub-item" onclick="sh('sal_arch','📒 Архив выплат')">📒 Архив выплат</div>
        <div class="sub-item" onclick="sh('plans','🏁 Планы')">🏁 Планы</div>
        <div class="sub-item" onclick="sh('fix_month','🔒 Фиксация')">🔒 Фиксация месяца</div>
        <div class="sub-item" onclick="sh('kpi_set','⚙️ Параметры')">⚙️ Параметры KPI</div>
        <div class="sub-item" onclick="sh('sal_cats','💳 Категории')">💳 Категории ЗП</div>
        <div class="sub-item" onclick="sh('products','📦 Товары')">📦 Товары</div>
        <div class="sub-item" onclick="sh('import','📥 Импорт')">📥 Импорт Excel</div>
    </div>

    <div class="info-pane">
        <div style="flex: 1;">
            <span id="info-title">Центр навигации KUB</span>
            <p id="info-text">Выберите необходимый модуль для перехода к управлению или получения оперативной сводки.</p>
        </div>
        <a href="#" id="info-btn" class="go-btn">ЗАПУСТИТЬ →</a>
    </div>
</div>

<script>
    const dbData = <?php echo json_encode($descriptions); ?>;

    function openSection(id) {
        document.querySelectorAll('.sub-box').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.cat-card').forEach(el => el.classList.remove('active'));
        
        document.getElementById(id).classList.add('active');
        document.getElementById('b-' + id).classList.add('active');
    }

    function sh(key, title) {
        const item = dbData[key];
        const btn = document.getElementById('info-btn');
        
        document.getElementById('info-title').innerText = title;
        document.getElementById('info-text').innerText = item ? item.text : "Описание скоро будет добавлено.";
        
        if (item && item.link) {
            btn.href = item.link;
            btn.style.display = 'block';
        } else {
            btn.style.display = 'none';
        }
    }
</script>