<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

// Check if user is authorized
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = ($user_id == 1); // Admin check (replace with role check if using 'role' column)

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($is_admin) {
    // Admin view: Status of all students
    $stmt = $conn->prepare("SELECT name, balance, grade, (SELECT COUNT(*) FROM badges WHERE badges.user_id = users.id) as badge_count FROM users WHERE id != ? ORDER BY balance DESC");
    $stmt->execute([$user_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Student view: Only their status
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT * FROM daily_challenges WHERE user_id = ? AND challenge_date = ?");
    $stmt->execute([$user_id, $today]);
    $challenge = $stmt->fetch();

    $badges = $conn->prepare("SELECT badge_name FROM badges WHERE user_id = ?");
    $badges->execute([$user_id]);
    $badge_list = $badges->fetchAll();

    // Track completed keys
    $completed_keys = isset($_SESSION['completed_keys']) ? $_SESSION['completed_keys'] : [];
}
?>

<?php if ($is_admin): ?>
    <h2>Admin Dashboard - Student Status</h2>
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Grade</th>
                <th>Balance (Birr)</th>
                <th>Badges</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?php echo $student['name']; ?></td>
                    <td><?php echo $student['grade']; ?></td>
                    <td><?php echo $student['balance']; ?></td>
                    <td><?php echo $student['badge_count']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="admin/manage_questions.php" class="btn btn-primary">Manage Questions</a>
<?php else: ?>
    <h2>Welcome, <?php echo $user['name']; ?></h2>
    <p>Grade: <?php echo $user['grade']; ?></p>
    <p>Balance: <?php echo $user['balance']; ?> Birr</p>

    <!-- Daily Challenge -->
    <div class="mb-3">
        <?php if (!$challenge || !$challenge['completed']): ?>
            <a href="quiz.php?daily=1" class="btn btn-warning">Daily Challenge (Bonus: 2 Birr)</a>
        <?php else: ?>
            <p class="text-success">Daily Challenge Completed!</p>
        <?php endif; ?>
    </div>

    <!-- Badges -->
    <h4>Your Badges</h4>
    <?php foreach ($badge_list as $badge): ?>
        <span class="badge-item"><?php echo $badge['badge_name']; ?></span>
    <?php endforeach; ?>

    <!-- Locked Keys -->
    <h4>Unlock Knowledge</h4>
    <div class="key-container">
        <?php for ($i = 1; $i <= 20; $i++): ?>
            <?php
            $is_active = ($i == 1 || in_array($i - 1, $completed_keys)) && !isset($_SESSION['quiz_in_progress']);
            $is_opened = in_array($i, $completed_keys);
            $questions_count = $i * 5; // 5, 10, 15, ..., 100
            ?>
            <?php if ($is_opened): ?>
                <div class="key-card opened" title="Key #<?php echo $i; ?> Completed (<?php echo $questions_count; ?> Questions)">
                    <i class="bi bi-unlock-fill"></i>
                    <span><?php echo $i; ?></span>
                </div>
            <?php elseif ($is_active): ?>
                <a href="quiz.php?key_id=<?php echo $i; ?>" class="key-card active" title="<?php echo $questions_count; ?> Questions">
                    <i class="bi bi-lock-fill"></i>
                    <span><?php echo $i; ?></span>
                </a>
            <?php else: ?>
                <div class="key-card inactive" title="<?php echo $questions_count; ?> Questions (Complete Key #<?php echo $i - 1; ?> first)">
                    <i class="bi bi-lock-fill"></i>
                    <span><?php echo $i; ?></span>
                </div>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>