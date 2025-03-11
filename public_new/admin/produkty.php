<?php
session_start();
require_once '../includes/db.php';

// Sprawdzenie, czy użytkownik jest zalogowany
if (!isset($_SESSION['loggedin'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Przykładowe pobranie listy produktów z bazy danych
$stmt = $pdo->prepare("SELECT * FROM produkty");
$stmt->execute();
$produkty = $stmt->fetchAll();

?>

<?php include('../includes/header.php'); ?>
<?php include('../includes/navbar.php'); ?>

<div class="container">
    <h1>Zarządzaj Produktami</h1>

    <a href="dodaj_produkt.php" class="btn btn-primary">Dodaj Produkt</a>

    <table>
        <thead>
            <tr>
                <th>Nazwa</th>
                <th>Cena</th>
                <th>Ilość</th>
                <th>Akcje</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produkty as $produkt): ?>
            <tr>
                <td><?= $produkt['nazwa'] ?></td>
                <td><?= $produkt['cena'] ?></td>
                <td><?= $produkt['ilosc'] ?></td>
                <td>
                    <a href="edytuj_produkt.php?id=<?= $produkt['id'] ?>">Edytuj</a>
                    <a href="usun_produkt.php?id=<?= $produkt['id'] ?>">Usuń</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include('../includes/footer.php'); ?>
