<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Лабораторная работа №3 — Форма</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #1e293b;
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 20px 35px -12px #020617;
        }
        h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: #a5b4fc;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #cbd5e1;
        }
        input[type="text"],
        input[type="tel"],
        input[type="email"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 12px;
            border: 1px solid #334155;
            background: #0f172a;
            color: #e2e8f0;
            font-size: 1rem;
        }
        .radio-group {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.5rem;
        }
        .radio-group label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: normal;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .checkbox-group input {
            width: auto;
        }
        button {
            background: #6366f1;
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        button:hover {
            background: #818cf8;
            transform: scale(1.02);
        }
        .error {
            background: #7f1a1a;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            color: #fecaca;
        }
        .success {
            background: #15803d;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            color: #dcfce7;
        }
        hr {
            border-color: #334155;
            margin: 1.5rem 0;
        }
        small {
            color: #94a3b8;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📝 Регистрационная форма</h1>

        <?php
        // Подключаемся к базе данных
        $host = 'localhost';
        $dbname = 'lab3';
        $username = 'u82431';
        $password = '6531457';
        
        $error = '';
        $success = '';

        // Проверяем, была ли отправлена форма
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Получаем данные из формы
            $full_name = trim($_POST['full_name'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $birth_date = $_POST['birth_date'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $languages = $_POST['languages'] ?? [];
            $bio = trim($_POST['bio'] ?? '');
            $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;
            
            // Валидация данных
            $errors = [];
            
            // 1. ФИО: только буквы, пробелы, дефисы, не длиннее 150
            if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $full_name)) {
                $errors[] = 'ФИО должно содержать только буквы, пробелы и дефисы (до 150 символов)';
            }
            
            // 2. Телефон: цифры, +, -, пробелы, скобки (10-20 символов)
            if (!preg_match('/^[\d\-\+\(\)\s]{10,20}$/', $phone)) {
                $errors[] = 'Телефон должен содержать только цифры, +, -, пробелы и скобки (10-20 символов)';
            }
            
            // 3. Email: валидный формат
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Введите корректный email (например, name@example.com)';
            }
            
            // 4. Дата рождения: не в будущем
            if (strtotime($birth_date) > time()) {
                $errors[] = 'Дата рождения не может быть в будущем';
            }
            
            // 5. Пол: только male или female
            if (!in_array($gender, ['male', 'female'])) {
                $errors[] = 'Выберите корректный пол';
            }
            
            // 6. Языки: минимум 1, все из списка
            $allowed_langs = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
            if (empty($languages)) {
                $errors[] = 'Выберите хотя бы один язык программирования';
            } else {
                foreach ($languages as $lang) {
                    if (!in_array($lang, $allowed_langs)) {
                        $errors[] = 'Недопустимый язык программирования: ' . htmlspecialchars($lang);
                        break;
                    }
                }
            }
            
            // 7. Чекбокс обязателен
            if (!$contract_accepted) {
                $errors[] = 'Вы должны согласиться с условиями контракта';
            }
            
            // Если ошибок нет — сохраняем в базу
            if (empty($errors)) {
                try {
                    // Подключаемся к базе данных
                    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Начинаем транзакцию
                    $pdo->beginTransaction();
                    
                    // Вставляем данные в таблицу users
                    $stmt = $pdo->prepare("
                        INSERT INTO users (full_name, phone, email, birth_date, gender, bio, contract_accepted)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $bio, $contract_accepted]);
                    
                    // Получаем ID только что созданной записи
                    $userId = $pdo->lastInsertId();
                    
                    // Вставляем языки программирования
                    $stmtLang = $pdo->prepare("INSERT INTO user_programming_languages (user_id, language) VALUES (?, ?)");
                    foreach ($languages as $lang) {
                        $stmtLang->execute([$userId, $lang]);
                    }
                    
                    // Фиксируем транзакцию
                    $pdo->commit();
                    
                    $success = '✅ Данные успешно сохранены!';
                    
                    // Очищаем форму
                    $_POST = [];
                    $full_name = $phone = $email = $birth_date = $gender = $bio = '';
                    $languages = [];
                    $contract_accepted = 0;
                    
                } catch (PDOException $e) {
                    // Откатываем транзакцию в случае ошибки
                    $pdo->rollBack();
                    $errors[] = 'Ошибка базы данных: ' . $e->getMessage();
                }
            }
            
            if (!empty($errors)) {
                $error = implode('<br>', $errors);
            }
        }
        ?>

        <?php if ($error): ?>
            <div class="error">
                ❌ <strong>Ошибка!</strong><br><?= $error ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>ФИО *</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($full_name ?? '') ?>" required maxlength="150">
            </div>
            
            <div class="form-group">
                <label>Телефон *</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Дата рождения *</label>
                <input type="date" name="birth_date" value="<?= htmlspecialchars($birth_date ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label>Пол *</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male" <?= (($gender ?? '') == 'male') ? 'checked' : '' ?> required> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?= (($gender ?? '') == 'female') ? 'checked' : '' ?> required> Женский</label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Любимые языки программирования *</label>
                <select name="languages[]" multiple size="5" required>
                    <option value="Pascal" <?= in_array('Pascal', $languages ?? []) ? 'selected' : '' ?>>Pascal</option>
                    <option value="C" <?= in_array('C', $languages ?? []) ? 'selected' : '' ?>>C</option>
                    <option value="C++" <?= in_array('C++', $languages ?? []) ? 'selected' : '' ?>>C++</option>
                    <option value="JavaScript" <?= in_array('JavaScript', $languages ?? []) ? 'selected' : '' ?>>JavaScript</option>
                    <option value="PHP" <?= in_array('PHP', $languages ?? []) ? 'selected' : '' ?>>PHP</option>
                    <option value="Python" <?= in_array('Python', $languages ?? []) ? 'selected' : '' ?>>Python</option>
                    <option value="Java" <?= in_array('Java', $languages ?? []) ? 'selected' : '' ?>>Java</option>
                    <option value="Haskell" <?= in_array('Haskell', $languages ?? []) ? 'selected' : '' ?>>Haskell</option>
                    <option value="Clojure" <?= in_array('Clojure', $languages ?? []) ? 'selected' : '' ?>>Clojure</option>
                    <option value="Prolog" <?= in_array('Prolog', $languages ?? []) ? 'selected' : '' ?>>Prolog</option>
                    <option value="Scala" <?= in_array('Scala', $languages ?? []) ? 'selected' : '' ?>>Scala</option>
                    <option value="Go" <?= in_array('Go', $languages ?? []) ? 'selected' : '' ?>>Go</option>
                </select>
                <small>Удерживайте Ctrl (или Cmd на Mac) для выбора нескольких языков</small>
            </div>
            
            <div class="form-group">
                <label>Биография</label>
                <textarea name="bio" rows="5"><?= htmlspecialchars($bio ?? '') ?></textarea>
            </div>
            
            <div class="form-group checkbox-group">
                <input type="checkbox" name="contract_accepted" value="1" <?= ($contract_accepted ?? 0) ? 'checked' : '' ?> required>
                <label>Я ознакомлен(а) с контрактом *</label>
            </div>
            
            <button type="submit">Сохранить</button>
        </form>
    </div>
</body>
</html>