<?php include 'header.php'; ?>

<h2>Связаться с нами</h2>
<p>Оставьте ваше сообщение, и мы ответим вам в ближайшее время.</p>

<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $to = "admin@studio.ru"; // Email администратора
    $subject = "Новое сообщение с сайта Студии";
    
    $user_name = htmlspecialchars($_POST['user_name']);
    $user_email = htmlspecialchars($_POST['user_email']);
    $message_text = htmlspecialchars($_POST['message']);
    
    $headers = "From: $user_email" . "\r\n" .
               "Reply-To: $user_email" . "\r\n" .
               "X-Mailer: PHP/" . phpversion();
    
    $full_message = "Отправитель: $user_name\nEmail: $user_email\n\nТекст сообщения:\n$message_text";

    // Функция mail() отправляет письмо через почтовый сервер
    // Примечание: на локальных серверах (OpenServer/XAMPP) письмо сохранится в папку mail/temp
    if (mail($to, $subject, $full_message, $headers)) {
        echo "<p style='color:green; font-weight:bold;'>Ваше сообщение успешно отправлено!</p>";
    } else {
        echo "<p style='color:red;'>Произошла ошибка при отправке. Пожалуйста, попробуйте позже.</p>";
    }
}
?>

<div style="max-width: 600px; margin-top: 20px;">
    <form method="POST">
        <label>Ваше имя:</label>
        <input type="text" name="user_name" placeholder="Иван Иванов" required>
        
        <label>Ваш E-mail:</label>
        <input type="email" name="user_email" placeholder="example@mail.ru" required>
        
        <label>Сообщение:</label>
        <textarea name="message" rows="6" style="width: 100%; padding: 10px; background: #2a2a2a; color: white; border: 1px solid #333; border-radius: 4px; margin-bottom: 15px;" required></textarea>
        
        <input type="submit" value="Отправить сообщение">
    </form>
</div>

<div style="margin-top: 40px; padding: 20px; border: 1px dashed #e74c3c;">
    <h3>Наш адрес</h3>
    <p>ул. Звуковая, д. 808, г. Москва</p>
    <p>Телефон: +7 (999) 123-45-67</p>
</div>

<?php include 'footer.php'; ?>