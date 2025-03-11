<?php
require '../includes/db.php';

$type = $_GET['type'];
$data = [
    'labels' => [],
    'values' => []
];

switch ($type) {
    case 'bestMonth':
        $query = $conn->query("SELECT DATE_FORMAT(sale_date, '%Y-%m') AS month, SUM(price * ilosc) AS total_sales FROM sales GROUP BY month ORDER BY total_sales DESC LIMIT 1");
        while ($row = $query->fetch_assoc()) {
            $data['labels'][] = $row['month'];
            $data['values'][] = $row['total_sales'];
        }
        break;
    case 'highestSalesUnits':
        $query = $conn->query("SELECT product_name, SUM(ilosc) AS total_units FROM sales GROUP BY product_name ORDER BY total_units DESC LIMIT 1");
        while ($row = $query->fetch_assoc()) {
            $data['labels'][] = $row['product_name'];
            $data['values'][] = $row['total_units'];
        }
        break;
    case 'highestSalesValue':
        $query = $conn->query("SELECT product_name, SUM(price * ilosc) AS total_value FROM sales GROUP BY product_name ORDER BY total_value DESC LIMIT 1");
        while ($row = $query->fetch_assoc()) {
            $data['labels'][] = $row['product_name'];
            $data['values'][] = $row['total_value'];
        }
        break;
}

header('Content-Type: application/json');
echo json_encode($data);
?>
