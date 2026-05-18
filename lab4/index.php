<?php
session_start();

$host = 'localhost';
$dbname = 'u82431';
$username = 'u82431';
$password = '6531457';

$error = '';
$success = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// ========= НОВОЕ: работа с Cookies =========
// Функция для получения значения из Cookies или POST, или пустой строки
function getValue($key, $default = '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$key])) {
        return trim($_POST[$key]);
    }
    if (isset($_COOKIE[$key])) {
        return trim($_COOKIE[$key]);
    }
    return $default;
}

// Для массива языков (multiple select)
function getLanguagesValue() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['languages'])) {
        return $_POST['languages'];
    }
    if (isset($_COOKIE['languages'])) {
        return explode(',', $_COOKIE['languages']);
    }
    return [];
}

$full_name = getValue('full_name');
$phone = getValue('phone');
$email = getValue('email');
$birth_date = getValue('birth_date');
$gender = getValue('gender');
$languages = getLanguagesValue();
$bio = getValue('bio');
$contract_accepted = getValue('contract_accepted', 0);

// Сохраняем в Cookies (успешные данные или ошибочные — всё равно)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    setcookie('full_name', $full_name, time() + 365*24*3600, '/');
    setcookie('phone', $phone, time() + 365*24*3600, '/');
    setcookie('email', $email, time() + 365*24*3600, '/');
    setcookie('birth_date', $birth_date, time() + 365*24*3600, '/');
    setcookie('gender', $gender, time() + 365*24*3600, '/');
    setcookie('languages', implode(',', $languages), time() + 365*24*3600, '/');
    setcookie('bio', $bio, time() + 365*24*3600, '/');
    setcookie('contract_accepted', $contract_accepted, time() + 365*24*3600, '/');
}
// =========================================

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ========= ВАЛИДАЦИЯ (та же, что в lab3) =========
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $full_name)) {
        $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефисы (до 150 символов)';
    }

    if (!preg_match('/^[\d\-\+\(\)\s]{10,20}$/', $phone)) {
        $errors['phone'] = 'Телефон должен содержать только цифры, +, -, пробелы и скобки (10–20 символов)';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный email (например, name@example.com)';
    }

    if (strtotime($birth_date) > time()) {
        $errors['birth_date'] = 'Дата рождения не может быть в будущем';
    }

    if (!in_array($gender, ['male', 'female'])) {
        $errors['gender'] = 'Выберите корректный пол';
    }

    $allowed = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования';
    } else {
        foreach ($languages as $lang) {
            if (!in_array($lang, $allowed)) {
                $errors['languages'] = 'Недопустимый язык: ' . htmlspecialchars($lang);
                break;
            }
        }
    }

    if (!$contract_accepted) {
        $errors['contract_accepted'] = 'Вы должны согласиться с условиями';
    }
    // ===============================================

    // Если нет ошибок — сохраняем в БД и показываем успех
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, phone, email, birth_date, gender, bio, contract_accepted)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $bio, $contract_accepted]);
            $userId = $pdo->lastInsertId();

            $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language) VALUES (?, ?)");
            foreach ($languages as $lang) {
                $stmtLang->execute([$userId, $lang]);
            }

            $pdo->commit();
            $success = '✅ Данные успешно сохранены!';

            // После успеха — не очищаем форму (Cookies подставят значения при следующем визите)
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Ошибка БД: ' . $e->getMessage();
        }
    } else {
        // Сохраняем ошибки в Cookies (до конца сессии)
        setcookie('errors', json_encode($errors), 0, '/');
        $error = 'Пожалуйста, исправьте ошибки в форме.';
    }
}

// Загружаем ошибки из Cookies (если есть)
$errorsFromCookie = [];
if (isset($_COOKIE['errors'])) {
    $errorsFromCookie = json_decode($_COOKIE['errors'], true);
    setcookie('errors', '', time() - 3600, '/'); // удаляем после прочтения
}
$errors = array_merge($errors, $errorsFromCookie);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Лабораторная №4 — Валидация + Cookies</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>📝 Регистрационная форма (lab4)</h1>

        <?php if ($error): ?>
            <div class="error">❌ <?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
                <label>ФИО *</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($full_name) ?>">
                <?php if (isset($errors['full_name'])): ?>
                    <div class="field-error"><?= $errors['full_name'] ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                <label>Телефон *</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($phone) ?>">
                <?php if (isset($errors['phone'])): ?>
                    <div class="field-error"><?= $errors['phone'] ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
                <label>Email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>">
                <?php if (isset($errors['email'])): ?>
                    <div class="field-error"><?= $errors['email'] ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['birth_date']) ? 'has-error' : '' ?>">
                <label>Дата рождения *</label>
                <input type="date" name="birth_date" value="<?= htmlspecialchars($birth_date) ?>">
                <?php if (isset($errors['birth_date'])): ?>
                    <div class="field-error"><?= $errors['birth_date'] ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['gender']) ? 'has-error' : '' ?>">
                <label>Пол *</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male" <?= $gender == 'male' ? 'checked' : '' ?>> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?= $gender == 'female' ? 'checked' : '' ?>> Женский</label>
                </div>
                <?php if (isset($errors['gender'])): ?>
                    <div class="field-error"><?= $errors['gender'] ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['languages']) ? 'has-error' : '' ?>">
                <label>Любимые языки *</label>
                <select name="languages[]" multiple size="5">
                    <?php
                    $langList = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
                    foreach ($langList as $lang): ?>
                        <option value="<?= $lang ?>" <?= in_array($lang, $languages) ? 'selected' : '' ?>><?= $lang ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['languages'])): ?>
                    <div class="field-error"><?= $errors['languages'] ?></div>
                <?php endif; ?>
                <small>Удерживайте Ctrl (Cmd) для выбора нескольких</small>
            </div>

            <div class="form-group">
                <label>Биография</label>
                <textarea name="bio" rows="4"><?= htmlspecialchars($bio) ?></textarea>
            </div>

            <div class="form-group checkbox-group <?= isset($errors['contract_accepted']) ? 'has-error' : '' ?>">
                <input type="checkbox" name="contract_accepted" value="1" <?= $contract_accepted ? 'checked' : '' ?>>
                <label>Я ознакомлен(а) с контрактом *</label>
                <?php if (isset($errors['contract_accepted'])): ?>
                    <div class="field-error"><?= $errors['contract_accepted'] ?></div>
                <?php endif; ?>
            </div>

            <button type="submit">Сохранить</button>
        </form>
    </div>
</body>
</html>