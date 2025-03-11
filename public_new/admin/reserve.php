<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

require '../includes/db.php';

// Pobieramy dane z bazy dla kategorii
$query_categories = "SELECT * FROM categories";
$categories_result = mysqli_query($conn, $query_categories);

// Pobieramy admin_username z sesji
$admin_username = $_SESSION['username'];

// Obsługa formularza
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalize_reservation'])) {
    $reservations = json_decode($_POST['reservations'], true);
    $customer_name = $_POST['customer_name'];

    // Rozpoczynamy transakcję
    mysqli_begin_transaction($conn);

    try {
        foreach ($reservations as $reservation) {
            $category_id = $reservation['category_id'];
            $flavor = $reservation['flavor'];
            $quantity = intval($reservation['quantity']);

            // Pobieramy product_id
            $query_product = "SELECT id FROM produkty WHERE smak = '$flavor' AND category_id = $category_id";
            $product_result = mysqli_query($conn, $query_product);
            if ($product_result && mysqli_num_rows($product_result) > 0) {
                $product_row = mysqli_fetch_assoc($product_result);
                $product_id = $product_row['id'];
            } else {
                throw new Exception("Nie znaleziono produktu dla kategorii '$category_id' i smaku '$flavor'.");
            }

            // Zapisujemy rezerwację do tabeli reservations
            $insert_reservation = "INSERT INTO reservations (product_id, quantity, admin_username, customer_name)
                                   VALUES ('$product_id', '$quantity', '$admin_username', '$customer_name')";
            
            if (!mysqli_query($conn, $insert_reservation)) {
                throw new Exception("Błąd przy dodawaniu rezerwacji: " . mysqli_error($conn));
            }

            $reservation_id = mysqli_insert_id($conn);

            // Zapisujemy szczegóły rezerwacji do tabeli reservation_details
            $insert_reservation_details = "INSERT INTO reservation_details (reservation_id, product_id, quantity)
                                           VALUES ('$reservation_id', '$product_id', '$quantity')";
            
            if (!mysqli_query($conn, $insert_reservation_details)) {
                throw new Exception("Błąd przy dodawaniu szczegółów rezerwacji: " . mysqli_error($conn));
            }
        }

        // Zatwierdzamy transakcję
        mysqli_commit($conn);
        $_SESSION['message'] = "Rezerwacja została pomyślnie zarejestrowana.";
        header("Location: reserve.php");
        exit();
    } catch (Exception $e) {
        // Wycofujemy transakcję w razie błędu
        mysqli_rollback($conn);
        echo "<p>Wystąpił błąd: " . $e->getMessage() . "</p>";
    }
}

// Obsługa usuwania rezerwacji
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_reservation'])) {
    $reservation_id = intval($_POST['reservation_id']);

    // Rozpoczynamy transakcję
    mysqli_begin_transaction($conn);

    try {
        // Usuwamy szczegóły rezerwacji
        $delete_reservation_details = "DELETE FROM reservation_details WHERE reservation_id = $reservation_id";
        if (!mysqli_query($conn, $delete_reservation_details)) {
            throw new Exception("Błąd przy usuwaniu szczegółów rezerwacji: " . mysqli_error($conn));
        }

        // Usuwamy rezerwację
        $delete_reservation = "DELETE FROM reservations WHERE id = $reservation_id";
        if (!mysqli_query($conn, $delete_reservation)) {
            throw new Exception("Błąd przy usuwaniu rezerwacji: " . mysqli_error($conn));
        }

        // Zatwierdzamy transakcję
        mysqli_commit($conn);
        $_SESSION['message'] = "Rezerwacja została pomyślnie usunięta.";
        header("Location: reserve.php");
        exit();
    } catch (Exception $e) {
        // Wycofujemy transakcję w razie błędu
        mysqli_rollback($conn);
        echo "<p>Wystąpił błąd: " . $e->getMessage() . "</p>";
    }
}

// Pobranie listy rezerwacji
$query_reservations = "SELECT r.id, r.customer_name, r.quantity, r.reserved_at, 
                              c.category_name, p.smak AS flavor, r.admin_username
                       FROM reservations r
                       JOIN produkty p ON r.product_id = p.id
                       JOIN categories c ON p.category_id = c.id
                       ORDER BY r.reserved_at DESC";
$reservations_result = mysqli_query($conn, $query_reservations);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/reserve.css">
    <title>Rezerwacje</title>
