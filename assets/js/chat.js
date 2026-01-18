(function() {
    let currentTab = 'general';
    let lastMsgId = 0;
    // Безопасное получение userId
    const chatLayout = document.querySelector('.chat-layout');
    if (!chatLayout) return; // Если мы не на странице чата, прекращаем выполнение скрипта

    let userId = chatLayout.dataset.userId;
    let localSeen = JSON.parse(localStorage.getItem('chat_seen_v4_' + userId) || '{}');
    let hiddenPrivates = JSON.parse(localStorage.getItem('chat_hidden_' + userId) || '[]');
    
    let originalTitle = document.title;
    let notificationInterval = null;

    function init() {
        // Проверка глубоких ссылок (из уведомлений)
        const urlParams = new URLSearchParams(window.location.search);
        const openTab = urlParams.get('open');
        if (openTab) {
            switchTab(openTab);
        } else {
            switchTab('general');
        }

        setInterval(loadMessages, 2000); 
        setInterval(syncPrivates, 2500); 
        setInterval(syncStockChannels, 4000); 
        setInterval(checkStockAlerts, 5000);

        window.onfocus = () => {
            stopBlinking();
        };

        const form = document.getElementById('chat-form');
        if (form) {
            form.onsubmit = async (e) => {
                e.preventDefault();
                const input = document.getElementById('chat-input');
                const msg = input.value.trim();
                if (!msg) return;
                input.value = '';

                const res = await fetch('/api/chat_handler.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `channel=${currentTab}&message=${encodeURIComponent(msg)}`
                });
                const data = await res.json();
                
                if (data.error === 'closed') {
                    alert('Этот чат закрыт владельцем');
                    return;
                }

                if (currentTab.startsWith('p_')) {
                    hiddenPrivates = hiddenPrivates.filter(i => i !== currentTab);
                    localStorage.setItem('chat_hidden_' + userId, JSON.stringify(hiddenPrivates));
                    syncPrivates(); 
                }
                loadMessages();
            };
        }

        const pSearch = document.getElementById('p_search');
        if (pSearch) {
            pSearch.oninput = async function() {
                const q = this.value.trim();
                if(q.length < 2) {
                    document.getElementById('p_results').innerHTML = '';
                    return;
                }
                const res = await fetch(`/cabinet/ajax/search_products.php?q=${encodeURIComponent(q)}`);
                const data = await res.json();
                
                document.getElementById('p_results').innerHTML = data.map(i => `
                    <div onclick="createStockRequest('${i.name.replace(/'/g, "\\'")}')" 
                         style="padding:12px 15px; cursor:pointer; border-bottom:1px solid rgba(255,255,255,0.05); color:#fff; font-size:13px; transition:0.2s; display:flex; align-items:center; gap:10px;"
                         onmouseover="this.style.background='rgba(88,86,214,0.1)'" 
                         onmouseout="this.style.background='transparent'">
                        <span style="color:#5856d6;">📦</span> ${i.name}
                    </div>
                `).join('');
            };
        }
    }

    function stopBlinking() {
        clearInterval(notificationInterval);
        notificationInterval = null;
        document.title = originalTitle;
    }

    function startTabNotification(text) {
        if (notificationInterval || document.hasFocus()) return;
        notificationInterval = setInterval(() => {
            document.title = document.title === originalTitle ? text : originalTitle;
        }, 1000);
    }

    // ОБНОВЛЕННАЯ ФУНКЦИЯ СЕРВЕРНОЙ ПОМЕТКИ ПРОЧТЕНИЯ
    window.switchTab = function(tab, label = '') {
        currentTab = tab;
        lastMsgId = 0;
        document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active', 'has-new'));
        
        const msgBox = document.getElementById('messages-box');
        const contBox = document.getElementById('contacts-box');
        const partArea = document.getElementById('participants-area');
        const actions = document.getElementById('chat-actions');

        if (!msgBox) return;

        // --- НОВОЕ: Сообщаем серверу, что чат прочитан (запись в БД) ---
        fetch('/api/chat_handler.php?action=mark_read', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `slug=${tab}`
        });

        // Также дублируем в localStorage для мгновенной реакции локального интерфейса
        localSeen[tab] = 999999999; 
        localStorage.setItem('chat_seen_v4_' + userId, JSON.stringify(localSeen));
        
        const dot = document.getElementById('chat-unread-dot');
        if (dot) dot.style.display = 'none';
        // -------------------------------------------------------------

        if (partArea) partArea.style.display = 'none';
        actions.innerHTML = '';
        msgBox.style.opacity = '1';
        msgBox.style.pointerEvents = 'auto';
        const oldWatermark = document.getElementById('watermark');
        if(oldWatermark) oldWatermark.remove();

        if (tab === 'contacts_list') {
            msgBox.style.display = 'none'; 
            if (contBox) contBox.style.display = 'block';
            loadContacts();
        } else {
            msgBox.style.display = 'flex'; 
            if (contBox) contBox.style.display = 'none';
            msgBox.innerHTML = '';
            
            if (tab.startsWith('p_')) {
                actions.innerHTML = `<button onclick="hidePrivateChat('${tab}')" style="background:rgba(255,59,48,0.1); border:1px solid #ff3b30; color:#ff3b30; padding:4px 8px; border-radius:6px; cursor:pointer; font-size:10px; font-weight:bold;">🗑 СКРЫТЬ ЧАТ</button>`;
            }

            if (tab.startsWith('stock_')) {
                if (partArea) partArea.style.display = 'flex';
                loadParticipants(tab.replace('stock_', ''));
            }
            
            loadMessages();
        }
        const titleEl = document.getElementById('chat-title');
        if (titleEl) titleEl.innerText = label || '# ' + tab.toUpperCase();
        stopBlinking();
    };

    async function loadMessages() {
        if (currentTab === 'contacts_list') return;
        const msgBox = document.getElementById('messages-box');
        if (!msgBox) return;

        try {
            const res = await fetch(`/api/chat_handler.php?action=load&channel=${currentTab}&last_id=${lastMsgId}`);
            const data = await res.json();
            const actions = document.getElementById('chat-actions');
            
            if (data.channel_status === 'closed') {
                msgBox.style.opacity = '0.5';
                msgBox.style.pointerEvents = 'none';
                if(!document.getElementById('watermark')) {
                    msgBox.insertAdjacentHTML('afterbegin', `<div id="watermark" class="closed-watermark">ЗАКРЫТО</div>`);
                }
                if (actions) actions.innerHTML = ''; 
            } else if (currentTab.startsWith('stock_')) {
                loadParticipants(currentTab.replace('stock_', ''));
                if (actions && actions.innerHTML === '') {
                    actions.innerHTML = `<button onclick="closeRequest('${currentTab}')" style="background:#ff3b30; color:#fff; border:none; padding:5px 10px; border-radius:8px; cursor:pointer; font-size:11px; font-weight:bold;">ЗАКРЫТЬ ЗАПРОС</button>`;
                }
            }

            if (data.messages && data.messages.length > 0) {
                let incoming = false;
                data.messages.forEach(m => {
                    const isMine = m.user_id == userId;
                    if (!isMine) incoming = true;
                    msgBox.insertAdjacentHTML('beforeend', `
                        <div class="bubble ${isMine ? 'mine' : 'other'}" id="msg-${m.id}">
                            ${!isMine ? `<div style="font-size:10px; color:#5856d6; font-weight:800; margin-bottom:3px;">${m.first_name}</div>` : ''}
                            <div>${m.message}</div>
                            <div style="font-size:9px; opacity:0.3; text-align:right; margin-top:4px;">${m.time}</div>
                            ${isMine ? `<button onclick="deleteMsg(${m.id})" style="position:absolute; top:0; right:-25px; background:none; border:none; color:#ff3b30; cursor:pointer; font-size:12px;" title="Удалить">🗑</button>` : ''}
                        </div>
                    `);
                    lastMsgId = m.id;
                });

                if (incoming) {
                    playNotify();
                    startTabNotification("📩 НОВОЕ СООБЩЕНИЕ");
                }
                msgBox.scrollTop = msgBox.scrollHeight;
                localSeen[currentTab] = lastMsgId;
                localStorage.setItem('chat_seen_v4_' + userId, JSON.stringify(localSeen));
            }
        } catch(e) {}
    }

    async function syncPrivates() {
        const list = document.getElementById('private-chats-list');
        if (!list) return;
        let params = "";
        for (let ch in localSeen) { params += `&seen_id_${ch}=${localSeen[ch]}`; }
        const res = await fetch('/api/chat_handler.php?action=get_my_privates' + params);
        const chats = await res.json();
        const visibleChats = chats.filter(c => !hiddenPrivates.includes(c.id));
        list.innerHTML = visibleChats.map(c => `
            <div class="nav-item ${c.has_new ? 'has-new' : ''} ${c.id === currentTab ? 'active' : ''}" 
                 onclick="switchTab('${c.id}', '👤 ${c.name}')">👤 ${c.name}</div>
        `).join('');
    }

    async function syncStockChannels() {
        const list = document.getElementById('stock-channels-list');
        if (!list) return;
        const res = await fetch('/api/chat_handler.php?action=get_active_stock_channels');
        const channels = await res.json();
        list.innerHTML = channels.map(c => `
            <div class="nav-item ${c.slug === currentTab ? 'active' : ''}" id="nav-${c.slug}"
                 onclick="switchTab('${c.slug}', '${c.name}')">${c.name}</div>
        `).join('');
    }

    window.hidePrivateChat = function(id) {
        if(!confirm('Убрать из списка?')) return;
        hiddenPrivates.push(id);
        localStorage.setItem('chat_hidden_' + userId, JSON.stringify(hiddenPrivates));
        switchTab('general');
        syncPrivates();
    };

    window.deleteMsg = async function(id) {
        if(!confirm('Удалить сообщение для всех?')) return;
        const res = await fetch('/api/chat_handler.php?action=delete_message', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `id=${id}`
        });
        const data = await res.json();
        if(data.status === 'ok') document.getElementById('msg-'+id).remove();
    };

    window.closeRequest = async function(slug) {
        if(!confirm('Закрыть этот запрос для всех?')) return;
        const res = await fetch('/api/chat_handler.php?action=close_stock', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `slug=${slug}`
        });
        const data = await res.json();
        if(data.status === 'ok') {
            syncStockChannels();
            switchTab('general', '💬 Общий чат'); 
        } else alert(data.error);
    };

    async function loadParticipants(reqId) {
        const area = document.getElementById('participants-area');
        if (!area) return;
        const res = await fetch(`/api/chat_handler.php?action=get_participants&request_id=${reqId}`);
        const data = await res.json();
        area.innerHTML = `<div style="font-size: 10px; opacity: 0.5; width: 100%; margin-bottom: 5px;">СТАТУСЫ НА СМЕНЕ:</div>` + data.map(p => {
            let color = '#707586';
            if(p.status === 'green') color = '#34c759';
            if(p.status === 'red') color = '#ff3b30';
            return `<div class="p-badge" style="color:${color}; border-color:${color}44;"><span style="width:6px;height:6px;background:${color};border-radius:50%"></span> ${p.full_name}</div>`;
        }).join('');
    }

    function playNotify() {
        const audio = document.getElementById('chat-sound');
        if (audio) { audio.currentTime = 0; audio.play().catch(() => {}); }
    }

    window.loadContacts = async function() {
        const inner = document.getElementById('contacts-inner');
        if (!inner) return;
        const res = await fetch('/api/chat_handler.php?action=get_contacts');
        const users = await res.json();
        inner.innerHTML = users.map(u => `
            <div class="contact-row">
                <div class="c-avatar">${u.first_name[0]}</div>
                <div style="flex:1; font-weight:bold; color:#fff;">${u.first_name} ${u.last_name || ''}</div>
                <button class="c-btn" onclick="openPrivate(${u.id}, '${u.first_name} ${u.last_name || ''}')">Написать</button>
            </div>`).join('');
    };

    window.openPrivate = (id, name) => {
        const ch = userId < id ? `p_${userId}_${id}` : `p_${id}_${userId}`;
        hiddenPrivates = hiddenPrivates.filter(i => i !== ch);
        localStorage.setItem('chat_hidden_' + userId, JSON.stringify(hiddenPrivates));
        switchTab(ch, '👤 ' + name);
    };

    async function checkStockAlerts() {
        const res = await fetch('/api/chat_handler.php?action=check_stock');
        const data = await res.json();
        const topBar = document.getElementById('stock-alerts-top');
        if (!topBar) return;
        if (data && data.length > 0) {
            if(topBar.style.display === 'none') startTabNotification("📦 НОВЫЙ ЗАПРОС");
            topBar.style.display = 'flex';
            topBar.innerHTML = data.map(req => `
                <div class="mini-stock-card">
                    <div style="font-size:11px; font-weight:700; color:#fff;">${req.product_name}</div>
                    <div style="display:flex; gap:5px; margin-top:5px;">
                        <button onclick="handleStock(event, ${req.id}, 'confirm_stock')" style="background:#34c759; flex:1; border:none; color:#fff; border-radius:5px; padding:5px; cursor:pointer; font-size:10px;">ЕСТЬ</button>
                        <button onclick="handleStock(event, ${req.id}, 'decline_stock')" style="background:#ff3b30; flex:1; border:none; color:#fff; border-radius:5px; padding:5px; cursor:pointer; font-size:10px;">НЕТ</button>
                        <button onclick="switchTab('stock_${req.id}', '📦 ${req.product_name}')" style="background:#5856d6; flex:1; border:none; color:#fff; border-radius:5px; padding:5px; cursor:pointer; font-size:10px;">ЧАТ</button>
                    </div>
                </div>`).join('');
        } else { topBar.style.display = 'none'; }
    }

    window.handleStock = async function(e, id, act) {
        e.stopPropagation();
        await fetch('/api/chat_handler.php?action=' + act, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `request_id=${id}`
        });
        checkStockAlerts();
    };

    window.createStockRequest = async (name) => {
        const res = await fetch('/api/chat_handler.php?action=create_stock', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `product=${encodeURIComponent(name)}`
        });
        const d = await res.json();
        if (d.slug) { 
            const modal = document.getElementById('modalStock');
            if (modal) modal.style.display = 'none';
            syncStockChannels();
            switchTab(d.slug, '📦 ' + name);
        }
    };

    window.showModal = (id) => { const modal = document.getElementById(id); if (modal) modal.style.display = 'flex'; };
    window.hideModal = (id) => { const modal = document.getElementById(id); if (modal) modal.style.display = 'none'; };

    init();
})();