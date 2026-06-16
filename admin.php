<?php
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_date_ru($date) {
    if (empty($date)) {
        return '';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return h($date);
    }

    return date('d.m.Y', $timestamp);
}

function redirect_admin($tab, $message = '') {
    $url = 'admin.php?tab=' . urlencode($tab);

    if ($message !== '') {
        $url .= '&message=' . urlencode($message);
    }

    header('Location: ' . $url);
    exit();
}

function count_unique_engineers($conn) {
    $result = mysqli_query($conn, "SELECT COUNT(DISTINCT name) AS cnt FROM engineers");
    $row = mysqli_fetch_assoc($result);

    return (int)$row['cnt'];
}

function count_table($conn, $table) {
    $allowed = ['clients', 'services', 'engineers', 'bookings'];

    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM `$table`");
    $row = mysqli_fetch_assoc($result);

    return (int)$row['cnt'];
}


function admin_email_dir() {
    return 'C:\OpenServer\userdata\temp\email';
}

function text_to_utf8($text) {
    if (!is_string($text) || $text === '') {
        return '';
    }

    if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
        $encoding = mb_detect_encoding($text, ['UTF-8', 'Windows-1251', 'CP1251', 'KOI8-R', 'ISO-8859-1'], true);

        if ($encoding && strtoupper($encoding) !== 'UTF-8') {
            return mb_convert_encoding($text, 'UTF-8', $encoding);
        }
    }

    return $text;
}

function decode_email_header_value($value) {
    $value = text_to_utf8(trim((string)$value));

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_decode_mimeheader')) {
        return text_to_utf8(mb_decode_mimeheader($value));
    }

    if (function_exists('imap_mime_header_decode')) {
        $parts = imap_mime_header_decode($value);
        $decoded = '';

        foreach ($parts as $part) {
            $charset = $part->charset ?? 'default';
            $text = $part->text ?? '';

            if ($charset !== 'default' && function_exists('mb_convert_encoding')) {
                $text = mb_convert_encoding($text, 'UTF-8', $charset);
            }

            $decoded .= $text;
        }

        return text_to_utf8($decoded);
    }

    return $value;
}

function parse_email_headers($content) {
    $content = str_replace("\r\n", "\n", (string)$content);
    $content = str_replace("\r", "\n", $content);
    $headerPart = $content;

    if (strpos($content, "\n\n") !== false) {
        $headerPart = substr($content, 0, strpos($content, "\n\n"));
    }

    $lines = explode("\n", $headerPart);
    $unfolded = [];

    foreach ($lines as $line) {
        if ($line === '') {
            continue;
        }

        if (($line[0] ?? '') === ' ' || ($line[0] ?? '') === "\t") {
            if (!$unfolded) {
                continue;
            }
            $unfolded[count($unfolded) - 1] .= ' ' . trim($line);
        } else {
            $unfolded[] = $line;
        }
    }

    $headers = [];

    foreach ($unfolded as $line) {
        $pos = strpos($line, ':');

        if ($pos === false) {
            continue;
        }

        $name = strtolower(trim(substr($line, 0, $pos)));
        $value = trim(substr($line, $pos + 1));

        if ($name !== '') {
            $headers[$name] = $value;
        }
    }

    return $headers;
}

function extract_email_body($content, $headers) {
    $content = str_replace("\r\n", "\n", (string)$content);
    $content = str_replace("\r", "\n", $content);
    $body = $content;

    if (strpos($content, "\n\n") !== false) {
        $body = substr($content, strpos($content, "\n\n") + 2);
    }

    $encoding = strtolower(trim($headers['content-transfer-encoding'] ?? ''));

    if ($encoding === 'quoted-printable') {
        $body = quoted_printable_decode($body);
    } elseif ($encoding === 'base64') {
        $decoded = base64_decode(preg_replace('/\s+/', '', $body), true);

        if ($decoded !== false) {
            $body = $decoded;
        }
    }

    $body = text_to_utf8($body);
    $body = trim(strip_tags($body));

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($body, 'UTF-8') > 5000) {
            $body = mb_substr($body, 0, 5000, 'UTF-8') . '...';
        }
    } elseif (strlen($body) > 5000) {
        $body = substr($body, 0, 5000) . '...';
    }

    return $body;
}

