// ========= СЛАЙДЕР =========
let currentSlide = 0;
const slides = document.querySelectorAll('.slide');
const dots = document.querySelectorAll('.dot');

function showSlide(index) {
    if (!slides.length) return;
    if (index >= slides.length) index = 0;
    if (index < 0) index = slides.length - 1;
    
    currentSlide = index;
    const slider = document.querySelector('.slider');
    if (slider) slider.style.transform = `translateX(-${currentSlide * 100}%)`;
    
    dots.forEach((dot, i) => {
        dot.classList.toggle('active', i === currentSlide);
    });
}

function nextSlide() { showSlide(currentSlide + 1); }
function prevSlide() { showSlide(currentSlide - 1); }

if (slides.length) {
    setInterval(nextSlide, 5000);
}

// ========= АСИНХРОННАЯ ФОРМА =========
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mainForm');
    const messageArea = document.getElementById('message-area');
    
    if (!form) return;
    
    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        
        const languages = Array.from(form.querySelector('select[name="languages[]"]')?.selectedOptions || [])
            .map(opt => opt.value);
        
        const data = {
            full_name: form.full_name?.value || '',
            phone: form.phone?.value || '',
            email: form.email?.value || '',
            birth_date: form.birth_date?.value || '',
            gender: form.querySelector('input[name="gender"]:checked')?.value || '',
            languages: languages,
            bio: form.bio?.value || '',
            contract_accepted: form.contract_accepted?.checked ? 1 : 0,
            csrf_token: form.csrf_token?.value || ''
        };
        
        const isLoggedIn = document.querySelector('.info') !== null;
        const method = isLoggedIn ? 'PUT' : 'POST';
        
        try {
            const response = await fetch('index.php?action=user', {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': data.csrf_token
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok) {
                if (result.login && result.password) {
                    messageArea.innerHTML = `
                        <div class="success">
                            ✅ ${result.message}<br>
                            Логин: <strong>${result.login}</strong><br>
                            Пароль: <strong>${result.password}</strong>
                        </div>`;
                    form.reset();
                    setTimeout(() => { messageArea.innerHTML = ''; }, 5000);
                } else {
                    messageArea.innerHTML = `<div class="success">✅ ${result.message}</div>`;
                    setTimeout(() => { messageArea.innerHTML = ''; }, 3000);
                }
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