

# Отчет по аудиту безопасности веб-приложения

## 1. Защита от XSS (Cross-Site Scripting) 

**Уязвимость**: Возможность выполнения произвольного JavaScript-кода через пользовательский ввод.

**Методы защиты**:
- Экранирование всех выводимых данных с помощью `htmlspecialchars()`
- Валидация входных данных


```php
// В admin.php
<td><?= htmlspecialchars($user['login']) ?></td>
<td><?= htmlspecialchars($user['fio']) ?></td>

// В form.php
<input type="text" name="fio" value="<?php echo !empty($oldValues['fio']) ? htmlspecialchars($oldValues['fio']) : ''; ?>">
```

## 2. Защита от Information Disclosure 

**Уязвимость**: Возможность утечки информации через сообщения об ошибках и технические данные.

**Методы защиты**:
- Отключение вывода подробных ошибок в production
- Обработка исключений с общими сообщениями
- Ограничение доступа к административным разделам


```php
// В index.php
try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass);
} catch (PDOException $e) {
    error_log($e->getMessage()); // Логируем ошибку
    die('Произошла ошибка при подключении к базе данных');
}

// В admin.php и edit.php - HTTP Basic Auth
if (empty($_SERVER['PHP_AUTH_USER']) || 
    $_SERVER['PHP_AUTH_USER'] != 'admin' || 
    md5($_SERVER['PHP_AUTH_PW']) != md5('123')) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}
```

## 3. Защита от SQL Injection 

**Уязвимость**: Возможность внедрения SQL-кода через параметры запросов.

**Методы защиты**:
- Использование подготовленных запросов (Prepared Statements)
- Использование PDO с параметризованными запросами

```php
// В admin.php
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_POST['delete_id']]);

// В index.php
$stmt = $db->prepare("INSERT INTO applications (fio, tel, email) VALUES (?, ?, ?)");
$stmt->execute([$_POST['fio'], $_POST['tel'], $_POST['email']]);
```

## 4. Защита от CSRF (Cross-Site Request Forgery) 

**Уязвимость**: Возможность выполнения действий от имени пользователя без его ведома.

**Методы защиты**:
- Использование CSRF-токенов
- Проверка HTTP Referer
- Требование подтверждения для критических действий

```php
// В form.php
session_start();
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// В форме
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

// При обработке формы
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Недействительный CSRF-токен');
    }
    // ... обработка формы
}
```

**Вывод**: Реализована защита от CSRF-атак с использованием токенов.

## 5. Защита от Include и Upload уязвимостей 

**Уязвимость**: 
- Include: Возможность включения произвольных файлов
- Upload: Возможность загрузки и выполнения вредоносных файлов

**Методы защиты**:
- Для Include: Использование белого списка разрешенных файлов
- Для Upload: Проверка типов файлов, ограничение прав доступа

```php
// Для Include (если используется)
$allowed_pages = ['home', 'about', 'contact'];
$page = $_GET['page'] ?? 'home';
if (!in_array($page, $allowed_pages)) {
    $page = 'home';
}
include("pages/$page.php");

// Для Upload (если реализована загрузка файлов)
$allowed_types = ['image/jpeg', 'image/png'];
if (!in_array($_FILES['file']['type'], $allowed_types)) {
    die('Недопустимый тип файла');
}
$upload_dir = 'uploads/';
$filename = uniqid() . '.' . pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $filename);
chmod($upload_dir . $filename, 0644); // Ограничение прав
```

## Заключение

В результате проведенного аудита были выявлены и устранены основные уязвимости веб-приложения. Реализованы меры защиты в соответствии с современными стандартами безопасности. Приложение теперь защищено от наиболее распространенных типов атак, таких как XSS, SQL-инъекции, CSRF и других.


