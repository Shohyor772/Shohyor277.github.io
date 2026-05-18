<?php
session_start();

$host = 'localhost';
$dbname = 'u82431';
$username = 'u82431';
$password = '6531457';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// ========= HTTP-авторизация (через PHP) =========
$adminLogin = 'admin';
$adminPassword = 'admin123'; // в реальном проекте — хеш, но для учебного так

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] != $adminLogin || 
    $_SERVER['PHP_AUTH_PW'] != $adminPassword) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Доступ запрещён';
    exit;
}
// ================================================

// ========= Обработка действий админа =========
$message = '';

// Удаление пользователя
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $message = "Пользователь ID $id удалён.";
    header("Location: admin.php?msg=" . urlencode($message));
    exit;
}

// Редактирование пользователя (обновление)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $editId = (int)$_POST['edit_id'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $birth_date = $_POST['birth_date'];
    $gender = $_POST['gender'];
    $bio = trim($_POST['bio']);
    $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;
    $languages = $_POST['languages'] ?? [];

    // Валидация
    $errors = [];
    if (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u', $full_name)) {
        $errors[] = 'ФИО некорректно';
    }
    if (!preg_match('/^[\d\-\+\(\)\s]{10,20}$/', $phone)) {
        $errors[] = 'Телефон некорректен';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email некорректен';
    }
    if (strtotime($birth_date) > time()) {
        $errors[] = 'Дата рождения не может быть в будущем';
    }
    if (!in_array($gender, ['male', 'female'])) {
        $errors[] = 'Пол некорректен';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE users 
            SET full_name = ?, phone = ?, email = ?, birth_date = ?, gender = ?, bio = ?, contract_accepted = ?
            WHERE id = ?
        ");
        $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, $bio, $contract_accepted, $editId]);

        // Обновляем языки
        $stmtDel = $pdo->prepare("DELETE FROM user_languages WHERE user_id = ?");
        $stmtDel->execute([$editId]);
        $stmtLang = $pdo->prepare("INSERT INTO user_languages (user_id, language) VALUES (?, ?)");
        foreach ($languages as $lang) {
            $stmtLang->execute([$editId, $lang]);
        }
        $pdo->commit();
        $message = "Пользователь ID $editId обновлён.";
        header("Location: admin.php?msg=" . urlencode($message));
        exit;
    } else {
        $message = "Ошибка: " . implode(', ', $errors);
    }
}

// Получаем сообщение из URL
if (isset($_GET['msg'])) {
    $message = htmlspecialchars($_GET['msg']);
}

// ========= Получение данных для отображения =========
// Список всех пользователей
$usersStmt = $pdo->query("
    SELECT u.*, 
           GROUP_CONCAT(ul.language SEPARATOR ', ') AS languages
    FROM users u
    LEFT JOIN user_languages ul ON u.id = ul.user_id
    GROUP BY u.id
    ORDER BY u.id DESC
");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Статистика по языкам
$statsStmt = $pdo->query("
    SELECT ul.language, COUNT(*) AS cnt
    FROM user_languages ul
    GROUP BY ul.language
    ORDER BY cnt DESC
");
$stats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

// Если есть редактирование — получаем данные пользователя
$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$editId]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($editUser) {
        $langStmt = $pdo->prepare("SELECT language FROM user_languages WHERE user_id = ?");
        $langStmt->execute([$editId]);
        $editLanguages = $langStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <title>Админ-панель — Управление пользователями</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            padding: 2rem 1rem;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #a5b4fc; margin-bottom: 1rem; }
        h2 { color: #cbd5e1; margin: 1.5rem 0 1rem; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #1e293b;
            border-radius: 16px;
            overflow: hidden;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #334155;
        }
        th { background: #334155; color: #f1f5f9; }
        tr:hover { background: #2d3a4f; }
        a.button, button {
            display: inline-block;
            background: #6366f1;
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            margin: 0.2rem;
        }
        a.button.danger { background: #ef4444; }
        a.button.warning { background: #f59e0b; }
        button:hover, a.button:hover { opacity: 0.9; }
        .message {
            background: #15803d;
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        .stats {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: #1e293b;
            padding: 0.75rem 1.5rem;
            border-radius: 20px;
            border-left: 4px solid #6366f1;
        }
        .stat-card strong {
            font-size: 1.5rem;
            color: #a5b4fc;
        }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.3rem; font-weight: 600; }
        input, select, textarea {
            width: 100%;
            padding: 0.5rem;
            border-radius: 8px;
            border: 1px solid #334155;
            background: #0f172a;
            color: white;
        }
        select[multiple] { height: 100px; }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #818cf8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛡️ Админ-панель</h1>

        <?php if ($message): ?>
            <div class="message">ℹ️ <?= $message ?></div>
        <?php endif; ?>

        <?php if ($editUser): ?>
            <h2>✏️ Редактирование пользователя: <?= htmlspecialchars($editUser['full_name']) ?></h2>
            <form method="POST">
                <input type="hidden" name="edit_id" value="<?= $editUser['id'] ?>">
                <div class="form-group">
                    <label>ФИО</label>
                    <input type="text" name="full_name" value="<?= htmlspecialchars($editUser['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Телефон</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($editUser['phone']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editUser['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Дата рождения</label>
                    <input type="date" name="birth_date" value="<?= $editUser['birth_date'] ?>" required>
                </div>
                <div class="form-group">
                    <label>Пол</label>
                    <select name="gender">
                        <option value="male" <?= $editUser['gender'] == 'male' ? 'selected' : '' ?>>Мужской</option>
                        <option value="female" <?= $editUser['gender'] == 'female' ? 'selected' : '' ?>>Женский</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Любимые языки (Ctrl + клик)</label>
                    <select name="languages[]" multiple>
                        <?php
                        $allLangs = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala', 'Go'];
                        foreach ($allLangs as $lang): ?>
                            <option value="<?= $lang ?>" <?= in_array($lang, $editLanguages ?? []) ? 'selected' : '' ?>><?= $lang ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="bio" rows="3"><?= htmlspecialchars($editUser['bio'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="contract_accepted" value="1" <?= $editUser['contract_accepted'] ? 'checked' : '' ?>> Контракт принят</label>
                </div>
                <button type="submit">💾 Сохранить изменения</button>
                <a href="admin.php" class="button">↩️ Отмена</a>
            </form>
        <?php else: ?>
            <h2>📊 Статистика по языкам программирования</h2>
            <div class="stats">
                <?php foreach ($stats as $stat): ?>
                    <div class="stat-card">
                        <strong><?= htmlspecialchars($stat['language']) ?></strong><br>
                        <?= $stat['cnt'] ?> пользователь(ей)
                    </div>
                <?php endforeach; ?>
            </div>

            <h2>📋 Все пользователи</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Дата рожд.</th>
                        <th>Пол</th>
                        <th>Языки</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td><?= htmlspecialchars($user['phone']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= $user['birth_date'] ?></td>
                            <td><?= $user['gender'] == 'male' ? 'М' : 'Ж' ?></td>
                            <td><?= htmlspecialchars($user['languages'] ?? '—') ?></td>
                            <td>
                                <a href="admin.php?edit=<?= $user['id'] ?>" class="button warning">✏️</a>
                                <a href="admin.php?delete=<?= $user['id'] ?>" class="button danger" onclick="return confirm('Удалить пользователя?')">🗑️</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <br>
        <a href="index.php" class="back-link">← Вернуться к форме</a>
    </div>
</body>
</html>