function find_admin_messages_by_contact($contact, $limit = 50) {
    $contact = trim((string)$contact);
    $emailDir = admin_email_dir();

    $result = [
        'dir' => $emailDir,
        'error' => '',
        'messages' => []
    ];

    if ($contact === '') {
        $result['error'] = 'У пользователя не указана почта';
        return $result;
    }

    if (!is_dir($emailDir)) {
        $result['error'] = 'Папка с письмами не найдена: ' . $emailDir;
        return $result;
    }

    $files = [];

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($emailDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $files[] = [
                'path' => $file->getPathname(),
                'name' => $file->getFilename(),
                'mtime' => $file->getMTime(),
                'size' => $file->getSize()
            ];
        }
    } catch (Exception $e) {
        $result['error'] = 'Не удалось прочитать папку с письмами';
        return $result;
    }

    usort($files, function ($a, $b) {
        return $b['mtime'] <=> $a['mtime'];
    });

    $contactLower = strtolower($contact);

    foreach ($files as $file) {
        if (count($result['messages']) >= $limit) {
            break;
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            continue;
        }

        $content = @file_get_contents($file['path']);

        if ($content === false) {
            continue;
        }

        $contentUtf8 = text_to_utf8($content);

        if (stripos($contentUtf8, $contactLower) === false) {
            continue;
        }

        $headers = parse_email_headers($contentUtf8);
        $from = decode_email_header_value($headers['from'] ?? '');
        $replyTo = decode_email_header_value($headers['reply-to'] ?? '');
        $to = decode_email_header_value($headers['to'] ?? '');
        $subject = decode_email_header_value($headers['subject'] ?? 'Без темы');
        $date = decode_email_header_value($headers['date'] ?? '');

        $result['messages'][] = [
            'file' => $file['name'],
            'modified' => $file['mtime'],
            'from' => $from,
            'reply_to' => $replyTo,
            'to' => $to,
            'subject' => $subject !== '' ? $subject : 'Без темы',
            'date' => $date,
            'body' => extract_email_body($contentUtf8, $headers)
        ];
    }

    return $result;
}

/*
    ВЫХОД ИЗ АДМИН-ПАНЕЛИ
*/
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit();
}

/*
    ФОРМА ВХОДА В АДМИН-ПАНЕЛЬ
*/
$adminLoginError = '';

