<?php
session_start();
require_once 'config.php';
require_once 'db.php';

// Проверка на права администратора
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== ROLE_ADMIN && $_SESSION['role'] !== ROLE_MAIN_ADMIN)) {
    header("Location: index.php");
    exit();
}

$current_theme = $_SESSION['theme'] ?? DEFAULT_THEME; 
$is_main_admin = $_SESSION['role'] === ROLE_MAIN_ADMIN;
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Veaimpex — KUB.MD — Admin</title>
  <link rel="stylesheet" href="styles/main.css">
  <link rel="stylesheet" href="styles/admin.css"> 
  <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
</head>
<body class="<?php echo htmlspecialchars($current_theme); ?>">

  <button class="btn burger-menu-btn" id="sidebarToggle">
    <span class="burger-icon">☰</span>
    <span class="burger-text">Меню</span>
  </button> 
  
  <header class="site-header">
    <div class="logo-row">
      <img src="<?php echo LOGO_PATH; ?>" alt="Veaimpex" class="logo">
    </div>
    <h1>Админ-панель инструкций</h1>
    <div class="header-actions-admin">
        <a class="btn secondary" href="index.php">← На сайт (Сотрудник)</a>
        <a href="auth.php?logout" class="btn primary">🚪 Выход</a> 
    </div>
  </header>

  <main class="container">
    <aside class="sidebar" id="mainSidebar"> 
        <h2>Разделы Админки</h2>
        <div class="categories">
            <button class="category active" data-target="instructionsContent" id="menuInstructions">📁 Инструкции</button>
            <button class="category" data-target="questionsContent" id="menuQuestions">❓ Вопросы & Тесты</button>
            <button class="category" data-target="quizResultsContent" id="menuQuizResults">📊 Результаты тестов</button> <button class="category" data-target="employeesContent" id="menuEmployees">👥 Сотрудники</button>
            
            <button class="category" data-target="attendanceContent" id="menuAttendance">✅ Отметки (Посещаемость)</button> 
            <button class="category" data-target="settingsContent" id="menuSettings">⚙️ Настройки (Тесты/Таймер)</button> 
            <?php if ($is_main_admin) : ?>
                <button class="category" data-target="adminsContent" id="menuAdmins">👑 Администраторы</button> 
            <?php endif; ?>
        </div>
    </aside>
    
    <section class="content content-area"> 
        
        <div id="instructionsContent" class="admin-content active">
             <h3>Управление Инструкциями</h3>
             <div class="editor-sub-container">
                <div class="groups-sidebar">
                    <button class="btn primary" id="addGroup">➕ Добавить Группу</button>
                    <div id="groupsContainer" class="groups">
                        <p>Загрузка групп...</p>
                    </div>
                </div>
                <div class="subtopics-area">
                    <button class="btn primary" id="addSubtopic" disabled>➕ Добавить Подтему</button>
                    <div id="subtopicsGrid" class="subtopics-grid">
                        <p>Выберите группу для отображения подтем.</p>
                    </div>
                </div>
             </div>
        </div>

        <div id="questionsContent" class="admin-content hidden quiz-drill-down">
            <h3>Управление Вопросами</h3>
            <div id="questionGroupsContainer" class="groups">
                 <p>Загрузка категорий вопросов...</p>
            </div>
            <div id="questionsGrid" class="subtopics-grid">
                <p>Выберите категорию вопросов.</p>
            </div>
        </div>
        
        <div id="quizResultsContent" class="admin-content hidden">
             <h3>📊 Результаты прохождения тестов и экзаменов</h3>
             <table class="data-table">
                 <thead>
                    <tr>
                        <th>ID</th>
                        <th>Сотрудник</th>
                        <th>Тип</th>
                        <th>Счет</th>
                        <th>%</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody id="quizResultsTableBody">
                    <tr><td colspan="6">Нажмите на раздел для загрузки данных.</td></tr>
                </tbody>
             </table>
        </div>

        <div id="employeesContent" class="admin-content hidden">
            <h3>Управление Сотрудниками</h3>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Телефон (Логин)</th>
                        <th>Дата рождения</th>
                        <th>Дата регистрации</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody id="employeesTableBody">
                    <tr><td colspan="6">Загрузка данных...</td></tr>
                </tbody>
            </table>
        </div>
        
        <div id="attendanceContent" class="admin-content hidden">
             <h3>Отчет по Отметкам Сотрудников</h3>
             <button id="addBranchBtn" class="btn secondary" style="margin-bottom: 15px;">➕ Добавить Филиал</button>
             <div class="filter-controls">
                <label for="filterDate">Дата:</label>
                <input type="date" id="filterDate" value="<?php echo date('Y-m-d'); ?>">
                <label for="filterBranch">Филиал:</label>
                <select id="filterBranch">
                    <option value="">Все филиалы</option>
                    </select>
                <button id="refreshAttendance" class="btn primary">Показать</button>
             </div>
             <table class="data-table">
                 <thead>
                    <tr>
                        <th>Филиал</th>
                        <th>ФИО Сотрудника</th>
                        <th>Телефон</th>
                        <th>Время Отметки</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody">
                    <tr><td colspan="5">Выберите дату для просмотра отметок.</td></tr>
                </tbody>
             </table>
        </div>

        <div id="settingsContent" class="admin-content hidden">
             <h3>Настройки Тестов и Экзаменов</h3>
             <div class="settings-form-group">
                <h4>Настройки Теста</h4>
                <label for="quizCount">Количество случайных вопросов в Тесте:</label>
                <input type="number" id="quizCount" min="5" max="100" placeholder="20" required>
             </div>
             <div class="settings-form-group">
                <h4>Настройки Экзамена</h4>
                <label for="examTimer">Таймер Экзамена (минуты):</label>
                <input type="number" id="examTimer" min="10" max="180" placeholder="60" required>
             </div>
             <button id="saveSettingsBtn" class="btn primary" style="margin-top: 20px;">💾 Сохранить Настройки</button>
             <div id="settingsMessage" style="margin-top: 10px;"></div>
        </div>

        <?php if ($is_main_admin) : ?>
            <div id="adminsContent" class="admin-content hidden">
                <h3>Управление Администраторами</h3>
                <form id="addAdminForm" style="margin-bottom: 20px; padding: 15px; border: 1px solid var(--border-color); border-radius: 5px;">
                    <h4>Добавить нового Администратора</h4>
                    <input type="text" id="newAdminLogin" placeholder="Логин (например, user123)" required style="width: 48%; margin-right: 2%;">
                    <input type="password" id="newAdminPassword" placeholder="Пароль" required style="width: 48%;">
                    <button type="submit" class="btn primary">➕ Добавить Администратора</button>
                </form>
                
                <h4>Список Администраторов</h4>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>Роль</th>
                            <th>Дата создания</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody id="adminsTableBody">
                        <tr><td colspan="5">Загрузка данных...</td></tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
    </section>
  </main>

    <div id="quillEditorModal" class="modal large-modal hidden">
        <div class="modal-content">
            <h3 id="quillModalTitle">Редактирование подтемы</h3>
            <form id="quillEditorForm">
                <input type="hidden" id="quillSubtopicId" name="id">
                <input type="hidden" id="quillSubtopicGroupId" name="group_id">
                
                <div class="form-group">
                    <label for="quillTitle">Заголовок подтемы:</label>
                    <input type="text" id="quillTitle" name="title" required>
                </div>

                <div class="form-group">
                    <label>Контент инструкции (Quill Editor):</label>
                    <div id="quillEditorContainer" style="height: 300px;"></div>
                </div>
                
                <div class="form-group">
                    <label for="quillImages">Пути к изображениям (JSON массив):</label>
                    <input type="text" id="quillImages" name="images" placeholder='["assets/img1.png", "assets/img2.png"]'>
                </div>
                
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn primary">💾 Сохранить Инструкцию</button>
                    <button type="button" class="btn secondary" onclick="document.getElementById('quillEditorModal').classList.add('hidden');">Отмена</button>
                </div>
            </form>
        </div>
    </div>

    <div id="crudModal" class="modal hidden">
        <div class="modal-content small-modal">
            <h3 id="crudModalTitle"></h3>
            <form id="crudForm">
                <input type="hidden" id="crudActionType" value="">
                <input type="hidden" id="crudTargetId" value="">
                <input type="hidden" id="crudGroupId" value=""> 

                <div id="fieldsContainer">
                    </div>
                
                <div class="form-actions" style="margin-top: 20px;">
                    <button type="submit" class="btn primary" id="crudSubmitBtn">Сохранить</button>
                    <button type="button" class="btn secondary" onclick="document.getElementById('crudModal').classList.add('hidden');">Отмена</button>
                </div>
            </form>
        </div>
    </div>
    
    <div id="themeEditorModal" class="modal hidden">
        <div class="modal-content small-modal">
            <h3>🎨 Редактор темы</h3>
            <div id="themePalette">
                <p>Загрузка палитры...</p>
            </div>
            <div class="form-actions" style="margin-top: 20px;">
                <button type="button" class="btn primary" id="saveThemeBtn">Выбрать и Применить</button>
                <button type="button" class="btn secondary" onclick="document.getElementById('themeEditorModal').classList.add('hidden');">Отмена</button>
            </div>
        </div>
    </div>
    
    <div id="toastContainer"></div> <input id="importFile" type="file" accept="application/json" style="display:none">
  
  <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
  <script src="scripts/api.js"></script>
</body>
</html>