<?php
session_start();
require '../includes/db.php'; // Upewnij się, że ta ścieżka jest poprawna

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Sprawdź, czy użytkownik już istnieje
    $stmt = $conn->prepare("SELECT * FROM admini WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Haszowanie hasła
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Wstawianie nowego użytkownika do bazy danych
        $stmt = $conn->prepare("INSERT INTO admini (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hashed_password);

        if ($stmt->execute()) {
            // Użytkownik został dodany
            $_SESSION['username'] = $username;
            header("Location: ../admin/dashboard.php"); // Przekierowanie na dashboard
            exit();
        } else {
            $error = "Błąd podczas rejestracji.";
        }
    } else {
        $error = "Użytkownik o podanej nazwie już istnieje.";
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style-login.css">
    <title>Rejestracja</title>
</head>
<body>
    <div class="login-container">
        <h1>Zarejestruj się</h1>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
        <form action="" method="POST">
            <input type="text" name="username" placeholder="Nazwa użytkownika" required>
            <input type="password" name="password" placeholder="Hasło" required>
            <button type="submit">Zarejestruj się</button>
        </form>
        <p>Masz już konto? <a href="login.php">Zaloguj się</a></p>
    </div>
</body>
</html>
