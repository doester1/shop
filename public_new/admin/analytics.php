<?php
session_start();
require '../includes/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

$username = $_SESSION['username'];

// Ustawienia domyślne dla filtracji
$startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01'); // Pierwszy dzień bieżącego miesiąca
$endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t'); // Ostatni dzień bieżącego miesiąca

// Pobranie danych sprzedaży dziennej z filtracją
$salesQuery = $conn->prepare("SELECT DATE(sale_date) AS sale_day, SUM(price * ilosc) AS daily_revenue FROM sales WHERE admin_username = ? AND sale_date BETWEEN ? AND ? GROUP BY DATE(sale_date) ORDER BY DATE(sale_date)");
$salesQuery->bind_param("sss", $username, $startDate, $endDate);
$salesQuery->execute();
$salesResult = $salesQuery->get_result();

// Pobranie danych miesięcznych do wykresu porównawczego
$monthlyRevenueQuery = $conn->prepare("SELECT MONTH(sale_date) AS month, SUM(price * ilosc) AS total_revenue FROM sales WHERE admin_username = ? AND sale_date BETWEEN ? AND ? GROUP BY MONTH(sale_date)");
$monthlyRevenueQuery->bind_param("sss", $username, $startDate, $endDate);
$monthlyRevenueQuery->execute();
$monthlyRevenueResult = $monthlyRevenueQuery->get_result();

// Pobranie danych o najlepszych produktach
$topProductsQuery = $conn->prepare("SELECT p.nazwa, SUM(s.ilosc) AS total_sold, SUM(s.price * s.ilosc) AS total_revenue FROM sales s JOIN produkty p ON s.product_id = p.id WHERE s.admin_username = ? AND sale_date BETWEEN ? AND ? GROUP BY p.nazwa ORDER BY total_sold DESC LIMIT 5");
$topProductsQuery->bind_param("sss", $username, $startDate, $endDate);
$topProductsQuery->execute();
$topProductsResult = $topProductsQuery->get_result();

// Pobranie danych o ogólnym przychodzie i ilości sprzedaży
$overallStatsQuery = $conn->prepare("SELECT COUNT(*) AS total_sales, SUM(price * ilosc) AS total_revenue FROM sales WHERE admin_username = ? AND sale_date BETWEEN ? AND ?");
$overallStatsQuery->bind_param("sss", $username, $startDate, $endDate);
$overallStatsQuery->execute();
$overallStatsResult = $overallStatsQuery->get_result();
$overallStats = $overallStatsResult->fetch_assoc();

// Pobranie danych o nowych klientach w ostatnim miesiącu
$newClientsQuery = $conn->prepare("SELECT COUNT(*) AS new_clients FROM klienci WHERE data_rejestracji >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
$newClientsQuery->execute();
$newClientsResult = $newClientsQuery->get_result();
$newClients = $newClientsResult->fetch_assoc();

// Pobranie danych o najlepszych dniach sprzedaży
$bestDaysQuery = $conn->prepare("SELECT DATE (sale_date) AS sale_day, SUM(price * ilosc) AS total_revenue FROM sales WHERE admin_username = ? AND sale_date BETWEEN ? AND ? GROUP BY DATE(sale_date) ORDER BY total_revenue DESC LIMIT 5");
$bestDaysQuery->bind_param("sss", $username, $startDate, $endDate);
$bestDaysQuery->execute();
$bestDaysResult = $bestDaysQuery->get_result();

// Historia zmian
$historyQuery = $conn->prepare("SELECT change_date, change_description, admin_username FROM history ORDER BY change_date DESC");
$historyQuery->execute();
$historyResult = $historyQuery->get_result();

// Eksport danych do CSV
if (isset($_POST['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_data.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Data', 'Przychód']);

    $salesResult->data_seek(0);
    while ($row = $salesResult->fetch_assoc()) {
        fputcsv($output, [date('d-m-Y', strtotime($row['sale_day'])), number_format($row['daily_revenue'], 2)]);
    }
    fclose($output);
    exit();
}

// Eksport danych do PDF
if (isset($_POST['export_pdf'])) {
    // Ensure the path to TCPDF is correct
    require_once('../includes/tcpdf/tcpdf.php');

    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);

    $pdf->Cell(0, 10, 'Data', 1, 0, 'C');
    $pdf->Cell(0, 10, 'Przychód', 1, 1, 'C');

    $salesResult->data_seek(0);
    while ($row = $salesResult->fetch_assoc()) {
        $pdf->Cell(0, 10, date('d-m-Y', strtotime($row['sale_day'])), 1, 0, 'C');
        $pdf->Cell(0, 10, number_format($row['daily_revenue'], 2), 1, 1, 'C');
    }

    $pdf->Output('sales_data.pdf', 'D');
    exit();
}

