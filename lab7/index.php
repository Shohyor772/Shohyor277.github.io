<?php
session_start();
// Генерация CSRF-токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$host = 'localhost';
$dbname = 'u82431';
$username = 'u82431';
$password = '6531457';

// Отключаем показ ошибок (Information Disclosure)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$error = '';
$success = '';
$loginInfo = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Ошибка подключения к БД. Пожалуйста, попробуйте позже.");
}

// Функции (те же, что в lab5/6)
function getUserByLogin($pdo, $login) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
    $stmt->execute([$login]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getUserLanguages($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT language FROM user_languages WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function saveUserLanguages($pdo, $userId, $languages) {
    $stmt = $pdo->prepare("DELETE FROM user_languages WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language) VALUES (?, ?)");
    foreach ($languages as $lang) {
        $stmtLang->execute([$userId, $lang]);
    }
}

// Cookies для незалогиненных
function getValue($key, $default = '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$key])) {
        return trim($_POST[$key]);
    }
    if (isset($_COOKIE[$key])) {
        return trim($_COOKIE[$key]);
    }
    return $default;
}

function getLanguagesValue() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['languages'])) {
        return $_POST['languages'];
    }
    if (isset($_COOKIE['languages'])) {
        return explode(',', $_COOKIE['languages']);
    }
    return [];
}