if (isset($_POST['admin_login'])) {
    $contact = trim($_POST['contact'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($contact === '' || $password === '') {
        $adminLoginError = 'Введите логин и пароль';
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_client, name, contact, password, role 
             FROM clients 
             WHERE contact = ? 
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 's', $contact);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        $passwordOk = false;

        if ($user) {
            if (password_verify($password, $user['password'])) {
                $passwordOk = true;
            } elseif ($password === $user['password']) {
                $passwordOk = true;
            }
        }

        if (!$user || !$passwordOk) {
            $adminLoginError = 'Неверный логин или пароль';
        } elseif ($user['role'] !== 'admin') {
            $adminLoginError = 'У вас нет прав администратора';
        } else {
            $_SESSION['user_id'] = (int)$user['id_client'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            header('Location: admin.php');
            exit();
        }
    }
}

/*
    ЕСЛИ ПОЛЬЗОВАТЕЛЬ НЕ ВОШЁЛ — ПОКАЗЫВАЕМ ФОРМУ
*/
if (!isset($_SESSION['user_id'])):
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в админ-панель</title>

    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #111111;
            font-family: Arial, sans-serif;
        }

        .admin-login-box {
            width: 370px;
            padding: 30px;
            background: #000000;
            border-radius: 14px;
            border: 1px solid #333333;
            color: #ffffff;
        }

        .admin-login-box h1 {
            margin-top: 0;
            margin-bottom: 25px;
            text-align: center;
            font-size: 26px;
        }

        .admin-login-box label {
            display: block;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .admin-login-box input {
            display: block;
            width: 100%;
            height: 42px;
            margin-top: 6px;
            padding: 8px 10px;
            box-sizing: border-box;
            border: 1px solid #555555;
            border-radius: 6px;
            font-size: 16px;
        }

        .admin-login-box button {
            width: 100%;
            height: 44px;
            margin-top: 10px;
            background: #e53935;
            color: #ffffff;
            border: 0;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
        }

        .admin-login-error {
            margin-bottom: 15px;
            padding: 10px;
            background: #b00020;
            color: #ffffff;
            border-radius: 6px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="admin-login-box">
    <h1>Вход в админ-панель</h1>

    <?php if ($adminLoginError !== ''): ?>
        <div class="admin-login-error">
            <?= h($adminLoginError) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="admin_login" value="1">

        <label>
            Логин / почта
            <input type="text" name="contact" required>
        </label>

        <label>
            Пароль
            <input type="password" name="password" required>
        </label>

        <button type="submit">Войти</button>
    </form>
</div>

</body>
</html>

<?php
exit();
endif;

/*
    ПРОВЕРКА РОЛИ АДМИНА ДЛЯ УЖЕ ВОШЕДШЕГО ПОЛЬЗОВАТЕЛЯ
*/
$currentUserId = (int)$_SESSION['user_id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT id_client, name, role 
     FROM clients 
     WHERE id_client = ? 
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, 'i', $currentUserId);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$currentUser = mysqli_fetch_assoc($result);

if (!$currentUser || $currentUser['role'] !== 'admin') {
    session_destroy();
    header('Location: admin.php');
    exit();
}

$_SESSION['user_role'] = 'admin';

/*
    ОСНОВНАЯ ЛОГИКА АДМИН-ПАНЕЛИ
*/
$mainAdminId = 1;

$allowedTabs = ['dashboard', 'bookings', 'services', 'users'];
$tab = $_GET['tab'] ?? 'dashboard';

// Старые ссылки оставлены совместимыми, но открывают единую вкладку "Услуги".
if ($tab === 'add_service' || $tab === 'edit_services') {
    $tab = 'services';
}

if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'dashboard';
}

$message = $_GET['message'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['admin_login'])) {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_booking') {
        $id = (int)($_POST['id_booking'] ?? 0);

        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM bookings WHERE id_booking = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
        }

        redirect_admin('bookings', 'Бронирование удалено');
    }

    if ($action === 'update_booking') {
        $id = (int)($_POST['id_booking'] ?? 0);
        $idClient = (int)($_POST['id_client'] ?? 0);
        $idEngineer = (int)($_POST['id_engineer'] ?? 0);
        $bookingDate = $_POST['booking_date'] ?? '';

        if ($id > 0 && $idClient > 0 && $idEngineer > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate)) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE bookings 
                 SET id_client = ?, id_engineer = ?, booking_date = ? 
                 WHERE id_booking = ?"
            );

            mysqli_stmt_bind_param($stmt, 'iisi', $idClient, $idEngineer, $bookingDate, $id);
            mysqli_stmt_execute($stmt);

            redirect_admin('bookings', 'Бронирование обновлено');
        }

        redirect_admin('bookings', 'Проверьте данные бронирования');
    }

    if ($action === 'add_service') {
        $serviceName = trim($_POST['service_name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $duration = (int)($_POST['duration_minutes'] ?? 0);

        if ($serviceName !== '' && $price >= 0 && $duration > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO services (service_name, price, duration_minutes) 
                 VALUES (?, ?, ?)"
            );

            mysqli_stmt_bind_param($stmt, 'sdi', $serviceName, $price, $duration);
            mysqli_stmt_execute($stmt);

            redirect_admin('services', 'Услуга добавлена');
        }

        redirect_admin('services', 'Заполните все поля услуги');
    }

    if ($action === 'bulk_update_service_prices') {
        $selectedServices = $_POST['selected_services'] ?? [];
        $pricePercentRaw = str_replace(',', '.', trim($_POST['price_percent'] ?? ''));

        if (!is_array($selectedServices) || count($selectedServices) === 0) {
            redirect_admin('services', 'Выберите хотя бы одну услугу');
        }

        if ($pricePercentRaw === '' || !is_numeric($pricePercentRaw)) {
            redirect_admin('services', 'Введите процент изменения цены');
        }

        $pricePercent = (float)$pricePercentRaw;

        if ($pricePercent < -100) {
            redirect_admin('services', 'Процент не может быть меньше -100');
        }

        $selectedIds = [];

        foreach ($selectedServices as $selectedService) {
            $selectedId = (int)$selectedService;

            if ($selectedId > 0 && !in_array($selectedId, $selectedIds, true)) {
                $selectedIds[] = $selectedId;
            }
        }

        if (!$selectedIds) {
            redirect_admin('services', 'Выберите хотя бы одну услугу');
        }

        $priceFactor = 1 + ($pricePercent / 100);
        $updatedCount = 0;

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE services 
             SET price = GREATEST(0, ROUND(price * ?, 2)) 
             WHERE id_service = ?"
        );

        foreach ($selectedIds as $id) {
            mysqli_stmt_bind_param($stmt, 'di', $priceFactor, $id);
            mysqli_stmt_execute($stmt);
            $updatedCount++;
        }

        redirect_admin('services', 'Цены обновлены для выбранных услуг: ' . $updatedCount);
    }

    if ($action === 'update_service') {
        $id = (int)($_POST['id_service'] ?? 0);
        $serviceName = trim($_POST['service_name'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $duration = (int)($_POST['duration_minutes'] ?? 0);

        if ($id > 0 && $serviceName !== '' && $price >= 0 && $duration > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE services 
                 SET service_name = ?, price = ?, duration_minutes = ? 
                 WHERE id_service = ?"
            );

            mysqli_stmt_bind_param($stmt, 'sdii', $serviceName, $price, $duration, $id);
            mysqli_stmt_execute($stmt);

            redirect_admin('services', 'Услуга обновлена');
        }

        redirect_admin('services', 'Проверьте данные услуги');
    }

    if ($action === 'delete_service') {
        $id = (int)($_POST['id_service'] ?? 0);

        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM services WHERE id_service = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
        }

        redirect_admin('services', 'Услуга удалена');
    }

}

