<?php
session_start();
require '../includes/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Pobierz zamówienia z bazy danych
$stmt = $conn->prepare("SELECT * FROM zamowienia ORDER BY data DESC");
$stmt->execute();
$orders = $stmt->get_result();

// Funkcja do edycji statusu zamówienia
if (isset($_POST['update_order'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE zamowienia SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    $stmt->execute();
    header("Location: manage_orders.php");
    exit();
}

// Funkcja do usuwania zamówienia
if (isset($_POST['delete_order'])) {
    $order_id = $_POST['order_id'];

    $stmt = $conn->prepare("DELETE FROM zamowienia WHERE id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    header("Location: manage_orders.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style-dashboard.css">
    <title>Zarządzaj zamówieniami</title>
    <style>
        body {
            background-color: #f8f9fa;
        }
        header {
            background-color: #343a40;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        h1 {
            margin: 0;
        }
        table {
            background-color: #fff;
            box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-inline {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .btn {
            margin: 0 5px;
        }
        .btn-update {
            background-color: #0d6efd;
            color: white;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-update:hover, .btn-delete:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
<header class="container py-3">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand" href="manage_orders.php">Zarządzaj Zamówieniami</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Przełącznik nawigacji">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="../dashboard/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="manage_products.php">Zarządzaj produktami</a></li>
                        <li class="nav-item"><a class="nav-link" href="sales_statistics.php">Statystyki sprzedaży</a></li>
                        <li class="nav-item"><a class="nav-link" href="../auth/logout.php">Wyloguj się</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main class="container mt-5">
        <h2 class="mb-4">Lista zamówień</h2>
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Produkt</th>
                        <th>Smak</th>
                        <th>Ilość</th>
                        <th>Imię</th>
                        <th>Nazwisko</th>
                        <th>Data</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $orders->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo $row['produkt']; ?></td>
                        <td><?php echo $row['smak']; ?></td>
                        <td><?php echo $row['ilosc']; ?></td>
                        <td><?php echo $row['imie']; ?></td>
                        <td><?php echo $row['nazwisko']; ?></td>
                        <td><?php echo $row['data']; ?></td>
                        <td>
                            <span class="badge 
                                <?php
                                    if ($row['status'] === 'Oczekujące') echo 'bg-warning text-dark';
                                    elseif ($row['status'] === 'W trakcie') echo 'bg-primary';
                                    elseif ($row['status'] === 'Zrealizowane') echo 'bg-success';
                                    elseif ($row['status'] === 'Anulowane') echo 'bg-danger';
                                ?>">
                                <?php echo $row['status']; ?>
                            </span>
                        </td>
                        <td>
                            <form action="manage_orders.php" method="POST" class="form-inline d-inline-block">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                <select name="status" class="form-select form-select-sm" required>
                                    <option value="Oczekujące" <?php echo ($row['status'] === 'Oczekujące') ? 'selected' : ''; ?>>Oczekujące</option>
                                    <option value="W trakcie" <?php echo ($row['status'] === 'W trakcie') ? 'selected' : ''; ?>>W trakcie</option>
                                    <option value="Zrealizowane" <?php echo ($row['status'] === 'Zrealizowane') ? 'selected' : ''; ?>>Zrealizowane</option>
                                    <option value="Anulowane" <?php echo ($row['status'] === 'Anulowane') ? 'selected' : ''; ?>>Anulowane</option>
                                </select>
                                <button type="submit" name="update_order" class="btn btn-update btn-sm">Aktualizuj</button>
                            </form>
                            <form action="manage_orders.php" method="POST" class="form-inline d-inline-block">
                                <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" name="delete_order" class="btn btn-delete btn-sm" onclick="return confirm('Czy na pewno chcesz usunąć to zamówienie?')">Usuń</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

