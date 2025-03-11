<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

$username = $_SESSION['username'];
$initial = strtoupper($username[0]);

// Łączna sprzedaż
$salesQuery = $conn->prepare("SELECT SUM(ilosc) AS total_sales FROM sales WHERE admin_username = ?");
$salesQuery->bind_param("s", $username);
$salesQuery->execute();
$salesResult = $salesQuery->get_result();
$totalSales = $salesResult->fetch_assoc()['total_sales'] ?? 0;

// Całkowity przychód
$totalRevenueQuery = $conn->prepare("SELECT SUM(price * ilosc) AS total_revenue FROM sales WHERE admin_username = ?");
$totalRevenueQuery->bind_param("s", $username);
$totalRevenueQuery->execute();
$totalRevenueResult = $totalRevenueQuery->get_result();
$totalRevenue = $totalRevenueResult->fetch_assoc()['total_revenue'] ?? 0;

// Nowe zamówienia
$newOrdersQuery = $conn->prepare("SELECT COUNT(*) AS new_orders FROM zamowienia WHERE status = 'nowe' AND admin_username = ?");
$newOrdersQuery->bind_param("s", $username);
$newOrdersQuery->execute();
$newOrdersResult = $newOrdersQuery->get_result();
$newOrdersCount = $newOrdersResult->fetch_assoc()['new_orders'] ?? 0;

// W magazynie
$inStockQuery = $conn->prepare("SELECT SUM(ilosc) AS total_stock FROM produkty");
$inStockQuery->execute();
$inStockResult = $inStockQuery->get_result();
$totalStock = $inStockResult->fetch_assoc()['total_stock'] ?? 0;

// Liczba odwiedzin
$visitCountQuery = $conn->query("SELECT COUNT(*) AS total_visits FROM visits");
$visitCount = $visitCountQuery->fetch_assoc()['total_visits'] ?? 0;

// Powiadomienia
$notificationsQuery = $conn->query("SELECT id, message, created_at, is_read FROM notifications ORDER BY created_at DESC");

// Oznacz wybrane powiadomienie jako przeczytane
if (isset($_POST['mark_as_read_single'])) {
    $notificationId = intval($_POST['notification_id']);
    $markAsReadQuery = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
    $markAsReadQuery->bind_param("i", $notificationId);
    $markAsReadQuery->execute();
    header("Location: dashboard.php");
    exit();
}

// Oznacz wszystkie powiadomienia jako przeczytane
if (isset($_POST['mark_as_read'])) {
    $markAsReadQuery = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
    $markAsReadQuery->execute();
    header("Location: dashboard.php");
    exit();
}

// Clear statistics
if (isset($_POST['clear_stats'])) {
    $statType = $_POST['stat_type'];
    switch ($statType) {
        case 'total_sales':
            $clearQuery = $conn->prepare("UPDATE sales SET ilosc = 0 WHERE admin_username = ?");
            break;
        case 'total_revenue':
            $clearQuery = $conn->prepare("UPDATE sales SET price = 0 WHERE admin_username = ?");
            break;
        case 'new_orders':
            $clearQuery = $conn->prepare("UPDATE zamowienia SET status = 'cleared' WHERE status = 'nowe' AND admin_username = ?");
            break;
        case 'total_stock':
            $clearQuery = $conn->prepare("UPDATE produkty SET ilosc = 0");
            break;
        case 'total_visits':
            $clearQuery = $conn->prepare("DELETE FROM visits");
            break;
        default:
            $clearQuery = null;
    }
    if ($clearQuery) {
        $clearQuery->bind_param("s", $username);
        $clearQuery->execute();
    }
    header("Location: dashboard.php");
    exit();
}

// Function to determine text color based on background color
function getTextColor($backgroundColor) {
    $r = hexdec(substr($backgroundColor, 1, 2));
    $g = hexdec(substr($backgroundColor, 3, 2));
    $b = hexdec(substr($backgroundColor, 5, 2));
    $luminance = 0.299 * $r + 0.587 * $g + 0.114 * $b;
    return ($luminance > 186) ? '#000000' : '#FFFFFF';
}

