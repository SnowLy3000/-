<?php

require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/db.php';

require_login();

if (in_array($_SESSION['user']['role'], ['admin','owner'], true)) {
    header('Location: /admin/dashboard.php');
    exit;
}

$userId = (int)$_SESSION['user']['id'];

// Темы + признак «прочитано»
$themes = $pdo->prepare("
    SELECT t.id, t.title,
           IF(kv.id IS NULL, 0, 1) AS viewed
    FROM themes t
    LEFT JOIN knowledge_views kv
        ON kv.theme_id = t.id
       AND kv.user_id = ?
       AND kv.subtheme_id IS NULL
    ORDER BY t.title
");
$themes->execute([$userId]);
$themes = $themes->fetchAll();

?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<title>База знаний</title>

<link rel="stylesheet" href="/assets/css/base.css">
<link rel="stylesheet" href="/assets/css/neon.css">

<style>
.read {
    color:#9ff;
    font-weight:bold;
}

/* ===== SEARCH ===== */
.search-wrap {
    position: relative;
    margin-bottom: 25px;
}

.search-wrap input {
    width: 100%;
    padding: 12px;
    font-size: 16px;
    background: #0e0e0e;
    border: 1px solid #333;
    color: #fff;
}

/* ===== AUTOCOMPLETE ===== */
.suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #111;
    border: 1px solid #333;
    z-index: 999;
}

.suggestions div {
    padding: 10px 12px;
    cursor: pointer;
    color: #f0f0f0;        /* 🔥 КЛЮЧЕВОЙ ФИКС */
    font-size: 14px;
}

.suggestions div:hover {
    background: #222;
}

.type {
    color: #8fd3ff;
    font-size: 11px;
    margin-right: 6px;
    text-transform: uppercase;
}
</style>
</head>
<body>

<div class="card neon" style="max-width:900px;margin:40px auto;">
    <h1>📚 База знаний</h1>

    <!-- ПОИСК -->
    <div class="search-wrap">
        <input
            type="text"
            id="search"
            placeholder="🔍 Начните вводить тему или текст..."
            autocomplete="off"
        >
        <div id="suggestions" class="suggestions" style="display:none"></div>
    </div>

    <!-- JS AUTOCOMPLETE -->
    <script>
    const input = document.getElementById('search');
    const box   = document.getElementById('suggestions');

    input.addEventListener('input', async () => {
        const q = input.value.trim();

        if (q.length < 2) {
            box.style.display = 'none';
            return;
        }

        try {
            const res = await fetch('/cabinet/knowledge_autocomplete.php?q=' + encodeURIComponent(q));
            const data = await res.json();

            box.innerHTML = '';

            if (!data.length) {
                box.style.display = 'none';
                return;
            }

            data.forEach(item => {
                const div = document.createElement('div');
                div.innerHTML = `<span class="type">${item.type}</span>${item.title}`;
                div.onclick = () => window.location.href = item.url;
                box.appendChild(div);
            });

            box.style.display = 'block';
        } catch (e) {
            box.style.display = 'none';
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.search-wrap')) {
            box.style.display = 'none';
        }
    });
    </script>

    <hr style="margin:30px 0">

    <!-- СПИСОК ТЕМ -->
    <?php foreach ($themes as $t): ?>
        <div class="card neon" style="margin-bottom:15px;">
            <b><?= htmlspecialchars($t['title']) ?></b>

            <?php if ($t['viewed']): ?>
                <span class="read">✔️ прочитано</span>
            <?php endif; ?>

            <div style="margin-top:10px;">
                <a class="btn" href="/cabinet/knowledge_view.php?theme=<?= (int)$t['id'] ?>">
                    Открыть
                </a>
            </div>
        </div>
    <?php endforeach; ?>

    <a href="/cabinet/index.php">← Назад в кабинет</a>
</div>

</body>
</html>