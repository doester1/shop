<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

$seller = $_GET['seller'] ?? 'Brak danych';
$sale = $_GET['sale'] ?? 'Brak danych';
$startDate = $_GET['start_date'] ?? 'Brak danych';
$endDate = $_GET['end_date'] ?? 'Brak danych';
$previousMonthStart = date("Y-m-01", strtotime("first day of previous month"));
$previousMonthEnd = date("Y-m-t", strtotime("last day of previous month"));

if ($sale !== 'Brak danych') {
    $saleQuery = $conn->prepare("SELECT buyer_name, price FROM sales WHERE id = ?");
    $saleQuery->bind_param("i", $sale);
    $saleQuery->execute();
    $saleResult = $saleQuery->get_result()->fetch_assoc();
    $buyerName = $saleResult['buyer_name'] ?? 'Brak danych';
    $salePrice = $saleResult['price'] ?? 0;
}

// Define the highest daily sale variables
$highestDailySaleQuery = $conn->query("SELECT buyer_name, SUM(price * ilosc) AS total_amount, sale_date FROM sales GROUP BY buyer_name, sale_date ORDER BY total_amount DESC LIMIT 1");
if ($highestDailySaleQuery) {
    $highestDailySale = $highestDailySaleQuery->fetch_assoc();
    $highestDailySaleBuyer = $highestDailySale['buyer_name'] ?? 'Brak danych';
    $highestDailySaleAmount = $highestDailySale['total_amount'] ?? 0;
    $highestDailySaleDate = $highestDailySale['sale_date'] ?? 'Brak danych';
} else {
    $highestDailySaleBuyer = 'Brak danych';
    $highestDailySaleAmount = 0;
    $highestDailySaleDate = 'Brak danych';
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Szczegóły - Najlepszy sprzedawca / Największa sprzedaż</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style-dashboard.css">
</head>
<body class="bg-light">
    <header class="container py-3">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand" href="dashboard.php">Dashboard</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Przełącznik nawigacji">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="manage_sales.php">Zarządzanie sprzedażą</a></li>
                        <li class="nav-item"><a class="nav-link" href="sales_statistics.php">Statystyki sprzedaży</a></li>
                        <li class="nav-item"><a class="nav-link" href="../auth/logout.php">Wyloguj się</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main class="container mt-4 details-container">
        <?php if ($seller !== 'Brak danych'): ?>
            <h2>Najlepszy sprzedawca: <?php echo htmlspecialchars($seller); ?></h2>
            <p>Najlepszy sprzedawca jest wyznaczany na podstawie łącznej ilości sprzedanych produktów w danym okresie. Obliczenia uwzględniają wszystkie transakcje dokonane przez sprzedawcę.</p>
            <p>Podstawowe kroki obliczeń:</p>
            <ul>
                <li>Pobranie wszystkich transakcji sprzedawcy z bazy danych.</li>
                <li>Sumowanie ilości sprzedanych produktów dla każdej transakcji.</li>
                <li>Porównanie wyników z innymi sprzedawcami i wyznaczenie najlepszego sprzedawcy na podstawie najwyższej sumy sprzedaży.</li>
            </ul>
            <p>Dane są pobierane z okresu: <?php echo htmlspecialchars($startDate); ?> do <?php echo htmlspecialchars($endDate); ?>.</p>
            <p>Porównanie jest dokonywane do okresu: <?php echo date("Y-m-d", strtotime($previousMonthStart)); ?> - <?php echo date("Y-m-d", strtotime($previousMonthEnd)); ?>.</p>
        <?php endif; ?>

        <?php if ($sale !== 'Brak danych'): ?>
            <h2>Największa sprzedaż</h2>
            <p>Największa sprzedaż jest wyznaczana na podstawie najwyższej wartości pojedynczej transakcji w danym okresie. Obliczenia uwzględniają wszystkie transakcje dokonane przez sprzedawcę.</p>
            <p>Podstawowe kroki obliczeń:</p>
            <ul>
                <li>Pobranie wszystkich transakcji z bazy danych.</li>
                <li>Porównanie wartości każdej transakcji.</li>
                <li>Wyznaczenie największej sprzedaży na podstawie najwyższej wartości transakcji.</li>
            </ul>
            <p>Dane są pobierane z okresu: <?php echo htmlspecialchars($startDate); ?> do <?php echo htmlspecialchars($endDate); ?>.</p>
            <p>Porównanie jest dokonywane do okresu: <?php echo date("Y-m-d", strtotime($previousMonthStart)); ?> - <?php echo date("Y-m-d", strtotime($previousMonthEnd)); ?>.</p>
            <p>Kupujący: <?php echo htmlspecialchars($buyerName); ?></p>
            <p>Wartość transakcji: <?php echo number_format($highestDailySaleAmount, 2, ',', ' '); ?> zł</p>
        <?php endif; ?>

        <p>W przypadku pytań lub wątpliwości, prosimy o kontakt z administratorem systemu.</p>
        <a href="dashboard.php" class="btn btn-primary mt-3">Powrót do Dashboardu</a>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
