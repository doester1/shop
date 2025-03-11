<?php
session_start();
require '../includes/db.php';

// Włącz wyświetlanie błędów
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Funkcja do edytowania produktu
$message = ""; // Zmienna do komunikatów
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_product'])) {
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $product_price = $_POST['product_price'];
    $product_quantity = $_POST['product_quantity'];
    $product_flavor = $_POST['product_flavor'];

    // Walidacja danych
    if (!empty($product_name) && is_numeric($product_price) && is_numeric($product_quantity)) {
        $stmt = $conn->prepare("UPDATE produkty SET nazwa=?, cena=?, ilosc=?, smak=? WHERE id=?");
        $stmt->bind_param("sdssi", $product_name, $product_price, $product_quantity, $product_flavor, $product_id);
        if ($stmt->execute()) {
            $message = "Produkt zaktualizowany pomyślnie!";

            // Rejestracja zmiany
            $history_stmt = $conn->prepare("INSERT INTO history (admin_username, change_description) VALUES (?, ?)");
            $change_description = "Edytowano produkt: " . htmlspecialchars($product_name) . ", Smak: " . htmlspecialchars($product_flavor);
            $history_stmt->bind_param("ss", $_SESSION['username'], $change_description);
            $history_stmt->execute();
        } else {
            $message = "Błąd podczas aktualizacji produktu: " . $stmt->error;
        }
    } else {
        $message = "Wszystkie pola muszą być wypełnione poprawnie!";
    }
}

// Pobierz dane produktów
$product_stmt = $conn->prepare("SELECT * FROM produkty");
$product_stmt->execute();
$products_result = $product_stmt->get_result();

// Pobierz dane produktu do edytowania
$product_id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM produkty WHERE id=?");
if (!$stmt) {
    die("Błąd w zapytaniu SQL: " . $conn->error);
}

$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

// Pobierz smaki dla wybranego produktu
$flavor_stmt = $conn->prepare("SELECT DISTINCT smak FROM produkty WHERE id=?");
$flavor_stmt->bind_param("i", $product_id);
$flavor_stmt->execute();
$flavors_result = $flavor_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/edit_product.css">
    <title>Edytuj produkt</title>
</head>
<body>
    <header>
        <h1>Edytuj produkt</h1>
        <nav>
            <ul>
                <li><a href="/index.php">Strona główna</a></li>
                <li><a href="../dashboard/dashboard.php">Dashboard</a></li>
                <li><a href="sales_statistics.php">Statystyki sprzedaży</a></li>
                <li><a href="../auth/logout.php">Wyloguj się</a></li>
            </ul>
        </nav>   
    </header>
    <main>
        <?php if ($message): ?>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form action="" method="POST">
            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
            
            <label for="product">Wybierz produkt:</label>
            <select name="product_id" id="product" required>
                <?php while ($row = $products_result->fetch_assoc()): ?>
                    <option value="<?php echo $row['id']; ?>" <?php if ($row['id'] == $product['id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($row['nazwa']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="product_name">Nazwa produktu:</label>
            <input type="text" name="product_name" value="<?php echo htmlspecialchars($product['nazwa']); ?>" required>
            
            <label for="product_price">Cena:</label>
            <input type="number" name="product_price" value="<?php echo htmlspecialchars($product['cena']); ?>" required step="0.01">
            
            <label for="product_quantity">Ilość:</label>
            <input type="number" name="product_quantity" value="<?php echo htmlspecialchars($product['ilosc']); ?>" required>
            
            <label for="product_flavor">Wybierz smak:</label>
            <select name="product_flavor" id="product_flavor" required>
                <?php while ($flavor_row = $flavors_result->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($flavor_row['smak']); ?>" <?php if ($flavor_row['smak'] == $product['smak']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($flavor_row['smak']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <button type="submit" name="edit_product">Zapisz zmiany</button>
        </form>
    </main>
</body>
</html>