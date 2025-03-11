<?php
session_start();
require './includes/db.php'; // Załaduj plik z połączeniem do bazy danych

// Sprawdzenie połączenia
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Pobierz dane o wizycie
$ipAddress = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT']; // Informacja o przeglądarce
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''; // Strona odsyłająca

// Zapisz wizytę w tabeli 'visits'
$insertVisitQuery = $conn->prepare("INSERT INTO visits (ip_address, user_agent, referer) VALUES (?, ?, ?)");
$insertVisitQuery->bind_param("sss", $ipAddress, $userAgent, $referer);
$insertVisitQuery->execute();

// Dodaj powiadomienie dla administratora
$message = "Nowa wizyta z IP: $ipAddress";
$type = "visit";
$insertNotificationQuery = $conn->prepare("INSERT INTO notifications (message, type) VALUES (?, ?)");
$insertNotificationQuery->bind_param("ss", $message, $type);
$insertNotificationQuery->execute();

// Przygotowanie zapytania SQL do pobrania wszystkich dostępnych produktów
$sql = "SELECT p.*, pr.rabat, COALESCE(SUM(r.quantity), 0) AS reserved_quantity
        FROM produkty p 
        LEFT JOIN promotions pr ON p.id = pr.produkt_id 
        LEFT JOIN reservations r ON p.id = r.product_id
        WHERE p.ilosc > 0
        GROUP BY p.id"; // Pobierz tylko dostępne produkty
$products_result = $conn->query($sql);

// Nowe zapytania do nowości i bestsellerów, tylko dla dostępnych produktów
$new_sql = "SELECT p.*, pr.rabat, COALESCE(SUM(r.quantity), 0) AS reserved_quantity
            FROM produkty p 
            LEFT JOIN promotions pr ON p.id = pr.produkt_id 
            LEFT JOIN reservations r ON p.id = r.product_id
            WHERE p.ilosc > 0 
            GROUP BY p.id
            ORDER BY p.data_dodania DESC"; // Nowości
$new_products_result = $conn->query($new_sql);

// Zapytanie do bestsellerów z tabeli sales
$best_sql = "
    SELECT p.*, COALESCE(SUM(s.ilosc), 0) AS total_sold, pr.rabat, COALESCE(SUM(r.quantity), 0) AS reserved_quantity
    FROM produkty p
    LEFT JOIN sales s ON p.id = s.product_id 
    LEFT JOIN promotions pr ON p.id = pr.produkt_id 
    LEFT JOIN reservations r ON p.id = r.product_id
    WHERE p.ilosc > 0 
    GROUP BY p.id 
    ORDER BY total_sold DESC 
    LIMIT 5"; // Bestsellery
$best_products_result = $conn->query($best_sql);

