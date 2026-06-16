<?php session_start(); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Студия Звукозаписи</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #121212; color: #f0f0f0; margin: 0; padding: 0; }
        header { background-color: #1f1f1f; padding: 20px; text-align: center; border-bottom: 2px solid #e74c3c; }
        nav a { color: #e74c3c; margin: 0 15px; text-decoration: none; font-weight: bold; }
        nav a:hover { color: #ff7968; }
        .container { width: 80%; margin: 20px auto; padding: 20px; background: #1e1e1e; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        input[type="text"], input[type="password"], input[type="email"] { padding: 10px; margin: 5px 0; width: 100%; box-sizing: border-box; }
        input[type="submit"], button { padding: 10px 20px; background-color: #e74c3c; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
<header>
    <h1>🎵 Студия Звукозаписи</h1>
    <nav>
        <a href="index.php">Главная</a>
        <a href="gallery.php">Галерея</a>
        <a href="services.php">Услуги</a>
        <a href="cart.php">Корзина</a>
        <a href="contact.php">Контакты</a>
        <?php if(isset($_SESSION['user_id'])): ?>
            <a href="profile.php">Личный кабинет</a>
            <a href="logout.php">Выход</a>
        <?php else: ?>
            <a href="login.php">Вход</a>
            <a href="register.php">Регистрация</a>
        <?php endif; ?>
    </nav>
</header>
<div class="container">