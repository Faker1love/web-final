<?php 
require_once 'db.php';
include 'header.php'; 

$search = '';
if (isset($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
}
?>
<h2>Наши звукорежиссеры</h2>
<form method="GET">
    <input type="text" name="search" placeholder="Поиск по специализации (например: Сведение)" value="<?php echo htmlspecialchars($search); ?>">
    <input type="submit" value="Найти">
</form>

<table>
    <tr><th>Имя</th><th>Услуга</th><th>Опыт (лет)</th><th>Цена</th><th>Длительность</th><th>Действие</th></tr>
    <?php
    $query = "SELECT engineers.*, services.id_service, services.service_name, services.price, services.duration_minutes FROM engineers LEFT JOIN services ON engineers.specialization=services.service_name WHERE engineers.specialization LIKE '%$search%' OR engineers.name LIKE '%$search%' OR services.service_name LIKE '%$search%' ORDER BY engineers.name ASC";
    $result = mysqli_query($conn, $query);
    while($row = mysqli_fetch_assoc($result)) {
        $service_id = $row['id_service'];
        $service_name = $row['service_name'];
        $price = $row['price'];
        $duration = $row['duration_minutes'];
        if ($service_id == '') {
            $service_name = $row['specialization'];
            $price = '-';
            $duration = '-';
        }
        echo "<tr>
                <td>{$row['name']}</td>
                <td>{$row['specialization']}</td>
                <td>{$row['experience']}</td>
                <td>{$price} руб.</td>
                <td>{$duration} мин.</td>
                <td>
                    <form method='POST' action='cart.php'>
                        <input type='hidden' name='engineer_id' value='{$row['id_engineer']}'>
                        <input type='hidden' name='engineer_name' value='{$row['name']}'>
                        <input type='hidden' name='service_id' value='{$service_id}'>
                        <input type='hidden' name='service_name' value='{$service_name}'>
                        <button type='submit' name='add_to_cart'>Забронировать</button>
                    </form>
                </td>
              </tr>";
    }
    ?>
</table>
<?php include 'footer.php'; ?>
