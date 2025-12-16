// =========================================================================
// API.JS - ФИНАЛЬНАЯ ИСПРАВЛЕННАЯ ВЕРСИЯ (Admin, Index, Mobile Menu)
// =========================================================================

const THEMES = [
    { name: 'Soft White (Светлый)', class: 'theme-soft-white' },
    { name: 'Eco Beige (Бежевый)', class: 'theme-eco-beige' },
    { name: 'Charcoal (Угольный)', class: 'theme-charcoal-dark' }, 
    { name: 'Forest Velvet (Лесной)', class: 'theme-forest-velvet' },
];
const DEFAULT_THEME = 'theme-charcoal-dark'; 
const EXAM_PASSWORD = 'test1'; 

let data = { instructions: [], quizData: { questions: [] } };
let quizSettings = {
    quiz_questions_count: 20,
    exam_timer_minutes: 60
};
let currentQuizQuestions = [];
let quizAnswers = [];
let quizTimer = null;
let quizSecondsLeft = 0;
let quizResults = null; 
let activeAdminGroupId = null; 

let quillEditor = null; 


// --- Утилиты (Toast и API) ---
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('hide');
        toast.addEventListener('transitionend', () => toast.remove());
    }, 5000);
}

async function apiCall(action, method = 'GET', payload = {}) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
        };

        let url = `api.php?action=${action}`;
        
        if (method === 'GET') {
            const queryParams = new URLSearchParams(payload).toString();
            if (queryParams) {
                url += `&${queryParams}`;
            }
        } else if (method === 'POST') {
            options.body = JSON.stringify({ ...payload, action: action });
            url = 'api.php';
        }

        const response = await fetch(url, options);
        if (!response.ok) {
             const errorText = await response.text();
             // Улучшенная обработка для SyntaxError
             if (!errorText.trim().startsWith('{')) {
                 showToast("Критическая ошибка PHP: Неверный формат JSON. (Проверьте консоль F12 и PHP-файлы)", 'error');
             } else {
                 showToast(`Сервер вернул ошибку ${response.status}: ${errorText.substring(0, 100)}...`, 'error');
             }
             throw new Error(`HTTP Error ${response.status}: ${errorText}`);
        }
        
        const result = await response.json();

        if (result.success) {
            return result.data;
        } else {
            if (response.status === 403) {
                 if (window.location.pathname.includes('admin.php')) {
                     window.location.href = 'index.php'; 
                 }
            }
            showToast(result.message || 'Ошибка выполнения запроса.', 'error');
            throw new Error(result.message || 'API call failed');
        }
    } catch (error) {
        console.error('API Error:', error);
        if (error.message.includes("Unexpected token") || error.message.includes("HTTP Error")) {
             showToast("Критическая ошибка: Ответ сервера не является чистым JSON (проверьте PHP-файлы).", 'error');
        } else if (!window.location.pathname.includes('admin.php')) {
            showToast('Ошибка: Не удалось подключиться к серверу.', 'error');
        }
        return null;
    }
}


// =========================================================================
// 1. ЛОГИКА ТЕМЫ
// =========================================================================

function initThemePicker() {
    const themeSwitcherBtn = document.getElementById('themeSwitcherBtn');
    
    if (!themeSwitcherBtn) return; 

    themeSwitcherBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        showThemeEditorModal();
    });
}