$clients = mysqli_query($conn, "SELECT id_client, name FROM clients ORDER BY name");
$engineers = mysqli_query($conn, "SELECT id_engineer, name, specialization FROM engineers ORDER BY name, specialization");

include 'header.php';
?>

<style>
    .admin-wrap {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .admin-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        flex-wrap: wrap;
    }

    .admin-logout {
        padding: 10px 14px;
        background: #b00020;
        color: #ffffff;
        text-decoration: none;
        border-radius: 8px;
    }

    .admin-nav {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin: 20px 0;
    }

    .admin-nav a {
        padding: 10px 14px;
        border: 1px solid #cccccc;
        border-radius: 8px;
        text-decoration: none;
        color: #222222;
        background: #f7f7f7;
    }

    .admin-nav a.active {
        background: #222222;
        color: #ffffff;
    }

    .cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
    }

    .card {
        border: 1px solid #dddddd;
        border-radius: 12px;
        padding: 18px;
        background: #000000;
        color: #ffffff;
    }

    .card strong {
        display: block;
        font-size: 34px;
        margin-top: 8px;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 18px;
        background: #000000;
    }

    .admin-table th,
    .admin-table td {
        border: 1px solid #dddddd;
        padding: 9px;
        vertical-align: top;
        color: #ffffff;
    }

    .admin-table th {
        background: #000000;
        color: #ffffff;
    }

    .inline-form {
        display: inline-block;
        margin: 2px 0;
    }

    .row-form {
        display: grid;
        grid-template-columns: repeat(4, minmax(130px, 1fr));
        gap: 8px;
        align-items: center;
    }

    .filter-form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: end;
        margin: 15px 0;
    }

    .service-form {
        display: block;
        max-width: 430px;
        margin: 15px 0;
        padding: 25px;
        background: #111111;
        border: 1px solid #333333;
        border-radius: 12px;
    }

    .service-form label {
        display: block;
        margin-bottom: 18px;
        color: #ffffff;
        font-size: 18px;
    }

    .service-form input {
        display: block;
        width: 100%;
        height: 42px;
        margin-top: 6px;
        padding: 8px 10px;
        box-sizing: border-box;
        font-size: 16px;
    }

    .service-form button {
        display: block;
        width: 100%;
        height: 44px;
        margin-top: 8px;
        background: #e53935;
        color: #ffffff;
        border: 0;
        font-size: 16px;
        cursor: pointer;
    }

    .services-layout {
        display: block;
    }

    .services-panel {
        min-width: 0;
        margin-bottom: 28px;
    }

    .bulk-price-box {
        margin: 0 0 14px 0;
        padding: 16px;
        background: #111111;
        border: 1px solid #333333;
        border-radius: 12px;
        color: #ffffff;
    }

    .bulk-price-controls {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: end;
    }

    .bulk-price-controls label {
        display: block;
        color: #ffffff;
    }

    .bulk-price-controls input {
        display: block;
        width: 220px;
        margin-top: 6px;
    }

    .bulk-price-controls button {
        height: 38px;
        background: #e53935;
        color: #ffffff;
        border: 0;
        border-radius: 4px;
    }

    .bulk-price-note {
        margin-top: 8px;
        font-size: 14px;
        color: #dddddd;
    }

    .admin-table input[type="checkbox"] {
        width: auto;
        height: auto;
    }

    input,
    select,
    button {
        padding: 8px;
        box-sizing: border-box;
    }

    button,
    input[type="submit"] {
        cursor: pointer;
    }

    .admin-button-link {
        display: inline-block;
        padding: 8px 10px;
        background: #e53935;
        color: #ffffff;
        border-radius: 6px;
        text-decoration: none;
        white-space: nowrap;
    }

    .mail-panel {
        margin: 18px 0;
        padding: 18px;
        background: #111111;
        color: #ffffff;
        border: 1px solid #333333;
        border-radius: 12px;
    }

    .mail-panel h3 {
        margin-top: 0;
    }

    .mail-card {
        margin-top: 14px;
        padding: 14px;
        background: #000000;
        border: 1px solid #444444;
        border-radius: 10px;
    }

    .mail-meta {
        margin: 4px 0;
        color: #dddddd;
        font-size: 14px;
    }

    .mail-body {
        margin-top: 10px;
        padding: 10px;
        background: #1a1a1a;
        border-radius: 8px;
        white-space: pre-wrap;
        color: #ffffff;
    }

    .message {
        padding: 10px;
        background: #e8f6e8;
        border: 1px solid #b7dfb7;
        border-radius: 8px;
        margin: 10px 0;
        color: #222222;
    }

    .danger {
        background: #b00020;
        color: #ffffff;
        border: 0;
    }
