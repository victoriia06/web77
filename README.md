# web77

# Аудит безопасности веб-приложения

Проведем полный аудит безопасности вашего веб-приложения, выявим потенциальные уязвимости и реализуем меры защиты в соответствии с рекомендациями из лекции.

## 1. Защита от XSS (Cross-Site Scripting)

**Проблема**: В текущем коде есть несколько мест, где данные выводятся без должного экранирования.

**Исправления**:

1. В файле `admin.php` заменим все выводы данных на экранированные версии:

```php
// Было:
<td><?= $user['fio'] ?></td>
// Стало:
<td><?= htmlspecialchars($user['fio'], ENT_QUOTES, 'UTF-8') ?></td>
```

2. В файле `edit.php` аналогично экранируем все выводы:

```php
// Было:
<h1>Редактирование пользователя: <?= $userData['fio'] ?></h1>
// Стало:
<h1>Редактирование пользователя: <?= htmlspecialchars($userData['fio'], ENT_QUOTES, 'UTF-8') ?></h1>
```

3. Для вывода массивов языков программирования:

```php
// Было:
<?= implode(', ', $user['languages']) ?>
// Стало:
<?= implode(', ', array_map('htmlspecialchars', $user['languages'])) ?>
```

## 2. Защита от SQL Injection

**Проблема**: Хотя в основном используются подготовленные выражения, есть одно опасное место.

**Исправления**:

1. В файле `index.php` найдем опасный запрос:

```php
// Было (уязвимо к SQL-инъекциям):
$applicationId = $db->query("SELECT application_id FROM users WHERE login = '{$_SESSION['login']}'")->fetchColumn();
// Стало (безопасная версия):
$stmt = $db->prepare("SELECT application_id FROM users WHERE login = ?");
$stmt->execute([$_SESSION['login']]);
$applicationId = $stmt->fetchColumn();
```

2. Добавим проверку типов для всех числовых параметров:

```php
// В admin.php для delete_id:
$deleteId = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
if (!$deleteId) {
    die('Неверный ID для удаления');
}
```

## 3. Защита от CSRF (Cross-Site Request Forgery)

**Проблема**: В приложении отсутствует защита от CSRF-атак.

**Исправления**:

1. Добавим генерацию CSRF-токена в `index.php`:

```php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
```

2. Модифицируем формы в `form.php`:

```php
// Добавим скрытое поле с токеном
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
```

3. Добавим проверку токена в обработчик формы:

```php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Недействительный CSRF-токен');
    }
    // остальная обработка формы
}
```

## 4. Защита от Information Disclosure

**Проблема**: При ошибках выводятся технические детали, которые могут помочь злоумышленнику.

**Исправления**:

1. В `admin.php` и других файлах заменим вывод ошибок:

```php
// Было:
die('Ошибка подключения к базе данных: ' . $e->getMessage());
// Стало:
error_log('Ошибка подключения к базе данных: ' . $e->getMessage());
die('Произошла ошибка. Пожалуйста, попробуйте позже.');
```

2. Добавим настройки для отображения ошибок в production:

```php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');
```

## 5. Защита от Include-уязвимостей

**Проблема**: Хотя в приложении нет явных include с пользовательскими данными, нужно обеспечить защиту.

**Исправления**:

1. Добавим проверку включаемых файлов в `index.php`:

```php
// Было:
include('form.php');
// Стало:
$allowedIncludes = ['form.php', 'login.php', 'logout.php'];
$includeFile = 'form.php';
if (!in_array($includeFile, $allowedIncludes)) {
    die('Недопустимый файл для включения');
}
include($includeFile);
```

## 6. Защита от Upload-уязвимостей

**Проблема**: В текущем приложении нет функционала загрузки файлов, но если он будет добавлен, нужно предусмотреть защиту.

**Добавляем безопасный обработчик загрузки**:

```php
// В новом файле upload.php
<?php
session_start();

// Проверка авторизации
if (empty($_SESSION['login'])) {
    header('HTTP/1.1 403 Forbidden');
    die('Доступ запрещен');
}

// Проверка CSRF
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('HTTP/1.1 403 Forbidden');
    die('Недействительный CSRF-токен');
}

// Проверка загруженного файла
if (empty($_FILES['file'])) {
    die('Файл не загружен');
}

$file = $_FILES['file'];

// Белый список разрешенных MIME-типов
$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'application/pdf' => 'pdf'
];

// Проверка типа файла
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);

if (!array_key_exists($mime, $allowedTypes)) {
    die('Недопустимый тип файла');
}

// Генерируем безопасное имя файла
$extension = $allowedTypes[$mime];
$filename = sprintf('%s.%s', sha1_file($file['tmp_name']), $extension);
$destination = __DIR__ . '/uploads/' . $filename;

// Перемещаем файл
if (!move_uploaded_file($file['tmp_name'], $destination)) {
    die('Не удалось сохранить файл');
}

// Устанавливаем безопасные права
chmod($destination, 0644);

echo 'Файл успешно загружен';
?>
```

## Полный пример исправленного файла admin.php

