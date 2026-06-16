<?php
class ErrorHandler {
    private $errors = [];
    private $success = [];

    public function addError($field, $message) {
        $this->errors[$field] = $message;
    }

    public function addSuccess($message) {
        $this->success[] = $message;
    }

    public function hasErrors() {
        return !empty($this->errors);
    }

    public function getError($field) {
        return $this->errors[$field] ?? '';
    }

    public function displayErrors() {
        if ($this->hasErrors()) {
            echo '<div style="background:#f8d7da; color:#842029; padding:15px; border:1px solid #f5c2c7; margin-bottom:15px;">';
            echo '<ul>';
            foreach ($this->errors as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }

    public function displaySuccess() {
        if (!empty($this->success)) {
            echo '<div style="background:#d1e7dd; color:#0f5132; padding:15px; border:1px solid #badbcc; margin-bottom:15px;">';
            foreach ($this->success as $message) {
                echo '<p>' . $message . '</p>';
            }
            echo '</div>';
        }
    }

    public function validateName($name) {
        $name = trim($name);

        if ($name === '') {
            $this->addError('name', 'Имя не может быть пустым');
            return false;
        }

        if (mb_strlen($name) < 2) {
            $this->addError('name', 'Имя должно содержать не менее 2 символов');
            return false;
        }

        if (preg_match('/[\'";\\\\]/', $name)) {
            $this->addError('name', 'Имя содержит подозрительные символы');
            return false;
        }

        return true;
    }

    public function validateEmail($contact) {
    	$contact = trim($contact);

    	if ($contact === '') {
        	$this->addError('contact', 'Почта не может быть пустой');
        	return false;
    	}

    	if (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
       		$this->addError('contact', 'Почта имеет неверный формат');
        	return false;
    	}
	
    	if (preg_match('/[\'";\\\\]/', $contact)) {
        	$this->addError('contact', 'Почта содержит подозрительные символы');
        	return false;
    	}

    	return true;
    }

    public function validatePassword($password) {
        if ($password === '') {
            $this->addError('password', 'Пароль не может быть пустым');
            return false;
        }

        if (strlen($password) < 6) {
            $this->addError('password', 'Пароль должен быть не менее 6 символов');
            return false;
        }

        return true;
    }

    public function validateCaptcha($answer, $correctAnswer) {
        if (!isset($answer) || (int)$answer !== (int)$correctAnswer) {
            $this->addError('captcha', 'Неверно решён пример. Подтвердите, что вы не робот.');
            return false;
        }

        return true;
    }

    public function logError($message, $level = 'ERROR') {
        if (!is_dir('logs')) {
            mkdir('logs', 0777, true);
        }

        $logMessage = date('D-m-y H:i:s') . " [$level] $message\n";
        error_log($logMessage, 3, 'logs/error.log');
    }

    public static function handleFatalError() {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            if (!is_dir('logs')) {
                mkdir('logs', 0777, true);
            }

            error_log(
                date('Y-m-d H:i:s') . " [FATAL] {$error['message']} in {$error['file']} on line {$error['line']}\n",
                3,
                'logs/fatal.log'
            );

            echo '<div style="background:#f8d7da; color:#842029; padding:20px; border:1px solid #f5c2c7;">';
            echo '<h2>Произошла фатальная ошибка</h2>';
            echo '<p>На сервере возникла ошибка. Попробуйте позже.</p>';
            echo '</div>';
        }
    }
}

class DatabaseExceptionHandler {
    private $errorHandler;

    public function __construct($errorHandler) {
        $this->errorHandler = $errorHandler;
    }

    public function safeQuery($conn, $sql, $types = '', $params = []) {
        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            $this->errorHandler->logError('Ошибка подготовки запроса: ' . mysqli_error($conn), 'DB_ERROR');
            return [
                'success' => false,
                'error' => 'Ошибка базы данных. Попробуйте позже.'
            ];
        }

        if (!empty($params)) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        if (!mysqli_stmt_execute($stmt)) {
            $this->errorHandler->logError('Ошибка выполнения запроса: ' . mysqli_error($conn), 'DB_ERROR');
            return [
                'success' => false,
                'error' => 'Ошибка выполнения запроса.'
            ];
        }

        return [
            'success' => true,
            'stmt' => $stmt,
            'result' => mysqli_stmt_get_result($stmt)
        ];
    }
}

register_shutdown_function(['ErrorHandler', 'handleFatalError']);
?>