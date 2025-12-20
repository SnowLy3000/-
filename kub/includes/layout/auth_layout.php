<?php
// страховки, если вдруг auth_logic не создал переменные
$error = $error ?? '';
$success = $success ?? '';
$old = $old ?? [
    'login' => ['login' => ''],
    'register' => [
        'phone' => '',
        'fullname' => '',
        'birthdate' => '',
        'gender' => '',
        'telegram_username' => '',
    ]
];

// тема гостя (в сессии), по умолчанию light
$guestTheme = $_SESSION['guest_theme'] ?? 'light';

// показать какую форму при первом рендере
$startForm = 'login';
if (!empty($_POST['action']) && $_POST['action'] === 'register') $startForm = 'register';
?>
<div class="auth-page" data-theme="<?= htmlspecialchars($guestTheme) ?>">
  <button class="theme-toggle" id="themeToggle" type="button" title="Сменить тему">
    <span class="theme-ico" aria-hidden="true">🌞</span>
  </button>

  <div class="auth-wrapper">
    <div class="auth-card" role="main">
      <img class="auth-logo" src="https://kub.md/image/catalog/logo_new.png" alt="KUB" width="172" height="50">

      <!-- LOGIN -->
      <form class="auth-form <?= $startForm === 'login' ? 'active' : '' ?>" id="formLogin" method="post" novalidate>
        <input type="hidden" name="action" value="login">

        <h2>Вход</h2>

        <input
          name="login"
          placeholder="Телефон (пример: 79111111) или логин"
          value="<?= htmlspecialchars($old['login']['login'] ?? '') ?>"
          autocomplete="username"
        >

        <input
          type="password"
          name="password"
          placeholder="Пароль"
          autocomplete="current-password"
        >

        <button class="btn-primary" name="login_submit" value="1" type="submit">Войти</button>

        <div class="auth-switch">
          Нет аккаунта?
          <a href="#" data-switch="register">Регистрация</a>
        </div>

        <?php if ($startForm === 'login' && $error): ?>
          <div class="auth-msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($startForm === 'login' && $success): ?>
          <div class="auth-msg ok"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
      </form>

      <!-- REGISTER -->
      <form class="auth-form <?= $startForm === 'register' ? 'active' : '' ?>" id="formRegister" method="post" novalidate>
        <input type="hidden" name="action" value="register">

        <h2>Регистрация</h2>

        <input
          name="phone"
          placeholder="Телефон (пример: 79111111)"
          value="<?= htmlspecialchars($old['register']['phone'] ?? '') ?>"
          inputmode="numeric"
          autocomplete="tel"
        >

        <input
          name="fullname"
          placeholder="Имя Фамилия (латиницей)"
          value="<?= htmlspecialchars($old['register']['fullname'] ?? '') ?>"
          autocomplete="name"
        >

        <input
          name="birthdate"
          placeholder="Дата рождения (12.12.2012)"
          value="<?= htmlspecialchars($old['register']['birthdate'] ?? '') ?>"
          inputmode="numeric"
        >

        <select name="gender">
          <option value="">Пол</option>
          <option value="male"   <?= (($old['register']['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Мужской</option>
          <option value="female" <?= (($old['register']['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Женский</option>
        </select>

        <input
          name="telegram_username"
          placeholder="Telegram логин (например: ion_popescu)"
          value="<?= htmlspecialchars($old['register']['telegram_username'] ?? '') ?>"
          autocomplete="off"
        >

        <div class="tg-hint">
          Как найти логин Telegram: <b>Telegram → Settings → Edit profile → Username</b><br>
          Пример: <code>@ion_popescu</code> (можно вводить и без @)
        </div>

        <input
          type="password"
          name="password"
          placeholder="Пароль (мин. 6 символов)"
          autocomplete="new-password"
        >

        <input
          type="password"
          name="password2"
          placeholder="Повтор пароля"
          autocomplete="new-password"
        >

        <button class="btn-primary" type="submit">Зарегистрироваться</button>

        <div class="auth-switch">
          Уже есть аккаунт?
          <a href="#" data-switch="login">Войти</a>
        </div>

        <?php if ($startForm === 'register' && $error): ?>
          <div class="auth-msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($startForm === 'register' && $success): ?>
          <div class="auth-msg ok"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
      </form>

    </div>
  </div>
</div>