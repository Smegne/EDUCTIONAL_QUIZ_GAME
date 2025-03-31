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
$is_admin = ($user_id == 1); // Admin check

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($is_admin) {
    $stmt = $conn->prepare("SELECT name, balance, grade, (SELECT COUNT(*) FROM badges WHERE badges.user_id = users.id) as badge_count FROM users WHERE id != ? ORDER BY balance DESC");
    $stmt->execute([$user_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    try {
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
    } catch (PDOException $e) {
        echo "<div class='alert alert-danger'>Error fetching user progress: " . $e->getMessage() . "</div>";
        $user_progress = [];
    }
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

    $progress_stmt = $conn->prepare("SELECT SUM(questions_answered) as answered_questions FROM key_progress WHERE user_id = ?");
    $progress_stmt->execute([$user_id]);
    $answered_questions = $progress_stmt->fetchColumn() ?? 0;

    $keys_stmt = $conn->prepare("SELECT COUNT(*) as unlocked_count FROM user_keys WHERE user_id = ?");
    $keys_stmt->execute([$user_id]);
    $unlocked_count = $keys_stmt->fetchColumn();
    $total_questions = $unlocked_count * 5;
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

    <!-- Modal for Assessment Details -->
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
    function updateProgressTable() {
        fetch('admin_progress.php')
            .then(response => response.json())
            .then(data => {
                const tbody = document.querySelector('#progress-table tbody');
                tbody.innerHTML = '';
                data.forEach(user => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${user.name}</td>
                        <td>${user.unlocked_count}</td>
                        <td>
                            <button class="btn btn-sm btn-info view-details" data-user-id="${user.user_id}" data-bs-toggle="modal" data-bs-target="#assessmentModal">View</button>
                        </td>
                        <td>${user.total_score || 0}</td>
                    `;
                    tbody.appendChild(row);
                });
                attachViewEventListeners();
            })
            .catch(error => console.error('Error updating progress:', error));
    }

    function attachViewEventListeners() {
        document.querySelectorAll('.view-details').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                fetch(`user_assessments.php?user_id=${userId}`)
                    .then(response => response.json())
                    .then(data => {
                        const tbody = document.querySelector('#assessment-table tbody');
                        tbody.innerHTML = '';
                        data.forEach(assessment => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${assessment.assessment_name}</td>
                                <td>${assessment.questions_answered}/${assessment.total_questions}</td>
                                <td>${assessment.score}</td>
                            `;
                            tbody.appendChild(row);
                        });
                        document.getElementById('assessmentModalLabel').textContent = `Assessments for ${data[0]?.user_name || 'User'}`;
                    })
                    .catch(error => console.error('Error fetching assessments:', error));
            });
        });
    }

    setInterval(updateProgressTable, 10000);
    updateProgressTable();
    </script>
<?php else: ?>
    <h2>Welcome, <?php echo $user['name']; ?></h2>
    <p>Grade: <?php echo $user['grade']; ?></p>
    <p>Balance: <?php echo $user['balance']; ?> Birr</p>
    <p>Total Score: <?php echo $total_score ?? 0; ?> Birr</p>
    <p>Answered Questions: <?php echo $answered_questions . '/' . $total_questions; ?></p>
    <div class="mb-3">
        <?php if (!$challenge || !$challenge['completed']): ?>
            <a href="quiz.php?daily=1" class="btn btn-warning">Daily Challenge (Bonus: 2 Birr)</a>
        <?php else: ?>
            <p class="text-success">Daily Challenge Completed!</p>
        <?php endif; ?>
    </div>
    <h4>Your Badges</h4>
    <?php foreach ($badge_list as $badge): ?>
        <span class="badge-item"><?php echo $badge['badge_name']; ?></span>
    <?php endforeach; ?>
    <h4>Unlock Knowledge</h4>
    <div class="key-container">
        <?php for ($i = 1; $i <= 20; $i++): ?>
            <?php
            $is_active = ($i == 1 || in_array($i - 1, $completed_keys)) && !isset($_SESSION['quiz_in_progress']);
            $is_opened = in_array($i, $completed_keys);
            $questions_count = $i * 5;
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