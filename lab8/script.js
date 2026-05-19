document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mainForm');
    const messageArea = document.getElementById('message-area');
    
    form.addEventListener('submit', async function(event) {
        event.preventDefault(); // Отменяем стандартную отправку
        
        // Собираем данные формы
        const formData = new FormData(form);
        const languages = formData.getAll('languages[]');
        
        const data = {
            full_name: formData.get('full_name'),
            phone: formData.get('phone'),
            email: formData.get('email'),
            birth_date: formData.get('birth_date'),
            gender: formData.get('gender'),
            languages: languages,
            bio: formData.get('bio'),
            contract_accepted: formData.get('contract_accepted') === '1' ? 1 : 0,
            csrf_token: formData.get('csrf_token')
        };
        
        // Определяем, создаём нового пользователя или обновляем
        const isLoggedIn = document.querySelector('.info') !== null;
        const url = isLoggedIn ? '/zadanie-1/lab8/index.php?action=user' : '/zadanie-1/lab8/index.php?action=user';
        const method = isLoggedIn ? 'PUT' : 'POST';
        
        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': data.csrf_token
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                if (result.login && result.password) {
                    messageArea.innerHTML = `<div class="success">✅ ${result.message}<br>Логин: <strong>${result.login}</strong><br>Пароль: <strong>${result.password}</strong></div>`;
                } else {
                    messageArea.innerHTML = `<div class="success">✅ ${result.message}</div>`;
                }
                // Очищаем форму
                form.reset();
            } else {
                if (result.errors) {
                    let errorHtml = '<div class="error">❌ Ошибки:<ul>';
                    for (const [field, msg] of Object.entries(result.errors)) {
                        errorHtml += `<li>${field}: ${msg}</li>`;
                    }
                    errorHtml += '</ul></div>';
                    messageArea.innerHTML = errorHtml;
                } else {
                    messageArea.innerHTML = `<div class="error">❌ ${result.error || 'Ошибка при отправке'}</div>`;
                }
            }
        } catch (error) {
            console.error('Ошибка:', error);
            messageArea.innerHTML = '<div class="error">❌ Ошибка сети или сервера</div>';
        }
    });
});