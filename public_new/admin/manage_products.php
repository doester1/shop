<?php
session_start();
require '../includes/db.php';

// Sprawdzenie, czy użytkownik jest zalogowany
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Funkcja do dodawania produktów
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $category_id = $_POST['product_category'];
    $smak_produktu = $_POST['product_flavor'];
    $poziom_nikotyny = $_POST['nicotine_strength'];
    $cena_produktu = $_POST['product_price'];
    $ilosc_produktu = $_POST['product_quantity'];

    // Pobranie nazwy kategorii z tabeli `categories`
    $stmt_category_name = $conn->prepare("SELECT category_name FROM categories WHERE id = ?");
    $stmt_category_name->bind_param("i", $category_id);
    $stmt_category_name->execute();
    $result_category = $stmt_category_name->get_result();
    $category = $result_category->fetch_assoc();
    $nazwa_produktu = $category['category_name'];

    // Obsługa zdjęcia
    $target_dir = "../uploads/";
    $image_name = basename($_FILES["product_image"]["name"]);
    $target_file = $target_dir . $image_name;

    // Sprawdzenie typu pliku
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($_FILES["product_image"]["type"], $allowed_types)) {
        $_SESSION['message'] = "Dozwolone są tylko pliki JPEG, PNG i GIF.";
        header("Location: manage_product.php");
        exit();
    }

    // Przesunięcie pliku do katalogu
    if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
        // Sprawdzenie, czy produkt już istnieje
        $stmt = $conn->prepare("SELECT * FROM produkty WHERE nazwa=? AND smak=? AND poziom_nikotyny=?");
        $stmt->bind_param("ssi", $nazwa_produktu, $smak_produktu, $poziom_nikotyny);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Produkt już istnieje, aktualizujemy ilość
            $row = $result->fetch_assoc();
            $new_quantity = $row['ilosc'] + $ilosc_produktu; // Zwiększamy ilość
            $stmt = $conn->prepare("UPDATE produkty SET ilosc=? WHERE id=?");
            $stmt->bind_param("ii", $new_quantity, $row['id']);
            $stmt->execute();

            if ($stmt->error) {
                $_SESSION['message'] = "Błąd podczas aktualizacji: " . $stmt->error;
            } else {
                $_SESSION['message'] = "Ilość produktu została zaktualizowana.";

                // Rejestracja zmiany
                $history_stmt = $conn->prepare("INSERT INTO history (admin_username, change_description) VALUES (?, ?)");
                $change_description = "Zaktualizowano ilość produktu: " . htmlspecialchars($nazwa_produktu);
                $history_stmt->bind_param("ss", $_SESSION['username'], $change_description);
                $history_stmt->execute();
            }
        } else {
            // Produkt nie istnieje, dodajemy nowy
            $stmt = $conn->prepare("INSERT INTO produkty (nazwa, smak, poziom_nikotyny, cena, ilosc, zdjecie) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssddis", $nazwa_produktu, $smak_produktu, $poziom_nikotyny, $cena_produktu, $ilosc_produktu, $image_name);
            $stmt->execute();

            if ($stmt->error) {
                $_SESSION['message'] = "Błąd podczas dodawania produktu: " . $stmt->error;
            } else {
                $_SESSION['message'] = "Produkt dodany pomyślnie.";

                // Rejestracja zmiany
                $history_stmt = $conn->prepare("INSERT INTO history (admin_username, change_description) VALUES (?, ?)");
                $change_description = "Dodano nowy produkt: " . htmlspecialchars($nazwa_produktu);
                $history_stmt->bind_param("ss", $_SESSION['username'], $change_description);
                $history_stmt->execute();
            }
        }
    } else {
        $_SESSION['message'] = "Błąd podczas przesyłania zdjęcia.";
    }

    // Redirect to avoid form resubmission
    header("Location: manage_products.php");
    exit();
}

// Pobranie kategorii z bazy danych
$stmt_categories = $conn->prepare("SELECT id, category_name FROM categories");
$stmt_categories->execute();
$result_categories = $stmt_categories->get_result();
$categories = [];
while ($row = $result_categories->fetch_assoc()) {
    $categories[] = $row;
}