// Логин
$loginError = '';
if (isset($_POST['login_action'])) {
    $inputLogin = trim($_POST['input_login'] ?? '');
    $inputPassword = $_POST['input_password'] ?? '';
    
    $user = getUserByLogin($pdo, $inputLogin);
    if ($user && password_verify($inputPassword, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_login'] = $user['login'];
        // Регенерируем CSRF-токен после логина
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $success = 'Добро пожаловать, ' . htmlspecialchars($user['full_name']) . '!';
    } else {
        $loginError = 'Неверный логин или пароль.';
    }
}

// Выход
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Заполнение формы
$full_name = '';
$phone = '';
$email = '';
$birth_date = '';
$gender = '';
$languages = [];
$bio = '';
$contract_accepted = 0;

if (isset($_SESSION['user_id'])) {
    $user = getUserById($pdo, $_SESSION['user_id']);
    if ($user) {
        $full_name = $user['full_name'];
        $phone = $user['phone'];
        $email = $user['email'];
        $birth_date = $user['birth_date'];
        $gender = $user['gender'];
        $bio = $user['bio'];
        $contract_accepted = $user['contract_accepted'];
        $languages = getUserLanguages($pdo, $user['id']);
    }
} else {
    $full_name = getValue('full_name');
    $phone = getValue('phone');
    $email = getValue('email');
    $birth_date = getValue('birth_date');
    $gender = getValue('gender');
    $languages = getLanguagesValue();
    $bio = getValue('bio');
    $contract_accepted = getValue('contract_accepted', 0);
}

// Обработка сохранения с проверкой CSRF
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_action'])) {
    // **CSRF ЗАЩИТА**
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Ошибка безопасности: неверный CSRF-токен. Попробуйте снова.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $birth_date = $_POST['birth_date'] ?? '';
        $gender = $_POST['gender'] ?? '';
        $languages = $_POST['languages'] ?? [];
        $bio = trim($_POST['bio'] ?? '');
        $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;

        // Валидация
        if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $full_name)) {
            $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефисы (до 150 символов)';
        }
        if (!preg_match('/^[\d\-\+\(\)\s]{10,20}$/', $phone)) {
            $errors['phone'] = 'Телефон должен содержать только цифры, +, -, пробелы и скобки (10–20 символов)';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Введите корректный email';
        }
        if (strtotime($birth_date) > time()) {
            $errors['birth_date'] = 'Дата рождения не может быть в будущем';
        }
        if (!in_array($gender, ['male', 'female'])) {
            $errors['gender'] = 'Выберите корректный пол';
        }
        $allowed = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
        if (empty($languages)) {
            $errors['languages'] = 'Выберите хотя бы один язык';
        } else {
            foreach ($languages as $lang) {
                if (!in_array($lang, $allowed)) {
                    $errors['languages'] = 'Недопустимый язык';
                    break;
                }
            }
        }
        if (!$contract_accepted) {
            $errors['contract_accepted'] = 'Вы должны согласиться с условиями';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                if (isset($_SESSION['user_id'])) {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET full_name = ?, phone = ?, email = ?, birth_date = ?, gender = ?, bio = ?, contract_accepted = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $bio, $contract_accepted, $_SESSION['user_id']]);
                    saveUserLanguages($pdo, $_SESSION['user_id'], $languages);
                    $success = '✅ Данные успешно обновлены!';
                } else {
                    $login = 'user_' . bin2hex(random_bytes(4));
                    $plainPassword = bin2hex(random_bytes(3));
                    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("
                        INSERT INTO users (full_name, phone, email, birth_date, gender, bio, contract_accepted, login, password_hash)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $bio, $contract_accepted, $login, $passwordHash]);
                    $userId = $pdo->lastInsertId();

                    $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language) VALUES (?, ?)");
                    foreach ($languages as $lang) {
                        $stmtLang->execute([$userId, $lang]);
                    }
                    $loginInfo = "Ваш логин: <strong>$login</strong><br>Ваш пароль: <strong>$plainPassword</strong><br>";
                    $success = '✅ Данные успешно сохранены! ' . $loginInfo;
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['user_login'] = $login;
                }
                $pdo->commit();
                // Регенерируем CSRF-токен после успешной операции
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log($e->getMessage());
                $error = 'Ошибка при сохранении данных. Пожалуйста, попробуйте позже.';
            }
        } else {
            if (!isset($_SESSION['user_id'])) {
                setcookie('full_name', $full_name, time() + 365*24*3600, '/');
                setcookie('phone', $phone, time() + 365*24*3600, '/');
                setcookie('email', $email, time() + 365*24*3600, '/');
                setcookie('birth_date', $birth_date, time() + 365*24*3600, '/');
                setcookie('gender', $gender, time() + 365*24*3600, '/');
                setcookie('languages', implode(',', $languages), time() + 365*24*3600, '/');
                setcookie('bio', $bio, time() + 365*24*3600, '/');
                setcookie('contract_accepted', $contract_accepted, time() + 365*24*3600, '/');
            }
            $error = 'Пожалуйста, исправьте ошибки в форме.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <title>Лабораторная №7 — Безопасная форма</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>🔒 Безопасная форма (lab7)</h1>
        <a href="admin.php" style="float: right; background: #6366f1; padding: 0.3rem 1rem; border-radius: 20px; text-decoration: none; color: white;">🔐 Админ-панель</a>
        <div style="clear:both"></div>

        <?php if ($loginInfo): ?>
            <div class="success">🎉 <?= $loginInfo ?><br><small>Сохраните эти данные!</small></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success && !$loginInfo): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="info" style="background: #1e3a5f; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
                ✅ Вы авторизованы как <strong><?= htmlspecialchars($_SESSION['user_login']) ?></strong>
                <a href="?logout=1" style="float: right; color: #f87171;">Выйти</a>
            </div>
        <?php else: ?>
            <div class="login-form" style="background: #0f172a; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
                <h3>🔐 Вход для редактирования</h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Логин</label>
                        <input type="text" name="input_login" required>
                    </div>
                    <div class="form-group">
                        <label>Пароль</label>
                        <input type="password" name="input_password" required>
                    </div>
                    <button type="submit" name="login_action">Войти</button>
                    <?php if ($loginError): ?>
                        <div class="field-error"><?= htmlspecialchars($loginError) ?></div>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group <?= isset($errors['full_name']) ? 'has-error' : '' ?>">
                <label>ФИО *</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($full_name) ?>">
                <?php if (isset($errors['full_name'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errors['full_name']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
                <label>Телефон *</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($phone) ?>">
                <?php if (isset($errors['phone'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errors['phone']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
                <label>Email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email) ?>">
                <?php if (isset($errors['email'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errors['email']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['birth_date']) ? 'has-error' : '' ?>">
                <label>Дата рождения *</label>
                <input type="date" name="birth_date" value="<?= htmlspecialchars($birth_date) ?>">
                <?php if (isset($errors['birth_date'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errors['birth_date']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['gender']) ? 'has-error' : '' ?>">
                <label>Пол *</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male" <?= $gender == 'male' ? 'checked' : '' ?>> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?= $gender == 'female' ? 'checked' : '' ?>> Женский</label>
                </div>
                <?php if (isset($errors['gender'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errors['gender']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group <?= isset($errors['languages']) ? 'has-error' : '' ?>">
                <label>Любимые языки *</label>
                <select name="languages[]" multiple size="5">
                    <?php
                    $langList = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
                    foreach ($langList as $lang): ?>
                        <option value="<?= htmlspecialchars($lang) ?>" <?= in_array($lang, $languages) ? 'selected' : '' ?>><?= htmlspecialchars($lang) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['languages'])): ?>
                    <div class="field-error"><?= htmlspecialchars($errors['languages']) ?></div>
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
                    <div class="field-error"><?= htmlspecialchars($errors['contract_accepted']) ?></div>
                <?php endif; ?>
            </div>

            <button type="submit" name="save_action">Сохранить</button>
        </form>
    </div>
</body>
</html>