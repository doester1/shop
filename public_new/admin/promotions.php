<?php
session_start();
require '../includes/db.php';


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Sprawdzenie połączenia
if ($conn->connect_error) {
    die("Połączenie nieudane: " . $conn->connect_error);
}

// Usuwanie wygasłych promocji
$sql_delete_expired = "DELETE FROM promotions WHERE typ_promo = 'czasowa' AND data_zakonczenia IS NOT NULL AND data_zakonczenia < NOW()";
$conn->query($sql_delete_expired);

// Obsługa dodawania promocji
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_promo'])) {
    $typ_promo = $_POST['typ_promo'];
    $produkt_id = $_POST['produkt_id'];
    $kategoria_id = $_POST['kategoria_id'];
    $smak = $_POST['smak'];
    $rabat = $_POST['rabat'];
    $dni = (int)$_POST['dni'];
    $godziny = (int)$_POST['godziny'];
    $minuty = (int)$_POST['minuty'];
    $sekundy = (int)$_POST['sekundy'];

    // Pobranie ceny produktu
    $sql_get_price = "SELECT cena FROM produkty WHERE id='$produkt_id'";
    $result_price = $conn->query($sql_get_price);
    $row_price = $result_price->fetch_assoc();
    $cena = $row_price['cena'];

    // Obliczenie ceny promocyjnej
    $cena_promocyjna = $cena - ($cena * ($rabat / 100));

    // Ustawienie dat na NULL dla promocji na smak i kategorię
    if ($typ_promo == 'smak' || $typ_promo == 'kategoria') {
        $data_rozpoczecia = 'NULL';
        $data_zakonczenia = 'NULL';
    } else {
        $data_rozpoczecia = "'" . date('Y-m-d H:i:s') . "'";
        $czas_trwania = ($dni * 86400) + ($godziny * 3600) + ($minuty * 60) + $sekundy;
        $data_zakonczenia = "'" . date('Y-m-d H:i:s', strtotime("+$czas_trwania seconds")) . "'";
    }

    $sql_insert_promo = "INSERT INTO promotions (typ_promo, produkt_id, kategoria_id, smak, rabat, data_rozpoczecia, data_zakonczenia, cena_promocyjna)
                         VALUES ('$typ_promo', '$produkt_id', '$kategoria_id', '$smak', '$rabat', $data_rozpoczecia, $data_zakonczenia, '$cena_promocyjna')";

    if ($conn->query($sql_insert_promo) === TRUE) {
        echo "<div class='alert alert-success' role='alert'>Promocja została dodana pomyślnie!</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "<div class='alert alert-danger' role='alert'>Błąd: " . $conn->error . "</div>";
    }
}

// Pobranie danych z tabeli `produkty`
$sql = "SELECT * FROM produkty";
$result = $conn->query($sql);

// Pobranie obecnych promocji
$sql_promocje = "SELECT promotions.*, produkty.zdjecie FROM promotions LEFT JOIN produkty ON promotions.produkt_id = produkty.id";
$result_promocje = $conn->query($sql_promocje);

