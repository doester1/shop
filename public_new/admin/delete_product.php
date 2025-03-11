<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Sprawdzanie, czy produkt do usunięcia został wybrany
if (isset($_GET['id'])) {
    $product_id = $_GET['id'];
    
    // Sprawdzenie, czy produkt istnieje
    $stmt = $conn->prepare("SELECT * FROM produkty WHERE id=?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Jeśli produkt istnieje, wykonaj usunięcie
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM produkty WHERE id=?");
        $stmt->bind_param("i", $product_id);
        if ($stmt->execute()) {
            // Sukces: Przekierowanie z komunikatem
            $_SESSION['message'] = "Produkt został pomyślnie usunięty.";

            // Rejestracja zmiany
            $history_stmt = $conn->prepare("INSERT INTO history (admin_username, change_description) VALUES (?, ?)");
            $change_description = "Usunięto produkt o ID: " . $product_id;
            $history_stmt->bind_param("ss", $_SESSION['username'], $change_description);
            $history_stmt->execute();
        } else {
            // Błąd: Przekierowanie z komunikatem o błędzie
            $_SESSION['error'] = "Wystąpił błąd podczas usuwania produktu: " . $stmt->error;
        }
    } else {
        $_SESSION['error'] = "Produkt o podanym ID nie istnieje.";
    }
} else {
    $_SESSION['error'] = "ID produktu nie zostało przekazane.";
}

// Przekierowanie do strony zarządzania produktami
header("Location: manage_products.php");
exit();
?>