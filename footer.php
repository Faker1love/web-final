</div> <!-- конец container -->
<footer style="text-align: center; padding: 20px; background: #1f1f1f; margin-top: 40px; border-top: 2px solid #e74c3c;">
    <?php
    // Дополнительный сервис: счетчик просмотров страниц
    $counter_file = 'counter.txt';
    if (!file_exists($counter_file)) { file_put_contents($counter_file, 0); }
    $visits = file_get_contents($counter_file);
    $visits++;
    file_put_contents($counter_file, $visits);
    echo "<p>Количество просмотров сайта: " . $visits . "</p>";
    ?>
    <p>&copy; 2026 Студия Звукозаписи. Все права защищены.</p>
</footer>
</body>
</html>