<div class="auth-wrapper">
  <div class="auth-card">

    <img src="https://kub.md/image/catalog/logo_new.png" class="auth-logo">

    <h2 style="text-align:center;color:#fff">
        <?= htmlspecialchars($pendingUser['fullname']) ?>,
    </h2>

    <p style="text-align:center;color:#ccc;line-height:1.6">
        <?php if ($pendingUser['gender'] === 'female'): ?>
            ваша заявка находится на проверке.<br>
            Пожалуйста, ожидайте подтверждения администратора.
        <?php else: ?>
            ваша заявка находится на проверке.<br>
            Пожалуйста, ожидайте подтверждения администратора.
        <?php endif; ?>
    </p>

    <p style="text-align:center;margin-top:20px;opacity:.6">
        После подтверждения вы сможете войти в систему.
    </p>

  </div>
</div>