// Handle message form submission
if (isset($_POST['add_message'])) {
    $message = $_POST['message'];
    $type = $_POST['type'];
    $background_color = $_POST['background_color'];
    $text_color = getTextColor($background_color);

    $addMessageQuery = $conn->prepare("INSERT INTO komunikaty (message, type, text_color, background_color) VALUES (?, ?, ?, ?)");
    $addMessageQuery->bind_param("ssss", $message, $type, $text_color, $background_color);
    $addMessageQuery->execute();
    header("Location: dashboard.php");
    exit();
}

// Handle message deletion
if (isset($_POST['delete_message'])) {
    $message_id = $_POST['message_id'];
    $deleteMessageQuery = $conn->prepare("DELETE FROM komunikaty WHERE id = ?");
    $deleteMessageQuery->bind_param("i", $message_id);
    $deleteMessageQuery->execute();
    header("Location: dashboard.php");
    exit();
}

// Fetch messages
$messagesQuery = $conn->query("SELECT * FROM komunikaty ORDER BY created_at DESC");

// Check for new notifications
$newNotificationsQuery = $conn->query("SELECT COUNT(*) AS new_notifications FROM notifications WHERE is_read = 0");
$newNotificationsCount = $newNotificationsQuery->fetch_assoc()['new_notifications'] ?? 0;

// Handle adding an admin to statistics
if (isset($_POST['add_admin_to_stats'])) {
    $admin_to_add = $_POST['admin_to_add'];
    if (!isset($_SESSION['admins_in_stats'])) {
        $_SESSION['admins_in_stats'] = [];
    }
    if (!in_array($admin_to_add, $_SESSION['admins_in_stats'])) {
        $_SESSION['admins_in_stats'][] = $admin_to_add;
    }
    header("Location: dashboard.php");
    exit();
}

// Handle removing an admin from statistics
if (isset($_POST['remove_admin_from_stats'])) {
    $admin_to_remove = $_POST['admin_to_remove'];
    if (($key = array_search($admin_to_remove, $_SESSION['admins_in_stats'])) !== false) {
        unset($_SESSION['admins_in_stats'][$key]);
    }
    header("Location: dashboard.php");
    exit();
}

// Handle adding a new admin
if (isset($_POST['add_admin'])) {
    $new_admin = $_POST['new_admin'];
    $addAdminQuery = $conn->prepare("INSERT INTO admini (username) VALUES (?)");
    $addAdminQuery->bind_param("s", $new_admin);
    $addAdminQuery->execute();
    header("Location: dashboard.php");
    exit();
}

// Handle removing an admin
if (isset($_POST['remove_admin'])) {
    $admin_to_remove = $_POST['admin_to_remove'];
    $removeAdminQuery = $conn->prepare("DELETE FROM admini WHERE username = ?");
    $removeAdminQuery->bind_param("s", $admin_to_remove);
    $removeAdminQuery->execute();
    header("Location: dashboard.php");
    exit();
}

// Fetch all admins
$adminsQuery = $conn->query("SELECT username FROM admini");

// Fetch admins in statistics
$adminsInStats = $_SESSION['admins_in_stats'] ?? [];

// Calculate total revenue for the selected period and admins
$startDate = $_POST['start_date'] ?? null;
$endDate = $_POST['end_date'] ?? null;
$useDateRange = isset($_POST['use_date_range']) ? true : false;
$totalRevenuePeriod = 0;