// Obsługa edycji promocji
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_promo'])) {
    $promo_id = $_POST['promo_id'];
    $typ_promo = $_POST['typ_promo'];
    $produkt_id = $_POST['produkt_id'];
    $kategoria_id = $_POST['kategoria_id'];
    $smak = $_POST['smak'];
    $rabat = $_POST['rabat'];
    $dni = (int)$_POST['dni'];
    $godziny = (int)$_POST['godziny'];
    $minuty = (int)$_POST['minuty'];
    $sekundy = (int)$_POST['sekundy'];

    // Pobranie ceny produktu
    $sql_get_price = "SELECT cena FROM produkty WHERE id='$produkt_id'";
    $result_price = $conn->query($sql_get_price);
    $row_price = $result_price->fetch_assoc();
    $cena = $row_price['cena'];

    // Obliczenie ceny promocyjnej
    $cena_promocyjna = $cena - ($cena * ($rabat / 100));

    // Ustawienie dat na NULL dla promocji na smak i kategorię
    if ($typ_promo == 'smak' || $typ_promo == 'kategoria') {
        $data_rozpoczecia = 'NULL';
        $data_zakonczenia = 'NULL';
    } else {
        $data_rozpoczecia = "'" . date('Y-m-d H:i:s') . "'";
        $czas_trwania = ($dni * 86400) + ($godziny * 3600) + ($minuty * 60) + $sekundy;
        $data_zakonczenia = "'" . date('Y-m-d H:i:s', strtotime("+$czas_trwania seconds")) . "'";
    }

    $sql_update_promo = "UPDATE promotions 
                         SET typ_promo='$typ_promo', produkt_id='$produkt_id', kategoria_id='$kategoria_id', smak='$smak', rabat='$rabat', data_rozpoczecia=$data_rozpoczecia, data_zakonczenia=$data_zakonczenia, cena_promocyjna='$cena_promocyjna' 
                         WHERE id='$promo_id'";

    if ($conn->query($sql_update_promo) === TRUE) {
        echo "<div class='alert alert-success' role='alert'>Promocja została zaktualizowana pomyślnie!</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "<div class='alert alert-danger' role='alert'>Błąd: " . $conn->error . "</div>";
    }
}

