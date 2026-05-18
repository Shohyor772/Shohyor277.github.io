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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $languages = $_POST['languages'] ?? [];
    $bio = trim($_POST['bio'] ?? '');
    $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;

    $errors = [];

    // 1. ФИО: буквы, пробелы, дефисы, до 150 символов
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $full_name)) {
        $errors[] = 'ФИО должно содержать только буквы, пробелы и дефисы (до 150 символов)';
    }

    // 2. Телефон: цифры, +, -, пробелы, скобки (10–20 символов)
    if (!preg_match('/^[\d\-\+\(\)\s]{10,20}$/', $phone)) {
        $errors[] = 'Телефон должен содержать только цифры, +, -, пробелы и скобки (10–20 символов)';
    }

    // 3. Email: стандартная проверка
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
    $allowed = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
    if (empty($languages)) {
        $errors[] = 'Выберите хотя бы один язык программирования';
    } else {
        foreach ($languages as $lang) {
            if (!in_array($lang, $allowed)) {
                $errors[] = 'Недопустимый язык: ' . htmlspecialchars($lang);
                break;
            }
        }
    }

    // 7. Чекбокс
    if (!$contract_accepted) {
        $errors[] = 'Вы должны согласиться с условиями';
    }

    // Если нет ошибок — сохраняем
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

            // Очищаем форму
            $_POST = [];
            $full_name = $phone = $email = $birth_date = $gender = $bio = $contract_accepted = '';
            $languages = [];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Ошибка БД: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Лабораторная №3 — Форма + БД</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>📝 Регистрационная форма (lab3)</h1>

        <?php if ($error): ?>
            <div class="error">❌ <?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>ФИО *</label>
                <input type="text" name="full_name" value="<?= htmlspecialchars($full_name ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Телефон *</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($phone ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Дата рождения *</label>
                <input type="date" name="birth_date" value="<?= htmlspecialchars($birth_date ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Пол *</label>
                <div class="radio-group">
                    <label><input type="radio" name="gender" value="male" <?= (($gender ?? '') == 'male') ? 'checked' : '' ?>> Мужской</label>
                    <label><input type="radio" name="gender" value="female" <?= (($gender ?? '') == 'female') ? 'checked' : '' ?>> Женский</label>
                </div>
            </div>
            <div class="form-group">
                <label>Любимые языки *</label>
                <select name="languages[]" multiple size="5">
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
                <small>Удерживайте Ctrl (Cmd) для выбора нескольких</small>
            </div>
            <div class="form-group">
                <label>Биография</label>
                <textarea name="bio" rows="4"><?= htmlspecialchars($bio ?? '') ?></textarea>
            </div>
            <div class="form-group checkbox-group">
                <input type="checkbox" name="contract_accepted" value="1" <?= ($contract_accepted ?? 0) ? 'checked' : '' ?>>
                <label>Я ознакомлен(а) с контрактом *</label>
            </div>
            <button type="submit">Сохранить</button>
        </form>
    </div>
</body>
</html>