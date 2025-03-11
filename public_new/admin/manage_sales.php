<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

require '../includes/db.php';

// Pobieramy dane z bazy dla kategorii
$query_categories = "SELECT c.id, c.category_name, SUM(p.ilosc) as total_quantity 
                     FROM categories c 
                     LEFT JOIN produkty p ON c.id = p.category_id 
                     GROUP BY c.id, c.category_name";
$categories_result = mysqli_query($conn, $query_categories);

// Obsługa formularza
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $admin_username = $_SESSION['username'];
    $category_id = $_POST['category_id'];
    $flavor = $_POST['flavor'];
    $ilosc = intval($_POST['ilosc']);
    $price = floatval($_POST['price']);
    $buyer_name = $_POST['buyer_name'];

    // Walidacja danych
    if ($price <= 0) {
        $_SESSION['message'] = "Cena musi być liczbą dodatnią.";
        header("Location: manage_sales.php");
        exit();
    }

    if (is_numeric($buyer_name)) {
        $_SESSION['message'] = "Imię i nazwisko kupującego nie może być liczbą.";
        header("Location: manage_sales.php");
        exit();
    }

    // Pobieramy product_id, produkt_name i ilość
    $query_product = "SELECT id, nazwa, ilosc FROM produkty WHERE smak = '$flavor' AND category_id = $category_id";
    $product_result = mysqli_query($conn, $query_product);
    if ($product_result && mysqli_num_rows($product_result) > 0) {
        $product_row = mysqli_fetch_assoc($product_result);
        $product_id = $product_row['id'];
        $produkt_name = $product_row['nazwa'];
        $available_quantity = $product_row['ilosc'];
    } else {
        $product_id = null;
        $produkt_name = 'Nieznany produkt';
        $available_quantity = 0;
    }

    if ($ilosc > $available_quantity) {
        $_SESSION['message'] = "Nie można sprzedać więcej niż dostępna ilość w magazynie.";
        header("Location: manage_sales.php");
        exit();
    }

    // Rozpoczynamy transakcję
    mysqli_begin_transaction($conn);

    try {
        // Zapisujemy sprzedaż do tabeli sales
        $insert_sale = "INSERT INTO sales (admin_username, category_id, flavor, ilosc, price, buyer_name, product_id, produkt_name)
                        VALUES ('$admin_username', '$category_id', '$flavor', '$ilosc', '$price', '$buyer_name', '$product_id', '$produkt_name')";
        
        if (!mysqli_query($conn, $insert_sale)) {
            throw new Exception("Błąd przy dodawaniu sprzedaży: " . mysqli_error($conn));
        }

        // Aktualizujemy ilość w tabeli produkty
        $update_quantity = "UPDATE produkty 
                            SET ilosc = ilosc - $ilosc 
                            WHERE smak = '$flavor' AND category_id = $category_id AND ilosc >= $ilosc";

        if (!mysqli_query($conn, $update_quantity)) {
            throw new Exception("Błąd przy aktualizowaniu ilości: " . mysqli_error($conn));
        }

        // Sprawdzamy, czy zmniejszenie ilości było skuteczne
        if (mysqli_affected_rows($conn) == 0) {
            throw new Exception("Nie można zmniejszyć ilości produktu. Sprawdź dostępność.");
        }

        // Rejestracja zmiany w historii
        $changeDescription = "Sprzedaż: $ilosc sztuk smaku '$flavor' w cenie $price zł dla klienta '$buyer_name'";
        $historyStmt = $conn->prepare("INSERT INTO history (admin_username, change_description) VALUES (?, ?)");
        $historyStmt->bind_param("ss", $admin_username, $changeDescription);
        if (!$historyStmt->execute()) {
            throw new Exception("Błąd przy rejestrowaniu zmiany: " . $historyStmt->error);
        }

        // Zatwierdzamy transakcję
        mysqli_commit($conn);
        $_SESSION['message'] = "Sprzedaż została pomyślnie zarejestrowana i ilość produktu została zaktualizowana.";
        header("Location: manage_sales.php");
        exit();
    } catch (Exception $e) {
        // Wycofujemy transakcję w razie błędu
        mysqli_rollback($conn);
        $_SESSION['message'] = "Wystąpił błąd: " . $e->getMessage();
        header("Location: manage_sales.php");
        exit();
    }
}

