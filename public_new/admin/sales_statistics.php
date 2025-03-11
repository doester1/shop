<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require '../includes/db.php';
require '../vendor/autoload.php'; // Dodajemy autoload z Composer dla PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Sprawdzenie sesji logowania
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Pobierz całkowitą sprzedaż
$stmt_total_sales = $conn->prepare("SELECT SUM(daily_sales) as total_sales FROM sales");
$stmt_total_sales->execute();
$total_sales_result = $stmt_total_sales->get_result();
$total_sales = $total_sales_result->fetch_assoc()['total_sales'];

// Pobierz sprzedaż z ostatnich 7 dni
$stmt = $conn->prepare("
    SELECT DATE(sale_date) as date, SUM(daily_sales) as daily_sales 
    FROM sales 
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
    GROUP BY DATE(sale_date)
    ORDER BY sale_date ASC
");

$stmt->execute();
$sales_last_7_days = $stmt->get_result();

// Inicjalizacja danych do wykresu
$sales_data = [];
while ($row = $sales_last_7_days->fetch_assoc()) {
    $sales_data[] = $row;
}

// Pobierz najlepiej sprzedające się produkty
$stmt_best_selling = $conn->prepare("
    SELECT produkt_name, SUM(ilosc) as total_quantity 
    FROM sales 
    GROUP BY produkt_name
    ORDER BY total_quantity DESC 
    LIMIT 5
");

$stmt_best_selling->execute();
$best_selling_products = $stmt_best_selling->get_result();

// Średnia sprzedaż dzienna
$stmt_avg_sales = $conn->prepare("
    SELECT AVG(daily_sales) as avg_sales
    FROM (
        SELECT DATE(sale_date) as date, SUM(price * ilosc) as daily_sales 
        FROM sales 
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(sale_date)
    ) as sales_data
");

$stmt_avg_sales->execute();
$avg_sales_result = $stmt_avg_sales->get_result();
$avg_sales = $avg_sales_result->fetch_assoc()['avg_sales'];

// Pobierz produkt, na którym najwięcej zarabiamy
$stmt_most_profitable = $conn->prepare("
    SELECT produkt_name, SUM(daily_sales) - SUM(pe.total_amount) as profit, SUM(ilosc) as total_quantity, SUM(daily_sales) as total_sales
    FROM sales s
    JOIN ProductExpenses pe ON s.product_id = pe.id
    GROUP BY produkt_name
    ORDER BY profit DESC
    LIMIT 3
");
$stmt_most_profitable->execute();
$most_profitable_products = $stmt_most_profitable->get_result();

// Pobierz najczęściej sprzedawane smaki
$stmt_most_sold_flavors = $conn->prepare("
    SELECT flavor, COUNT(*) as count
    FROM sales
    GROUP BY flavor
    ORDER BY count DESC
    LIMIT 5
");
$stmt_most_sold_flavors->execute();
$most_sold_flavors = $stmt_most_sold_flavors->get_result();

// Pobierz zysk netto z każdej dostawy
$stmt_net_profit = $conn->prepare("
    SELECT pe.expense_date, SUM(s.daily_sales) - pe.total_amount as net_profit
    FROM sales s
    JOIN ProductExpenses pe ON s.product_id = pe.id
    WHERE s.sale_date >= pe.expense_date
    GROUP BY pe.expense_date
    ORDER BY pe.expense_date ASC
");
$stmt_net_profit->execute();
$net_profit_result = $stmt_net_profit->get_result();

// Pobierz najbardziej dochodowe kategorie
$stmt_most_profitable_categories = $conn->prepare("
    SELECT c.category_name, SUM(s.daily_sales) - SUM(pe.total_amount) as profit
    FROM sales s
    JOIN ProductExpenses pe ON s.product_id = pe.id
    JOIN categories c ON s.category_id = c.id
    GROUP BY c.category_name
    ORDER BY profit DESC
    LIMIT 3
");
$stmt_most_profitable_categories->execute();
$most_profitable_categories = $stmt_most_profitable_categories->get_result();

// Pobierz całkowitą kwotę wydaną na wszystkie zamówienia
$stmt_total_spent = $conn->prepare("SELECT SUM(total_amount) as total_spent FROM ProductExpenses");
$stmt_total_spent->execute();
$total_spent_result = $stmt_total_spent->get_result();
$total_spent = $total_spent_result->fetch_assoc()['total_spent'];

// Pobierz średnią cenę produktów
$stmt_avg_price = $conn->prepare("SELECT AVG(total_amount / quantity) as avg_price FROM ProductExpenses");
$stmt_avg_price->execute();
$avg_price_result = $stmt_avg_price->get_result();
$avg_price = $avg_price_result->fetch_assoc()['avg_price'];

// Pobierz całkowitą ilość zamówionych produktów
$stmt_total_quantity = $conn->prepare("SELECT SUM(quantity) as total_quantity FROM ProductExpenses");
$stmt_total_quantity->execute();
$total_quantity_result = $stmt_total_quantity->get_result();
$total_quantity = $total_quantity_result->fetch_assoc()['total_quantity'];

// Pobierz szczegóły wszystkich zamówień
$stmt_orders = $conn->prepare("SELECT expense_date, total_amount, quantity FROM ProductExpenses ORDER BY expense_date DESC");
$stmt_orders->execute();
$orders = $stmt_orders->get_result();

// Oblicz całkowity zysk netto
$total_net_profit = $total_sales - $total_spent;

// Inicjalizacja danych zysku netto
$net_profit_data = [];
while ($row = $net_profit_result->fetch_assoc()) {
    $net_profit_data[] = $row;
}

// Dodanie funkcji eksportu do Excela
if (isset($_POST['export_excel'])) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Dodawanie nagłówków
    $sheet->setCellValue('A1', 'Statystyki sprzedaży');
    $sheet->setCellValue('A3', 'Całkowita sprzedaż');
    $sheet->setCellValue('B3', number_format($total_sales, 2));
    $sheet->setCellValue('A4', 'Średnia sprzedaż dzienna (ostatnie 30 dni)');
    $sheet->setCellValue('B4', number_format($avg_sales, 2));

    // Wpisywanie danych sprzedaży z ostatnich 7 dni
    $sheet->setCellValue('A6', 'Sprzedaż z ostatnich 7 dni');
    $sheet->setCellValue('A7', 'Data');
    $sheet->setCellValue('B7', 'Kwota sprzedaży (PLN)');

    $row = 8;
    foreach ($sales_data as $data) {
        $sheet->setCellValue('A' . $row, $data['date']);
        $sheet->setCellValue('B' . $row, number_format($data['daily_sales'], 2));
        $row++;
    }

    // Wpisywanie danych najlepiej sprzedających się produktów
    $sheet->setCellValue('A' . $row, 'Najlepiej sprzedające się produkty');
    $row++;
    $sheet->setCellValue('A' . $row, 'Produkt');
    $sheet->setCellValue('B' . $row, 'Ilość sprzedanych sztuk');
    $row++;

    while ($product = $best_selling_products->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $product['produkt_name']);
        $sheet->setCellValue('B' . $row, $product['total_quantity']);
        $row++;
    }

    // Dodawanie najbardziej dochodowych produktów
    $sheet->setCellValue('A' . $row, 'Najbardziej dochodowe produkty');
    $row++;
    $sheet->setCellValue('A' . $row, 'Produkt');
    $sheet->setCellValue('B' . $row, 'Ilość sprzedanych sztuk');
    $sheet->setCellValue('C' . $row, 'Suma pieniędzy (PLN)');
    $row++;

    while ($product = $most_profitable_products->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $product['produkt_name']);
        $sheet->setCellValue('B' . $row, $product['total_quantity']);
        $sheet->setCellValue('C' . $row, number_format($product['total_sales'], 2));
        $row++;
    }

    // Dodawanie najczęściej sprzedawanych smaków
    $sheet->setCellValue('A' . $row, 'Najczęściej sprzedawane smaki');
    $row++;
    $sheet->setCellValue('A' . $row, 'Smak');
    $sheet->setCellValue('B' . $row, 'Ilość sprzedanych sztuk');
    $row++;

    while ($flavor = $most_sold_flavors->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $flavor['flavor']);
        $sheet->setCellValue('B' . $row, $flavor['count']);
        $row++;
    }

    // Dodawanie najbardziej dochodowych kategorii
    $sheet->setCellValue('A' . $row, 'Najbardziej dochodowe kategorie');
    $row++;
    $sheet->setCellValue('A' . $row, 'Kategoria');
    $sheet->setCellValue('B' . $row, 'Zysk (PLN)');
    $row++;

    while ($category = $most_profitable_categories->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $category['category_name']);
        $sheet->setCellValue('B' . $row, number_format($category['profit'], 2));
        $row++;
    }

    // Dodawanie zysku netto z każdej dostawy
    $sheet->setCellValue('A' . $row, 'Zysk netto z każdej dostawy');
    $row++;
    $sheet->setCellValue('A' . $row, 'Data dostawy');
    $sheet->setCellValue('B' . $row, 'Zysk netto (PLN)');
    $row++;

    foreach ($net_profit_data as $profit) {
        $sheet->setCellValue('A' . $row, $profit['expense_date']);
        $sheet->setCellValue('B' . $row, number_format($profit['net_profit'], 2));
        $row++;
    }

    // Dodawanie całkowitego zysku netto
    $sheet->setCellValue('A' . $row, 'Całkowity zysk netto');
    $sheet->setCellValue('B' . $row, number_format($total_net_profit, 2));
    $row++;

    // Dodawanie szczegółów zamówień
    $sheet->setCellValue('A' . $row, 'Szczegóły zamówień');
    $row++;
    $sheet->setCellValue('A' . $row, 'Data zamówienia');
    $sheet->setCellValue('B' . $row, 'Kwota zamówienia (PLN)');
    $sheet->setCellValue('C' . $row, 'Ilość produktów');
    $row++;

    while ($order = $orders->fetch_assoc()) {
        $sheet->setCellValue('A' . $row, $order['expense_date']);
        $sheet->setCellValue('B' . $row, number_format($order['total_amount'], 2));
        $sheet->setCellValue('C' . $row, $order['quantity']);
        $row++;
    }

    // Tworzenie pliku Excel
    $writer = new Xlsx($spreadsheet);
    $filename = 'statystyki_sprzedazy_' . date('Y-m-d') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Pragma: public');

    $writer->save('php://output');
    exit;
}

?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/style-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Dodanie wykresów -->
    <title>Statystyki sprzedaży</title>
    <style>
        .statistics-container {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .stats-box {
            width: 90%;
            max-width: 1200px;
            background: #fff;
            padding: 20px;
            margin: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .stats-box h2 {
            margin-bottom: 20px;
            color: #e74c3c;
        }
        .stats-box p {
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        .chart-container {
            width: 100%;
            height: 400px;
            margin-top: 30px;
        }
        .export-btn {
            margin: 20px 0;
            padding: 10px 20px;
            background-color: #2ecc71;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .export-btn:hover {
            background-color: #27ae60;
        }
        .section-title {
            margin-top: 40px;
            font-size: 1.5em;
            color: #3498db;
        }
    </style>
</head>
<body>
    <header>
        <h1>Statystyki sprzedaży</h1>
        <nav>
            <ul>
                <li><a href="/index.php">Strona główna</a></li>
                <li><a href="../dashboard/dashboard.php">Dashboard</a></li>
                <li><a href="manage_products.php">Zarządzaj produktami</a></li>
                <li><a href="../auth/logout.php">Wyloguj się</a></li>
            </ul>
        </nav>
    </header>
    <main class="statistics-container">
        <!-- Statystyki sprzedaży -->
        <div class="section-title">Statystyki sprzedaży</div>
        <div class="stats-box">
            <h2>Całkowita sprzedaż</h2>
            <p>Całkowita sprzedaż: <strong><?php echo $total_sales ? number_format($total_sales, 2) : '0.00'; ?> PLN</strong></p>
        </div>

        <div class="stats-box">
            <h2>Średnia sprzedaż dzienna (ostatnie 30 dni)</h2>
            <p>Średnia sprzedaż dzienna: <strong><?php echo $avg_sales ? number_format($avg_sales, 2) : '0.00'; ?> PLN</strong></p>
        </div>

        <div class="stats-box">
            <h2>Sprzedaż z ostatnich 7 dni</h2>
            <div class="chart-container">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <div class="stats-box">
            <h2>Najlepiej sprzedające się produkty</h2>
            <ul>
                <?php while ($row = $best_selling_products->fetch_assoc()): ?>
                    <li><?php echo $row['produkt_name']; ?>: <?php echo $row['total_quantity']; ?> sztuk</li>
                <?php endwhile; ?>
            </ul>
        </div>

        <div class="stats-box">
            <h2>Najbardziej dochodowe produkty</h2>
            <ul>
                <?php while ($row = $most_profitable_products->fetch_assoc()): ?>
                    <li><?php echo $row['produkt_name']; ?>: <?php echo $row['total_quantity']; ?> sztuk (<?php echo number_format($row['total_sales'], 2); ?> PLN)</li>
                <?php endwhile; ?>
            </ul>
        </div>

        <div class="stats-box">
            <h2>Najczęściej sprzedawane smaki</h2>
            <ul>
                <?php 
                $i = 1;
                while ($row = $most_sold_flavors->fetch_assoc()): ?>
                    <li><strong>Top <?php echo $i; ?>:</strong> <?php echo $row['flavor']; ?>: <strong><?php echo $row['count']; ?> sztuk</strong></li>
                    <?php $i++; ?>
                <?php endwhile; ?>
            </ul>
        </div>

        <div class="stats-box">
            <h2>Najbardziej dochodowe kategorie</h2>
            <ul>
                <?php while ($row = $most_profitable_categories->fetch_assoc()): ?>
                    <li><?php echo $row['category_name']; ?>: <?php echo number_format($row['profit'], 2); ?> PLN</li>
                <?php endwhile; ?>
            </ul>
        </div>

        <div class="stats-box">
            <h2>Zysk netto z każdej dostawy</h2>
            <ul>
                <?php foreach ($net_profit_data as $row): ?>
                    <li><?php echo $row['expense_date']; ?>: <?php echo number_format($row['net_profit'], 2); ?> PLN</li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="stats-box">
            <h2>Całkowity zysk netto</h2>
            <p>Całkowity zysk netto: <strong><?php echo number_format($total_net_profit, 2); ?> PLN</strong></p>
        </div>

        <!-- Statystyki zamówień -->
        <div class="section-title">Statystyki zamówień</div>
        <div class="stats-box">
            <h2>Całkowita kwota wydana na wszystkie zamówienia</h2>
            <p>Całkowita kwota wydana: <strong><?php echo number_format($total_spent, 2); ?> PLN</strong></p>
        </div>

        <div class="stats-box">
            <h2>Średnia cena produktów</h2>
            <p>Średnia cena produktów: <strong><?php echo number_format($avg_price, 2); ?> PLN</strong></p>
        </div>

        <div class="stats-box">
            <h2>Całkowita ilość zamówionych produktów</h2>
            <p>Całkowita ilość zamówionych produktów: <strong><?php echo $total_quantity; ?></strong></p>
        </div>

        <div class="stats-box">
            <h2>Szczegóły zamówień</h2>
            <ul>
                <?php while ($row = $orders->fetch_assoc()): ?>
                    <li><?php echo $row['expense_date']; ?>: <strong><?php echo number_format($row['total_amount'], 2); ?></strong> PLN (<strong><?php echo $row['quantity']; ?></strong> sztuk)</li>
                <?php endwhile; ?>
            </ul>
        </div>

        <!-- Przycisk eksportu do Excela -->
        <form method="post">
            <button type="submit" name="export_excel" class="export-btn">Eksportuj do Excela</button>
        </form>
    </main>

    <script>
        // Przygotowanie danych do wykresu
        const salesData = <?php echo json_encode($sales_data); ?>;

        const labels = salesData.map(item => item.date);
        const salesAmounts = salesData.map(item => item.daily_sales);

        // Konfiguracja wykresu
        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Sprzedaż dzienna (PLN)',
                    data: salesAmounts,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
