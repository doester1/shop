<?php
session_start();
require '../includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

$username = $_SESSION['username'];

// Handle settings form submission
if (isset($_POST['update_settings'])) {
    $new_username = $_POST['username'];
    $new_password = $_POST['password'];

    // Update user settings in the database
    if (!empty($new_password)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $updateSettingsQuery = $conn->prepare("UPDATE admini SET username = ?, password = ? WHERE username = ?");
        if ($updateSettingsQuery === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error));
        }
        $updateSettingsQuery->bind_param("sss", $new_username, $hashed_password, $username);
    } else {
        $updateSettingsQuery = $conn->prepare("UPDATE admini SET username = ? WHERE username = ?");
        if ($updateSettingsQuery === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error));
        }
        $updateSettingsQuery->bind_param("ss", $new_username, $username);
    }
    $updateSettingsQuery->execute();

    // Update session username
    $_SESSION['username'] = $new_username;

    header("Location: ustawienia.php");
    exit();
}

// Fetch user settings
$userSettingsQuery = $conn->prepare("SELECT username, last_login FROM admini WHERE username = ?");
if ($userSettingsQuery === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$userSettingsQuery->bind_param("s", $username);
$userSettingsQuery->execute();
$userSettingsResult = $userSettingsQuery->get_result();
$userSettings = $userSettingsResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ustawienia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style-dashboard.css">
</head>
<body class="bg-light">
    <header class="container py-3">
        <div class="profile-container">
            <div class="profile">
                <div class="initial"><?php echo strtoupper($username[0]); ?></div>
                <div class="username"><?php echo htmlspecialchars($username); ?></div>
            </div>
        </div>
    </header>
    <main class="container mt-4">
        <section class="mt-4">
            <h3>Ustawienia</h3>
            <form method="post">
                <div class="mb-3">
                    <label for="username" class="form-label">Nazwa użytkownika</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($userSettings['username']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Nowe hasło (opcjonalnie)</label>
                    <input type="password" class="form-control" id="password" name="password">
                </div>
                <button type="submit" name="update_settings" class="btn btn-primary">Zaktualizuj ustawienia</button>
            </form>
            <div class="mt-3">
                <p>Ostatnie logowanie: <?php echo htmlspecialchars($userSettings['last_login']); ?></p>
            </div>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
