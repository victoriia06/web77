<?php
// HTTP-аутентификация
if (empty($_SERVER['PHP_AUTH_USER']) ||
    empty($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != 'admin' ||
    md5($_SERVER['PHP_AUTH_PW']) != md5('123')) {
  header('HTTP/1.1 401 Unauthorized');
  header('WWW-Authenticate: Basic realm="Admin Area"');
  echo '<h1>401 Требуется авторизация</h1>';
  exit();
}

// Подключение к БД
$user = 'u70422';
$pass = '4545635';
$dbname = 'u70422';

try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}

// Получаем ID пользователя из URL
$userId = $_GET['id'] ?? null;
if (!$userId) {
    die('ID пользователя не указан');
}

// Получаем данные пользователя
try {
    $stmt = $db->prepare("
        SELECT a.*, u.login 
        FROM applications a 
        JOIN users u ON a.id = u.application_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    
    if (!$userData) {
        die('Пользователь не найден');
    }
    
    // Получаем языки программирования
    $stmt = $db->prepare("
        SELECT pl.name 
        FROM application_languages al 
        JOIN programming_languages pl ON al.language_id = pl.id 
        WHERE al.application_id = ?
    ");
    $stmt->execute([$userData['id']]);
    $languages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    die("Ошибка при получении данных: " . $e->getMessage());
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();
        
        // Обновляем данные заявки
        $stmt = $db->prepare("
            UPDATE applications SET 
                fio = ?, 
                tel = ?, 
                email = ?, 
                birth_date = ?, 
                gender = ?, 
                bio = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['fio'],
            $_POST['tel'],
            $_POST['email'],
            $_POST['date'],
            $_POST['gender'],
            $_POST['bio'],
            $userData['id']
        ]);
        
        // Удаляем старые языки
        $stmt = $db->prepare("DELETE FROM application_languages WHERE application_id = ?");
        $stmt->execute([$userData['id']]);
        
        // Добавляем новые языки
        if (!empty($_POST['plang'])) {
            $stmt = $db->prepare("INSERT INTO application_languages (application_id, language_id) 
                                SELECT ?, id FROM programming_languages WHERE name = ?");
            foreach ($_POST['plang'] as $lang) {
                $stmt->execute([$userData['id'], $lang]);
            }
        }
        
        $db->commit();
        
        header("Location: admin.php");
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        die("Ошибка при обновлении данных: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование пользователя</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #2c3e50;
            margin-top: 0;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input[type="text"],
        .form-group input[type="tel"],
        .form-group input[type="email"],
        .form-group input[type="date"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-group textarea {
            min-height: 100px;
        }
        
        .form-group select[multiple] {
            height: 120px;
        }
        
        .gender-options {
            display: flex;
            gap: 15px;
        }
        
        .gender-options label {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-cancel {
            background-color: #95a5a6;
            margin-left: 10px;
        }
        
        .btn-cancel:hover {
            background-color: #7f8c8d;
        }
        
        .actions {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Редактирование пользователя: <?php echo htmlspecialchars($userData['fio']); ?></h1>
        
        <form method="POST">
            <div class="form-group">
                <label for="fio">ФИО:</label>
                <input type="text" id="fio" name="fio" value="<?php echo htmlspecialchars($userData['fio']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="tel">Телефон:</label>
                <input type="tel" id="tel" name="tel" value="<?php echo htmlspecialchars($userData['tel']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="date">Дата рождения:</label>
                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($userData['birth_date']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Пол:</label>
                <div class="gender-options">
                    <label>
                        <input type="radio" name="gender" value="male" <?php echo $userData['gender'] == 'male' ? 'checked' : ''; ?> required>
                        Мужской
                    </label>
                    <label>
                        <input type="radio" name="gender" value="female" <?php echo $userData['gender'] == 'female' ? 'checked' : ''; ?> required>
                        Женский
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label for="plang">Языки программирования:</label>
                <select id="plang" name="plang[]" multiple required>
                    <?php
                    $allLangs = $db->query("SELECT name FROM programming_languages ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($allLangs as $lang) {
                        $selected = in_array($lang, $languages) ? 'selected' : '';
                        echo "<option value=\"$lang\" $selected>$lang</option>";
                    }
                    ?>
                </select>
                <small>Удерживайте Ctrl для выбора нескольких языков</small>
            </div>
            
            <div class="form-group">
                <label for="bio">Биография:</label>
                <textarea id="bio" name="bio" required><?php echo htmlspecialchars($userData['bio']); ?></textarea>
            </div>
            
            <div class="actions">
                <button type="submit" class="btn">Сохранить изменения</button>
                <a href="admin.php" class="btn btn-cancel">Отмена</a>
            </div>
        </form>
    </div>
</body>
</html>
