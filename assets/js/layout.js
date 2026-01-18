document.addEventListener('DOMContentLoaded', function () {
    const contentArea = document.querySelector('.page-container');

    // Инициализация аккордеонов
    function initAccordions() {
        document.querySelectorAll('.menu-trigger').forEach(trigger => {
            const targetId = trigger.getAttribute('data-target');
            const content = document.getElementById(targetId);
            if (!content) return;

            // Восстановление состояния из localStorage
            if (localStorage.getItem('menu_open_' + targetId) === 'true' || content.querySelector('.active')) {
                content.classList.add('open');
                trigger.classList.add('active-trigger');
            }

            trigger.addEventListener('click', () => {
                const isOpen = content.classList.toggle('open');
                trigger.classList.toggle('active-trigger');
                localStorage.setItem('menu_open_' + targetId, isOpen);
            });
        });
    }

    // AJAX-навигация
    window.loadPage = function(url, pushState = true) {
        const currentPath = window.location.pathname;
        const targetPath = new URL(url, window.location.origin).pathname;
        
        if ((currentPath.includes('/admin/') && targetPath.includes('/cabinet/')) ||
            (currentPath.includes('/cabinet/') && targetPath.includes('/admin/'))) {
            window.location.href = url;
            return;
        }

        if (typeof NProgress !== 'undefined') NProgress.start();
        
        const ajaxUrl = url + (url.includes('?') ? '&' : '?') + 'ajax=1';
        
        fetch(ajaxUrl)
            .then(res => res.text())
            .then(html => {
                contentArea.innerHTML = html;
                // Выполняем скрипты
                const scripts = contentArea.querySelectorAll('script');
                scripts.forEach(oldScript => {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => 
                        newScript.setAttribute(attr.name, attr.value)
                    );
                    newScript.textContent = oldScript.textContent;
                    oldScript.replaceWith(newScript);
                });
                
                if (pushState) history.pushState({ url }, '', url);
                updateActiveMenu(url);
                if (typeof NProgress !== 'undefined') NProgress.done();
                document.querySelector('.content').scrollTop = 0;
            })
            .catch(() => window.location.href = url);
    };

    function updateActiveMenu(url) {
        document.querySelectorAll('.item').forEach(el => el.classList.remove('active'));
        const activeLink = document.querySelector(`.item[href="${url}"]`) ||
                          document.querySelector(`.item[href$="${new URL(url).search}"]`);
        if (activeLink) activeLink.classList.add('active');
    }

    // Клик по меню
    document.addEventListener('click', e => {
        const link = e.target.closest('.item');
        if (link && link.href && !link.href.includes('logout.php') && link.target !== '_blank') {
            const url = new URL(link.href);
            if (url.origin === window.location.origin) {
                e.preventDefault();
                loadPage(link.getAttribute('href'));
            }
        }
    });

    // Back button
    window.addEventListener('popstate', e => {
        if (e.state?.url) loadPage(e.state.url, false);
    });

    // Запуск
    initAccordions();
});