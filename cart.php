<?php
require_once 'db.php';
include 'header.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_POST['add_to_cart'])) {
    $item = [
        'id' => $_POST['engineer_id'],
        'name' => $_POST['engineer_name'],
        'service_id' => $_POST['service_id'],
        'service_name' => $_POST['service_name']
    ];
    $_SESSION['cart'][] = $item;
    echo "<p style='color:green;'>Услуга добавлена в корзину!</p>";
}

if (isset($_POST['clear_cart'])) {
    $_SESSION['cart'] = [];
}

// Оформление заказа в БД
if (isset($_POST['checkout']) && isset($_SESSION['user_id'])) {
    $client_id = $_SESSION['user_id'];
    $date = date('Y-m-d');
    
    foreach ($_SESSION['cart'] as $cart_item) {
        $eng_id = $cart_item['id'];
        $service_id = $cart_item['service_id'];
        if ($service_id == '') {
            $service_result = mysqli_query($conn, "SELECT id_service FROM services ORDER BY id_service LIMIT 1");
            $service_row = mysqli_fetch_assoc($service_result);
            $service_id = $service_row['id_service'];
        }

        mysqli_query($conn, "INSERT INTO bookings (id_client, id_engineer, booking_date) VALUES ('$client_id', '$eng_id', '$date')");
        $booking_id = mysqli_insert_id($conn);

        mysqli_query($conn, "INSERT INTO sessions (id_booking, id_service, session_date, room) VALUES ('$booking_id', '$service_id', '$date', 'Студия A')");
    }
    $_SESSION['cart'] = [];
    echo "<p style='color:green;'>Заказ успешно оформлен!</p>";
} elseif (isset($_POST['checkout'])) {
    echo "<p style='color:red;'>Для оформления заказа необходимо <a href='login.php'>авторизоваться</a>.</p>";
}
?>

<h2>Корзина (Оформление сессии)</h2>
<?php if (count($_SESSION['cart']) > 0): ?>
    <ul>
        <?php foreach ($_SESSION['cart'] as $item): ?>
            <li>Инженер: <?php echo htmlspecialchars($item['name']); ?> | Услуга: <?php echo htmlspecialchars($item['service_name']); ?></li>
        <?php endforeach; ?>
    </ul>
    <form method="POST" style="display:inline;">
        <input type="submit" name="checkout" value="Оформить заказ">
    </form>
    <form method="POST" style="display:inline;">
        <input type="submit" name="clear_cart" value="Очистить корзину" style="background-color: #777;">
    </form>
<?php else: ?>
    <p>Корзина пуста. Перейдите в <a href="services.php">услуги</a>.</p>
<?php endif; ?>

<?php include 'footer.php'; ?>