</head>
<body class="bg-light">
    <header class="container py-3">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand" href="reserve.php">Zarządzanie rezerwacjami</a>
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
    <main class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success text-center"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>
        <section class="my-5">
            <h2>Dodaj rezerwację produktu</h2>
            <form id="reservationForm" class="bg-white p-4 rounded shadow-sm">
                <div class="mb-3">
                    <label for="category" class="form-label">Kategoria produktu:</label>
                    <select name="category_id" id="category" class="form-select" required onchange="loadFlavors()">
                        <option value="" disabled selected>Wybierz kategorię</option>
                        <?php while ($category = mysqli_fetch_assoc($categories_result)) { ?>
                            <option value="<?= $category['id']; ?>"><?= $category['category_name']; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="flavor" class="form-label">Smak:</label>
                    <select name="flavor" id="flavor" class="form-select" required>
                        <option value="" disabled selected>Wybierz smak</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="quantity" class="form-label">Ilość:</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                </div>
                <button type="button" class="btn btn-primary w-100" onclick="addToReservation()">Dodaj do rezerwacji</button>
            </form>
        </section>

        <section class="my-5">
            <h2>Podgląd rezerwacji</h2>
            <div id="reservationPreview" class="reservation-preview"></div>
            <form method="POST" action="" class="bg-white p-4 rounded shadow-sm">
                <input type="hidden" name="reservations" id="reservationsInput">
                <div class="mb-3">
                    <label for="customer_name" class="form-label">Imię i nazwisko klienta:</label>
                    <input type="text" name="customer_name" id="customer_name" class="form-control" required>
                </div>
                <button type="submit" name="finalize_reservation" class="btn btn-success w-100">Zatwierdź rezerwację</button>
            </form>
        </section>

        <section class="my-5">
            <h2>Historia rezerwacji</h2>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Klient</th>
                            <th>Kategoria</th>
                            <th>Smak</th>
                            <th>Ilość</th>
                            <th>Data rezerwacji</th>
                            <th>Administrator</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($reservations_result) > 0) { ?>
                            <?php while ($reservation = mysqli_fetch_assoc($reservations_result)) { ?>
                                <tr>
                                    <td><?= $reservation['id']; ?></td>
                                    <td><?= $reservation['customer_name']; ?></td>
                                    <td><?= $reservation['category_name']; ?></td>
                                    <td><?= $reservation['flavor']; ?></td>
                                    <td><?= $reservation['quantity']; ?></td>
                                    <td><?= $reservation['reserved_at']; ?></td>
                                    <td><?= $reservation['admin_username']; ?></td>
                                    <td>
                                        <form method="POST" action="" onsubmit="return confirm('Czy na pewno chcesz usunąć tę rezerwację?');">
                                            <input type="hidden" name="reservation_id" value="<?= $reservation['id']; ?>">
                                            <button type="submit" name="delete_reservation" class="btn btn-danger">Usuń</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="8" class="text-center">Brak zarejestrowanych rezerwacji.</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let reservations = [];

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

        function addToReservation() {
            const categorySelect = document.getElementById('category');
            const flavorSelect = document.getElementById('flavor');
            const quantityInput = document.getElementById('quantity');

            const category_id = categorySelect.value;
            const category_name = categorySelect.options[categorySelect.selectedIndex].text;
            const flavor = flavorSelect.value;
            const quantity = quantityInput.value;

            if (category_id && flavor && quantity) {
                reservations.push({ category_id, category_name, flavor, quantity });
                updateReservationPreview();
            }
        }

        function updateReservationPreview() {
            const reservationPreview = document.getElementById('reservationPreview');
            reservationPreview.innerHTML = '';

            reservations.forEach((reservation, index) => {
                const div = document.createElement('div');
                div.className = 'reservation-item';
                div.innerHTML = `
                    <p><strong>Kategoria:</strong> ${reservation.category_name}</p>
                    <p><strong>Smak:</strong> ${reservation.flavor}</p>
                    <p><strong>Ilość:</strong> ${reservation.quantity}</p>
                    <button type="button" class="btn btn-danger" onclick="removeFromReservation(${index})">Usuń</button>
                `;
                reservationPreview.appendChild(div);
            });

            document.getElementById('reservationsInput').value = JSON.stringify(reservations);
        }

        function removeFromReservation(index) {
            reservations.splice(index, 1);
            updateReservationPreview();
        }
    </script>
</body>
</html>
