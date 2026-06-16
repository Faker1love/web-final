<?php
function studioErrorHandler($errno, $errstr, $errfile, $errline) {
    echo "<div style='background:#ffebee; border:2px solid #f44336; padding:10px; margin:10px 0;'>";
    echo "<strong>ОШИБКА:</strong> " . htmlspecialchars($errstr) . "<br>";
    echo "<small>Файл: $errfile, строка: $errline</small>";
    echo "</div>";

    return true;
}

set_error_handler("studioErrorHandler", E_USER_WARNING);

echo "<h1>Пользовательский обработчик ошибок сайта студии звукозаписи</h1>";
echo "<hr>";

echo "<h2>1. Нефатальная ошибка E_USER_WARNING</h2>";

function calculateSessionPrice($duration, $pricePerHour) {
    if (!is_numeric($duration) || $duration <= 0) {
        trigger_error("Длительность сессии должна быть положительным числом!", E_USER_WARNING);
        return null;
    }

    return ($duration / 60) * $pricePerHour;
}

echo "Расчёт стоимости записи при длительности: 'abc'<br>";

$result = calculateSessionPrice("abc", 2000);

echo "Результат: " . ($result ?? "ошибка") . "<br>";

echo "<hr>";
echo "<h2>2. Фатальная ошибка E_USER_ERROR</h2>";

function createBooking($engineerId) {
    if (empty($engineerId)) {
        trigger_error("Нельзя оформить бронирование без выбранного звукорежиссёра!", E_USER_ERROR);
        return null;
    }

    return true;
}

echo "Пробуем оформить бронирование без звукорежиссёра<br>";

createBooking(null);

echo "Эта строка не выполнится после фатальной ошибки";
?>