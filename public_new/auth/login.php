<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require '../includes/db.php'; // Upewnij się, że ta ścieżka jest poprawna

$error = ''; // Zmienna na błędy

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Użyj zapytań do bazy danych, aby uzyskać dane logowania
    $stmt = $conn->prepare("SELECT * FROM admini WHERE username = ?"); 
    
    if ($stmt === false) {
        die('Błąd przygotowania zapytania: ' . htmlspecialchars($conn->error));
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // Sprawdź, czy hasło jest poprawne
        if (password_verify($password, $row['password'])) { 
            $_SESSION['username'] = $username;

            // Rejestracja logowania
            $history_stmt = $conn->prepare("INSERT INTO history (admin_username, change_description) VALUES (?, ?)");
            $change_description = "Zalogowano się";
            $history_stmt->bind_param("ss", $username, $change_description);
            $history_stmt->execute();

            header("Location: ../dashboard/dashboard.php");
            exit();
        } else {
            $error = "Niepoprawne dane logowania.";
        }
    } else {
        $error = "Niepoprawne dane logowania.";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logowanie</title>
    <link rel="stylesheet" href="style-login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

<div class="login-container">
    <h1>Logowanie</h1>

    <!-- Wyświetlanie błędów logowania -->
    <?php if (!empty($error)): ?>
        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="input-group">
            <i class="fas fa-user"></i>
            <input type="text" name="username" placeholder="Nazwa użytkownika" required>
        </div>

        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" placeholder="Hasło" required>
        </div>

        <button type="submit" class="custom-button">Zaloguj się</button>
    </form>
</div>

</body>
</html>