function showThemeEditorModal() {
    const modal = document.getElementById('themeEditorModal');
    const paletteContainer = document.getElementById('themePalette');
    const saveThemeBtn = document.getElementById('saveThemeBtn');
    if (!modal || !paletteContainer || !saveThemeBtn) return;

    paletteContainer.innerHTML = '';
    
    let selectedTheme = document.body.className;

    THEMES.forEach(theme => {
        const isCurrent = theme.class === document.body.className;
        const card = document.createElement('div');
        card.className = `theme-card ${theme.class}`;
        card.setAttribute('data-theme', theme.class);
        
        card.innerHTML = `
            <div class="theme-preview">
                <div class="header-preview"></div>
                <div class="sidebar-preview"></div>
                <div class="content-preview"></div>
            </div>
            <p>${theme.name}</p>
            ${isCurrent ? '<span class="current-label">Текущая</span>' : ''}
        `;
        
        card.addEventListener('click', () => {
            document.querySelectorAll('#themePalette .theme-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedTheme = theme.class;
        });
        
        if (isCurrent) {
             card.classList.add('selected');
        }

        paletteContainer.appendChild(card);
    });
    
    saveThemeBtn.onclick = async () => {
        if (selectedTheme) {
            await setTheme(selectedTheme);
            modal.classList.add('hidden');
            document.body.className = selectedTheme;
            showToast(`Тема изменена на: ${selectedTheme.split('-').slice(1).join(' ').toUpperCase()}`);
            
            if (window.location.pathname.includes('admin.php')) {
                 loadInstructionsAdmin(activeAdminGroupId);
                 showThemeEditorModal(); 
            }
        }
    };

    modal.classList.remove('hidden');
}

async function setTheme(themeClass) {
    await apiCall('set_theme', 'POST', { theme_class: themeClass });
}


// =========================================================================
// 2. ЗАГРУЗКА ДАННЫХ ДЛЯ КЛИЕНТА (Новый стиль меню)
// =========================================================================

async function loadFullData() {
    const result = await apiCall('get_instructions_data', 'GET');
    if (result) {
        data = result;
        renderInstructionsMenu();
        handleHashChange(); 
    }
}

/**
 * Генерирует меню в новом компактном стиле
 */
function renderInstructionsMenu() {
    const categoriesContainer = document.getElementById('categories');
    if (!categoriesContainer) return;
    categoriesContainer.innerHTML = '';
    
    data.instructions.forEach((group) => {
        // 1. Создаем заголовок группы (без возможности скрытия)
        const groupTitleDiv = document.createElement('div');
        groupTitleDiv.className = 'category-group-header'; 
        groupTitleDiv.innerHTML = `
            <h3 class="group-header-text">
                <span class="icon">${group.icon || '📖'}</span>
                <span>${group.title}</span>
            </h3>
        `;
        categoriesContainer.appendChild(groupTitleDiv);

        // 2. Добавляем контейнер для подтем
        const subtopicsContainer = document.createElement('div');
        subtopicsContainer.className = 'subtopics-compact';
        
        // 3. Добавляем все подтемы внутрь
        group.subtopics.forEach(subtopic => {
            const subtopicLink = document.createElement('button');
            subtopicLink.className = 'subtopic-link';
            subtopicLink.setAttribute('data-group-id', group.id);
            subtopicLink.setAttribute('data-subtopic-id', subtopic.id);
            subtopicLink.textContent = subtopic.title;
            
            subtopicLink.addEventListener('click', (e) => {
                e.preventDefault();
                const subtopicId = subtopicLink.getAttribute('data-subtopic-id');
                window.location.hash = `#instruction/${subtopicId}`; 
                
                // Для мобильной навигации: скрываем меню при выборе темы
                if (window.innerWidth <= 768) {
                    document.body.classList.remove('sidebar-open');
                    document.getElementById('mainSidebar')?.classList.remove('active');
                }
                
                // Активный класс
                document.querySelectorAll('.subtopic-link').forEach(link => link.classList.remove('active'));
                subtopicLink.classList.add('active');
            });
            subtopicsContainer.appendChild(subtopicLink);
        });

        categoriesContainer.appendChild(subtopicsContainer);
    });
}

/**
 * Загружает и отображает контент подтемы на главной странице.
 */
async function showInstruction(subtopicId) {
    const instructionBlock = document.getElementById('instructionBlock');
    const welcomeMessage = document.getElementById('welcomeMessage');
    const instTitle = document.getElementById('instTitle');
    const instText = document.getElementById('instText');
    const instImages = document.getElementById('instImages');

    if (!instructionBlock || !instTitle || !instText || !instImages) return;

    welcomeMessage?.classList.add('hidden');
    document.getElementById('quizBlock')?.classList.add('hidden');
    instructionBlock.classList.remove('hidden');
    
    instTitle.textContent = 'Загрузка...';
    instText.innerHTML = '<p>Загрузка инструкции...</p>';
    instImages.innerHTML = '';

    // Подсветка активной ссылки в меню
    document.querySelectorAll('.subtopic-link').forEach(link => link.classList.remove('active'));
    document.querySelector(`.subtopic-link[data-subtopic-id="${subtopicId}"]`)?.classList.add('active');


    const result = await apiCall('load_subtopic', 'POST', { subtopic_id: subtopicId });
    if (!result) {
        instTitle.textContent = 'Ошибка загрузки';
        instText.innerHTML = '<p>Не удалось загрузить данные инструкции.</p>';
        return;
    }
    
    const subtopic = result.subtopic;
    
    instTitle.textContent = subtopic.title;
    instText.innerHTML = subtopic.instruction; 
    
    instImages.innerHTML = subtopic.images && subtopic.images.length > 0
        ? `
            <div class="image-container">
                ${subtopic.images.map(img => `
                    <img src="${img}" alt="${subtopic.title}" data-full-src="${img}" class="instruction-image">
                `).join('')}
            </div>
          `
        : '<p>Изображений нет.</p>';
        
    instImages.querySelectorAll('.instruction-image').forEach(img => {
        img.addEventListener('click', () => {
            const lightbox = document.getElementById('imageLightbox');
            const lightboxImage = document.getElementById('lightboxImage');
            if (lightbox && lightboxImage) {
                lightboxImage.src = img.getAttribute('data-full-src');
                lightbox.style.display = 'flex';
            }
        });
    });
}

function handleHashChange() {
    const hash = window.location.hash;
    const instructionBlock = document.getElementById('instructionBlock');
    const quizBlock = document.getElementById('quizBlock');
    
    instructionBlock?.classList.add('hidden');
    quizBlock?.classList.add('hidden');

    if (hash.startsWith('#instruction/')) {
        const parts = hash.split('/');
        const subtopicId = parts[1];
        if (subtopicId) {
            showInstruction(subtopicId);
        } else {
            document.getElementById('welcomeMessage')?.classList.remove('hidden');
        }
    } else {
         document.getElementById('welcomeMessage')?.classList.remove('hidden');
    }
}

// =========================================================================
// 3. ЛОГИКА ТЕСТОВ И ЭКЗАМЕНОВ
// =========================================================================
function shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
}

