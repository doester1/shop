<?php
session_start();
require '../includes/db.php';

// Usuń powiadomienia, które zostały przeczytane ponad 48 godzin temu
$deleteOldNotificationsQuery = $conn->prepare(
    "DELETE FROM notifications WHERE is_read = 1 AND read_at <= DATE_SUB(NOW(), INTERVAL 48 HOUR)"
);
if ($deleteOldNotificationsQuery === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$deleteOldNotificationsQuery->execute();

if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit();
}

$username = $_SESSION['username'];

// Powiadomienia
$notificationsQuery = $conn->query("SELECT id, message, created_at, is_read FROM notifications ORDER BY created_at DESC");

// Oznacz wybrane powiadomienie jako przeczytane
if (isset($_POST['mark_as_read_single'])) {
    $notificationId = intval($_POST['notification_id']);
    $markAsReadQuery = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
    if ($markAsReadQuery === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $markAsReadQuery->bind_param("i", $notificationId);
    $markAsReadQuery->execute();
    header("Location: powiadomienia.php");
    exit();
}

// Oznacz wszystkie powiadomienia jako przeczytane
if (isset($_POST['mark_as_read'])) {
    $markAsReadQuery = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
    if ($markAsReadQuery === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $markAsReadQuery->execute();
    header("Location: powiadomienia.php");
    exit();
}

// Handle sending a message
if (isset($_POST['send_message'])) {
    $message = $_POST['message'];

    $sendMessageQuery = $conn->prepare("INSERT INTO admin_messages (username, message) VALUES (?, ?)");
    if ($sendMessageQuery === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    $sendMessageQuery->bind_param("ss", $username, $message);
    $sendMessageQuery->execute();
    header("Location: powiadomienia.php");
    exit();
}

// Fetch messages
$messagesQuery = $conn->query("SELECT * FROM admin_messages ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Powiadomienia</title>
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
        <div class="row">
            <div class="col-md-6">
                <section class="mt-4">
                    <h3>Powiadomienia</h3>
                    <h6 class="text-muted">Przeczytane powiadomienia znikają po 48 godzinach</h6>
                    <form method="post" class="mb-3">
                        <button type="submit" name="mark_as_read" class="btn btn-primary">Oznacz wszystkie jako przeczytane</button>
                    </form>

                    <ul class="list-group">
                        <?php while ($row = $notificationsQuery->fetch_assoc()): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($row['message']); ?></strong>
                                    <?php if ($row['is_read'] == 1): ?>
                                        <span class="text-muted">Powiadomienie przeczytane</span>
                                    <?php else: ?>
                                        <small class="text-muted"><?php echo date('Y-m-d H:i:s', strtotime($row['created_at'])); ?></small>
                                    <?php endif; ?>
                                </div>
                                <form method="post" class="mb-0">
                                    <input type="hidden" name="notification_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="mark_as_read_single" class="btn btn-link">Oznacz jako przeczytane</button>
                                </form>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </section>
            </div>
            <div class="col-md-6">
                <section class="mt-4">
                    <h3>Komunikator</h3>
                    <form method="post" class="mb-3">
                        <div class="mb-3">
                            <label for="message" class="form-label">Wiadomość:</label>
                            <textarea class="form-control" id="message" name="message" required></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-primary">Wyślij</button>
                    </form>

                    <ul class="list-group">
                        <?php while ($row = $messagesQuery->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($row['username']); ?>:</strong>
                                <p><?php echo htmlspecialchars($row['message']); ?></p>
                                <small class="text-muted"><?php echo date('Y-m-d H:i:s', strtotime($row['created_at'])); ?></small>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                </section>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
