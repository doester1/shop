<?php
session_start();
require '../includes/db.php'; // Załaduj plik z połączeniem do bazy danych

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Sprawdzenie połączenia
    if ($conn->connect_error) {
        throw new Exception("Błąd połączenia z bazą danych: " . $conn->connect_error);
    }

    // Odbierz dane z formularza
    $product_id = $_POST['product_id'] ?? null;
    $name = $_POST['name'] ?? null;
    $surname = $_POST['surname'] ?? null;
    $quantity = $_POST['quantity'] ?? null;

    if (!$product_id || !$name || !$surname || !$quantity) {
        throw new Exception("Wszystkie pola formularza muszą być wypełnione.");
    }

    // Przygotuj zapytanie do pobrania produktu
    $sql = "SELECT * FROM produkty WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Błąd przygotowania zapytania SQL: " . $conn->error);
    }

    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product_result = $stmt->get_result();

    if ($product_result->num_rows > 0) {
        $product = $product_result->fetch_assoc();

        // Dodaj wpis do zamówień niezależnie od dostępności
        $order_sql = "INSERT INTO zamowienia (produkt, smak, ilosc, imie, nazwisko, status, admin_username, product_id) VALUES (?, ?, ?, ?, ?, 'Oczekujące', ?, ?)";
        $order_stmt = $conn->prepare($order_sql);

        if (!$order_stmt) {
            throw new Exception("Błąd przygotowania zapytania dodawania zamówienia: " . $conn->error);
        }

        // Przykładowe dane dla brakujących zmiennych
        $produkt = $product['nazwa'];
        $smak = $product['smak'];
        $ilosc = $quantity;
        $imie = $name;
        $nazwisko = $surname;
        $admin_username = $_SESSION['username'] ?? 'Nieznany';

        $order_stmt->bind_param("ssisssi", $produkt, $smak, $ilosc, $imie, $nazwisko, $admin_username, $product_id);
        if (!$order_stmt->execute()) {
            throw new Exception("Błąd wykonania zapytania dodawania zamówienia: " . $order_stmt->error);
        }

        // Jeśli ilość w magazynie jest niewystarczająca, wyślij informację tylko do panelu
        if ($product['ilosc'] < $quantity) {
            echo "<div class='notification success'>";
            echo "<i class='icon'>✅</i><h2>Zamówienie zostało złożone pomyślnie!</h2>";
            echo "<p>Produkt: {$produkt}</p>";
            echo "<p>Smak: {$smak}</p>";
            echo "<p>Ilość: {$ilosc}</p>";
            echo "</div>";
        } else {
            // Aktualizuj ilość w bazie danych
            $new_quantity = $product['ilosc'] - $quantity;
            $update_sql = "UPDATE produkty SET ilosc = ? WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);

            if (!$update_stmt) {
                throw new Exception("Błąd przygotowania zapytania aktualizacji: " . $conn->error);
            }

            $update_stmt->bind_param("ii", $new_quantity, $product_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Błąd wykonania zapytania aktualizacji: " . $update_stmt->error);
            }

            echo "<div class='notification success'>";
            echo "<i class='icon'>✅</i><h2>Zamówienie zostało złożone pomyślnie!</h2>";
            echo "<p>Produkt: {$produkt}</p>";
            echo "<p>Ilość: {$ilosc}</p>";
            echo "</div>";
        }
    } else {
        throw new Exception("Nie znaleziono produktu.");
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo "<div class='notification error'>";
    echo "<i class='icon'>❌</i><h2>Wystąpił błąd</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
    margin: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

.notification {
    max-width: 500px;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    margin: 20px;
    animation: fadeIn 0.5s ease;
}

.notification h2 {
    margin: 0 0 10px;
    font-size: 1.5rem;
}

.notification p {
    margin: 0;
    font-size: 1rem;
    color: #555;
}

.notification.success {
    background: #e0f8e9;
    color: #27ae60;
    border-left: 5px solid #2ecc71;
}

.notification.error {
    background: #fdecea;
    color: #e74c3c;
    border-left: 5px solid #e74c3c;
}

.notification.warning {
    background: #fff4e5;
    color: #e67e22;
    border-left: 5px solid #f39c12;
}

.icon {
    font-size: 2rem;
    margin-right: 10px;
    vertical-align: middle;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