function startQuiz(type) {
    currentQuizType = type;
    document.getElementById('instructionBlock')?.classList.add('hidden');
    document.getElementById('welcomeMessage')?.classList.add('hidden');
    const quizBlock = document.getElementById('quizBlock');
    if (!quizBlock) return;

    quizBlock.classList.remove('hidden');
    
    const allQuestions = data.quizData.questions.filter(q => q.type === type);
    const count = type === 'test' 
        ? quizSettings.quiz_questions_count 
        : allQuestions.length;
    
    currentQuizQuestions = shuffleArray(allQuestions).slice(0, count);
    quizAnswers = Array(currentQuizQuestions.length).fill(null);
    quizResults = null;
    
    document.getElementById('quizTitle').textContent = type === 'test' ? `Тест (Случайные ${count} вопросов)` : 'Экзамен (Все вопросы)';
    renderQuiz();
    
    if (type === 'exam') {
        startExamTimer(quizSettings.exam_timer_minutes);
    } else {
        if (quizTimer) clearInterval(quizTimer);
    }
}

function startExamTimer(minutes) {
    if (quizTimer) clearInterval(quizTimer);
    quizSecondsLeft = minutes * 60;
    
    const quizTitle = document.getElementById('quizTitle');
    
    function updateTimerDisplay() {
        const mins = String(Math.floor(quizSecondsLeft / 60)).padStart(2, '0');
        const secs = String(quizSecondsLeft % 60).padStart(2, '0');
        quizTitle.textContent = `🚨 Экзамен (Осталось: ${mins}:${secs})`;
    }
    
    updateTimerDisplay();

    quizTimer = setInterval(() => {
        quizSecondsLeft--;
        updateTimerDisplay();
        
        if (quizSecondsLeft <= 0) {
            clearInterval(quizTimer);
            showQuizResults();
            showToast('Время экзамена вышло!', 'error');
        }
    }, 1000);
}

function renderQuiz() {
    const quizContent = document.getElementById('quizContent');
    if (!quizContent) return; 

    quizContent.innerHTML = currentQuizQuestions.map((q, qIndex) => `
        <div class="question-card" data-index="${qIndex}">
            <p><strong>Вопрос ${qIndex + 1}:</strong> ${q.title}</p>
            <div class="answers-list">
                ${shuffleArray([...q.answers]).map((answer, aIndex) => `
                    <label class="answer-item ${quizAnswers[qIndex] === answer ? 'selected' : ''}">
                        <input type="radio" name="question-${qIndex}" value="${answer}" 
                            ${quizAnswers[qIndex] === answer ? 'checked' : ''}
                            onchange="recordAnswer(${qIndex}, '${answer.replace(/'/g, "\\'")}')">
                        ${answer}
                    </label>
                `).join('')}
            </div>
            ${quizResults ? renderQuestionResult(q, qIndex) : ''}
        </div>
    `).join('');
    
    if (!quizResults) {
         quizContent.innerHTML += `
            <button id="submitQuizBtn" class="btn primary" style="margin-top: 20px;">Завершить ${currentQuizType === 'test' ? 'Тест' : 'Экзамен'}</button>
        `;
         document.getElementById('submitQuizBtn')?.addEventListener('click', showQuizResults);
    }
}

function recordAnswer(qIndex, answer) {
    quizAnswers[qIndex] = answer;
}

