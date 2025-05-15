<?php
/**
 * Задача 6. Реализовать вход администратора с использованием
 * HTTP-авторизации для просмотра и удаления результатов.
 **/

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

// Подключение к базе данных
$user = 'u70422';
$pass = '4545635';
$dbname = 'u70422';

try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Обработка действий администратора
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!empty($_POST['delete_id'])) {
            // Полное удаление пользователя и всех связанных данных
            try {
                $db->beginTransaction();
                
                // 1. Получаем ID заявки
                $stmt = $db->prepare("SELECT application_id FROM users WHERE id = ?");
                $stmt->execute([$_POST['delete_id']]);
                $appId = $stmt->fetchColumn();
                
                if ($appId) {
                    // 2. Удаляем языки программирования
                    $stmt = $db->prepare("DELETE FROM application_languages WHERE application_id = ?");
                    $stmt->execute([$appId]);
                    
                    // 3. Удаляем пользователя
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$_POST['delete_id']]);
                    
                    // 4. Удаляем заявку
                    $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
                    $stmt->execute([$appId]);
                }
                
                $db->commit();
                
                header("Location: admin.php");
                exit();
                
            } catch (PDOException $e) {
                $db->rollBack();
                die('Ошибка при удалении пользователя: ' . $e->getMessage());
            }
        }
    }
    
    // Получение статистики по языкам (только для активных пользователей)
    $stats = $db->query("
        SELECT 
            pl.id,
            pl.name, 
            COUNT(al.application_id) as user_count 
        FROM programming_languages pl 
        LEFT JOIN application_languages al ON pl.id = al.language_id 
        LEFT JOIN users u ON al.application_id = u.application_id
        GROUP BY pl.id, pl.name
        ORDER BY user_count DESC, pl.name
    ")->fetchAll();
    
    // Получение всех пользователей с их данными
    $users = $db->query("
        SELECT 
            u.id as user_id,
            u.login,
            a.id as app_id,
            a.fio,
            a.tel,
            a.email,
            a.birth_date,
            a.gender,
            a.bio,
            (
                SELECT GROUP_CONCAT(pl.name SEPARATOR ', ')
                FROM application_languages al
                JOIN programming_languages pl ON al.language_id = pl.id
                WHERE al.application_id = a.id
                GROUP BY al.application_id
            ) as languages
        FROM users u
        JOIN applications a ON u.application_id = a.id
        ORDER BY a.id DESC
    ")->fetchAll();
    
} catch (PDOException $e) {
    die('Ошибка базы данных: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #e9e9e9;
        }
        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        .btn-edit {
            background-color: #2196F3;
        }
        .btn-delete {
            background-color: #f44336;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .stats {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Панель администратора</h1>
            <p>Вы вошли как: <strong><?= htmlspecialchars($_SERVER['PHP_AUTH_USER']) ?></strong></p>
        </div>
        
        <h2>Все пользователи</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Логин</th>
                    <th>ФИО</th>
                    <th>Телефон</th>
                    <th>Email</th>
                    <th>Дата рождения</th>
                    <th>Пол</th>
                    <th>Языки программирования</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['user_id']) ?></td>
                    <td><?= htmlspecialchars($user['login']) ?></td>
                    <td><?= htmlspecialchars($user['fio']) ?></td>
                    <td><?= htmlspecialchars($user['tel']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['birth_date']) ?></td>
                    <td><?= $user['gender'] == 'male' ? 'Мужской' : 'Женский' ?></td>
                    <td><?= htmlspecialchars($user['languages'] ?? 'Нет данных') ?></td>
                    <td>
                        <a href="edit.php?id=<?= $user['user_id'] ?>" class="btn btn-edit">Редактировать</a>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="delete_id" value="<?= $user['user_id'] ?>">
                            <button type="submit" class="btn btn-delete" 
                                    onclick="return confirm('Вы уверены, что хотите удалить этого пользователя?')">
                                Удалить
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="stats">
            <h2>Статистика по языкам программирования</h2>
            <table>
                <thead>
                    <tr>
                        <th>Язык программирования</th>
                        <th>Количество пользователей</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $stat): ?>
                    <tr>
                        <td><?= htmlspecialchars($stat['name']) ?></td>
                        <td><?= htmlspecialchars($stat['user_count']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
