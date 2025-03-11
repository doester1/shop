<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Pobieranie kategorii
$categoriesQuery = $conn->query("SELECT * FROM categories");
$categories = $categoriesQuery->fetch_all(MYSQLI_ASSOC);

// Pobieranie smaków dla wybranej kategorii
$flavors = [];
if (isset($_POST['category_id'])) {
    $categoryId = intval($_POST['category_id']);
    $flavorsQuery = $conn->prepare("SELECT DISTINCT smak FROM produkty WHERE category_id = ?");
    $flavorsQuery->bind_param("i", $categoryId);
    $flavorsQuery->execute();
    $flavorsResult = $flavorsQuery->get_result();
    $flavors = $flavorsResult->fetch_all(MYSQLI_ASSOC);
}

// Dodanie produktu do tymczasowego magazynu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $nazwa = $_POST['nazwa'];
    $cena = $_POST['cena'];
    $smak = $_POST['smak'];
    $poziom_nikotyny = $_POST['poziom_nikotyny'];
    $ilosc = $_POST['ilosc'];
    $zdjecie = $_POST['zdjecie'];
    $category_id = $_POST['category_id'];

    // Dodanie do tymczasowego magazynu
    $insertQuery = $conn->prepare(
        "INSERT INTO produkty_temp (nazwa, cena, smak, poziom_nikotyny, ilosc, zdjecie, category_id) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $insertQuery->bind_param("ssdiiis", $nazwa, $cena, $smak, $poziom_nikotyny, $ilosc, $zdjecie, $category_id);
    $insertQuery->execute();

    // Przekierowanie po dodaniu produktu
    header("Location: manage_products.php");
    exit();
}

// Przeniesienie produktów z tymczasowego magazynu do głównej tabeli
if (isset($_POST['transfer_to_store'])) {
    // Przeniesienie do głównej tabeli
    $transferQuery = $conn->prepare(
        "INSERT INTO produkty (nazwa, cena, smak, poziom_nikotyny, ilosc, zdjecie, category_id)
         SELECT nazwa, cena, smak, poziom_nikotyny, ilosc, zdjecie, category_id FROM produkty_temp"
    );
    $transferQuery->execute();

    // Usuwanie produktów z tymczasowego magazynu
    $deleteQuery = $conn->query("DELETE FROM produkty_temp");

    header("Location: manage_products.php");
    exit();
}

// Pobieranie produktów z tymczasowego magazynu
$tempProductsQuery = $conn->query("SELECT * FROM produkty_temp");
$tempProducts = $tempProductsQuery->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dodaj Produkt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <header class="container py-3">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand" href="dashboard.php">Panel Administratora</a>
            </div>
        </nav>
    </header>

    <main class="container mt-4">
        <h1>Dodaj Nowy Produkt</h1>

        <form method="POST" class="mt-4">
            <!-- Kategoria produktu -->
            <div class="mb-3">
                <label for="category_id" class="form-label">Wybierz Kategorię</label>
                <select id="category_id" name="category_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Wybierz kategorię</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo isset($_POST['category_id']) && $_POST['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Smak produktu -->
            <div class="mb-3">
                <label for="smak" class="form-label">Smak</label>
                <select id="smak" name="smak" class="form-select">
                    <option value="">Wybierz smak</option>
                    <?php foreach ($flavors as $flavor): ?>
                        <option value="<?php echo $flavor['smak']; ?>">
                            <?php echo htmlspecialchars($flavor['smak']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Pozostałe dane produktu -->
            <div class="mb-3">
                <label for="cena" class="form-label">Cena</label>
                <input type="number" step="0.01" class="form-control" id="cena" name="cena" required>
            </div>

            <div class="mb-3">
                <label for="ilosc" class="form-label">Ilość</label>
                <input type="number" class="form-control" id="ilosc" name="ilosc" required>
            </div>

            <div class="mb-3">
                <label for="poziom_nikotyny" class="form-label">Poziom Nikotyny</label>
                <input type="number" class="form-control" id="poziom_nikotyny" name="poziom_nikotyny" required>
            </div>

            <div class="mb-3">
                <label for="zdjecie" class="form-label">Link do Zdjęcia</label>
                <input type="text" class="form-control" id="zdjecie" name="zdjecie">
            </div>

            <button type="submit" name="add_product" class="btn btn-primary">Dodaj Produkt</button>
        </form>

        <h2 class="mt-4">Produkty w Tymczasowym Magazynie</h2>
        <table class="table mt-3">
            <thead>
                <tr>
                    <th>Produkt</th>
                    <th>Smak</th>
                    <th>Cena</th>
                    <th>Ilość</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tempProducts as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['nazwa']); ?></td>
                        <td><?php echo htmlspecialchars($product['smak']); ?></td>
                        <td><?php echo htmlspecialchars($product['cena']); ?></td>
                        <td><?php echo htmlspecialchars($product['ilosc']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <form method="POST">
            <button type="submit" name="transfer_to_store" class="btn btn-success">Przenieś do Sklepu</button>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