function showQuizResults() {
    if (currentQuizType === 'exam' && quizTimer) {
         clearInterval(quizTimer);
    }
    
    let correctCount = 0;
    
    quizResults = currentQuizQuestions.map((q, qIndex) => {
        const selectedAnswer = quizAnswers[qIndex];
        const correctAnswer = q.answers[q.correctIndex];
        const isCorrect = selectedAnswer === correctAnswer;
        if (isCorrect) correctCount++;
        
        return {
            title: q.title,
            selected: selectedAnswer,
            correct: correctAnswer,
            isCorrect: isCorrect,
            linkHint: q.linkHint
        };
    });
    
    const percentage = ((correctCount / currentQuizQuestions.length) * 100).toFixed(1);
    const resultsTitle = document.getElementById('resultsTitle');
    const resultsContent = document.getElementById('resultsContent');
    const resultsModal = document.getElementById('resultsModal');

    if (!resultsTitle || !resultsContent || !resultsModal) return;

    resultsTitle.textContent = `${currentQuizType === 'test' ? 'Результаты Теста' : 'Результаты Экзамена'}`;
    
    let html = `
        <p><strong>Вопросов:</strong> ${currentQuizQuestions.length}</p>
        <p><strong>Верно:</strong> ${correctCount}</p>
        <p><strong>Неверно:</strong> ${currentQuizQuestions.length - correctCount}</p>
        <p><strong>Процент:</strong> ${percentage}%</p>
        <p class="result-message" style="color: ${percentage >= 80 ? 'green' : (percentage >= 50 ? 'orange' : 'red')}; font-weight: bold; margin-top: 15px;">
            ${percentage >= 80 ? 'Отлично! Вы успешно прошли проверку.' : (percentage >= 50 ? 'Удовлетворительно. Рекомендуем повторить.' : 'Неудовлетворительно. Требуется обучение.')}
        </p>
        <h4 style="margin-top: 20px;">Детальный отчет:</h4>
    `;
    
    html += '<ul class="results-list">';
    quizResults.forEach((res, index) => {
        const linkParts = res.linkHint ? res.linkHint.split('/') : null;
        const linkHref = linkParts && linkParts.length > 1 ? `#instruction/${linkParts[1]}` : '#';

        html += `
            <li style="color: ${res.isCorrect ? 'green' : 'red'}; margin-bottom: 5px;">
                ${res.isCorrect ? '✅' : '❌'} Вопрос ${index + 1}: ${res.title}
                <br><small>Ваш ответ: ${res.selected || '— Нет ответа'}</small>
                ${!res.isCorrect ? `<br><small>Правильный ответ: ${res.correct}</small>` : ''}
                ${res.linkHint ? `<br><small>Инструкция: <a href="${linkHref}" onclick="document.getElementById('resultsModal').classList.add('hidden')">${res.linkHint}</a></small>` : ''}
            </li>
        `;
    });
    html += '</ul>';
    
    resultsContent.innerHTML = html;
    resultsModal.classList.remove('hidden');
    
    renderQuiz();
    
    // Отправка результатов на сервер
    apiCall('save_quiz_result', 'POST', {
        score: correctCount,
        total_questions: currentQuizQuestions.length,
        quiz_type: currentQuizType
    });
}

function renderQuestionResult(q, qIndex) {
    const selectedAnswer = quizAnswers[qIndex];
    const correctAnswer = q.answers[q.correctIndex];
    
    let html = '<div class="result-hint" style="margin-top: 10px; padding: 10px; border-top: 1px solid var(--border-color);">';
    
    if (selectedAnswer === correctAnswer) {
        html += '<p style="color: green;">✅ **Верно!**</p>';
    } else {
        html += '<p style="color: red;">❌ **Неверно.**</p>';
        html += `<p>Правильный ответ: <strong>${correctAnswer}</strong></p>`;
    }
    
    if (q.linkHint) {
        const linkParts = q.linkHint ? q.linkHint.split('/') : null;
        const linkHref = linkParts && linkParts.length > 1 ? `#instruction/${linkParts[1]}` : '#';
        html += `<p class="small-note">Повторить: <a href="${linkHref}">Инструкция: ${q.linkHint}</a></p>`;
    }
    
    html += '</div>';
    return html;
}

// =========================================================================
// 4. ЛОГИКА АДМИН-ПАНЕЛИ (admin.php)
// =========================================================================

function initAdmin() {
    if (!window.location.pathname.includes('admin.php')) return;
    
    // Инициализация Quill.js
    initQuillEditor();

    // Переключение вкладок
    document.querySelectorAll('.sidebar .category').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            document.querySelectorAll('.admin-content').forEach(content => content.classList.add('hidden'));
            document.getElementById(targetId)?.classList.remove('hidden');
            
            document.querySelectorAll('.sidebar .category').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            if (targetId === 'instructionsContent') loadInstructionsAdmin(); 
            if (targetId === 'questionsContent') loadQuestionsAdmin();     
            if (targetId === 'adminsContent') loadAdmins();
            if (targetId === 'attendanceContent') loadBranchesAndSetupAttendance();
            if (targetId === 'settingsContent') loadSettings();
            if (targetId === 'employeesContent') loadEmployees();
            if (targetId === 'quizResultsContent') loadQuizResults(); 
        });
    });
    
    loadInstructionsAdmin();
    
    document.getElementById('addAdminForm')?.addEventListener('submit', handleAddAdmin);
    document.getElementById('saveSettingsBtn')?.addEventListener('click', handleSaveSettings);
    
    // СЛУШАТЕЛИ CRUD:
    document.getElementById('addGroup')?.addEventListener('click', () => openCrudModal('add_group', 'Добавить Группу', 'Название группы', 'Иконка (например, 📖, 🛒, ⚙️)'));
    document.getElementById('addSubtopic')?.addEventListener('click', () => openCrudModal('add_subtopic', 'Добавить Подтему', 'Название подтемы'));
    document.getElementById('addBranchBtn')?.addEventListener('click', () => openCrudModal('add_branch', 'Добавить Филиал', 'Название филиала'));

    document.getElementById('crudForm')?.addEventListener('submit', handleCrudSubmit);
    document.getElementById('quillEditorForm')?.addEventListener('submit', handleSaveQuillContent); 
    
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        document.getElementById('mainSidebar')?.classList.toggle('active');
        document.body.classList.toggle('sidebar-open');
    });
}

