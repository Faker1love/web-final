<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = mysqli_prepare($conn, "SELECT id_client, name, password, role FROM clients WHERE name = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $login);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    // В текущей БД пароли сохранены обычным текстом. password_verify оставлен на случай перехода на хеши.
    $passwordOk = $user && ($password === $user['password'] || password_verify($password, $user['password']));

    if ($passwordOk) {
        $_SESSION['user_id'] = (int)$user['id_client'];
        $_SESSION['login'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header('Location: admin.php');
        } else {
            header('Location: profile.php');
        }
        exit();
    } else {
        $error = 'Неверное имя или пароль!';
    }
}

include 'header.php';
?>

<h2>Авторизация</h2>

<?php if (isset($error)): ?>
    <p style="color:red;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<form method="POST">
    <label>Имя:</label>
    <input type="text" name="login" required>

    <label>Пароль:</label>
    <input type="password" name="password" required>

    <input type="submit" value="Войти">
</form>

<?php include 'footer.php'; ?>