// Obsługa usuwania promocji
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_promo'])) {
    $promo_id = $_POST['promo_id'];

    $sql_delete_promo = "DELETE FROM promotions WHERE id='$promo_id'";

    if ($conn->query($sql_delete_promo) === TRUE) {
        echo "<div class='alert alert-success' role='alert'>Promocja została usunięta pomyślnie!</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "<div class='alert alert-danger' role='alert'>Błąd: " . $conn->error . "</div>";
    }
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produkty i Promocje</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/promocje.css">
</head>
<body>
    <header class="navbar">
        <a href="#" class="navbar-brand">Admin Panel</a>
        <div class="hamburger" onclick="toggleMenu()">
            <div></div>
            <div></div>
            <div></div>
        </div>
        <ul>
            <li><a href="/index.php">Strona główna</a></li>
            <li><a href="../dashboard/dashboard.php">Dashboard</a></li>
            <li><a href="manage_products.php">Zarządzaj produktami</a></li>
            <li><a href="../auth/logout.php">Wyloguj się</a></li>
        </ul>
    </header>
    <div class="container mt-5">
        <h1 class="mb-4">Lista produktów</h1>
        <div class="table-responsive">
            <?php
            if ($result->num_rows > 0) {
                echo "<table class='table table-striped'>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nazwa</th>
                                <th>Cena</th>
                                <th>Smak</th>
                                <th>Poziom nikotyny</th>
                                <th>Ilość</th>
                                <th>Zdjęcie</th>
                                <th>Data dodania</th>
                                <th>Kategoria</th>
                            </tr>
                        </thead>
                        <tbody>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                            <td>" . $row['id'] . "</td>
                            <td>" . htmlspecialchars($row['nazwa']) . "</td>
                            <td>" . number_format($row['cena'], 2, ',', ' ') . " PLN</td>
                            <td>" . htmlspecialchars($row['smak']) . "</td>
                            <td>" . ($row['poziom_nikotyny'] ? $row['poziom_nikotyny'] . "%" : "Brak") . "</td>
                            <td>" . $row['ilosc'] . "</td>
                            <td>" . ($row['zdjecie'] ? "<img src='" . htmlspecialchars($row['zdjecie']) . "' alt='Zdjęcie' class='img-thumbnail' width='100'>" : "Brak zdjęcia") . "</td>
                            <td>" . $row['data_dodania'] . "</td>
                            <td>" . ($row['category_id'] ? $row['category_id'] : "Brak kategorii") . "</td>
                        </tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<div class='alert alert-warning'>Brak produktów do wyświetlenia.</div>";
            }
            ?>
        </div>

        <h2 class="mt-5">Dodaj promocję</h2>
        <button class="btn btn-primary" onclick="openModal('add')">Dodaj promocję</button>

        <h2 class="mt-5">Obecne promocje</h2>
        <div class="table-responsive">
            <?php
            if ($result_promocje->num_rows > 0) {
                echo "<table class='table table-hover'>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Typ promocji</th>
                                <th>ID produktu</th>
                                <th>ID kategorii</th>
                                <th>Smak</th>
                                <th>Rabat</th>
                                <th>Data rozpoczęcia</th>
                                <th>Data zakończenia</th>
                                <th>Cena promocyjna</th>
                                <th>Zdjęcie</th>
                                <th>Akcja</th>
                            </tr>
                        </thead>
                        <tbody>";
                while ($promo = $result_promocje->fetch_assoc()) {
                    echo "<tr>
                            <td>" . $promo['id'] . "</td>
                            <td>" . htmlspecialchars($promo['typ_promo']) . "</td>
                            <td>" . $promo['produkt_id'] . "</td>
                            <td>" . $promo['kategoria_id'] . "</td>
                            <td>" . htmlspecialchars($promo['smak']) . "</td>
                            <td>" . $promo['rabat'] . "%</td>
                            <td>" . $promo['data_rozpoczecia'] . "</td>
                            <td>" . $promo['data_zakonczenia'] . "</td>
                            <td>" . number_format($promo['cena_promocyjna'], 2, ',', ' ') . " PLN</td>
                            <td>" . ($promo['zdjecie'] ? "<img src='" . htmlspecialchars($promo['zdjecie']) . "' alt='Zdjęcie' class='img-thumbnail' width='100'>" : "Brak zdjęcia") . "</td>
                            <td>
                                <form method='POST' action='' style='display:inline-block;'>
                                    <input type='hidden' name='promo_id' value='" . $promo['id'] . "'>
                                    <button type='submit' name='edit_promo' class='btn btn-warning btn-sm'>Edytuj</button>
                                </form>
                                <form method='POST' action='' style='display:inline-block;'>
                                    <input type='hidden' name='promo_id' value='" . $promo['id'] . "'>
                                    <button type='submit' name='delete_promo' class='btn btn-danger btn-sm'>Usuń</button>
                                </form>
                            </td>
                        </tr>";
                }
                echo "</tbody></table>";
            } else {
                echo "<div class='alert alert-info'>Brak promocji do wyświetlenia.</div>";
            }
            ?>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="promoModal" tabindex="-1" aria-labelledby="promoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="promoForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="promoModalLabel">Dodaj promocję</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="promo_id" id="promo_id">
                        <div class="mb-3">
                            <label for="typ_promo" class="form-label">Typ promocji</label>
                            <select name="typ_promo" id="typ_promo" class="form-select" onchange="toggleTimeFields()">
                                <option value="czasowa">Promocja czasowa</option>
                                <option value="kategoria" disabled selected>Promocja na kategorię (NIEDZIAŁA)</option>
                                <option value="smak">Promocja na smak</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="produkt_id" class="form-label">ID produktu (opcjonalne)</label>
                            <input type="text" id="produkt_id" name="produkt_id" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="kategoria_id" class="form-label">ID kategorii (opcjonalne)</label>
                            <input type="text" id="kategoria_id" name="kategoria_id" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="smak" class="form-label">Smak (opcjonalne)</label>
                            <input type="text" id="smak" name="smak" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="rabat" class="form-label">Rabat (%)</label>
                            <input type="text" id="rabat" name="rabat" class="form-control">
                        </div>
                        <div id="czas_trwania_fields" class="mb-3">
                            <label for="dni" class="form-label">Dni</label>
                            <input type="number" id="dni" name="dni" class="form-control">
                            <label for="godziny" class="form-label">Godziny</label>
                            <input type="number" id="godziny" name="godziny" class="form-control">
                            <label for="minuty" class="form-label">Minuty</label>
                            <input type="number" id="minuty" name="minuty" class="form-control">
                            <label for="sekundy" class="form-label">Sekundy</label>
                            <input type="number" id="sekundy" name="sekundy" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Zamknij</button>
                        <button type="submit" name="add_promo" class="btn btn-primary">Zapisz</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleMenu() {
            var menu = document.querySelector('.navbar ul');
            var hamburger = document.querySelector('.hamburger');
            menu.classList.toggle('active');
            hamburger.classList.toggle('active');
        }

        function openModal(action, promo = {}) {
            const modalLabel = document.getElementById('promoModalLabel');
            const promoForm = document.getElementById('promoForm');
            const promoId = document.getElementById('promo_id');
            const typPromo = document.getElementById('typ_promo');
            const produktId = document.getElementById('produkt_id');
            const kategoriaId = document.getElementById('kategoria_id');
            const smak = document.getElementById('smak');
            const rabat = document.getElementById('rabat');
            const dni = document.getElementById('dni');
            const godziny = document.getElementById('godziny');
            const minuty = document.getElementById('minuty');
            const sekundy = document.getElementById('sekundy');

            if (action === 'add') {
                modalLabel.textContent = 'Dodaj promocję';
                promoForm.action = '';
                promoId.value = '';
                typPromo.value = 'czasowa';
                produktId.value = '';
                kategoriaId.value = '';
                smak.value = '';
                rabat.value = '';
                dni.value = '';
                godziny.value = '';
                minuty.value = '';
                sekundy.value = '';
            } else if (action === 'edit') {
                modalLabel.textContent = 'Edytuj promocję';
                promoForm.action = '';
                promoId.value = promo.id;
                typPromo.value = promo.typ_promo;
                produktId.value = promo.produkt_id;
                kategoriaId.value = promo.kategoria_id;
                smak.value = promo.smak;
                rabat.value = promo.rabat;
                dni.value = promo.dni;
                godziny.value = promo.godziny;
                minuty.value = promo.minuty;
                sekundy.value = promo.sekundy;
            }

            toggleTimeFields();
            const promoModal = new bootstrap.Modal(document.getElementById('promoModal'));
            promoModal.show();
        }

        function toggleTimeFields() {
            const typPromo = document.getElementById('typ_promo').value;
            const czasTrwaniaFields = document.getElementById('czas_trwania_fields');
            if (typPromo === 'czasowa') {
                czasTrwaniaFields.style.display = 'block';
            } else {
                czasTrwaniaFields.style.display = 'none';
            }
        }

        function updateCountdown() {
            const countdownElements = document.querySelectorAll('.countdown');
            countdownElements.forEach(element => {
                const endTime = new Date(element.getAttribute('data-end-time')).getTime();
                const now = new Date().getTime();
                const distance = endTime - now;

                if (distance < 0) {
                    element.innerHTML = "Promocja zakończona";
                    // Usunięcie promocji po zakończeniu
                    const promoId = element.getAttribute('data-promo-id');
                    fetch(`delete_promo.php?id=${promoId}`, { method: 'GET' })
                        .then(response => response.text())
                        .then(data => {
                            if (data === 'success') {
                                element.closest('tr').remove();
                            }
                        });
                } else {
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    element.innerHTML = `${days}d ${hours}h ${minutes}m ${seconds}s`;
                }
            });
        }

        function checkExpiredPromotions() {
            fetch('delete_expired_promos.php', { method: 'GET' })
                .then(response => response.text())
                .then(data => {
                    if (data === 'success') {
                        location.reload(); // Refresh the page
                    }
                });
        }

        setInterval(updateCountdown, 1000);
        setInterval(checkExpiredPromotions, 60000); // Check every minute
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
