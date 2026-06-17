<?php 
require_once 'db.php';
include 'header.php'; 

$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

$selected_engineer = 0;
if (isset($_GET['engineer_id']) && is_numeric($_GET['engineer_id'])) {
    $selected_engineer = (int) $_GET['engineer_id'];
}

$engineers_query = "SELECT id_engineer, name FROM engineers ORDER BY name ASC";
$engineers_result = mysqli_query($conn, $engineers_query);
?>
<h2>Наши звукорежиссеры</h2>
<form method="GET">
    <input type="text" name="search" placeholder="Поиск по специализации (например: Сведение)" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">

    <select name="engineer_id">
        <option value="">Все звукорежиссеры</option>
        <?php
        if ($engineers_result) {
            while ($engineer = mysqli_fetch_assoc($engineers_result)) {
                $engineer_id = (int) $engineer['id_engineer'];
                $engineer_name = htmlspecialchars($engineer['name'], ENT_QUOTES, 'UTF-8');
                $selected = ($selected_engineer === $engineer_id) ? 'selected' : '';

                echo "<option value=\"{$engineer_id}\" {$selected}>{$engineer_name}</option>";
            }
        }
        ?>
    </select>

    <input type="submit" value="Найти">
</form>

<table>
    <tr><th>Имя</th><th>Услуга</th><th>Опыт (лет)</th><th>Цена</th><th>Длительность</th><th>Действие</th></tr>
    <?php
    $where = [];

    if ($search !== '') {
        $search_escaped = mysqli_real_escape_string($conn, $search);
        $where[] = "(engineers.specialization LIKE '%$search_escaped%' OR engineers.name LIKE '%$search_escaped%' OR services.service_name LIKE '%$search_escaped%')";
    }

    if ($selected_engineer > 0) {
        $where[] = "engineers.id_engineer = $selected_engineer";
    }

    $where_sql = '';
    if (!empty($where)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where);
    }

    $query = "
        SELECT engineers.*, services.id_service, services.service_name, services.price, services.duration_minutes
        FROM engineers
        LEFT JOIN services ON engineers.specialization = services.service_name
        $where_sql
        ORDER BY engineers.name ASC
    ";

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

        $engineer_id = htmlspecialchars($row['id_engineer'], ENT_QUOTES, 'UTF-8');
        $engineer_name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
        $engineer_specialization = htmlspecialchars($row['specialization'], ENT_QUOTES, 'UTF-8');
        $engineer_experience = htmlspecialchars($row['experience'], ENT_QUOTES, 'UTF-8');
        $service_id_safe = htmlspecialchars($service_id, ENT_QUOTES, 'UTF-8');
        $service_name_safe = htmlspecialchars($service_name, ENT_QUOTES, 'UTF-8');
        $price_safe = htmlspecialchars($price, ENT_QUOTES, 'UTF-8');
        $duration_safe = htmlspecialchars($duration, ENT_QUOTES, 'UTF-8');

        echo "<tr>
                <td>{$engineer_name}</td>
                <td>{$engineer_specialization}</td>
                <td>{$engineer_experience}</td>
                <td>{$price_safe} руб.</td>
                <td>{$duration_safe} мин.</td>
                <td>
                    <form method='POST' action='cart.php'>
                        <input type='hidden' name='engineer_id' value='{$engineer_id}'>
                        <input type='hidden' name='engineer_name' value='{$engineer_name}'>
                        <input type='hidden' name='service_id' value='{$service_id_safe}'>
                        <input type='hidden' name='service_name' value='{$service_name_safe}'>
                        <button type='submit' name='add_to_cart'>Забронировать</button>
                    </form>
                </td>
              </tr>";
    }
    ?>
</table>
<?php include 'footer.php'; ?>
