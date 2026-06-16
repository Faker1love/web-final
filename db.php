<?php
$host = '127.0.0.1';
$db   = 'studio_db_new';
$user = 'root'; // замени на свой логин от БД
$pass = '';     // замени на свой пароль от БД

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Ошибка подключения: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
?>