// Pobranie listy sprzedaży
$query_sales = "SELECT s.id, s.admin_username, s.flavor, s.ilosc, s.price, s.sold_at, 
                       c.category_name, s.buyer_name
                FROM sales s
                JOIN categories c ON s.category_id = c.id
                ORDER BY s.sold_at DESC";
$sales_result = mysqli_query($conn, $query_sales);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/manage_sales.css">
    <link rel="stylesheet" href="../css/new.css">
    <title>Statystyki sprzedaży</title>
</head>
<body class="bg-light">
    <header class="container py-3">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand" href="manage_sales.php">Zarządzanie sprzedażą</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Przełącznik nawigacji">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="/index.php">Strona główna</a></li>
                        <li class="nav-item"><a class="nav-link" href="../dashboard/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="sales_statistics.php">Statystyki sprzedaży</a></li>
                        <li class="nav-item"><a class="nav-link" href="../auth/logout.php">Wyloguj się</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main>
        <?php if (isset($_SESSION['message'])): ?>
            <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="messageModalLabel"><?= $_SESSION['message_type'] == 'success' ? 'Sukces' : 'Błąd'; ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?= $_SESSION['message']; unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <section>
            <h2>Dodaj sprzedaż produktu</h2>
            <form method="POST" action="">
                <label for="category">Kategoria produktu:</label>
                <select name="category_id" id="category" required onchange="loadFlavors()">
                    <option value="" disabled selected>Wybierz kategorię</option>
                    <?php while ($category = mysqli_fetch_assoc($categories_result)) { ?>
                        <option value="<?= $category['id']; ?>"><?= $category['category_name']; ?> (<?= $category['total_quantity'] ?? 0; ?> dostępnych)</option>
                    <?php } ?>
                </select>

                <label for="flavor">Smak:</label>
                <select name="flavor" id="flavor" required>
                    <option value="" disabled selected>Wybierz smak</option>
                </select>

                <label for="ilosc">Ilość:</label>
                <input type="number" name="ilosc" id="ilosc" min="1" required>

                <label for="price">Cena:</label>
                <input type="text" name="price" id="price" required>

                <label for="buyer_name">Imię i nazwisko kupującego:</label>
                <input type="text" name="buyer_name" id="buyer_name" required>

                <button type="submit">Zarejestruj sprzedaż</button>
            </form>
        </section>

        <section>
            <h2>Historia sprzedaży</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sprzedawca</th>
                        <th>Kategoria</th>
                        <th>Smak</th>
                        <th>Ilość</th>
                        <th>Cena</th>
                        <th>Data sprzedaży</th>
                        <th>Klient</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($sales_result) > 0) { ?>
                        <?php while ($sale = mysqli_fetch_assoc($sales_result)) { ?>
                            <tr>
                                <td data-label="ID"><?= $sale['id']; ?></td>
                                <td data-label="Sprzedawca"><?= $sale['admin_username']; ?></td>
                                <td data-label="Kategoria"><?= $sale['category_name']; ?></td>
                                <td data-label="Smak"><?= $sale['flavor']; ?></td>
                                <td data-label="Ilość"><?= $sale['ilosc']; ?></td>
                                <td data-label="Cena"><?= number_format($sale['price'], 2); ?> zł</td>
                                <td data-label="Data sprzedaży"><?= $sale['sold_at']; ?></td>
                                <td data-label="Klient"><?= $sale['buyer_name']; ?></td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="8">Brak zarejestrowanych sprzedaży.</td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </section>
    </main>

    <script>
        function loadFlavors() {
            var categoryId = document.getElementById('category').value;
            if (categoryId) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'get_flavors.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState == 4 && xhr.status == 200) {
                        document.getElementById('flavor').innerHTML = xhr.responseText;
                    }
                };
                xhr.send('category_id=' + categoryId);
            } else {
                document.getElementById('flavor').innerHTML = '<option value="">Wybierz smak</option>';
            }
        }

        // Show modal if message exists
        <?php if (isset($_SESSION['message'])): ?>
            var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
            messageModal.show();
        <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>