<?php
include 'db.php';

echo "<h1>Проверка обработки ошибок MySQLi на сайте студии звукозаписи</h1>";
echo "<hr>";

echo "<h2>1. SELECT-запрос: вывод услуг студии</h2>";

$min_price = 5000;

$sql = "
    SELECT id_service, service_name, price, duration_minutes
    FROM services
    WHERE price > ?
    ORDER BY price
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "Ошибка подготовки запроса: " . mysqli_error($conn);
    exit();
}

echo "Запрос подготовлен<br>";

if (!mysqli_stmt_bind_param($stmt, 'i', $min_price)) {
    echo "Ошибка связывания параметров: " . mysqli_error($conn);
    exit();
}

echo "Параметры связаны: минимальная цена = $min_price руб.<br>";

if (!mysqli_stmt_execute($stmt)) {
    echo "Ошибка выполнения запроса: " . mysqli_error($conn);
    exit();
}

echo "Запрос выполнен<br>";

$result = mysqli_stmt_get_result($stmt);

echo "Найдено услуг: " . mysqli_num_rows($result) . "<br><br>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "Услуга: " . htmlspecialchars($row['service_name']) . "<br>";
    echo "Цена: " . htmlspecialchars($row['price']) . " руб.<br>";
    echo "Длительность: " . htmlspecialchars($row['duration_minutes']) . " мин.<br><br>";
}

mysqli_stmt_close($stmt);

echo "<hr>";
echo "<h2>2. ОШИБКА: обращение к несуществующей таблице</h2>";

$sql = "SELECT * FROM missing_studio_table WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "Ошибка подготовки запроса<br>";
    echo "Код ошибки: " . mysqli_errno($conn) . "<br>";
    echo "Текст ошибки: " . mysqli_error($conn);
    exit();
}

echo "Запрос подготовлен";
mysqli_stmt_close($stmt);
?>