if ($useDateRange && $startDate && $endDate && !empty($adminsInStats)) {
    $adminPlaceholders = implode(',', array_fill(0, count($adminsInStats), '?'));
    $types = str_repeat('s', count($adminsInStats)) . 'ss';
    $params = array_merge($adminsInStats, [$startDate, $endDate]);

    $totalRevenuePeriodQuery = $conn->prepare("SELECT SUM(price * ilosc) AS total_revenue_period FROM sales WHERE admin_username IN ($adminPlaceholders) AND sale_date BETWEEN ? AND ?");
    if ($totalRevenuePeriodQuery === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $totalRevenuePeriodQuery->bind_param($types, ...$params);
    $totalRevenuePeriodQuery->execute();
    $totalRevenuePeriodResult = $totalRevenuePeriodQuery->get_result();
    $totalRevenuePeriod = $totalRevenuePeriodResult->fetch_assoc()['total_revenue_period'] ?? 0;
} else if (!empty($adminsInStats)) {
    $adminPlaceholders = implode(',', array_fill(0, count($adminsInStats), '?'));
    $types = str_repeat('s', count($adminsInStats));
    $params = $adminsInStats;

    $totalRevenuePeriodQuery = $conn->prepare("SELECT SUM(price * ilosc) AS total_revenue_period FROM sales WHERE admin_username IN ($adminPlaceholders)");
    if ($totalRevenuePeriodQuery === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $totalRevenuePeriodQuery->bind_param($types, ...$params);
    $totalRevenuePeriodQuery->execute();
    $totalRevenuePeriodResult = $totalRevenuePeriodQuery->get_result();
    $totalRevenuePeriod = $totalRevenuePeriodResult->fetch_assoc()['total_revenue_period'] ?? 0;
}

// Calculate percentage change from the previous month for selected admins
$previousMonthStart = date("Y-m-01", strtotime("first day of previous month"));
$previousMonthEnd = date("Y-m-t", strtotime("last day of previous month"));
$previousMonthRevenue = 0;

if (!empty($adminsInStats)) {
    $adminPlaceholders = implode(',', array_fill(0, count($adminsInStats), '?'));
    $types = str_repeat('s', count($adminsInStats)) . 'ss';
    $params = array_merge($adminsInStats, [$previousMonthStart, $previousMonthEnd]);

    $previousMonthRevenueQuery = $conn->prepare("SELECT SUM(price * ilosc) AS previous_month_revenue FROM sales WHERE admin_username IN ($adminPlaceholders) AND sale_date BETWEEN ? AND ?");
    if ($previousMonthRevenueQuery === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $previousMonthRevenueQuery->bind_param($types, ...$params);
    $previousMonthRevenueQuery->execute();
    $previousMonthRevenueResult = $previousMonthRevenueQuery->get_result();
    $previousMonthRevenue = $previousMonthRevenueResult->fetch_assoc()['previous_month_revenue'] ?? 0;
}

$percentageChange = 0;
$revenueDifference = 0;
if ($previousMonthRevenue > 0) {
    $percentageChange = (($totalRevenuePeriod - $previousMonthRevenue) / $previousMonthRevenue) * 100;
    $revenueDifference = $totalRevenuePeriod - $previousMonthRevenue;
}

// Define the best seller variables with time filtering and added administrators
if ($useDateRange && $startDate && $endDate && !empty($adminsInStats)) {
    $adminPlaceholders = implode(',', array_fill(0, count($adminsInStats), '?'));
    $types = str_repeat('s', count($adminsInStats)) . 'ss';
    $params = array_merge($adminsInStats, [$startDate, $endDate]);

    $bestSellerQuery = $conn->prepare("SELECT admin_username, SUM(ilosc) AS total_sales FROM sales WHERE admin_username IN ($adminPlaceholders) AND sale_date BETWEEN ? AND ? GROUP BY admin_username ORDER BY total_sales DESC LIMIT 1");
    $bestSellerQuery->bind_param($types, ...$params);
} else if (!empty($adminsInStats)) {
    $adminPlaceholders = implode(',', array_fill(0, count($adminsInStats), '?'));
    $types = str_repeat('s', count($adminsInStats));
    $params = $adminsInStats;

    $bestSellerQuery = $conn->prepare("SELECT admin_username, SUM(ilosc) AS total_sales FROM sales WHERE admin_username IN ($adminPlaceholders) GROUP BY admin_username ORDER BY total_sales DESC LIMIT 1");
    $bestSellerQuery->bind_param($types, ...$params);
} else {
    $bestSellerQuery = $conn->prepare("SELECT admin_username, SUM(ilosc) AS total_sales FROM sales GROUP BY admin_username ORDER BY total_sales DESC LIMIT 1");
}
$bestSellerQuery->execute();
$bestSeller = $bestSellerQuery->get_result()->fetch_assoc();
$bestSellerName = $bestSeller['admin_username'] ?? 'Brak danych';
$bestSellerQuantity = $bestSeller['total_sales'] ?? 0;

// Define the highest sale variables with time filtering and selected period
if ($useDateRange && $startDate && $endDate) {
    $highestSaleQuery = $conn->prepare("SELECT id, price, buyer_name FROM sales WHERE sale_date BETWEEN ? AND ? ORDER BY price DESC LIMIT 1");
    $highestSaleQuery->bind_param("ss", $startDate, $endDate);
} else {
    $highestSaleQuery = $conn->prepare("SELECT id, price, buyer_name FROM sales ORDER BY price DESC LIMIT 1");
}
$highestSaleQuery->execute();
$highestSale = $highestSaleQuery->get_result()->fetch_assoc();
$highestSaleId = $highestSale['id'] ?? 0;
$highestSalePrice = $highestSale['price'] ?? 0;
$highestSaleBuyer = $highestSale['buyer_name'] ?? 'Brak danych';

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

// Define the total number of transactions
$totalTransactionsQuery = $conn->query("SELECT COUNT(*) AS total_transactions FROM sales");
$totalTransactions = $totalTransactionsQuery->fetch_assoc()['total_transactions'] ?? 0;

// Define the total number of ordered products
$totalOrderedProductsQuery = $conn->query("SELECT SUM(quantity) AS total_ordered_products FROM ProductExpenses");
$totalOrderedProducts = $totalOrderedProductsQuery->fetch_assoc()['total_ordered_products'] ?? 0;

// Fetch total revenue and percentage change for each admin with time filtering
$adminRevenues = [];
foreach ($adminsInStats as $admin) {
    if ($useDateRange && $startDate && $endDate) {
        $revenueQuery = $conn->prepare("SELECT SUM(price * ilosc) AS total_revenue FROM sales WHERE admin_username = ? AND sale_date BETWEEN ? AND ?");
        $revenueQuery->bind_param("sss", $admin, $startDate, $endDate);
    } else {
        $revenueQuery = $conn->prepare("SELECT SUM(price * ilosc) AS total_revenue FROM sales WHERE admin_username = ?");
        $revenueQuery->bind_param("s", $admin);
    }
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
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
<div class="test-version">Wersja testowa DASHBOARDU, możliwe błędy. Pozdro ;)</div>
    <header class="container py-3">
        <div class="profile-container">
            <div class="profile">
                <div class="initial"><?php echo $initial; ?></div>
                <div class="username"><?php echo htmlspecialchars($username); ?></div>
            </div>
            <button class="plus-button" onclick="showKomunikatyModal()">+</button>
        </div>
    </header>
    <div class="left-sidebar">
        <div>
            <div class="circle">R</div>
            <div class="dropdown">
                <span class="dropdown-toggle">Razowki.pl <span class="arrow" onclick="toggleDropdown()"></span></span>
                <div class="dropdown-menu">
                    <a href="#">Option 1</a>
                    <a href="#">Option 2</a>
                    <a href="#">Option 3</a>
                </div>
            </div>
            <a href="../admin/manage_sales.php" class="icon-circle"><i class="fas fa-shopping-cart"></i></a>
            <a href="../admin/manage_products.php" class="icon-circle"><i class="fas fa-box"></i></a>
            <a href="../admin/reserve.php" class="icon-circle"><i class="fas fa-warehouse"></i></a>
            <a href="../admin/sales_statistics.php" class="icon-circle"><i class="fas fa-chart-pie"></i></a>
            <a href="../admin/promotions.php" class="icon-circle"><i class="fas fa-dollar-sign"></i></a> <!-- Manage Sales link -->
            <div class="icon-circle" onclick="toggleStructure()"><i class="fas fa-sitemap"></i></div> <!-- Struktura icon -->
        </div>
        <div class="bottom-icons">
            <a href="../dashboard/powiadomienia.php" class="icon-circle">
                <i class="fas fa-comments"></i>
                <?php if ($newNotificationsCount > 0): ?>
                    <div class="notification-badge"></div>
                <?php endif; ?>
            </a> <!-- Chat icon -->
            <a href="../dashboard/ustawienia.php" class="icon-circle"><i class="fas fa-cog"></i></a> <!-- Settings icon -->
        </div>
    </div>
    <div id="structure" class="structure">
        <ul>
            <li><a href="../admin/manage_products.php"><i class="fas fa-box"></i> Manage Product</a></li>
            <li><a href="../admin/manage_sales.php"><i class="fas fa-chart-bar"></i> Manage Sales</a></li>
            <li><a href="../admin/reserve.php"><i class="fas fa-calendar-check"></i> Reserve</a></li>
            <li><a href="../admin/promotions.php"><i class="fas fa-tags"></i> Promotions</a></li>
            <li><a href="../admin/sales_statistics.php"><i class="fas fa-chart-line"></i> Sales Statistics</a></li>
            <li><a href="../admin/manage_orders.php"><i class="fas fa-boxes"></i> Manage Orders</a></li>
            <li><a href="../admin/analytics.php"><i class="fas fa-chart-pie"></i> Analytics</a></li>
        </ul>
    </div>
    <main class="container mt-4">
        <div class="gray-window">
            <div class="admin-list">
                <button class="plus-button" onclick="showAddAdminModal()">+</button>
                <?php foreach ($adminsInStats as $admin): ?>
                    <div class="admin">
                        <?php echo htmlspecialchars($admin); ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="admin_to_remove" value="<?php echo htmlspecialchars($admin); ?>">
                            <button type="submit" name="remove_admin_from_stats" class="remove-button">&times;</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="report-container">
                <p class="report-text">Raport</p>
                <div class="time-filter">
                    <form method="post" class="d-flex align-items-center">
                        <label for="use-date-range" class="form-label me-2">Użyj zakresu dat:</label>
                        <input type="checkbox" id="use-date-range" name="use_date_range" class="form-check-input me-2" <?php if ($useDateRange) echo 'checked'; ?>>
                        <div class="date-range <?php if (!$useDateRange) echo 'd-none'; ?>">
                            <label for="start-date" class="form-label me-2">Od:</label>
                            <input type="date" id="start-date" name="start_date" class="form-control me-2" value="<?php echo htmlspecialchars($startDate); ?>">
                            <label for="end-date" class="form-label me-2">Do:</label>
                            <input type="date" id="end-date" name="end_date" class="form-control me-2" value="<?php echo htmlspecialchars($endDate); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Filtruj</button>
                    </form>
                </div>
            </div>
            <div class="revenue-container">
                <div>
                    <p class="revenue-text">Całkowity zarobek</p>
                    <div class="d-flex align-items-center">
                        <p class="revenue-amount"><?php echo number_format($totalRevenuePeriod, 2, ',', ' '); ?> zł</p>
                        <div class="change-container ms-3">
                            <div class="change-box">
                                <p><?php echo number_format($percentageChange, 2, ',', ' '); ?>%</p>
                                <?php if ($percentageChange > 0): ?>
                                    <i class="fas fa-arrow-up arrow-up"></i>
                                <?php elseif ($percentageChange < 0): ?>
                                    <i class="fas fa-arrow-down arrow-down"></i>
                                <?php endif; ?>
                            </div>
                            <div class="change-box">
                                <p><?php echo number_format($revenueDifference, 2, ',', ' '); ?> zł</p>
                            </div>
                        </div>
                    </div>
                    <p class="comparison-period">Porównane do okresu: <?php echo date("Y-m-d", strtotime($previousMonthStart)); ?> - <?php echo date("Y-m-d", strtotime($previousMonthEnd)); ?></p>
                </div>
                <div class="large-white-box ms-3">
                    <p class="best-seller-text">Najlepszy sprzedawca</p>
                    <p class="sold-quantity"><?php echo $bestSellerQuantity; ?></p>
                    <div class="d-flex align-items-start">
                        <div class="seller-circle"><?php echo strtoupper($bestSellerName[0]); ?></div>
                        <p class="seller-name ms-2 align-self-center"><?php echo htmlspecialchars($bestSellerName); ?></p>
                        <a href="details.php?seller=<?php echo urlencode($bestSellerName); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" class="details-arrow ms-auto align-self-center">&rarr;</a>
                    </div>
                </div>
                <div class="small-black-box ms-3">
                    <p class="highest-sale-text">Największa sprzedaż</p>
                    <p class="sale-price"><?php echo number_format($highestDailySaleAmount, 2, ',', ' '); ?> zł</p>
                    <div class="d-flex align-items-start">
                        <p class="buyer-name">Kupujący: <?php echo htmlspecialchars($highestDailySaleBuyer); ?></p>
                        <a href="details.php?sale=<?php echo urlencode($highestSaleId); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" class="details-arrow ms-auto">&rarr;</a>
                    </div>
                </div>
                <div class="small-white-box ms-3">
                    <p class="transactions-text">Ilość transakcji</p>
                    <p class="total-transactions"><?php echo $totalTransactions; ?></p>
                </div>
                <div class="small-white-box ms-3">
                    <p class="ordered-products-text">Zamówione produkty</p>
                    <p class="total-ordered-products"><?php echo $totalOrderedProducts; ?></p>
                </div>
                <div class="small-white-box ms-3"></div>
            </div>
            <div class="d-flex align-items-center">
                <div class="wide-rectangle">
                    <?php foreach ($adminRevenues as $adminData): ?>
                        <div class="admin-square">
                            <div class="admin-initial"><?= strtoupper($adminData['admin'][0]); ?></div>
                            <div class="admin-revenue"><?= number_format($adminData['total_revenue'], 2, ',', ' '); ?> zł</div>
                            <div class="admin-percentage"><?= number_format($adminData['percentage_change'], 2, ',', ' '); ?>%</div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="details-seller.php?seller=<?php echo urlencode($bestSellerName); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" class="btn btn-dark ms-3 details-button">Szczegóły</a>
            </div>
            <div class="d-flex align-items-center mt-4">
                <div class="square-box">
                    <p class="square-text">Dodatkowe informacje</p>
                </div>
                <div class="square-box ms-3">
                    <p class="square-text">Więcej informacji</p>
                </div>
                <div class="large-square-box ms-3">
                    <div class="chart-dropdown">
                        <select id="chartType" onchange="updateChart()">
                            <option value="bestMonth">Najlepszy miesiąc sprzedaży</option>
                            <option value="highestSalesUnits">Największa sprzedaż w sztukach</option>
                            <option value="highestSalesValue">Największa sprzedaż w zł</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="icon-container">
                <div class="icon-circle"><i class="fas fa-cog"></i></div>
                <div class="icon-circle"><i class="fas fa-download"></i></div>
                <div class="icon-circle"><i class="fas fa-share-alt"></i></div>
            </div>
        </div>
    </main>

    <div id="addAdminModal" class="modal">
        <div class="modal-content">
            <button class="close-button" onclick="hideAddAdminModal()">&times;</button>
            <h5>Dodaj Administratora</h5>
            <form method="post">
                <div class="mb-3">
                    <label for="new_admin" class="form-label">Wybierz Administratora</label>
                    <select class="form-select" id="new_admin" name="admin_to_add" required>
                        <?php
                        // Fetch available admins to add
                        $availableAdminsQuery = $conn->query("SELECT username FROM admini");
                        while ($availableAdmin = $availableAdminsQuery->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($availableAdmin['username']); ?>"><?php echo htmlspecialchars($availableAdmin['username']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" name="add_admin_to_stats" class="btn btn-primary">Dodaj</button>
            </form>
        </div>
    </div>

    <div id="komunikatyModal" class="modal">
        <div class="modal-content">
            <button class="close-button" onclick="hideKomunikatyModal()">&times;</button>
            <h5>Komunikaty</h5>
            <section class="mt-4">
                <h3>Dodaj Komunikat</h3>
                <form method="post">
                    <div class="mb-3">
                        <label for="message" class="form-label">Treść komunikatu</label>
                        <textarea class="form-control" id="message" name="message" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">Rodzaj</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="info">Informacja</option>
                            <option value="promotion">Promocja</option>
                            <option value="alert">Alert</option>
                            <option value="warning">Ostrzeżenie</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="background_color" class="form-label">Kolor tła</label>
                        <input type="color" class="form-control" id="background_color" name="background_color" required>
                    </div>
                    <button type="submit" name="add_message" class="btn btn-primary">Dodaj Komunikat</button>
                </form>
            </section>

            <section class="mt-4">
                <h3>Komunikaty</h3>
                <ul class="list-group">
                    <?php while ($row = $messagesQuery->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center" style="background-color: <?php echo htmlspecialchars($row['background_color']); ?>; color: <?php echo htmlspecialchars($row['text_color']); ?>;">
                            <span><?php echo htmlspecialchars($row['message']); ?></span>
                            <div>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($row['type']); ?></span>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="message_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="delete_message" class="btn btn-danger btn-sm">Usuń</button>
                                </form>
                            </div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </section>
            <button type="button" class="btn btn-secondary mt-3" onclick="hideKomunikatyModal()">Zamknij</button>
        </div>
    </div>

    <script>
        function showAddAdminModal() {
            document.getElementById('addAdminModal').style.display = 'flex';
        }

        function hideAddAdminModal() {
            document.getElementById('addAdminModal').style.display = 'none';
        }

        function removeAdmin(username) {
            if (confirm('Czy na pewno chcesz usunąć tego administratora?')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `<input type="hidden" name="admin_to_remove" value="${username}">`;
                form.innerHTML += `<input type="hidden" name="remove_admin_from_stats" value="1">`;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showModal(statType) {
            document.getElementById('statType').value = statType;
            document.getElementById('confirmationModal').style.display = 'flex';
        }

        function hideModal() {
            document.getElementById('confirmationModal').style.display = 'none';
        }

        function showKomunikatyModal() {
            document.getElementById('komunikatyModal').style.display = 'flex';
        }

        function hideKomunikatyModal() {
            document.getElementById('komunikatyModal').style.display = 'none';
        }

        function toggleDropdown() {
            document.querySelector('.dropdown').classList.toggle('open');
        }

        function toggleStructure() {
            document.getElementById('structure').classList.toggle('open');
        }

        function toggleDateRange() {
            document.querySelector('.date-range').classList.toggle('open');
        }

        const ctx = document.getElementById('salesChart').getContext('2d');
        let salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [], // Placeholder for labels
                datasets: [{
                    label: 'Dane sprzedaży',
                    data: [], // Placeholder for data
                    backgroundColor: 'rgba(28, 146, 210, 0.5)',
                    borderColor: 'rgba(28, 146, 210, 1)',
                    borderWidth: 1,
                    borderRadius: 5,
                    hoverBackgroundColor: 'rgba(28, 146, 210, 0.7)',
                    hoverBorderColor: 'rgba(28, 146, 210, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.7)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#1c92d2',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#333'
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            color: '#333'
                        }
                    }
                }
            }
        });

        function updateChart() {
            const chartType = document.getElementById('chartType').value;
            // Fetch data based on selected chart type
            fetch(`fetch_chart_data.php?type=${chartType}`)
                .then(response => response.json())
                .then(data => {
                    salesChart.data.labels = data.labels;
                    salesChart.data.datasets[0].data = data.values;
                    salesChart.update();
                });
        }

        // Initial chart update
        document.addEventListener('DOMContentLoaded', updateChart);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