// Fetch active messages
$activeMessagesQuery = $conn->query("SELECT * FROM komunikaty WHERE is_active = 1 ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mylead-verification" content="e7f20184bc965957e44e14a0fe0f9fc9">
    <!-- mylead-verification: e7f20184bc965957e44e14a0fe0f9fc9 -->
    <title>Sklep Jednorazówki - Dostępne Produkty</title>
    <link rel="preload" href="main.css" as="style">
    <link rel="stylesheet" href="main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="icon" href="./uploads/Watermelon-Ice.jpg" type="image/jpg">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .product-card {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .product-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
            padding: 10px;
            transform: translateY(100%);
            transition: transform 0.3s ease-in-out;
        }

        .product-card:hover .product-info {
            transform: translateY(0);
        }

        .unavailable-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 0, 0, 0.5);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            z-index: 1;
            transition: opacity 0.3s ease-in-out;
        }

        .product-card:hover .unavailable-overlay {
            opacity: 0;
        }

        .discount-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: red;
            color: white;
            padding: 5px;
            font-size: 14px;
            font-weight: bold;
            z-index: 2;
        }

        .reservation-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: yellow;
            color: black;
            padding: 5px;
            font-size: 14px;
            font-weight: bold;
            z-index: 2;
        }

        .old-price {
            text-decoration: line-through;
            color: grey;
        }

        .dark-mode {
            background-color: #121212;
            color: #ffffff;
        }
        .dark-mode header {
            background-color: #1e1e1e;
        }
        .dark-mode .product-card {
            background-color: #1e1e1e;
            border-color: #333;
        }
        .dark-mode .product-info {
            background: rgba(255, 255, 255, 0.7);
            color: #000;
        }
        .dark-mode .btn-secondary {
            background-color: #007bff;
        }
        .dark-mode .btn-secondary:hover {
            background-color: #0056b3;
        }

        .dark-mode .search-bar input,
        .dark-mode .filter-container select {
            background-color: #333;
            color: #fff;
            border: 1px solid #555;
        }

        .dark-mode-toggle {
            position: fixed;
            top: 60px; /* Ensure it does not overlap with the navbar */
            right: 20px;
            background-color: #333;
            color: #fff;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            z-index: 1001; /* Ensure it is above other elements */
        }

        .dark-mode-toggle i {
            margin-right: 10px;
        }

        .dark-mode-toggle:hover {
            background-color: #555;
        }

        @media (max-width: 768px) {
            .dark-mode-toggle {
                top: auto;
                bottom: 20px;
            }

            header nav {
                flex-direction: column;
                align-items: flex-start;
            }

            header .menu {
                flex-direction: column;
                width: 100%;
            }

            header .menu li {
                margin: 5px 0;
            }

            .product-container {
                flex-direction: column;
                align-items: center;
            }

            .product-card {
                width: 90%; /* Zajmij 90% szerokości ekranu */
                margin: 10px 0;
            }

            #orderForm {
                width: 95%;
            }

            #darkModeToggle {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #333;
                color: #fff;
                padding: 10px;
                border-radius: 50%;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
                z-index: 1000;
                cursor: pointer;
            }

            .product-info {
                transform: translateY(0); /* Ensure product info is always visible */
            }
        }

        @media (max-width: 480px) {
            header .logo a {
                font-size: 20px;
            }

            .product-card {
                width: 100%;
            }

            #orderForm {
                width: 100%;
            }

            #darkModeToggle {
                bottom: 15px;
                right: 15px;
                padding: 8px;
            }

            .product-info {
                transform: translateY(0); /* Ensure product info is always visible */
            }
        }

        /* iPhone-specific styles */
        body.iphone {
            font-size: 18px;
        }

        body.iphone header .logo a {
            font-size: 22px;
        }

        body.iphone .product-card {
            padding: 10px;
            max-width: 100%;
        }

        body.iphone .product-info {
            font-size: 16px;
        }

        /* Modern UI enhancements */
        .btn-secondary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 25px;
            transition: background 0.3s ease;
        }

        .btn-secondary:hover {
            background: linear-gradient(45deg, #0056b3, #003f7f);
        }

        footer {
            background-color: #333;
            color: #fff;
            padding: 40px 20px;
            text-align: center;
            position: relative;
        }

        footer .social-icons {
            margin: 20px 0;
        }

        footer .social-icons a {
            color: #fff;
            margin: 0 10px;
            font-size: 24px;
            transition: color 0.3s;
        }

        footer .social-icons a:hover {
            color: #ddd;
        }

        footer .footer-links {
            margin: 20px 0;
        }

        footer .footer-links a {
            color: #fff;
            text-decoration: none;
            margin: 0 15px;
            transition: color 0.3s;
        }

        footer .footer-links a:hover {
            color: #ddd;
        }

        footer .footer-logo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        footer .footer-logo img {
            width: 50px;
            vertical-align: middle;
            margin-right: 10px;
        }

        footer .footer-bottom {
            margin-top: 20px;
            font-size: 14px;
            color: #aaa;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-body {
            padding: 10px 0;
        }

        body.modal-open {
            overflow: hidden;
        }

        .product-image {
            width: auto;
            height: auto; /* Adjust height to auto for better responsiveness */
            max-height: 250px; /* Set a maximum height */
            object-fit: cover; /* Ensure the image covers the area */
            border-radius: 8px;
        }

        .search-bar {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .search-bar input {
            width: 80%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 16px;
        }

        .filter-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }

        .filter-container select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 16px;
        }

        .notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #333;
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
            display: none;
            z-index: 1000;
        }

        .notification.show {
            display: block;
        }
        .dark-mode-toggle {
            position: fixed;
            top: 60px; /* Ensure it does not overlap with the navbar */
            right: 20px;
            background-color: #333;
            color: #fff;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            z-index: 1001; /* Ensure it is above other elements */
        }

        .dark-mode-toggle i {
            margin-right: 10px;
        }

        .dark-mode-toggle:hover {
            background-color: #555;
        }

        @media (max-width: 768px) {
            .dark-mode-toggle {
                top: auto;
                bottom: 20px;
            }
        }

        @media (max-width: 480px) {
            .dark-mode-toggle {
                bottom: 15px;
                right: 15px;
                padding: 0px;
            }
        }

        .dark-mode {
            background: linear-gradient(135deg, #2c3e50, #4ca1af);
            color: #fff;
        }

        .dark-mode .faq-section, .dark-mode .contact-section {
            background: rgba(0, 0, 0, 0.8);
            color: #fff;
        }

        .dark-mode .faq-item h3 {
            background-color: #444;
        }

        .dark-mode .faq-item p {
            background-color: #333;
            border: 1px solid #555;
        }

        .age-verification-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .age-verification-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 400px;
            border-radius: 8px;
            text-align: center;
        }

        .age-verification-content h2 {
            margin: 0 0 20px;
        }

        .age-verification-content button {
            margin: 10px;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .age-verification-content .yes-button {
            background-color: #4CAF50;
            color: white;
        }

        .age-verification-content .yes-button:hover {
            background-color: #45a049;
        }

        .age-verification-content .no-button {
            background-color: #f44336;
            color: white;
        }

        .age-verification-content .no-button:hover {
            background-color: #e53935;
        }

        .cookie-consent-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: #333;
            color: #fff;
            padding: 10px 20px;
            text-align: center;
            z-index: 1000;
            display: none;
        }

        .cookie-consent-banner button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            margin-left: 10px;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .cookie-consent-banner button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <?php while ($row = $activeMessagesQuery->fetch_assoc()): ?>
        <div class="notification-banner" style="background-color: <?php echo htmlspecialchars($row['background_color']); ?>; color: <?php echo htmlspecialchars($row['text_color']); ?>; width: 100%;">
            <div class="notification-content" style="text-align: center;">
                <span class="notification-text"><?php echo htmlspecialchars($row['message']); ?></span>
                <span class="close-btn" onclick="this.parentElement.parentElement.style.display='none';">&times;</span>
            </div>
        </div>
    <?php endwhile; ?>
    <button class="dark-mode-toggle" id="darkModeToggle"><i class="fas fa-moon"></i>Dark Mode</button>
    <div class="notification" id="notification">Dark mode enabled</div>
    <div id="ageVerificationModal" class="age-verification-modal">
        <div class="age-verification-content">
            <h2>Czy masz 18 lat?</h2>
            <button class="yes-button" onclick="acceptAge()">Tak</button>
            <button class="no-button" onclick="declineAge()">Nie</button>
        </div>
    </div>
    <div class="cookie-consent-banner" id="cookieConsentBanner">
        Ta strona używa plików cookies, aby zapewnić najlepszą jakość korzystania z naszej strony. 
        <button onclick="acceptCookies()">Akceptuję</button>
    </div>
    <!-- Nagłówek -->
    <header>
        <nav>
            <div class="logo">
                <a>Jednorazówki</a>
            </div>
            <ul class="menu">
                <li><a href="#"><i class="fas fa-home"></i> Strona główna</a></li>
                <li><a href="#new-products"><i class="fas fa-star"></i> Nowości</a></li>
                <li><a href="#best-sellers"><i class="fas fa-chart-line"></i> Bestsellery</a></li>
                <li><a href="#unavailable-products"><i class="fas fa-ban"></i> Niedostępne</a></li>
                <li><a href="../admin/kontakt.php"><i class="fas fa-envelope"></i> Kontakt</a></li>
                <li><a href="../auth/login.php"><i class="fas fa-sign-in-alt"></i> Zaloguj się</a></li>
                <li><a href="#" id="searchIcon"><i class="fas fa-search"></i></a></li>
                <li><a href="#" id="filterIcon"><i class="fas fa-filter"></i></a></li>
            </ul>
        </nav>
    </header>

    <!-- Search Bar -->
    <div class="search-bar" id="searchBar" style="display: none;">
        <input type="text" id="searchInput" placeholder="Szukaj produktów...">
    </div>

    <!-- Filter Container -->
    <div class="filter-container" id="filterContainer" style="display: none;">
        <select id="filterSelect">
            <option value="all">Wszystkie</option>
            <option value="new">Nowości</option>
            <option value="best">Bestsellery</option>
            <option value="unavailable">Niedostępne</option>
        </select>
    </div>

    <!-- Sekcja główna -->
    <main>
        <section class="products" id="new-products">
            <h2>Nowości</h2>
            <div class="product-container">
                <?php if ($new_products_result->num_rows > 0): ?>
                    <?php while ($row = $new_products_result->fetch_assoc()): ?>
                        <div class="product-card">
                            <?php if ($row['rabat']): ?>
                                <div class="discount-badge"><?php echo $row['rabat']; ?>% OFF</div>
                            <?php endif; ?>
                            <?php if ($row['reserved_quantity'] > 0): ?>
                                <div class="reservation-badge"><?php echo $row['reserved_quantity']; ?> zarezerwowane</div>
                            <?php endif; ?>
                            <img src="../uploads/<?php echo htmlspecialchars($row['zdjecie']); ?>" alt="<?php echo htmlspecialchars($row['nazwa']); ?>" class="product-image" loading="lazy">
                            <div class="product-info">
                                <h3><?php echo htmlspecialchars($row['nazwa']); ?></h3>
                                <?php if ($row['rabat']): ?>
                                    <p>Cena: <span class="old-price"><?php echo number_format($row['cena'], 2); ?> PLN</span> <strong><?php echo number_format($row['cena'] * (1 - $row['rabat'] / 100), 2); ?> PLN</strong></p>
                                <?php else: ?>
                                    <p>Cena: <strong><?php echo number_format($row['cena'], 2); ?> PLN</strong></p>
                                <?php endif; ?>
                                <p class="product-flavor">Smak: <strong><?php echo htmlspecialchars($row['smak']); ?></strong></p>
                                <p>Dostępność: <strong><?php echo $row['ilosc'] . ' szt.'; ?></strong></p>
                                <p>Zarezerwowane: <strong><?php echo $row['reserved_quantity'] . ' szt.'; ?></strong></p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>Brak dostępnych produktów.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="products" id="best-sellers">
            <h2>Bestsellery</h2>
            <div class="product-container">
                <div class="ad-banner">
                    <a href="https://lowest-prices.eu/a/3JkBfRrmpIqqnE" target="_blank">
                        <img src="https://mir-s3-cdn-cf.behance.net/project_modules/hd/3c171269825617.5b8ee494d1541.jpg" alt="Adidas Banner" class="ad-image">
                    </a>
                </div>
            </div>
        </section>

        <!-- Sekcja niedostępnych produktów -->
        <section class="products" id="unavailable-products">
            <h2>Niedostępne Produkty</h2>
            <div class="product-container">
                <?php
                // Pobierz tylko niedostępne produkty
                $unavailable_sql = "SELECT * FROM produkty WHERE ilosc = 0"; // Niedostępne produkty
                $unavailable_products_result = $conn->query($unavailable_sql);
                ?>
                <?php if ($unavailable_products_result->num_rows > 0): ?>
                    <?php while ($row = $unavailable_products_result->fetch_assoc()): ?>
                        <div class="product-card" style="position: relative;"> <!-- Ustawienie pozycji -->
                            <div class="unavailable-overlay">Niedostępne</div>
                            <img src="../uploads/<?php echo htmlspecialchars($row['zdjecie']); ?>" alt="<?php echo htmlspecialchars($row['nazwa']); ?>" class="product-image" loading="lazy">
                            <div class="product-info">
                                <h3><?php echo htmlspecialchars($row['nazwa']); ?></h3>
                                <p>Cena: <strong><?php echo number_format($row['cena'], 2); ?> PLN</strong></p>
                                <p class="product-flavor">Smak: <strong><?php echo htmlspecialchars($row['smak']); ?></strong></p>
                                <p>Dostępność: <strong style="color: red;">Niedostępne</strong></p> <!-- Ustawienie koloru na czerwony -->
                                <button class="btn-secondary" onclick="openOrderForm(<?php echo $row['id']; ?>)">Zamów teraz</button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>Brak niedostępnych produktów.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Formularz zamówienia -->
    <div id="orderForm" style="display:none;">
        <div class="form-content">
            <h3>Składanie zamówienia</h3>
            <form id="orderFormElement" action="../admin/order_process.php" method="POST">
                <input type="hidden" name="product_id" id="product_id" value=""/>
                <label for="name">Imię:</label>
                <input type="text" name="name" required>
                <label for="surname">Nazwisko:</label>
                <input type="text" name="surname" required>
                <label for="quantity">Ilość:</label>
                <input type="number" name="quantity" min="1" required>
                <button type="submit">Złóż zamówienie</button>
                <button type="button" onclick="closeOrderForm()">Anuluj</button>
            </form>
        </div>
    </div>

    <!-- Modal for Privacy Policy -->
    <div id="privacyPolicyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Polityka Prywatności</h2>
                <span class="close" onclick="closeModal('privacyPolicyModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Twoja prywatność jest dla nas bardzo ważna. Niniejsza polityka prywatności wyjaśnia, jakie dane osobowe zbieramy, jak je wykorzystujemy i jakie masz prawa w związku z tymi danymi.</p>
                <h3>Jakie dane zbieramy?</h3>
                <p>Zbieramy różne rodzaje danych, w tym:</p>
                <ul>
                    <li>Dane są zbierane tylko wtedy, gdy jest to niezbędne do realizacji zamówienia. Nie zbieramy danych osobowych od osób niepełnoletnich i nie zbieramy ich bez świadomości klientów.</li>
                    <li>Dane kontaktowe, takie jak imię, nazwisko, adres e-mail, numer telefonu.</li>
                    <li>Dane dotyczące korzystania z naszej strony internetowej, czas spędzony na stronie.</li>
                </ul>
                <h3>Jak wykorzystujemy Twoje dane?</h3>
                <p>Twoje dane osobowe wykorzystujemy w celu:</p>
                <ul>
                    <li>Świadczenia naszych usług i realizacji zamówień.</li>
                    <li>Komunikacji z Tobą, w tym wysyłania powiadomień i ofert promocyjnych.</li>
                    <li>Analizy i poprawy naszych usług oraz strony internetowej.</li>
                </ul>
                <h3>Twoje prawa</h3>
                <p>Masz prawo do:</p>
                <ul>
                    <li>Dostępu do swoich danych osobowych.</li>
                    <li>Poprawiania swoich danych osobowych.</li>
                    <li>Usunięcia swoich danych osobowych.</li>
                    <li>Ograniczenia przetwarzania swoich danych osobowych.</li>
                    <li>Sprzeciwu wobec przetwarzania swoich danych osobowych.</li>
                </ul>
                <p>Jeśli masz jakiekolwiek pytania dotyczące naszej polityki prywatności, skontaktuj się z nami pod adresem <a href="mailto:kontakt@jednorazowki.pl">kontakt@jednorazowki.pl</a>.</p>
            </div>
        </div>
    </div>

    <!-- Modal for Terms of Service -->
    <div id="termsOfServiceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Warunki Korzystania z Usługi</h2>
                <span class="close" onclick="closeModal('termsOfServiceModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Niniejsze warunki korzystania z usługi określają zasady i warunki korzystania z naszej strony internetowej oraz usług oferowanych przez naszą firmę.</p>
                <h3>Akceptacja warunków</h3>
                <p>Korzystając z naszej strony internetowej, akceptujesz niniejsze warunki korzystania z usługi. Jeśli nie zgadzasz się z którymkolwiek z warunków, prosimy o niekorzystanie z naszej strony.</p>
                <h3>Zmiany w warunkach</h3>
                <p>Zastrzegamy sobie prawo do wprowadzania zmian w niniejszych warunkach korzystania z usługi w dowolnym czasie. Zmiany te będą publikowane na naszej stronie internetowej, a dalsze korzystanie z naszych usług będzie oznaczać akceptację tych zmian.</p>
                <h3>Korzystanie z usług</h3>
                <p>Użytkownik zobowiązuje się do korzystania z naszych usług zgodnie z obowiązującym prawem oraz niniejszymi warunkami. Zabrania się korzystania z naszych usług w sposób naruszający prawa innych osób lub w sposób niezgodny z prawem.</p>
                <h3>Ograniczenie odpowiedzialności</h3>
                <p>Nasza firma nie ponosi odpowiedzialności za jakiekolwiek szkody wynikające z korzystania z naszej strony internetowej lub usług, w tym za utratę danych, zysków lub innych strat.</p>
                <p>Jeśli masz jakiekolwiek pytania dotyczące naszych warunków korzystania z usługi, skontaktuj się z nami pod adresem <a href="mailto:kontakt@jednorazowki.pl">kontakt@jednorazowki.pl</a>.</p>
            </div>
        </div>
    </div>

    <!-- Stopka -->
    <footer>
        <div class="footer-logo">
            <img src="./uploads/Watermelon-Ice.jpg" alt="Logo"> Jednorazówki
        </div>
        <div class="social-icons">
            <a href="https://t.me/razowkipl" target="_blank"><i class="fab fa-telegram-plane"></i></a>
        </div>
        <div class="footer-links">
            <a href="#" onclick="openModal('privacyPolicyModal')">Polityka prywatności</a>
            <a href="#" onclick="openModal('termsOfServiceModal')">Warunki korzystania z usługi</a>
            <a href="../admin/kontakt.php">Kontakt</a>
        </div>
        <div class="footer-bottom">
            &copy; 2024 Sklep Jednorazówki. Wszystkie prawa zastrzeżone.
            <p>e7f20184bc965957e44e14a0fe0f9fc9</p>
        </div>
    </footer>

    <script>
        function openOrderForm(productId) {
            document.getElementById('product_id').value = productId;
            document.getElementById('orderForm').style.display = 'block';
        }

        function closeOrderForm() {
            document.getElementById('orderForm').style.display = 'none';
        }

        document.getElementById('darkModeToggle').addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const notification = document.getElementById('notification');
            notification.textContent = document.body.classList.contains('dark-mode') ? 'Dark mode włączony' : 'Dark mode wyłączony';
            notification.classList.add('show');
            setTimeout(() => {
                notification.classList.remove('show');
            }, 2000);

            // Apply dark mode to search bar and filter container
            const searchBar = document.getElementById('searchBar');
            const filterContainer = document.getElementById('filterContainer');
            if (document.body.classList.contains('dark-mode')) {
                searchBar.classList.add('dark-mode');
                filterContainer.classList.add('dark-mode');
            } else {
                searchBar.classList.remove('dark-mode');
                filterContainer.classList.remove('dark-mode');
            }
        });

        function isIphone() {
            return /iPhone/.test(navigator.userAgent);
        }

        if (isIphone()) {
            document.body.classList.add('iphone');
        }

        // Auto-refresh functionality
        document.getElementById('orderFormElement').addEventListener('submit', function() {
            setTimeout(function() {
                location.reload();
            }, 1000); // Adjust the delay as needed
        });

        // Modal functionality
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.classList.add('modal-open');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.classList.remove('modal-open');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchValue = this.value.toLowerCase();
            const productCards = document.querySelectorAll('.product-card');

            productCards.forEach(function(card) {
                const productName = card.querySelector('h3').textContent.toLowerCase();
                const productFlavor = card.querySelector('.product-flavor strong').textContent.toLowerCase();
                if (productName.includes(searchValue) || productFlavor.includes(searchValue)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Filter functionality
        document.getElementById('filterSelect').addEventListener('change', function() {
            const filterValue = this.value;
            const newProductsSection = document.getElementById('new-products');
            const bestSellersSection = document.getElementById('best-sellers');
            const unavailableProductsSection = document.getElementById('unavailable-products');

            if (filterValue === 'all') {
                newProductsSection.style.display = 'block';
                bestSellersSection.style.display = 'block';
                unavailableProductsSection.style.display = 'block';
            } else if (filterValue === 'new') {
                newProductsSection.style.display = 'block';
                bestSellersSection.style.display = 'none';
                unavailableProductsSection.style.display = 'none';
            } else if (filterValue === 'best') {
                newProductsSection.style.display = 'none';
                bestSellersSection.style.display = 'block';
                unavailableProductsSection.style.display = 'none';
            } else if (filterValue === 'unavailable') {
                newProductsSection.style.display = 'none';
                bestSellersSection.style.display = 'none';
                unavailableProductsSection.style.display = 'block';
            }
        });

        // Toggle search bar
        document.getElementById('searchIcon').addEventListener('click', function() {
            const searchBar = document.getElementById('searchBar');
            const filterContainer = document.getElementById('filterContainer');
            if (searchBar.style.display === 'none' || searchBar.style.display === '') {
                searchBar.style.display = 'block';
                filterContainer.style.display = 'none'; // Hide filter container
            } else {
                searchBar.style.display = 'none';
            }
        });

        // Toggle filter container
        document.getElementById('filterIcon').addEventListener('click', function() {
            const filterContainer = document.getElementById('filterContainer');
            const searchBar = document.getElementById('searchBar');
            if (filterContainer.style.display === 'none' || filterContainer.style.display === '') {
                filterContainer.style.display = 'block';
                searchBar.style.display = 'none'; // Hide search bar
            } else {
                filterContainer.style.display = 'none';
            }
        });

        function acceptAge() {
            document.getElementById('ageVerificationModal').style.display = 'none';
            localStorage.setItem('ageVerified', 'true');
        }

        function declineAge() {
            window.location.href = 'https://www.google.com';
        }

        function acceptCookies() {
            document.getElementById('cookieConsentBanner').style.display = 'none';
            localStorage.setItem('cookiesAccepted', 'true');
        }

        window.onload = function() {
            if (!localStorage.getItem('cookiesAccepted')) {
                document.getElementById('cookieConsentBanner').style.display = 'block';
            }
            if (!localStorage.getItem('ageVerified')) {
                document.getElementById('ageVerificationModal').style.display = 'block';
            }
        };
    </script>
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-3068135407934946" crossorigin="anonymous"></script>
</body>
</html>

<?php
// Zamknij połączenie z bazą danych
$conn->close();
?>