// Funkcja do usuwania produktów
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $product_id = $_GET['id'];

    $stmt = $conn->prepare("DELETE FROM produkty WHERE id=?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    if ($stmt->error) {
        $_SESSION['message'] = "Błąd podczas usuwania produktu: " . $stmt->error;
    } else {
        $_SESSION['message'] = "Produkt został pomyślnie usunięty.";

        // Rejestracja zmiany
        $history_stmt = $conn->prepare("INSERT INTO history (admin_username, change_description) VALUES (?, ?)");
        $change_description = "Usunięto produkt o ID: " . $product_id;
        $history_stmt->bind_param("ss", $_SESSION['username'], $change_description);
        $history_stmt->execute();
    }
}

// Funkcja do pobierania produktów
$stmt = $conn->prepare("SELECT * FROM produkty");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/manage_products.css">
    <title>Zarządzaj produktami</title>
    <style>
        /* Ogólny styl dla przycisków */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.3s;
        }

        /* Styl dla przycisku Edytuj */
        .btn-edit {
            background-color: #4CAF50; /* Zielony kolor */
            color: white;
            border: 2px solid #4CAF50;
        }

        .btn-edit:hover {
            background-color: #45a049; /* Zmiana koloru na ciemniejszy zielony przy najechaniu */
            border: 2px solid #45a049;
            transform: scale(1.05); /* Powiększenie przy najechaniu */
        }

        /* Styl dla przycisku Usuń */
        .btn-delete {
            background-color: #f44336; /* Czerwony kolor */
            color: white;
            border: 2px solid #f44336;
        }

        .btn-delete:hover {
            background-color: #e53935; /* Zmiana koloru na ciemniejszy czerwony przy najechaniu */
            border: 2px solid #e53935;
            transform: scale(1.05); /* Powiększenie przy najechaniu */
        }
    </style>
</head>
<body>
    <header>
        <h1>Zarządzaj produktami</h1>
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
        <?php if (isset($_SESSION['message'])): ?>
            <div class="notification">
                <?php
                echo $_SESSION['message'];
                unset($_SESSION['message']); // Usunięcie powiadomienia po wyświetleniu
                ?>
            </div>
        <?php endif; ?>

        <h2>Dodaj nowy produkt</h2>
        <form action="" method="POST" enctype="multipart/form-data" class="product-form">
            <label for="product_category">Kategoria:</label>
            <select name="product_category" id="product_category" required>
                <option value="">Wybierz kategorię</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>">
                        <?php echo htmlspecialchars($category['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="product_flavor">Smak:</label>
            <input type="text" name="product_flavor" id="product_flavor" placeholder="Smak" required>

            <label for="nicotine_strength">Moc nikotyny:</label>
            <select name="nicotine_strength" id="nicotine_strength" required>
                <option value="2.0">2%</option>
                <option value="3.0">3%</option>
                <option value="4.0">4%</option>
                <option value="5.0">5%</option>
            </select>

            <label for="product_price">Cena:</label>
            <input type="number" name="product_price" id="product_price" placeholder="Cena" step="0.01" required>

            <label for="product_quantity">Ilość:</label>
            <input type="number" name="product_quantity" id="product_quantity" placeholder="Ilość" required>

            <label for="product_image">Zdjęcie produktu:</label>
            <input type="file" name="product_image" id="product_image" required>

            <button type="submit" name="add_product">Dodaj produkt</button>
        </form>

        <h2>Lista produktów</h2>
        <table class="product-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nazwa</th>
                    <th>Smak</th>
                    <th>Moc Nikotyny</th>
                    <th>Cena</th>
                    <th>Ilość</th>
                    <th>Zdjęcie</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['nazwa']); ?></td>
                    <td><?php echo htmlspecialchars($row['smak']); ?></td>
                    <td><?php echo htmlspecialchars($row['poziom_nikotyny']); ?>%</td>
                    <td><?php echo htmlspecialchars($row['cena']); ?> zł</td>
                    <td><?php echo htmlspecialchars($row['ilosc']); ?></td>
                    <td><img src="../uploads/<?php echo htmlspecialchars($row['zdjecie']); ?>" alt="<?php echo htmlspecialchars($row['nazwa']); ?>" width="100"></td>
                    <td>
                        <a href="edit_product.php?id=<?php echo $row['id']; ?>" class="btn btn-edit">Edytuj</a>
                        <a href="delete_product.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-delete" onclick="return confirm('Czy na pewno chcesz usunąć ten produkt?');">Usuń</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </main>
</body>
</html>