// Eksport danych do Excel
if (isset($_POST['export_excel'])) {
    // Implementacja eksportu do Excel
    // Możesz użyć biblioteki PHPExcel lub PhpSpreadsheet
}
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strona Analityczna</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style-dashboard.css">
    <style>
        .stat-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            color: white;
            margin-bottom: 20px;
        }
        .stat-card .icon {
            font-size: 2.5rem;
        }
        .stat-card.total-sales { background: #4CAF50; }
        .stat-card.total-revenue { background: #2196F3; }
        .stat-card.best-day { background: #FF9800; }
        .stat-card.new-clients { background: #FF5722; }
    </style>
</head>
<body class="bg-light">
    <header class="container py-3">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand" href="dashboard.php">Panel Administratora</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Przełącznik nawigacji">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="../dashboard/dashboard.php">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link active" href="analytics.php">Analiza</a></li>
                        <li class="nav-item"><a class="nav-link" href="manage_products.php">Zarządzaj produktami</a></li>
                        <li class="nav-item"><a class="nav-link" href="../auth/logout.php">Wyloguj się</a></li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    <main class="container mt-4">
        <h1 class="mb-4">Analiza danych sprzedażowych</h1>

        <!-- Formularz filtracji danych -->
        <form method="POST" class="mb-4">
            <div class=" row">
                <div class="col-md-5">
                    <label for="start_date" class="form-label">Data początkowa</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $startDate ?>">
                </div>
                <div class="col-md-5">
                    <label for="end_date" class="form-label">Data końcowa</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $endDate ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary form-control">Filtruj</button>
                </div>
            </div>
        </form>

        <form method="POST" class="mb-4">
            <button type="submit" name="export" class="btn btn-success">Eksportuj do CSV</button>
            <button type="submit" name="export_pdf" class="btn btn-danger">Eksportuj do PDF</button>
            <button type="submit" name="export_excel" class="btn btn-info">Eksportuj do Excel</button>
        </form>

        <!-- Statystyki ogólne -->
        <section class="mb-4">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card total-sales">
                        <div>
                            <p>Całkowita liczba transakcji</p>
                            <h2><?= $overallStats['total_sales'] ?></h2>
                        </div>
                        <i class="fas fa-shopping-cart icon"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card total-revenue">
                        <div>
                            <p>Całkowity przychód</p>
                            <h2><?= number_format($overallStats['total_revenue'], 2) ?> zł</h2>
                        </div>
                        <i class="fas fa-chart-line icon"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card best-day">
                        <div>
                            <p>Najlepszy dzień sprzedaży</p>
                            <h2>Szukanie...</h2>
                        </div>
                        <i class="fas fa-calendar-alt icon"></i>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card new-clients">
                        <div>
                            <p>Nowi klienci w tym msc</p>
                            <h2 id="client-status">Szukanie...</h2>
                            <!--<h2><?= $newClients['new_clients'] ?></h2> -->
                        </div>
                        <i class="fas fa-user-plus icon"></i>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sekcja z wykresem dziennych przychodów -->
        <section class="mb-4">
            <h2>Przychód dzienny</h2>
            <div class="card shadow-sm">
                <div class="card-body">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </section>

        <!-- Sekcja z wykresem porównawczym miesięcznego przychodu -->
        <section class="mb-4">
            <h2>Porównanie miesięcznego przychodu</h2>
            <div class="card shadow-sm">
                <div class="card-body">
                    <canvas id="monthlyRevenueChart"></canvas>
                </div>
            </div>
        </section>

        <!-- Sekcja z najlepszymi produktami -->
        <section class="mb-4">
            <h2>Top produkty</h2>
            <div class="card shadow-sm">
                <div class="card-body">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Produkt</th>
                                <th>Sprzedane sztuki</th>
                                <th>Przychód</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $topProductsResult->fetch_assoc()) { ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['nazwa']) ?></td>
                                    <td><?= $row['total_sold'] ?></td>
                                    <td><?= number_format($row['total_revenue'], 2) ?> zł</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
 </div>
            </div>
        </section>

        <!-- Sekcja z najlepszymi dniami sprzedaży -->
        <section class="mb-4">
            <h2>Najlepsze dni sprzedaży</h2>
            <div class="card shadow-sm">
                <div class="card-body">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Przychód</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $bestDaysResult->fetch_assoc()) { ?>
                                <tr>
                                    <td><?= date('d-m-Y', strtotime($row['sale_day'])) ?></td>
                                    <td><?= number_format($row['total_revenue'], 2) ?> zł</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Sekcja z osadzonym arkuszem Excel -->
        <section class="mb-4">
            <h2>Osadzony arkusz Excel</h2>
            <div class="card shadow-sm">
                <div class="card-body">
                    <iframe src="https://onedrive.live.com/embed?resid=31FD87ECC4AEF317%21105&authkey=%21AK3n5v8F4G5G3mM&em=2" width="100%" height="500px" frameborder="0" scrolling="no"></iframe>
                </div>
            </div>
        </section>

        <!-- Sekcja z historią zmian -->
        <section class="mb-4">
            <h2>Historia zmian</h2>
            <div class="card shadow-sm">
                <div class="card-body">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Data zmiany</th>
                                <th>Opis zmiany</th>
                                <th>Administrator</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $historyResult->fetch_assoc()) { ?>
                                <tr>
                                    <td><?= date('d-m-Y H:i:s', strtotime($row['change_date'])) ?></td>
                                    <td><?= htmlspecialchars($row['change_description']) ?></td>
                                    <td><?= htmlspecialchars($row['admin_username']) ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Skrypty JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var ctx = document.getElementById('salesChart').getContext('2d');
            var chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [<?php
                        $labels = [];
                        $salesResult->data_seek(0);
                        while ($row = $salesResult->fetch_assoc()) {
                            $labels[] = '"' . date('d-m-Y', strtotime($row['sale_day'])) . '"';
                        }
                        echo implode(',', $labels);
                    ?>],
                    datasets: [{
                        label: 'Przychód dzienny (zł)',
                        data: [<?php
                            $salesResult->data_seek(0);
                            $data = [];
                            while ($row = $salesResult->fetch_assoc()) {
                                $data[] = $row['daily_revenue'];
                            }
                            echo implode(',', $data);
                        ?>],
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Data'
                            },
                            ticks: {
                                autoSkip: true,
                                maxTicksLimit: 10
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Przychód (zł)'
                            },
                            beginAtZero: true
                        }
                    }
                }
            });

            // Wykres porównawczy miesięcznego przychodu
            var monthlyCtx = document.getElementById('monthlyRevenueChart').getContext('2d');
            var monthlyChart = new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: [<?php
                        $monthlyLabels = [];
                        $monthlyRevenueResult->data_seek(0);
                        while ($row = $monthlyRevenueResult->fetch_assoc()) {
                            $monthlyLabels[] = '"' . date('F', mktime(0, 0, 0, $row['month'], 1)) . '"';
                        }
                        echo implode(',', $monthlyLabels);
                    ?>],
                    datasets: [{
                        label: 'Miesięczny przychód (zł)',
                        data: [<?php
                            $monthlyRevenueResult->data_seek(0);
                            $monthlyData = [];
                            while ($row = $monthlyRevenueResult->fetch_assoc()) {
                                $monthlyData[] = $row['total_revenue'];
                            }
                            echo implode(',', $monthlyData);
                        ?>],
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Miesiąc'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Przychód (zł)'
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
        });
    </script>
    <script>
    // Ustawienie opóźnienia na 3 sekundy (3000 ms)
    setTimeout(function() {
        // Zmiana tekstu na "Niedostępne"
        document.getElementById('client-status').innerText = 'Niedostępne';
    }, 3000);
</script>
</body>
</html>