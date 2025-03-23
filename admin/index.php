<?php
session_start();
include '../config/db.php';
include '../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

$students = $conn->query("SELECT name, balance, (SELECT COUNT(*) FROM badges WHERE badges.user_id = users.id) as badge_count FROM users")->fetchAll();
?>

<h2>Admin Dashboard</h2>
<a href="manage_questions.php" class="btn btn-primary">Manage Questions</a>
<h4>Student Progress</h4>
<table class="table">
    <thead>
        <tr><th>Name</th><th>Balance</th><th>Badges</th></tr>
    </thead>
    <tbody>
        <?php foreach ($students as $student): ?>
            <tr><td><?php echo $student['name']; ?></td><td><?php echo $student['balance']; ?> Birr</td><td><?php echo $student['badge_count']; ?></td></tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>