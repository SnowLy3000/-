(function() {
    // Если мы уже в чате, уведомления (всплывашки) не нужны
    if (window.location.href.includes('page=chat')) return;

    let originalTitle = document.title;
    let notificationInterval = null;
    let lastAlertKey = null; 

    // Звук уведомления
    const notifySound = new Audio('https://assets.mixkit.co/active_storage/sfx/2354/2354-preview.mp3');

    function initGlobal() {
        // 1. Запускаем проверку СРАЗУ при загрузке страницы
        checkGlobalNotifications();

        // 2. И далее каждые 2 секунды для максимальной скорости (Short Polling)
        setInterval(checkGlobalNotifications, 2000);
        
        window.onfocus = () => {
            stopBlinking();
        };
    }

    async function checkGlobalNotifications() {
        // Добавляем nocache и случайное число для обхода кэша сервера
        const urlSuffix = '&nocache=' + Math.random();
        const dot = document.getElementById('chat-unread-dot');
        
        let hasNewSomething = false;

        try {
            // --- 1. Проверка личных сообщений (ПОЛНОСТЬЮ НА SQL) ---
            const privateRes = await fetch('/api/chat_handler.php?action=get_my_privates' + urlSuffix, { cache: "no-store" });
            const privateChats = await privateRes.json();
            
            // PHP теперь сам вычисляет has_new на основе таблицы chat_read_status
            const newChat = privateChats.find(c => c.has_new);

            if (newChat) {
                // Показываем попап. Ключ включает last_msg_id, чтобы на новое сообщение в том же чате сработал звук
                showPopup(`Личное от: ${newChat.name}`, newChat.id, '📩 НОВОЕ СООБЩЕНИЕ', false, `p_${newChat.id}_${newChat.last_msg_id}`);
                hasNewSomething = true;
            } else {
                // Если непрочитанных в базе нет — убираем плашку
                const el = document.getElementById('global-stock-alert');
                if(el && el.dataset.type === 'private') el.remove();
            }

            // --- 2. Проверка запросов товара (НА SQL) ---
            const stockRes = await fetch('/api/chat_handler.php?action=check_stock' + urlSuffix, { cache: "no-store" });
            const stockData = await stockRes.json();
            
            // Фильтруем только те, что не помечены как прочитанные в базе
            const activeStock = stockData.find(s => !s.already_read);

            if (activeStock) {
                showPopup(`Запрос от: ${activeStock.owner_full_name} | ${activeStock.product_name}`, `stock_${activeStock.id}`, '📦 НОВЫЙ ЗАПРОС', true, `s_${activeStock.id}`);
                hasNewSomething = true;
            } else {
                const el = document.getElementById('global-stock-alert');
                if(el && el.dataset.type === 'stock') el.remove();
            }

            // --- 3. Красная точка в шапке ---
            if (dot) {
                dot.style.display = hasNewSomething ? 'block' : 'none';
            }

        } catch (e) {
            console.error("Notification error:", e);
        }
    }

    function showPopup(title, chatId, blinkText, isStock, uniqueKey) {
        let alertDiv = document.getElementById('global-stock-alert');
        
        if (alertDiv && alertDiv.dataset.key !== uniqueKey) {
            alertDiv.remove();
            alertDiv = null;
        }

        if (!alertDiv) {
            alertDiv = document.createElement('div');
            alertDiv.id = 'global-stock-alert';
            alertDiv.dataset.key = uniqueKey;
            alertDiv.dataset.type = isStock ? 'stock' : 'private';
            alertDiv.style = "position:fixed; bottom:20px; right:20px; z-index:10000; background:#1c212c; border:1px solid #6d5dfc; padding:15px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.6); color:#fff; width:260px; font-family: sans-serif; border-left: 5px solid #6d5dfc;";
            document.body.appendChild(alertDiv);
            
            if (uniqueKey !== lastAlertKey) {
                notifySound.play().catch(() => {});
                lastAlertKey = uniqueKey;
            }
        }
        
        startBlinking(blinkText);

        if (isStock) {
            alertDiv.innerHTML = `
                <div style="font-size:11px; font-weight:bold; margin-bottom:8px; color:#6d5dfc; display:flex; justify-content:space-between;">
                    <span>📦 ТОВАРНЫЙ ЗАПРОС</span>
                    <span onclick="this.parentElement.parentElement.remove()" style="cursor:pointer; opacity:0.5;">✕</span>
                </div>
                <div style="font-size:13px; background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; margin-bottom:12px; line-height:1.4;">${title}</div>
                <div style="display:flex; gap:8px;">
                    <button onclick="handleGlobalStock('${chatId.replace('stock_','')}', 'confirm_stock')" style="background:#34c759; border:none; color:#fff; padding:8px; border-radius:8px; flex:1; cursor:pointer; font-weight:bold; font-size:12px;">ЕСТЬ</button>
                    <a href="/cabinet/index.php?page=chat&open=${chatId}" style="background:#5856d6; text-align:center; text-decoration:none; color:#fff; padding:8px; border-radius:8px; flex:1; font-weight:bold; font-size:12px; display:flex; align-items:center; justify-content:center;">ЧАТ</a>
                </div>
            `;
        } else {
            alertDiv.innerHTML = `
                <div style="font-size:11px; font-weight:bold; margin-bottom:8px; color:#a29bfe; display:flex; justify-content:space-between;">
                    <span>📩 ЛИЧНОЕ СООБЩЕНИЕ</span>
                    <span onclick="this.parentElement.parentElement.remove()" style="cursor:pointer; opacity:0.5;">✕</span>
                </div>
                <div style="font-size:13px; background:rgba(255,255,255,0.05); padding:10px; border-radius:8px; margin-bottom:12px; line-height:1.4;">${title}</div>
                <a href="/cabinet/index.php?page=chat&open=${chatId}" style="display:block; background:#6d5dfc; text-align:center; text-decoration:none; color:#fff; padding:10px; border-radius:8px; font-weight:bold; font-size:12px;">ОТВЕТИТЬ</a>
            `;
        }
    }

    function startBlinking(text) {
        if (notificationInterval) return;
        notificationInterval = setInterval(() => {
            document.title = document.title === originalTitle ? text : originalTitle;
        }, 1000);
    }

    function stopBlinking() {
        clearInterval(notificationInterval);
        notificationInterval = null;
        document.title = originalTitle;
    }

    window.handleGlobalStock = function(id, act) {
        fetch('/api/chat_handler.php?action=' + act, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `request_id=${id}`
        }).then(() => {
            window.location.href = `/cabinet/index.php?page=chat&open=stock_${id}`;
        });
    };

    initGlobal();
})();