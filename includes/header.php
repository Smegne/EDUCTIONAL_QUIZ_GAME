<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Educational Quiz Game</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-green">
        <div class="container">
            <a class="navbar-brand " href="#">Quiz Game</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="dashboard.php">Dashboard</a>
                    <a class="nav-link" href="logout.php">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <div class="container mt-4">