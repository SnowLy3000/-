<?php
// =========================================================================
// INDEX.PHP - ГЛАВНАЯ СТРАНИЦА (Режим ТОЛЬКО АВТОРИЗАЦИЯ для гостей)
// =========================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$current_theme = $_SESSION['theme'] ?? DEFAULT_THEME;
$is_authenticated = isset($_SESSION['role']);

// Определяем, должен ли быть открыт модал при загрузке (если есть ошибка/успех)
$show_auth_modal = isset($_SESSION['error_message']) || isset($_SESSION['success_message']) || isset($_GET['error']) || isset($_GET['success']);
$initial_display = $show_auth_modal ? 'style="display: flex;"' : '';

?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Veaimpex — KUB.MD — Инструкции</title>
  <link rel="stylesheet" href="styles/main.css">
  <style>
     /* Стили для центрирования лого и модала, когда пользователь не авторизован */
     .auth-only-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100vh;
        width: 100%;
        text-align: center;
     }
     .auth-only-logo {
        margin-bottom: 30px;
        height: 60px; /* Увеличим лого, если это единственный элемент */
        width: auto;
     }
  </style>
</head>
<body class="<?php echo htmlspecialchars($current_theme); ?>">
  
  <?php if ($is_authenticated): ?>
      <button class="btn burger-menu-btn" id="sidebarToggle">
        <span class="burger-icon">☰</span>
        <span class="burger-text">Меню</span>
      </button> 
      
      <header class="site-header">
        <div class="logo-row">
          <img src="/assets/kub_logo.png" alt="Veaimpex" class="logo"> 
        </div>
        <div class="header-actions">
          <div class="theme-switcher-container">
            <button id="themeSwitcherBtn" class="btn" title="Выбрать тему">🎨 Тема</button>
            <div id="themePicker" class="theme-picker hidden"></div>
          </div>
          <?php if ($_SESSION['role'] === ROLE_ADMIN || $_SESSION['role'] === ROLE_MAIN_ADMIN): ?>
              <a class="btn secondary" href="admin.php">⚙️ Админ-панель</a>
          <?php endif; ?>
          <a href="auth.php?logout" class="btn primary">🚪 Выход</a> 
        </div>
      </header>

      <main class="container">
          <aside class="sidebar" id="mainSidebar"> 
            <h2>Разделы</h2>
            <div id="categories" class="categories"></div>
            
            <h2>Оценка знаний</h2>
            <div class="categories">
              <button class="category" id="testsMenuBtn">📋 Тесты</button>
              <button class="category" id="examMenuBtn">🚨 Экзамен</button>
            </div>
          </aside>

          <section class="content">
            <div id="toastContainer"></div>

            <div id="welcomeMessage" class="instruction">
              <h2>Добро пожаловать, <?php echo htmlspecialchars($_SESSION['username'] ?? $_SESSION['login'] ?? 'Сотрудник'); ?>!</h2>
              <p>Используйте меню слева для выбора интересующего раздела или пройдите тест для проверки знаний.</p>
            </div>
            
            <article id="instructionBlock" class="instruction hidden">
              <h2 id="instTitle"></h2>
              <div id="instText" class="inst-text"></div>
              <div id="instImages" class="inst-images"></div>
            </article>
            
            <div id="quizBlock" class="instruction hidden">
                <h3 id="quizTitle"></h3>
                <div id="quizContent"></div>
            </div>
          </section>
      </main>
      
  <?php else: ?>
      <main class="auth-only-container">
          <img src="/assets/kub_logo.png" alt="KUB.MD Logo" class="auth-only-logo"> 
          
          <div class="modal-content small-modal" style="display: block;">
              <h3 id="authModalTitle">Вход в систему</h3>
              
              <?php if(isset($_SESSION['error_message']) || isset($_SESSION['success_message'])): ?>
                   <div style="margin-bottom: 15px; padding: 10px; border-radius: 4px; background: <?php echo isset($_SESSION['error_message']) ? '#fdd' : '#dfd'; ?>; color: <?php echo isset($_SESSION['error_message']) ? '#c00' : '#080'; ?>;">
                        <?php 
                            if(isset($_SESSION['error_message'])) { echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); }
                            if(isset($_SESSION['success_message'])) { echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); }
                        ?>
                   </div>
              <?php endif; ?>

              <form id="loginForm" method="POST" action="auth.php" style="display: <?php echo (isset($_GET['error']) && $_GET['error'] === 'register') ? 'none' : 'block'; ?>;">
                  <input type="hidden" name="action" value="login">
                  <label for="login">Телефон / Логин (Админ):</label>
                  <input type="text" id="login" name="login" placeholder="Например: 079123456" required style="width: 100%; padding: 8px; margin-bottom: 10px;">
                  <label for="password">Пароль:</label>
                  <input type="password" id="password" name="password" placeholder="Пароль" required style="width: 100%; padding: 8px; margin-bottom: 20px;">
                  <button type="submit" class="btn primary" style="width: 100%;">Войти</button>
                  <p style="text-align: center; margin-top: 15px;">Нет аккаунта? <a href="#" id="switchToRegister">Зарегистрироваться</a></p>
              </form>
              
              <form id="registerForm" method="POST" action="auth.php" style="display: <?php echo (isset($_GET['error']) && $_GET['error'] === 'register') ? 'block' : 'none'; ?>;">
                  <input type="hidden" name="action" value="register">
                  <label for="regUsername">ФИО:</label>
                  <input type="text" id="regUsername" name="username" placeholder="Полное имя" required style="width: 100%; padding: 8px; margin-bottom: 10px;">
                  <label for="regPhone">Телефон (Логин):</label>
                  <input type="tel" id="regPhone" name="phone" placeholder="079123456" required style="width: 100%; padding: 8px; margin-bottom: 10px;">
                  <label for="regDob">Дата рождения:</label>
                  <input type="date" id="regDob" name="date_of_birth" required style="width: 100%; padding: 8px; margin-bottom: 10px;">
                  <label for="regPassword">Пароль:</label>
                  <input type="password" id="regPassword" name="password" placeholder="Пароль" required style="width: 100%; padding: 8px; margin-bottom: 20px;">
                  <button type="submit" class="btn primary" style="width: 100%;">Зарегистрироваться</button>
                  <p style="text-align: center; margin-top: 15px;"><a href="#" id="switchToLogin">Уже есть аккаунт? Войти</a></p>
              </form>
          </div>
          
      </main>
  <?php endif; ?>
  
  <?php if ($is_authenticated): ?>
      <div id="resultsModal" class="modal hidden">
          <div class="modal-content">
              <h3 id="resultsTitle">Результаты</h3>
              <div id="resultsContent"></div>
              <button id="closeResultsModal" class="btn primary" style="margin-top: 20px;">Закрыть</button>
          </div>
      </div>
      
      <div id="imageLightbox" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); z-index: 9999; justify-content: center; align-items: center; cursor: zoom-out;">
          <img id="lightboxImage" src="" alt="Полное изображение" style="max-width: 90%; max-height: 90%; object-fit: contain;">
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
  <?php endif; ?>

  <script src="scripts/api.js"></script>
  <script>
    // Логика переключения форм Входа/Регистрации (для гостевого режима)
    document.addEventListener('DOMContentLoaded', () => {
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const authTitle = document.getElementById('authModalTitle');

        if (!loginForm || !registerForm) return; // Выходим, если пользователь авторизован

        const showLogin = () => {
            authTitle.textContent = 'Вход в систему';
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
        };

        const showRegister = () => {
            authTitle.textContent = 'Регистрация сотрудника';
            loginForm.style.display = 'none';
            registerForm.style.display = 'block';
        };

        document.getElementById('switchToRegister')?.addEventListener('click', (e) => { e.preventDefault(); showRegister(); });
        document.getElementById('switchToLogin')?.addEventListener('click', (e) => { e.preventDefault(); showLogin(); });
        
        // Переключаем на регистрацию, если произошла ошибка регистрации
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error') === 'register' || urlParams.get('success') === 'register') {
             showRegister();
        } else {
             showLogin();
        }

        // Чистим URL, чтобы при обновлении страницы не было повторного переключения формы
        if (urlParams.has('error') || urlParams.has('success')) {
             window.history.replaceState({}, document.title, window.location.pathname);
        }
    });
  </script>
</body>
</html>