// --- Инициализация Quill.js ---
function initQuillEditor() {
    const container = document.getElementById('quillEditorContainer');
    if (!container) return;

    const toolbarOptions = [
        ['bold', 'italic', 'underline', 'strike'],        
        ['blockquote', 'code-block'],                     
        [{ 'header': 1 }, { 'header': 2 }],               
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],    
        [{ 'script': 'sub'}, { 'script': 'super' }],      
        [{ 'indent': '-1'}, { 'indent': '+1' }],          
        [{ 'direction': 'rtl' }],                         
        [{ 'size': ['small', false, 'large', 'huge'] }],  
        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],        
        [{ 'color': [] }, { 'background': [] }],          
        [{ 'font': [] }],                                 
        [{ 'align': [] }],                                
        ['clean']                                         
    ];

    quillEditor = new Quill('#quillEditorContainer', {
        modules: {
            toolbar: toolbarOptions
        },
        theme: 'snow' 
    });
}


// =========================================================================
// 5. ЛОГИКА CRUD
// =========================================================================

async function handleEditSubtopicClick(id) {
    const result = await apiCall('load_subtopic', 'POST', { subtopic_id: id });
    if (!result) return;
    const subtopic = result.subtopic;
    
    document.getElementById('quillSubtopicId').value = subtopic.id;
    document.getElementById('quillTitle').value = subtopic.title;
    document.getElementById('quillImages').value = JSON.stringify(subtopic.images); 
    
    if (quillEditor) {
        quillEditor.root.innerHTML = subtopic.instruction;
    }

    document.getElementById('quillEditorModal').classList.remove('hidden');
}

async function handleSaveQuillContent(e) {
    e.preventDefault();
    const id = document.getElementById('quillSubtopicId').value;
    const title = document.getElementById('quillTitle').value;
    const images = document.getElementById('quillImages').value;
    
    const instruction_html = quillEditor ? quillEditor.root.innerHTML : document.querySelector('#quillEditorContainer').innerHTML;

    try {
        JSON.parse(images);
    } catch (e) {
        showToast("Ошибка: Неверный формат JSON для изображений.", 'error');
        return;
    }

    const payload = {
        id: parseInt(id),
        title: title,
        instruction_html: instruction_html,
        images: images
    };

    const result = await apiCall('save_subtopic', 'POST', payload);

    if (result) {
        document.getElementById('quillEditorModal').classList.add('hidden');
        showToast(`Инструкция "${title}" успешно обновлена.`);
        loadInstructionsAdmin(activeAdminGroupId);
    }
}

async function handleDeleteInstructionItem(type, id) {
    const item = type === 'group' ? 'группу' : 'подтему';
    if (!confirm(`Вы уверены, что хотите удалить эту ${item}? Все связанные данные будут удалены!`)) {
        return;
    }

    const result = await apiCall('delete_instruction_item', 'POST', { type, id });

    if (result) {
        showToast(`${item} успешно удалена.`);
        
        if (type === 'group') {
             activeAdminGroupId = null;
             loadInstructionsAdmin();
        } else {
             loadInstructionsAdmin(activeAdminGroupId);
        }
    }
}

function openCrudModal(actionType, title, input1Placeholder, input2Placeholder = null) {
    const modal = document.getElementById('crudModal');
    const fieldsContainer = document.getElementById('fieldsContainer');
    const crudGroupId = document.getElementById('crudGroupId');

    if (!modal || !fieldsContainer || !crudGroupId) return;

    document.getElementById('crudModalTitle').textContent = title;
    document.getElementById('crudActionType').value = actionType;
    fieldsContainer.innerHTML = '';
    crudGroupId.value = '';

    fieldsContainer.innerHTML += `
        <label for="crudInput1">${input1Placeholder}:</label>
        <input type="text" id="crudInput1" name="input1" placeholder="${input1Placeholder}" required style="width: 100%; padding: 8px; margin-bottom: 10px;">
    `;

    if (input2Placeholder) {
        fieldsContainer.innerHTML += `
            <label for="crudInput2">${input2Placeholder}:</label>
            <input type="text" id="crudInput2" name="input2" placeholder="${input2Placeholder}" style="width: 100%; padding: 8px; margin-bottom: 10px;">
        `;
    }
    
    if (actionType === 'add_subtopic') {
        if (!activeAdminGroupId) {
            showToast('Сначала выберите группу!', 'error');
            return;
        }
        crudGroupId.value = activeAdminGroupId;
        fieldsContainer.innerHTML += '<p style="margin-top: 10px;">* Контент подтемы будет редактироваться в редакторе Quill после ее создания.</p>';
    }

    modal.classList.remove('hidden');
}

