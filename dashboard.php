<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = ($user_id == 1);

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($is_admin) {
    $stmt = $conn->prepare("SELECT name, balance, grade, (SELECT COUNT(*) FROM badges WHERE badges.user_id = users.id) as badge_count FROM users WHERE id != ? ORDER BY balance DESC");
    $stmt->execute([$user_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $progress_stmt = $conn->prepare("
        SELECT 
            u.id as user_id,
            u.name,
            COUNT(uk.key_id) as unlocked_count,
            SUM(uk.score) as total_score
        FROM users u
        LEFT JOIN user_keys uk ON u.id = uk.user_id
        WHERE u.id != ?
        GROUP BY u.id, u.name
        ORDER BY total_score DESC
    ");
    $progress_stmt->execute([$user_id]);
    $user_progress = $progress_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT * FROM daily_challenges WHERE user_id = ? AND challenge_date = ?");
    $stmt->execute([$user_id, $today]);
    $challenge = $stmt->fetch();

    $badges = $conn->prepare("SELECT badge_name FROM badges WHERE user_id = ?");
    $badges->execute([$user_id]);
    $badge_list = $badges->fetchAll();

    $keys_stmt = $conn->prepare("SELECT key_id FROM user_keys WHERE user_id = ?");
    $keys_stmt->execute([$user_id]);
    $completed_keys = array_column($keys_stmt->fetchAll(PDO::FETCH_ASSOC), 'key_id');

    $score_stmt = $conn->prepare("SELECT SUM(score) as total_score FROM user_keys WHERE user_id = ?");
    $score_stmt->execute([$user_id]);
    $total_score = $score_stmt->fetchColumn();

    // Check questions for each key
    $key_questions = [];
    for ($i = 1; $i <= 20; $i++) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM questions WHERE key_id = ?");
        $stmt->execute([$i]);
        $key_questions[$i] = $stmt->fetchColumn() > 0; // True if questions exist
    }
}
?>

<?php if ($is_admin): ?>
    <!-- Admin section unchanged -->
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

    <h2>User Progress</h2>
    <table class="table" id="progress-table">
        <thead>
            <tr>
                <th>User Name</th>
                <th>Unlocked Knowledge Count</th>
                <th>Answered Questions</th>
                <th>Score</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($user_progress as $progress): ?>
                <tr>
                    <td><?php echo $progress['name']; ?></td>
                    <td><?php echo $progress['unlocked_count']; ?></td>
                    <td>
                        <button class="btn btn-sm btn-info view-details" data-user-id="<?php echo $progress['user_id']; ?>" data-bs-toggle="modal" data-bs-target="#assessmentModal">View</button>
                    </td>
                    <td><?php echo $progress['total_score'] ?? 0; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <a href="admin/manage_questions.php" class="btn btn-primary">Manage Questions</a>

    <!-- Modal unchanged -->
    <div class="modal fade" id="assessmentModal" tabindex="-1" aria-labelledby="assessmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assessmentModalLabel">User Assessments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table" id="assessment-table">
                        <thead>
                            <tr>
                                <th>Assessment</th>
                                <th>Questions Answered</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function updateProgressTable() { /* unchanged */ }
    function attachViewEventListeners() { /* unchanged */ }
    setInterval(updateProgressTable, 10000);
    updateProgressTable();
    </script>
<?php else: ?>
    <!-- User Panel with Shadow Boxes -->
    <div class="container mt-4">
        <!-- User Info Shadow Box -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <h2 class="card-title">Welcome, <?php echo htmlspecialchars($user['name']); ?></h2>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Grade:</strong> <?php echo htmlspecialchars($user['grade']); ?></p>
                        <p><strong>Balance:</strong> <?php echo htmlspecialchars($user['balance']); ?> Birr</p>
                        <p><strong>Total Score:</strong> <?php echo $total_score ?? 0; ?> Birr</p>
                    </div>
                    <div class="col-md-6">
                        <h4>Badges</h4>
                        <?php if (empty($badge_list)): ?>
                            <p>No badges earned yet.</p>
                        <?php else: ?>
                            <?php foreach ($badge_list as $badge): ?>
                                <span class="badge bg-primary me-1"><?php echo htmlspecialchars($badge['badge_name']); ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-3">
                    <?php if (!$challenge || !$challenge['completed']): ?>
                        <a href="quiz.php?daily=1" class="btn btn-warning">Daily Challenge (Bonus: 2 Birr)</a>
                    <?php else: ?>
                        <p class="text-success">Daily Challenge Completed!</p>
                    <?php endif; ?>
                </div>
                <!-- Three Horizontal Buttons -->
                <div class="button-group mt-3">
                    <a href="quiz.php" class="btn btn-success">Start Quiz</a>
                    <a href="#progress" class="btn btn-success">View Progress</a>
                    <a href="logout.php" class="btn btn-success">Logout</a>
                </div>
            </div>
        </div>

        <!-- Keys Shadow Box -->
        <div class="card shadow">
            <div class="card-body">
                <h4 class="card-title">Unlock Knowledge</h4>
                <div class="row row-cols-2 row-cols-md-3 g-3 key-container">
                    <?php for ($i = 1; $i <= 20; $i++): ?>
                        <?php
                        $is_active = ($i == 1 || in_array($i - 1, $completed_keys)) && !isset($_SESSION['quiz_in_progress']);
                        $is_opened = in_array($i, $completed_keys);
                        $has_questions = $key_questions[$i];
                        $questions_count = $i * 5;
                        ?>
                        <div class="col">
                            <?php if ($is_opened): ?>
                                <div class="card key-card opened h-100" title="Key #<?php echo $i; ?> Completed (<?php echo $questions_count; ?> Questions)">
                                    <div class="card-body text-center">
                                        <i class="bi bi-unlock-fill"></i>
                                        <p class="card-text"><?php echo $i; ?></p>
                                    </div>
                                </div>
                            <?php elseif ($is_active && $has_questions): ?>
                                <a href="quiz.php?key_id=<?php echo $i; ?>" class="card key-card active h-100 text-decoration-none" title="<?php echo $questions_count; ?> Questions">
                                    <div class="card-body text-center">
                                        <i class="bi bi-lock-fill"></i>
                                        <p class="card-text"><?php echo $i; ?></p>
                                    </div>
                                </a>
                            <?php else: ?>
                                <div class="card key-card <?php echo $is_opened ? 'opened' : 'inactive'; ?> h-100" title="<?php echo $has_questions ? "$questions_count Questions (Complete Key #" . ($i - 1) . " first)" : 'No questions yet'; ?>">
                                    <div class="card-body text-center">
                                        <?php if ($has_questions): ?>
                                            <i class="bi bi-lock-fill"></i>
                                            <p class="card-text"><?php echo $i; ?></p>
                                        <?php else: ?>
                                            <p class="card-text">Nothing is yet</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>