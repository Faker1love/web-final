<?php
require_once 'db.php';
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    echo "<p>Пожалуйста, авторизуйтесь.</p>";
    include 'footer.php';
    exit();
}

$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM clients WHERE id_client='$user_id'";
$user_data = mysqli_fetch_assoc(mysqli_query($conn, $query));
?>
<h2>Личный кабинет: <?php echo htmlspecialchars($user_data['name']); ?></h2>
<p>Контакт: <?php echo htmlspecialchars($user_data['contact']); ?></p>
<p><strong>Дата регистрации:</strong> <?php echo htmlspecialchars($user_data['date_of_register']); ?></p>


<h3>Ваши бронирования</h3>
<table>
    <tr><th>ID Брони</th><th>Инженер</th><th>Услуга</th><th>Дата брони</th><th>Дата сессии</th><th>Комната</th></tr>
    <?php
    $book_query = "SELECT bookings.id_booking, bookings.booking_date, engineers.name AS engineer_name, services.service_name, sessions.session_date, sessions.room FROM bookings LEFT JOIN engineers ON bookings.id_engineer=engineers.id_engineer LEFT JOIN sessions ON bookings.id_booking=sessions.id_booking LEFT JOIN services ON sessions.id_service=services.id_service WHERE bookings.id_client='$user_id'";
    $book_result = mysqli_query($conn, $book_query);
    while($row = mysqli_fetch_assoc($book_result)) {
        echo "<tr><td>{$row['id_booking']}</td><td>{$row['engineer_name']}</td><td>{$row['service_name']}</td><td>{$row['booking_date']}</td><td>{$row['session_date']}</td><td>{$row['room']}</td></tr>";
    }
    ?>
</table>
<?php include 'footer.php'; ?>
