<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

$seller = $_GET['seller'] ?? 'Brak danych';
$startDate = $_GET['start_date'] ?? 'Brak danych';
$endDate = $_GET['end_date'] ?? 'Brak danych';
$previousMonthStart = date("Y-m-01", strtotime("first day of previous month"));
$previousMonthEnd = date("Y-m-t", strtotime("last day of previous month"));

// Calculate percentage change and revenue difference for the best seller
$bestSellerRevenueQuery = $conn->prepare("SELECT SUM(price * ilosc) AS total_revenue FROM sales WHERE admin_username = ? AND sale_date BETWEEN ? AND ?");
$bestSellerRevenueQuery->bind_param("sss", $seller, $startDate, $endDate);
$bestSellerRevenueQuery->execute();
$bestSellerRevenueResult = $bestSellerRevenueQuery->get_result();
$bestSellerRevenue = $bestSellerRevenueResult->fetch_assoc()['total_revenue'] ?? 0;

$previousBestSellerRevenueQuery = $conn->prepare("SELECT SUM(price * ilosc) AS previous_revenue FROM sales WHERE admin_username = ? AND sale_date BETWEEN ? AND ?");
$previousBestSellerRevenueQuery->bind_param("sss", $seller, $previousMonthStart, $previousMonthEnd);
$previousBestSellerRevenueQuery->execute();
$previousBestSellerRevenueResult = $previousBestSellerRevenueQuery->get_result();
$previousBestSellerRevenue = $previousBestSellerRevenueResult->fetch_assoc()['previous_revenue'] ?? 0;

$percentageChangeBestSeller = 0;
$revenueDifferenceBestSeller = 0;
if ($previousBestSellerRevenue > 0) {
    $percentageChangeBestSeller = (($bestSellerRevenue - $previousBestSellerRevenue) / $previousBestSellerRevenue) * 100;
    $revenueDifferenceBestSeller = $bestSellerRevenue - $previousBestSellerRevenue;
}

// Fetch total revenue and percentage change for each admin
$adminRevenues = [];
$adminsQuery = $conn->query("SELECT username FROM admini");
while ($adminRow = $adminsQuery->fetch_assoc()) {
    $admin = $adminRow['username'];
    $revenueQuery = $conn->prepare("SELECT SUM(price * ilosc) AS total_revenue FROM sales WHERE admin_username = ? AND sale_date BETWEEN ? AND ?");
    $revenueQuery->bind_param("sss", $admin, $startDate, $endDate);
    $revenueQuery->execute();
    $revenueResult = $revenueQuery->get_result();
    $totalRevenue = $revenueResult->fetch_assoc()['total_revenue'] ?? 0;

    $previousRevenueQuery = $conn->prepare("SELECT SUM(price * ilosc) AS previous_revenue FROM sales WHERE admin_username = ? AND sale_date BETWEEN ? AND ?");
    $previousRevenueQuery->bind_param("sss", $admin, $previousMonthStart, $previousMonthEnd);
    $previousRevenueQuery->execute();
    $previousRevenueResult = $previousRevenueQuery->get_result();
    $previousRevenue = $previousRevenueResult->fetch_assoc()['previous_revenue'] ?? 0;

    $percentageChange = 0;
    if ($previousRevenue > 0) {
        $percentageChange = (($totalRevenue - $previousRevenue) / $previousRevenue) * 100;
    }

    $adminRevenues[] = [
        'admin' => $admin,
        'total_revenue' => $totalRevenue,
        'percentage_change' => $percentageChange
    ];
}

// Sort admins by total revenue in descending order
usort($adminRevenues, function($a, $b) {
    return $b['total_revenue'] <=> $a['total_revenue'];
});

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Szczegóły - Najlepszy sprzedawca</title>
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
        <p>Zmiana procentowa: <?php echo number_format($percentageChangeBestSeller, 2, ',', ' '); ?>%</p>
        <p>Różnica w zł: <?php echo number_format($revenueDifferenceBestSeller, 2, ',', ' '); ?> zł</p>
        <h3>Porównanie z innymi administratorami:</h3>
        <ul>
            <?php foreach ($adminRevenues as $adminData): ?>
                <li><?php echo htmlspecialchars($adminData['admin']); ?>: <?php echo number_format($adminData['total_revenue'], 2, ',', ' '); ?> zł (<?php echo number_format($adminData['percentage_change'], 2, ',', ' '); ?>%)</li>
            <?php endforeach; ?>
        </ul>
        <p>W przypadku pytań lub wątpliwości, prosimy o kontakt z administratorem systemu.</p>
        <a href="dashboard.php" class="btn btn-primary mt-3">Powrót do Dashboardu</a>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