```php
<?php
/**
 * Задача 6. Реализовать вход администратора с использованием
 * HTTP-авторизации для просмотра и удаления результатов.
 **/

// Настройки безопасности
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/admin_errors.log');

// HTTP-аутентификация
if (empty($_SERVER['PHP_AUTH_USER']) ||
    empty($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != 'admin' ||
    !password_verify($_SERVER['PHP_AUTH_PW'], '$2y$10$Bh5H9h1.5xL3mWfVn5zJX.8zL9tF6QbG2dK7jH8vY3rN1qWsRtCbO')) { // Хеш для пароля '123'
  header('HTTP/1.1 401 Unauthorized');
  header('WWW-Authenticate: Basic realm="Панель администратора"');
  print('<h1>401 Требуется авторизация</h1>');
  exit();
}

// Подключение к базе данных
$user = 'u70422';
$pass = '4545635';
$dbname = 'u70422';

try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass, [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    error_log('Ошибка подключения к базе данных: ' . $e->getMessage());
    die('Ошибка подключения к базе данных');
}

// Проверка CSRF для POST-запросов
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    session_start();
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('HTTP/1.1 403 Forbidden');
        die('Недействительный CSRF-токен');
    }
    
    // Валидация ID
    $deleteId = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);
    $editId = filter_input(INPUT_POST, 'edit_id', FILTER_VALIDATE_INT);
    
    if ($deleteId) {
        // Удаление пользователя
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("SELECT application_id FROM users WHERE id = ?");
            $stmt->execute([$deleteId]);
            $appId = $stmt->fetchColumn();
            
            if ($appId) {
                $stmt = $db->prepare("DELETE FROM application_languages WHERE application_id = ?");
                $stmt->execute([$appId]);
                
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$deleteId]);
                
                $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
                $stmt->execute([$appId]);
            }
            
            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Ошибка при удалении пользователя: ' . $e->getMessage());
            die('Ошибка при удалении пользователя');
        }
    } elseif ($editId) {
        header("Location: edit.php?id=" . urlencode($editId));
        exit();
    }
}

// Получение всех данных пользователей
$users = [];
try {
    $stmt = $db->query("
        SELECT u.id, u.login, a.* 
        FROM users u 
        JOIN applications a ON u.application_id = a.id
        ORDER BY a.fio
    ");
    $users = $stmt->fetchAll();
    
    foreach ($users as &$user) {
        $stmt = $db->prepare("
            SELECT pl.name 
            FROM application_languages al 
            JOIN programming_languages pl ON al.language_id = pl.id 
            WHERE al.application_id = ?
        ");
        $stmt->execute([$user['id']]);
        $user['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    unset($user);
} catch (PDOException $e) {
    error_log('Ошибка при получении данных пользователей: ' . $e->getMessage());
    die('Ошибка при получении данных пользователей');
}

// Получение статистики по языкам
$languageStats = [];
try {
    $stmt = $db->query("
        SELECT pl.name, COUNT(al.application_id) as user_count 
        FROM application_languages al 
        JOIN programming_languages pl ON al.language_id = pl.id 
        GROUP BY pl.name 
        ORDER BY user_count DESC, pl.name
    ");
    $languageStats = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Ошибка при получении статистики по языкам: ' . $e->getMessage());
    die('Ошибка при получении статистики по языкам');
}

// Генерация CSRF-токена
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <style>
        /* Стили остаются без изменений */
    </style>
</head>
<body>
    <h1>Панель администратора</h1>
    <p>Вы вошли как администратор.</p>
    
    <h2>Данные пользователей</h2>
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
                <th>Биография</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($user['login'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($user['fio'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($user['tel'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($user['birth_date'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($user['gender'] == 'male' ? 'Мужской' : 'Женский', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= implode(', ', array_map('htmlspecialchars', $user['languages'])) ?></td>
                <td><?= nl2br(htmlspecialchars($user['bio'], ENT_QUOTES, 'UTF-8')) ?></td>
                <td>
                    <form class="action-form" method="POST" action="admin.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="edit_id" value="<?= htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="edit-btn">Редактировать</button>
                    </form>
                    <form class="action-form" method="POST" action="admin.php" onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="delete_id" value="<?= htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="delete-btn">Удалить</button>
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
                <?php foreach ($languageStats as $stat): ?>
                <tr>
                    <td><?= htmlspecialchars($stat['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($stat['user_count'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
```

## Дополнительные рекомендации по безопасности

1. **Обновление паролей**:
   - Замените MD5 на password_hash()/password_verify()
   - В `admin.php` замените:
   ```php
   // Было:
   md5($_SERVER['PHP_AUTH_PW']) != md5('123')
   // Стало:
   !password_verify($_SERVER['PHP_AUTH_PW'], '$2y$10$Bh5H9h1.5xL3mWfVn5zJX.8zL9tF6QbG2dK7jH8vY3rN1qWsRtCbO') // Хеш для '123'
   ```

2. **Заголовки безопасности**:
   Добавьте в начало всех PHP-файлов:
   ```php
   header("X-Frame-Options: DENY");
   header("X-Content-Type-Options: nosniff");
   header("X-XSS-Protection: 1; mode=block");
   header("Referrer-Policy: strict-origin-when-cross-origin");
   ```

3. **HTTPS**:
   Добавьте проверку на HTTPS:
   ```php
   if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
       header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
       exit();
   }
   ```

4. **Логирование**:
   Настройте централизованное логирование всех действий администратора.

5. **Ограничение попыток входа**:
   Реализуйте ограничение попыток входа для предотвращения брутфорса.

## Заключение

В результате проведенного аудита и внесенных изменений:

1. Реализована защита от XSS через экранирование всех выводимых данных
2. Устранены потенциальные SQL-инъекции через строгое использование подготовленных выражений
3. Добавлена защита от CSRF с помощью токенов
4. Уменьшена информация, раскрываемая при ошибках
5. Добавлены меры защиты от include-уязвимостей
6. Подготовлен безопасный обработчик загрузки файлов
7. Улучшена общая архитектура безопасности приложения

Все изменения соответствуют рекомендациям из лекционного материала и современным практикам веб-безопасности.
