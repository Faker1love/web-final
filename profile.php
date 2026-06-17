<?php
require_once 'db.php';
include 'header.php';

if (!isset($_SESSION['user_id'])) {
    echo "<p>Пожалуйста, авторизуйтесь.</p>";
    include 'footer.php';
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');

        if ($name === '' || $contact === '') {
            $error_message = 'Имя и контакт не могут быть пустыми.';
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE clients SET name = ?, contact = ? WHERE id_client = ?");
            mysqli_stmt_bind_param($stmt, 'ssi', $name, $contact, $user_id);

            if (mysqli_stmt_execute($stmt)) {
                $success_message = 'Данные личного кабинета обновлены.';
            } else {
                $error_message = 'Ошибка при обновлении данных.';
            }

            mysqli_stmt_close($stmt);
        }
    }

    if (isset($_POST['delete_booking'])) {
        $booking_id = (int)($_POST['booking_id'] ?? 0);

        if ($booking_id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE sessions FROM sessions INNER JOIN bookings ON sessions.id_booking = bookings.id_booking WHERE sessions.id_booking = ? AND bookings.id_client = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, "DELETE FROM bookings WHERE id_booking = ? AND id_client = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $user_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $success_message = 'Бронирование удалено.';
            } else {
                $error_message = 'Бронирование не найдено или у вас нет прав на его удаление.';
            }

            mysqli_stmt_close($stmt);
        }
    }
}

$stmt = mysqli_prepare($conn, "SELECT * FROM clients WHERE id_client = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$user_result = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($user_result);
mysqli_stmt_close($stmt);

if (!$user_data) {
    echo "<p>Пользователь не найден.</p>";
    include 'footer.php';
    exit();
}
?>
<h2>Личный кабинет: <?php echo htmlspecialchars($user_data['name']); ?></h2>

<?php if ($success_message !== ''): ?>
    <p style="color: green;"><?php echo htmlspecialchars($success_message); ?></p>
<?php endif; ?>

<?php if ($error_message !== ''): ?>
    <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
<?php endif; ?>

<p>Контакт: <?php echo htmlspecialchars($user_data['contact']); ?></p>
<p><strong>Дата регистрации:</strong> <?php echo htmlspecialchars($user_data['date_of_register']); ?></p>

<h3>Редактировать личный кабинет</h3>
<form method="POST">
    <p>
        <label>Имя:<br>
            <input type="text" name="name" value="<?php echo htmlspecialchars($user_data['name']); ?>" required>
        </label>
    </p>
    <p>
        <label>Контакт:<br>
            <input type="text" name="contact" value="<?php echo htmlspecialchars($user_data['contact']); ?>" required>
        </label>
    </p>
    <button type="submit" name="update_profile">Сохранить изменения</button>
</form>

<h3>Ваши бронирования</h3>
<table>
    <tr>
        <th>ID Брони</th>
        <th>Инженер</th>
        <th>Услуга</th>
        <th>Дата брони</th>
        <th>Дата сессии</th>
        <th>Комната</th>
        <th>Действие</th>
    </tr>
    <?php
    $stmt = mysqli_prepare($conn, "SELECT bookings.id_booking, bookings.booking_date, engineers.name AS engineer_name, services.service_name, sessions.session_date, sessions.room FROM bookings LEFT JOIN engineers ON bookings.id_engineer = engineers.id_engineer LEFT JOIN sessions ON bookings.id_booking = sessions.id_booking LEFT JOIN services ON sessions.id_service = services.id_service WHERE bookings.id_client = ? ORDER BY bookings.booking_date DESC");
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $book_result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($book_result) === 0) {
        echo "<tr><td colspan='7'>У вас пока нет бронирований.</td></tr>";
    }

    while($row = mysqli_fetch_assoc($book_result)) {
        $booking_id = (int)$row['id_booking'];
        $engineer_name = htmlspecialchars($row['engineer_name'] ?? '-');
        $service_name = htmlspecialchars($row['service_name'] ?? '-');
        $booking_date = htmlspecialchars($row['booking_date'] ?? '-');
        $session_date = htmlspecialchars($row['session_date'] ?? '-');
        $room = htmlspecialchars($row['room'] ?? '-');

        echo "<tr>
                <td>{$booking_id}</td>
                <td>{$engineer_name}</td>
                <td>{$service_name}</td>
                <td>{$booking_date}</td>
                <td>{$session_date}</td>
                <td>{$room}</td>
                <td>
                    <form method='POST' onsubmit=\"return confirm('Удалить это бронирование?');\">
                        <input type='hidden' name='booking_id' value='{$booking_id}'>
                        <button type='submit' name='delete_booking'>Удалить</button>
                    </form>
                </td>
              </tr>";
    }

    mysqli_stmt_close($stmt);
    ?>
</table>
<?php include 'footer.php'; ?>
