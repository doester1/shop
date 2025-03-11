<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

require '../includes/db.php';

// Ustawienie kodowania znaków
mysqli_set_charset($conn, "utf8mb4");

// Pobieramy dane z bazy
$query_categories = "SELECT * FROM categories";
$categories_result = mysqli_query($conn, $query_categories);
if (!$categories_result) {
    die("Błąd pobierania kategorii: " . mysqli_error($conn));
}

// Obsługa formularza
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_username = mysqli_real_escape_string($conn, $_SESSION['username']);
    $category_id = mysqli_real_escape_string($conn, $_POST['category_id']);
    $flavor = mysqli_real_escape_string($conn, $_POST['flavor']);
    $ilosc = (int)$_POST['ilosc'];
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $buyer_name = mysqli_real_escape_string($conn, $_POST['buyer_name']);

    // Zapis do bazy danych
    $insert_sale = "INSERT INTO sales (admin_username, category_id, flavor, ilosc, price, buyer_name)
                    VALUES ('$admin_username', '$category_id', '$flavor', '$ilosc', '$price', '$buyer_name')";
    
    if (mysqli_query($conn, $insert_sale)) {
        $success_message = "Sprzedaż została pomyślnie zarejestrowana.";
    } else {
        $error_message = "Wystąpił błąd przy dodawaniu sprzedaży: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dodaj sprzedaż</title>
    <link rel="stylesheet" href="styles.css"> <!-- Dodaj swój plik CSS -->
</head>
<body>
    <h1>Dodaj sprzedaż produktu</h1>

    <?php if (isset($success_message)): ?>
        <p class="success"><?= htmlspecialchars($success_message); ?></p>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <p class="error"><?= htmlspecialchars($error_message); ?></p>
    <?php endif; ?>

    <form method="POST" action="add_sale.php">
        <label for="category">Kategoria produktu:</label>
        <select name="category_id" id="category" required onchange="loadFlavors()">
        <option value="" disabled selected>Wybierz kategorię</option>
            <?php while ($category = mysqli_fetch_assoc($categories_result)) { ?>
                <option value="<?= htmlspecialchars($category['id']); ?>">
                    <?= htmlspecialchars($category['category_name']); ?>
                </option>
            <?php } ?>
        </select>

        <label for="flavor">Smak:</label>
        <select name="flavor" id="flavor" required>
        <option value="" disabled selected>Wybierz smak</option>
        </select>

        <label for="ilosc">Ilość:</label>
        <input type="number" name="ilosc" id="ilosc" min="1" required>

        <label for="price">Cena (PLN):</label>
        <input type="text" name="price" id="price" pattern="\d+(\.\d{1,2})?" placeholder="np. 19.99" required>

        <label for="buyer_name">Imię i nazwisko kupującego:</label>
        <input type="text" name="buyer_name" id="buyer_name" required>

        <button type="submit">Zarejestruj sprzedaż</button>
    </form>

    <script>
        // Funkcja do ładowania smaków po wybraniu kategorii
        function loadFlavors() {
            var categoryId = document.getElementById('category').value;
            if (categoryId) {
                // Tworzymy zapytanie AJAX do pobrania smaków
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'get_flavors.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState == 4 && xhr.status == 200) {
                        document.getElementById('flavor').innerHTML = xhr.responseText;
                    }
                };
                xhr.send('category_id=' + encodeURIComponent(categoryId));
            } else {
                document.getElementById('flavor').innerHTML = '<option value="">Wybierz smak</option>';
            }
        }
    </script>
</body>
</html>
