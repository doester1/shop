<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kontakt</title>
    <link rel="stylesheet" href="../main.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background 0.3s, color 0.3s;
        }

        .contact-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            flex: 1;
            max-width: 1200px;
            margin: 0 auto;
            flex-wrap: wrap;
        }

        .faq-section, .contact-section {
            width: 48%;
            overflow-y: auto;
            background: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: background 0.3s, color 0.3s;
            margin-bottom: 20px;
        }

        .faq-section h2, .contact-section h2 {
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .faq-section h2 i, .contact-section h2 i {
            margin-right: 10px;
        }

        .faq-item {
            margin-bottom: 15px;
        }

        .faq-item h3 {
            font-size: 18px;
            margin-bottom: 5px;
            cursor: pointer;
            background-color: #f1f1f1;
            padding: 10px;
            border-radius: 5px;
            transition: background 0.3s;
            display: flex;
            align-items: center;
        }

        .faq-item h3 i {
            margin-right: 10px;
        }

        .faq-item p {
            font-size: 16px;
            margin: 0;
            display: none;
            padding: 10px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 5px 5px;
            transition: background 0.3s, border-color 0.3s;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .contact-info p {
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .contact-info p i {
            margin-right: 10px;
        }

        .contact-info img {
            max-width: 200px;
            margin-top: 20px;
        }

        .contact-button {
            background-color: #333;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 10px;
            transition: background 0.3s;
            display: flex;
            align-items: center;
        }

        .contact-button i {
            margin-right: 10px;
        }

        .contact-button:hover {
            background-color: #555;
        }

        .dark-mode-toggle {
            position: fixed;
            top: 20px;
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
        }

        .dark-mode-toggle i {
            margin-right: 10px;
        }

        .dark-mode-toggle:hover {
            background-color: #555;
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

        footer {
            background-color: #333;
            color: #fff;
            padding: 20px;
            text-align: center;
        }

        footer .footer-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }

        footer .footer-section {
            width: 30%;
            margin-bottom: 20px;
            text-align: center;
        }

        footer .footer-section h3 {
            font-size: 18px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        footer .footer-section h3 i {
            margin-right: 10px;
        }

        footer .footer-section p, footer .footer-section a {
            font-size: 14px;
            color: #fff;
            text-decoration: none;
        }

        footer .footer-section a:hover {
            text-decoration: underline;
        }

        .scroll-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background-color: #333;
            color: #fff;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: none;
            transition: background 0.3s;
            display: flex;
            align-items: center;
        }

        .scroll-to-top i {
            margin-right: 10px;
        }

        .scroll-to-top:hover {
            background-color: #555;
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

        @media (max-width: 768px) {
            .contact-container {
                flex-direction: column;
                align-items: center;
            }

            footer .footer-section {
                width: 100%;
                text-align: center;
            }
        }

        .navbar-container {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .menu {
            display: flex;
            justify-content: center;
            align-items: center;
            list-style: none;
            flex-wrap: wrap; /* Ensure menu items wrap */
        }

        .menu li {
            margin: 0 10px;
        }

        .menu-toggle {
            display: none;
            cursor: pointer;
        }

        .menu-toggle .bar {
            display: block;
            width: 25px;
            height: 3px;
            margin: 5px auto;
            background-color: #333;
            transition: all 0.3s;
        }

        @media (max-width: 768px) {
            .menu {
                display: none;
                flex-direction: column;
                width: 100%;
                text-align: center;
            }

            .menu.active {
                display: flex;
            }

            .menu-toggle {
                display: block;
            }

            .navbar-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .logo {
                margin-bottom: 10px;
            }

            .contact-container {
                flex-direction: column;
                align-items: center;
            }

            .faq-section, .contact-section {
                width: 100%;
                margin-bottom: 20px;
            }

            .faq-item h3 {
                font-size: 16px;
            }

            .faq-item p {
                font-size: 14px;
            }

            .contact-info p {
                font-size: 16px;
            }

            .contact-button {
                font-size: 14px;
                padding: 8px 16px;
            }

            .footer-section {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <button class="dark-mode-toggle"><i class="fas fa-moon"></i>Dark Mode</button>
    <button class="scroll-to-top" id="scroll-to-top"><i class="fas fa-arrow-up"></i>Top</button>
    <div class="notification" id="notification">Dark mode enabled</div>
    <!-- Nagłówek -->
    <header>
        <nav class="navbar">
            <div class="navbar-container">
                <div class="logo">
                    <a href="#">Jednorazówki</a>
                </div>
                <div class="menu-toggle" id="mobile-menu">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
                <ul class="menu">
                    <li><a href="../index.php">Strona główna</a></li>
                    <li><a href="../index.php#new-products">Nowości</a></li>
                    <li><a href="../index.php#best-sellers">Bestsellery</a></li>
                    <li><a href="../index.php#unavailable-products">Niedostępne</a></li>
                    <li><a href="#">Kontakt</a></li>
                    <li><a href="../auth/login.php">Zaloguj się</a></li>
                </ul>
            </div>
        </nav>
    </header>

    <!-- Sekcja kontaktowa -->
    <main>
        <div class="contact-container">
            <div class="faq-section">
                <h2><i class="fas fa-question-circle"></i>FAQ</h2>
                <div class="faq-item">
                    <h3><i class="fas fa-shopping-cart"></i>Jak mogę złożyć zamówienie?</h3>
                    <p>WAŻNE! Aby złożyć zamówienie musi składać się z co najmniej 4 produktów, wybierz smak, ilość i napisz do nas na <a href="https://t.me/razowkipl">Telegramie</a>. Jest też możliwość złożenia zamówienia niedostępnego produktu (tylko komputer) musisz wypełnić formularz i w nawiasie po nazwisku napisz swoją nazwę telegram, a my zrobimy resztę.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-credit-card"></i>Jakie są metody płatności?</h3>
                    <p>Płatność jest przy odbiorze w paczkomacie.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-truck"></i>Jaka dostawa i jak szybko?</h3>
                    <p>Wysłamy tylko za pomocą paczkomatu inpost. Paczka zostanie wysłana najszybciej jak to jest możliwe.</p>
                </div>
                <div class="faq-item">
                    <h3><i class="fas fa-undo"></i>Czy mogę zwrócić produkt?</h3>
                    <p>Zwrot akceptujemy tylko wtedy gdy produkt nie jest rozpakowany, wysyłka zwrotna na koszt klienta.</p>
                </div>
            </div>
            <div class="contact-section">
                <h2><i class="fas fa-envelope"></i>Kontakt</h2>
                <div class="contact-info">
                    <p><i class="fab fa-telegram-plane"></i>Telegram: @razowkipl</p>
                    <img src="../uploads/Telegram.png" alt="QR Code Telegram">
                    <a href="https://t.me/razowkipl" class="contact-button"><i class="fab fa-telegram-plane"></i>Skontaktuj się</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Stopka -->
    <footer>
        <div class="footer-container">
            <div class="footer-section">
                <h3><i class="fas fa-info-circle"></i>O nas</h3>
                <p>Sklep Jednorazówki oferuje szeroki wybór produktów jednorazowego użytku. Naszym celem jest zapewnienie najwyższej jakości produktów w przystępnych cenach.</p>
            </div>
            <div class="footer-section">
                <h3><i class="fas fa-link"></i>Linki</h3>
                <a href="../index.php">Strona główna</a><br>
                <a href="../index.php#new-products">Nowości</a><br>
                <a href="../index.php#best-sellers">Bestsellery</a><br>
                <a href="../index.php#unavailable-products">Niedostępne</a><br>
                <a href="#">Kontakt</a><br>
                <a href="../auth/login.php">Zaloguj się</a>
            </div>
        </div>
        <p>&copy; 2024 Sklep Jednorazówki. Wszystkie prawa zastrzeżone.</p>
    </footer>

    <script>
        // Mobile menu toggle
        const mobileMenu = document.getElementById('mobile-menu');
        const menu = document.querySelector('.menu');

        mobileMenu.addEventListener('click', () => {
            mobileMenu.classList.toggle('is-active');
            menu.classList.toggle('active');
        });

        // FAQ toggle
        const faqItems = document.querySelectorAll('.faq-item h3');
        faqItems.forEach(item => {
            item.addEventListener('click', () => {
                const content = item.nextElementSibling;
                content.style.display = content.style.display === 'block' ? 'none' : 'block';
            });
        });

        // Dark mode toggle
        const darkModeToggle = document.querySelector('.dark-mode-toggle');
        const notification = document.getElementById('notification');

        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            notification.textContent = document.body.classList.contains('dark-mode') ? 'Dark mode enabled' : 'Dark mode disabled';
            notification.classList.add('show');
            setTimeout(() => {
                notification.classList.remove('show');
            }, 2000);
        });

        // Scroll to top button
        const scrollToTopButton = document.getElementById('scroll-to-top');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 200) {
                scrollToTopButton.style.display = 'block';
            } else {
                scrollToTopButton.style.display = 'none';
            }
        });

        scrollToTopButton.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
</body>
</html>