async function handleCrudSubmit(e) {
    e.preventDefault();
    const modal = document.getElementById('crudModal');
    const actionType = document.getElementById('crudActionType').value;
    const input1 = document.getElementById('crudInput1').value;
    const input2 = document.getElementById('crudInput2')?.value || '';
    const groupId = document.getElementById('crudGroupId').value;

    let payload = {};
    let apiAction = '';

    if (actionType === 'add_group') {
        payload = { title: input1, icon: input2 };
        apiAction = 'add_group';
    } else if (actionType === 'add_subtopic') {
        payload = { 
            group_id: groupId, 
            title: input1, 
            instruction: 'Начните редактирование этой инструкции.', 
            images: [] 
        };
        apiAction = 'add_subtopic';
    } else if (actionType === 'add_branch') {
        payload = { name: input1 };
        apiAction = 'add_branch';
    } else {
        return;
    }

    const result = await apiCall(apiAction, 'POST', payload);

    if (result) {
        modal.classList.add('hidden');
        showToast(`${actionType.split('_')[1]} успешно добавлена.`);
        
        if (actionType === 'add_group') {
            loadInstructionsAdmin(result.id); 
        } else if (actionType === 'add_subtopic') {
            loadInstructionsAdmin(parseInt(groupId)); 
        } else if (actionType === 'add_branch') {
             loadBranchesAndSetupAttendance(); 
             location.reload(); 
        }
    }
}


// =========================================================================
// 6. ЛОГИКА АДМИН-ПАНЕЛИ (Загрузка данных)
// =========================================================================

async function loadInstructionsAdmin(selectGroupId = null) {
    const result = await apiCall('get_instructions_data', 'GET'); 
    
    const groupsContainer = document.getElementById('groupsContainer');
    groupsContainer.innerHTML = ''; 
    document.getElementById('subtopicsGrid').innerHTML = '<p>Выберите группу для отображения подтем.</p>';
    document.getElementById('addSubtopic').disabled = true;
    activeAdminGroupId = null;

    if (!result) return;
    data = result; 
    
    if (!selectGroupId) {
         const activeBtn = document.querySelector('.groups-sidebar .group.active');
         if (activeBtn) {
             selectGroupId = parseInt(activeBtn.getAttribute('data-group-id'));
         }
    }
    
    if (data.instructions.length > 0) {
        data.instructions.forEach(group => {
            const groupID = group.id;
            const isActive = selectGroupId && groupID === selectGroupId;
            
            const groupBtn = document.createElement('button');
            groupBtn.className = `group ${isActive ? 'active' : ''}`;
            groupBtn.setAttribute('data-group-id', groupID);
            groupBtn.innerHTML = `
                <span class="group-title-text">${group.icon || '📖'} ${group.title}</span>
                <div class="group-actions">
                    <button class="btn secondary small edit-group-btn" data-id="${groupID}" data-type="group">✏️</button>
                    <button class="btn secondary small delete-group-btn" data-id="${groupID}" data-type="group">❌</button>
                </div>
            `;
            
            groupBtn.addEventListener('click', () => {
                document.querySelectorAll('.groups-sidebar .group').forEach(b => b.classList.remove('active'));
                groupBtn.classList.add('active');
                
                loadSubtopicsAdmin(groupID);
                document.getElementById('addSubtopic').disabled = false;
            });
            groupsContainer.appendChild(groupBtn);
            
            if (isActive) {
                loadSubtopicsAdmin(groupID);
                document.getElementById('addSubtopic').disabled = false;
            }
        });
        
        document.querySelectorAll('.delete-group-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation(); 
                handleDeleteInstructionItem('group', parseInt(btn.getAttribute('data-id')));
            });
        });
        
    } else {
        groupsContainer.innerHTML = '<p>Инструкции не найдены. Добавьте первую группу.</p>';
    }
}

function loadSubtopicsAdmin(groupId) {
    activeAdminGroupId = groupId; 
    const subtopicsGrid = document.getElementById('subtopicsGrid');
    subtopicsGrid.innerHTML = 'Загрузка подтем...';
    
    const group = data.instructions.find(g => g.id === groupId);

    if (!group || !group.subtopics || group.subtopics.length === 0) {
         subtopicsGrid.innerHTML = '<p>Нет подтем в этой группе.</p>';
         return;
    }
    
    subtopicsGrid.innerHTML = group.subtopics.map(subtopic => `
        <div class="subtopic-card" data-subtopic-id="${subtopic.id}">
            <span class="subtopic-title-text">${subtopic.title}</span>
            <div class="subtopic-actions">
                <button class="btn secondary small edit-subtopic-btn" data-id="${subtopic.id}">✏️</button>
                <button class="btn secondary small delete-subtopic-btn" data-id="${subtopic.id}">❌</button>
            </div>
        </div>
    `).join('');
    
    document.querySelectorAll('.edit-subtopic-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            handleEditSubtopicClick(parseInt(btn.getAttribute('data-id')));
        });
    });
    
    document.querySelectorAll('.delete-subtopic-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            handleDeleteInstructionItem('subtopic', parseInt(btn.getAttribute('data-id')));
        });
    });
}

