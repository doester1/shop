<?php
session_start();
require '../includes/db.php'; // Upewnij się, że ta ścieżka jest poprawna

// Sprawdzenie, czy użytkownik jest zalogowany
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];

    // Rejestracja wylogowania
    $history_stmt = $conn->prepare("INSERT INTO history (admin_username, change_description) VALUES (?, ?)");
    $change_description = "Wylogowano się";
    $history_stmt->bind_param("ss", $username, $change_description);
    $history_stmt->execute();
}

// Usunięcie wszystkich zmiennych sesyjnych
$_SESSION = [];

// Zniszczenie sesji
session_destroy();

// Przekierowanie do strony logowania
header("Location: ../auth/login.php");
exit();
?>