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

// Database connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $expense_date = $_POST['expense_date'];
    $total_amount = $_POST['total_amount'];
    $quantity = $_POST['quantity'];

    $sql = "INSERT INTO ProductExpenses (expense_date, total_amount, quantity) VALUES ('$expense_date', '$total_amount', '$quantity')";

    if ($conn->query($sql) === TRUE) {
        echo "New record created successfully";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

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

$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Product Expenses</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 50%;
            margin: 50px auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        label {
            margin-bottom: 5px;
            color: #555;
        }
        input[type="date"],
        input[type="number"],
        input[type="submit"] {
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        input[type="submit"] {
            background-color: #5cb85c;
            color: white;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #4cae4c;
        }
        .stats {
            margin-top: 20px;
        }
        .stats p {
            font-size: 1.2em;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table, th, td {
            border: 1px solid #ccc;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Wydatki na zamówienia</h2>
        <form method="post" action="">
            <label for="expense_date">Data:</label>
            <input type="date" id="expense_date" name="expense_date" required><br><br>
            <label for="total_amount">Całkowita cena:</label>
            <input type="number" step="0.01" id="total_amount" name="total_amount" required><br><br>
            <label for="quantity">Ilość:</label>
            <input type="number" id="quantity" name="quantity" required><br><br>
            <input type="submit" value="Submit">
        </form>

        <div class="stats">
            <p>Całkowita kwota wydana na wszystkie zamówienia: <strong><?php echo number_format($total_spent, 2); ?> PLN</strong></p>
            <p>Średnia cena produktów: <strong><?php echo number_format($avg_price, 2); ?> PLN</strong></p>
            <p>Całkowita ilość zamówionych produktów: <strong><?php echo $total_quantity; ?> sztuk</strong></p>
        </div>

        <h2>Szczegółty zamówienia</h2>
        <table>
            <tr>
                <th>Data zamówienia</th>
                <th>Kwota zamówienia (PLN)</th>
                <th>Ilość produktów</th>
            </tr>
            <?php while ($row = $orders->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['expense_date']; ?></td>
                    <td><?php echo number_format($row['total_amount'], 2); ?></td>
                    <td><?php echo $row['quantity']; ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