async function loadQuizResults() {
    const tableBody = document.getElementById('quizResultsTableBody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Загрузка результатов...</td></tr>';
    
    const result = await apiCall('load_quiz_results', 'GET');
    
    if (!result || !result.results || result.results.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Результаты не найдены.</td></tr>';
        return;
    }
    
    tableBody.innerHTML = '';
    result.results.forEach(res => {
        const pass = res.percentage >= 80;
        const statusClass = pass ? 'pass' : (res.percentage >= 50 ? 'warning' : 'fail');
        const formattedDate = new Date(res.created_at).toLocaleString();
        
        const row = tableBody.insertRow();
        row.innerHTML = `
            <td>${res.id}</td>
            <td>${res.username}</td>
            <td>${res.quiz_type === 'test' ? 'Тест' : 'Экзамен'}</td>
            <td>${res.score} / ${res.total_questions}</td>
            <td class="${statusClass} percentage-cell">${res.percentage}%</td>
            <td>${formattedDate}</td>
        `;
    });
}

async function loadQuestionsAdmin() {
    const result = await apiCall('load_admin_questions', 'POST');
    const groupsContainer = document.getElementById('questionGroupsContainer');
    const questionsGrid = document.getElementById('questionsGrid');
    
    if (!groupsContainer || !questionsGrid) return;
    
    groupsContainer.innerHTML = '';
    questionsGrid.innerHTML = '<p>Выберите категорию вопросов.</p>';
    
    if (!result) return;
    
    const questionsByCat = result.questions_by_category;

    if (Object.keys(questionsByCat).length > 0) {
        const categories = Object.keys(questionsByCat);
        
        categories.forEach((category) => {
            const categoryBtn = document.createElement('button');
            categoryBtn.className = 'category group';
            categoryBtn.textContent = category;
            
            categoryBtn.addEventListener('click', () => {
                document.querySelectorAll('#questionGroupsContainer .category').forEach(b => b.classList.remove('active'));
                categoryBtn.classList.add('active');
                
                renderQuestionsGrid(questionsByCat[category]);
            });
            groupsContainer.appendChild(categoryBtn);
        });
        
        groupsContainer.innerHTML += '<button class="category group primary" id="addNewQuestionBtn" style="margin-top: 10px;">➕ Добавить Вопрос</button>';
        
    } else {
        groupsContainer.innerHTML = '<p>Вопросы не найдены. Добавьте первый вопрос.</p>';
    }
}

function renderQuestionsGrid(questions) {
     const questionsGrid = document.getElementById('questionsGrid');
     if (!questionsGrid) return;

     questionsGrid.innerHTML = questions.map(q => `
        <div class="subtopic-card question-card" data-question-id="${q.id}">
            <span class="subtopic-title-text">
                ${q.title} 
                <span class="small-note" style="color: var(--text-color-light); margin-left: 10px;">(${q.type === 'test' ? 'Тест' : 'Экзамен'})</span>
            </span>
            <div class="subtopic-actions">
                 <button class="btn secondary small edit-question-btn" data-id="${q.id}">✏️</button>
                 <button class="btn secondary small delete-question-btn" data-id="${q.id}">❌</button>
            </div>
        </div>
     `).join('');
}


async function loadAdmins() {
    const data = await apiCall('load_admins', 'GET');
    const tableBody = document.getElementById('adminsTableBody');
    if (!tableBody) return;
    tableBody.innerHTML = '';
    
    if (data && data.admins) {
        data.admins.forEach(admin => {
            const isMainAdmin = admin.role === 'main_admin';
            const row = tableBody.insertRow();
            row.innerHTML = `
                <td>${admin.id}</td>
                <td>${admin.login}</td>
                <td><span class="admin-role ${isMainAdmin ? 'main' : 'regular'}">${isMainAdmin ? 'Главный' : 'Обычный'}</span></td>
                <td>${new Date(admin.created_at).toLocaleDateString()}</td>
                <td>
                    ${!isMainAdmin && window.location.pathname.includes('admin.php') ? 
                        `<button class="btn secondary small" onclick="handleDeleteAdmin(${admin.id})">Удалить</button>` 
                        : (isMainAdmin ? '—' : 'Недостаточно прав')}
                </td>
            `;
        });
    }
}

async function handleAddAdmin(e) {
    e.preventDefault();
    const loginInput = document.getElementById('newAdminLogin');
    const passwordInput = document.getElementById('newAdminPassword');
    
    if (!loginInput || !passwordInput) return;

    const login = loginInput.value;
    const password = passwordInput.value;
    
    const data = await apiCall('add_admin', 'POST', { login, password });
    
    if (data) {
        showToast('Администратор добавлен успешно.');
        loginInput.value = '';
        passwordInput.value = '';
        loadAdmins();
    }
}

async function handleDeleteAdmin(id) {
    if (!confirm('Вы уверены, что хотите удалить этого администратора?')) return;
    
    const data = await apiCall('delete_admin', 'POST', { id });
    
    if (data) {
        showToast('Администратор успешно удален.');
        loadAdmins();
    }
}

async function loadSettings() {
    const data = await apiCall('load_settings', 'GET');
    if (data && data.settings) {
        const quizCountInput = document.getElementById('quizCount');
        const examTimerInput = document.getElementById('examTimer');
        
        if (quizCountInput) {
            quizCountInput.value = data.settings.quiz_questions_count || 20;
            quizSettings.quiz_questions_count = parseInt(data.settings.quiz_questions_count);
        }
        
        if (examTimerInput) {
            examTimerInput.value = data.settings.exam_timer_minutes || 60;
            quizSettings.exam_timer_minutes = parseInt(data.settings.exam_timer_minutes);
        }
    }
}

async function handleSaveSettings() {
    const quizCountInput = document.getElementById('quizCount');
    const examTimerInput = document.getElementById('examTimer');
    
    if (!quizCountInput || !examTimerInput) return;

    const quiz_questions_count = quizCountInput.value;
    const exam_timer_minutes = examTimerInput.value;
    
    const data = await apiCall('save_settings', 'POST', { quiz_questions_count, exam_timer_minutes });
    
    if (data) {
        showToast('Настройки сохранены.');
        loadSettings(); 
    }
}

async function loadBranchesAndSetupAttendance() {
    const branchesData = await apiCall('load_branches', 'GET');
    const filterBranch = document.getElementById('filterBranch');
    
    if (branchesData && filterBranch) {
        filterBranch.innerHTML = '<option value="">Все филиалы</option>';
        if (branchesData.branches) {
            branchesData.branches.forEach(branch => {
                const option = document.createElement('option');
                option.value = branch.id;
                option.textContent = branch.name;
                filterBranch.appendChild(option);
            });
        }
    }
    
    document.getElementById('refreshAttendance')?.addEventListener('click', loadAttendance);
    document.getElementById('filterDate')?.addEventListener('change', loadAttendance);
    document.getElementById('filterBranch')?.addEventListener('change', loadAttendance);
    
    loadAttendance();
}

async function loadAttendance() {
    const date = document.getElementById('filterDate')?.value;
    const branch_id = document.getElementById('filterBranch')?.value;
    const tableBody = document.getElementById('attendanceTableBody');

    if (!date || !tableBody) return;
    
    const data = await apiCall('load_attendance', 'POST', { date, branch_id });
    tableBody.innerHTML = '';
    
    if (data && data.attendance) {
        if (data.attendance.length === 0) {
             tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">На дату ${date} отметок не найдено.</td></tr>`;
             return;
        }
        
        data.attendance.forEach(att => {
            const row = tableBody.insertRow();
            const statusIcon = att.status === 'Отметился' ? '🟢 Отметился' : '⚪ Не отметился'; 
            
            row.innerHTML = `
                <td>${att.branch_name}</td>
                <td>${att.username}</td>
                <td>${att.phone}</td>
                <td>${att.check_in_time}</td>
                <td>${statusIcon}</td>
            `;
        });
    } else {
         tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">Ошибка загрузки данных или отметок не найдено.</td></tr>`;
    }
}

async function loadEmployees() {
    const tableBody = document.getElementById('employeesTableBody');
    if (!tableBody) return;
    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Загрузка данных...</td></tr>';
    
    const data = await apiCall('load_employees', 'GET');
    
    if (data && data.employees) {
        tableBody.innerHTML = '';
        if (data.employees.length === 0) {
             tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">Сотрудники не найдены.</td></tr>`;
             return;
        }

        data.employees.forEach(user => {
            const row = tableBody.insertRow();
            row.innerHTML = `
                <td>${user.id}</td>
                <td>${user.username}</td>
                <td>${user.phone}</td>
                <td>${user.date_of_birth}</td>
                <td>${new Date(user.created_at).toLocaleDateString()}</td>
                <td><button class="btn secondary small" onclick="handleDeleteEmployee(${user.id})">Удалить</button></td>
            `;
        });
    } else {
        tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">Ошибка загрузки данных.</td></tr>`;
    }
}


// =========================================================================
// 7. ГЛАВНАЯ ИНИЦИАЛИЗАЦИЯ
// =========================================================================

async function init() {
    initThemePicker(); 
    
    // Добавляем слушатель для hashchange (для навигации без перезагрузки)
    window.addEventListener('hashchange', handleHashChange);
    
    if (window.location.pathname.includes('admin.php')) {
        initAdmin(); 
        await loadSettings(); 

    } else {
        // Логика для главной страницы
        // Загружаем данные только если пользователь авторизован (проверяем по наличию сайдбара)
        if (document.getElementById('mainSidebar')) {
             await loadFullData(); 
             await loadSettings(); 
            
             document.getElementById('testsMenuBtn')?.addEventListener('click', () => startQuiz('test'));
             document.getElementById('examMenuBtn')?.addEventListener('click', () => {
                 const password = prompt("Для начала Экзамена введите пароль:");
                 if (password === EXAM_PASSWORD) { 
                     startQuiz('exam');
                 } else {
                     showToast('Неверный пароль для Экзамена!', 'error');
                 }
             });
            
             document.getElementById('closeResultsModal')?.addEventListener('click', () => {
                 document.getElementById('resultsModal')?.classList.add('hidden');
             });
        }
    }
}

init();

// Слушатель для мобильного меню (бургер)
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('mainSidebar')?.classList.toggle('active');
    document.body.classList.toggle('sidebar-open');
});

// Слушатель для скрытия лайтбокса
document.getElementById('imageLightbox')?.addEventListener('click', (e) => {
    if (e.target.id === 'imageLightbox' || e.target.id === 'lightboxImage') {
        document.getElementById('imageLightbox').style.display = 'none';
    }
});