</style>

<div class="admin-wrap">
    <div class="admin-top">
        <h1>Административная панель</h1>
        <a class="admin-logout" href="admin.php?logout=1">Выйти</a>
    </div>

    <nav class="admin-nav">
        <a class="<?= $tab === 'dashboard' ? 'active' : '' ?>" href="admin.php?tab=dashboard">Главная</a>
        <a class="<?= $tab === 'bookings' ? 'active' : '' ?>" href="admin.php?tab=bookings">Бронирования</a>
        <a class="<?= $tab === 'services' ? 'active' : '' ?>" href="admin.php?tab=services">Услуги</a>
        <a class="<?= $tab === 'users' ? 'active' : '' ?>" href="admin.php?tab=users">Пользователи</a>
    </nav>

    <?php if ($message !== ''): ?>
        <div class="message"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($tab === 'dashboard'): ?>
        <div class="cards">
            <div class="card">
                Пользователей
                <strong><?= count_table($conn, 'clients') ?></strong>
            </div>

            <div class="card">
                Услуг
                <strong><?= count_table($conn, 'services') ?></strong>
            </div>

            <div class="card">
                Инженеров
                <strong><?= count_unique_engineers($conn) ?></strong>
            </div>

            <div class="card">
                Бронирований
                <strong><?= count_table($conn, 'bookings') ?></strong>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'bookings'): ?>
        <h2>Бронирования</h2>

        <form class="filter-form" method="GET">
            <input type="hidden" name="tab" value="bookings">

            <label>
                Клиент<br>
                <select name="client_filter">
                    <option value="">Все клиенты</option>

                    <?php mysqli_data_seek($clients, 0); ?>
                    <?php while ($client = mysqli_fetch_assoc($clients)): ?>
                        <option value="<?= (int)$client['id_client'] ?>"
                            <?= (isset($_GET['client_filter']) && (int)$_GET['client_filter'] === (int)$client['id_client']) ? 'selected' : '' ?>>
                            <?= h($client['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </label>

            <label>
                Дата брони<br>
                <input type="date" name="date_filter" value="<?= h($_GET['date_filter'] ?? '') ?>">
            </label>

            <button type="submit">Фильтровать</button>
            <a href="admin.php?tab=bookings">Сбросить</a>
        </form>

        <?php
        $where = [];
        $types = '';
        $params = [];

        if (!empty($_GET['client_filter'])) {
            $where[] = 'b.id_client = ?';
            $types .= 'i';
            $params[] = (int)$_GET['client_filter'];
        }

        if (!empty($_GET['date_filter']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_filter'])) {
            $where[] = 'b.booking_date = ?';
            $types .= 's';
            $params[] = $_GET['date_filter'];
        }

        $sql = "SELECT 
                    b.id_booking,
                    b.id_client,
                    b.id_engineer,
                    b.booking_date,
                    c.name AS client_name,
                    e.name AS engineer_name,
                    e.specialization
                FROM bookings b
                JOIN clients c ON c.id_client = b.id_client
                JOIN engineers e ON e.id_engineer = b.id_engineer";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY b.booking_date DESC, b.id_booking DESC';

        $stmt = mysqli_prepare($conn, $sql);

        if ($params) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        mysqli_stmt_execute($stmt);
        $bookings = mysqli_stmt_get_result($stmt);
        ?>

        <table class="admin-table">
            <tr>
                <th>ID</th>
                <th>Клиент</th>
                <th>Инженер</th>
                <th>Дата</th>
                <th>Редактирование</th>
                <th>Удаление</th>
            </tr>

            <?php while ($booking = mysqli_fetch_assoc($bookings)): ?>
                <tr>
                    <td><?= (int)$booking['id_booking'] ?></td>
                    <td><?= h($booking['client_name']) ?></td>
                    <td><?= h($booking['engineer_name'] . ' — ' . $booking['specialization']) ?></td>
                    <td><?= format_date_ru($booking['booking_date']) ?></td>

                    <td>
                        <form method="POST" class="row-form">
                            <input type="hidden" name="action" value="update_booking">
                            <input type="hidden" name="id_booking" value="<?= (int)$booking['id_booking'] ?>">

                            <select name="id_client" required>
                                <?php mysqli_data_seek($clients, 0); ?>
                                <?php while ($client = mysqli_fetch_assoc($clients)): ?>
                                    <option value="<?= (int)$client['id_client'] ?>"
                                        <?= (int)$booking['id_client'] === (int)$client['id_client'] ? 'selected' : '' ?>>
                                        <?= h($client['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>

                            <select name="id_engineer" required>
                                <?php mysqli_data_seek($engineers, 0); ?>
                                <?php while ($engineer = mysqli_fetch_assoc($engineers)): ?>
                                    <option value="<?= (int)$engineer['id_engineer'] ?>"
                                        <?= (int)$booking['id_engineer'] === (int)$engineer['id_engineer'] ? 'selected' : '' ?>>
                                        <?= h($engineer['name'] . ' — ' . $engineer['specialization']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>

                            <input type="date" name="booking_date" value="<?= h($booking['booking_date']) ?>" required>

                            <button type="submit">Сохранить</button>
                        </form>
                    </td>

                    <td>
                        <form method="POST" onsubmit="return confirm('Удалить бронирование?');">
                            <input type="hidden" name="action" value="delete_booking">
                            <input type="hidden" name="id_booking" value="<?= (int)$booking['id_booking'] ?>">

                            <button class="danger" type="submit">Удалить</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>

    <?php if ($tab === 'services'): ?>
        <h2>Услуги</h2>

        <?php
        $servicesResult = mysqli_query($conn, "SELECT * FROM services ORDER BY id_service");
        $services = [];

        while ($service = mysqli_fetch_assoc($servicesResult)) {
            $services[] = $service;
        }
        ?>

        <div class="services-layout">
            <div class="services-panel">
                <h3>Добавление услуги</h3>

                <form class="service-form" method="POST">
                    <input type="hidden" name="action" value="add_service">

                    <label>
                        Название
                        <input type="text" name="service_name" required>
                    </label>

                    <label>
                        Цена
                        <input type="number" name="price" step="0.01" min="0" required>
                    </label>

                    <label>
                        Длительность, мин.
                        <input type="number" name="duration_minutes" min="1" required>
                    </label>

                    <button type="submit">Добавить</button>
                </form>
            </div>

            <div class="services-panel">
                <h3>Редактирование услуг и массовое изменение цен</h3>

                <?php foreach ($services as $service): ?>
                    <?php $serviceId = (int)$service['id_service']; ?>
                    <form id="service-edit-<?= $serviceId ?>" method="POST"></form>
                <?php endforeach; ?>

                <form id="bulk-price-form" method="POST">
                    <input
                        type="hidden"
                        name="action"
                        value="bulk_update_service_prices"
                    >

                    <div class="bulk-price-box">
                        <div class="bulk-price-controls">
                            <label>
                                Общий процент изменения цены для отмеченных услуг
                                <input
                                    type="number"
                                    name="price_percent"
                                    step="0.01"
                                    placeholder="Например: -10 или 15"
                                    required
                                >
                            </label>

                            <button type="submit">Обновить цены</button>
                        </div>

                        <div class="bulk-price-note">
                            Поставьте галочки напротив нужных услуг ниже, укажите один общий процент и нажмите «Обновить цены». -10 уменьшит цены на 10%, 15 увеличит цены на 15%.
                        </div>
                    </div>

                    <table class="admin-table">
                        <tr>
                            <th>Выбрать</th>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Цена</th>
                            <th>Длительность</th>
                            <th>Действия</th>
                        </tr>

                    <?php if (!$services): ?>
                        <tr>
                            <td colspan="6">Услуги пока не добавлены</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($services as $service): ?>
                        <?php $serviceId = (int)$service['id_service']; ?>

                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    name="selected_services[]"
                                    value="<?= $serviceId ?>"
                                   
                                >
                            </td>

                            <td>
                                <?= $serviceId ?>
                                <input
                                    type="hidden"
                                    name="id_service"
                                    value="<?= $serviceId ?>"
                                    form="service-edit-<?= $serviceId ?>"
                                >
                            </td>

                            <td>
                                <input
                                    type="text"
                                    name="service_name"
                                    value="<?= h($service['service_name']) ?>"
                                    form="service-edit-<?= $serviceId ?>"
                                    required
                                >
                            </td>

                            <td>
                                <input
                                    type="number"
                                    name="price"
                                    step="0.01"
                                    min="0"
                                    value="<?= h($service['price']) ?>"
                                    form="service-edit-<?= $serviceId ?>"
                                    required
                                >
                            </td>

                            <td>
                                <input
                                    type="number"
                                    name="duration_minutes"
                                    min="1"
                                    value="<?= (int)$service['duration_minutes'] ?>"
                                    form="service-edit-<?= $serviceId ?>"
                                    required
                                >
                            </td>

                            <td>
                                <button
                                    type="submit"
                                    name="action"
                                    value="update_service"
                                    form="service-edit-<?= $serviceId ?>"
                                >
                                    Сохранить
                                </button>

                                <button
                                    class="danger"
                                    type="submit"
                                    name="action"
                                    value="delete_service"
                                    form="service-edit-<?= $serviceId ?>"
                                    onclick="return confirm('Удалить услугу?');"
                                >
                                    Удалить
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </table>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($tab === 'users'): ?>
        <h2>Пользователи</h2>

        <?php
        $selectedUserMessages = null;
        $selectedMessagesUser = null;
        $viewMessagesUserId = (int)($_GET['view_messages'] ?? 0);

        if ($viewMessagesUserId > 0) {
            $stmt = mysqli_prepare(
                $conn,
                "SELECT id_client, name, contact 
                 FROM clients 
                 WHERE id_client = ? 
                 LIMIT 1"
            );

            mysqli_stmt_bind_param($stmt, 'i', $viewMessagesUserId);
            mysqli_stmt_execute($stmt);
            $selectedMessagesUser = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if ($selectedMessagesUser) {
                $selectedUserMessages = find_admin_messages_by_contact($selectedMessagesUser['contact']);
            }
        }

        $users = mysqli_query(
            $conn,
            "SELECT id_client, name, contact, role, date_of_register 
             FROM clients 
             ORDER BY id_client"
        );
        ?>

        <?php if ($viewMessagesUserId > 0): ?>
            <div class="mail-panel">
                <?php if (!$selectedMessagesUser): ?>
                    <h3>Сообщения пользователя</h3>
                    <p>Пользователь не найден.</p>
                    <p><a class="admin-button-link" href="admin.php?tab=users">Вернуться к пользователям</a></p>
                <?php else: ?>
                    <h3>
                        Сообщения от пользователя:
                        <?= h($selectedMessagesUser['name']) ?>
                        <?= $selectedMessagesUser['contact'] !== '' ? '(' . h($selectedMessagesUser['contact']) . ')' : '' ?>
                    </h3>

                    <p class="mail-meta">
                    </p>

                    <p>
                        <a class="admin-button-link" href="admin.php?tab=users">Вернуться к пользователям</a>
                    </p>

                    <?php if (!empty($selectedUserMessages['error'])): ?>
                        <p><?= h($selectedUserMessages['error']) ?></p>
                    <?php elseif (empty($selectedUserMessages['messages'])): ?>
                        <p>Писем от этого пользователя не найдено.</p>
                    <?php else: ?>
                        <?php foreach ($selectedUserMessages['messages'] as $mail): ?>
                            <div class="mail-card">
                                <?php if ($mail['from'] !== ''): ?>
                                    <div class="mail-meta"><strong>От:</strong> <?= h($mail['from']) ?></div>
                                <?php endif; ?>

                                <?php if ($mail['reply_to'] !== ''): ?>
                                    <div class="mail-meta"><strong>Reply-To:</strong> <?= h($mail['reply_to']) ?></div>
                                <?php endif; ?>

                                <?php if ($mail['to'] !== ''): ?>
                                    <div class="mail-meta"><strong>Кому:</strong> <?= h($mail['to']) ?></div>
                                <?php endif; ?>

                                <div class="mail-meta">
                                    <strong>Дата:</strong>
                                    <?= $mail['date'] !== '' ? h($mail['date']) : date('d.m.Y H:i', $mail['modified']) ?>
                                </div>

                                <div class="mail-meta"><strong>Файл:</strong> <?= h($mail['file']) ?></div>

                                <div class="mail-body">
                                    <?= h($mail['body'] !== '' ? $mail['body'] : 'Текст письма пустой или не распознан.') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <table class="admin-table">
            <tr>
                <th>ID</th>
                <th>Имя</th>
                <th>Почта</th>
                <th>Дата регистрации</th>
                <th>Роль</th>
                <th>Сообщения</th>
            </tr>

            <?php while ($user = mysqli_fetch_assoc($users)): ?>
                <tr>
                    <td><?= (int)$user['id_client'] ?></td>

                    <td>
                        <?= h($user['name']) ?>
                    </td>

                    <td><?= h($user['contact']) ?></td>
                    <td><?= format_date_ru($user['date_of_register']) ?></td>
                    <td><?= h($user['role']) ?></td>
                    <td>
                        <a
                            class="admin-button-link"
                            href="admin.php?tab=users&view_messages=<?= (int)$user['id_client'] ?>"
                        >
                            Сообщения
                        </a>
                    </td>

                </tr>
            <?php endwhile; ?>
        </table>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>