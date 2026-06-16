<?php
session_start();

include 'db.php';
include 'header.php';

$errors = [];
$successMessages = [];

if (!isset($_SESSION['captcha_result'])) {
    $a = rand(1, 9);
    $b = rand(1, 9);
    $_SESSION['captcha_text'] = "$a + $b";
    $_SESSION['captcha_result'] = $a + $b;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['login'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';
    $captcha = $_POST['captcha'] ?? '';

    // Проверка имени
    if ($name === '') {
        $errors['name'] = 'Имя не может быть пустым';
    } elseif (mb_strlen($name) < 2) {
        $errors['name'] = 'Имя должно содержать не менее 2 символов';
    } elseif (preg_match('/[\'";\\\\]/', $name)) {
        $errors['name'] = 'Имя содержит подозрительные символы';
    }

    // Проверка почты
    if ($contact === '') {
        $errors['contact'] = 'Почта не может быть пустой';
    } elseif (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        $errors['contact'] = 'Почта имеет неверный формат';
    } elseif (preg_match('/[\'";\\\\]/', $contact)) {
        $errors['contact'] = 'Почта содержит подозрительные символы';
    }

    // Проверка пароля
    if ($password === '') {
        $errors['password'] = 'Пароль не может быть пустым';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Пароль должен быть не менее 6 символов';
    }

    // Проверка капчи
    if (!isset($captcha) || (int)$captcha !== (int)$_SESSION['captcha_result']) {
        $errors['captcha'] = 'Неверно решён пример. Подтвердите, что вы не робот.';
    }

    // Дополнительная простая проверка на подозрительные SQL-слова
    if (preg_match('/(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|--)/i', $name . $contact)) {
        $errors['security'] = 'Обнаружена попытка SQL-инъекции';
    }

    // Если есть ошибки, обновляем пример капчи
    if (!empty($errors)) {
        $a = rand(1, 9);
        $b = rand(1, 9);
        $_SESSION['captcha_text'] = "$a + $b";
        $_SESSION['captcha_result'] = $a + $b;
    }
    else {
    	$login = mysqli_real_escape_string($conn, $name);
    	$contact = mysqli_real_escape_string($conn, $contact);
    	$password = mysqli_real_escape_string($conn, $password);

    	$query = "INSERT INTO clients (name, contact, password) 
              VALUES ('$login', '$contact', '$password')";

    	if (mysqli_query($conn, $query)) {
        	$successMessages[] = 'Успешная регистрация!';
    	}
    }
}
?>

<h2>Регистрация клиента студии звукозаписи</h2>

<?php if (!empty($errors)): ?>
    <div style="background:#f8d7da; color:#842029; padding:15px; border:1px solid #f5c2c7; margin-bottom:15px;">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!empty($successMessages)): ?>
    <div style="background:#d1e7dd; color:#0f5132; padding:15px; border:1px solid #badbcc; margin-bottom:15px;">
        <?php foreach ($successMessages as $message): ?>
            <p><?= $message ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="POST">
    <label>Имя:</label>
    <input type="text" name="login" value="<?= $_POST['login'] ?? '' ?>">
    
    <label>Почта:</label>
    <input type="email" name="contact" value="<?= $_POST['contact'] ?? '' ?>">
    
    <label>Пароль:</label>
    <input type="password" name="password">

    <label>Решите пример: <?= htmlspecialchars($_SESSION['captcha_text']) ?> = </label><br>
    <input type="number" name="captcha">
    <br><br>

    <button type="submit">Зарегистрироваться</button>
</form>

<?php include 'footer.php'; ?>
