<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_daily = isset($_GET['daily']) && $_GET['daily'] == 1;
$key_id = isset($_GET['key_id']) ? (int)$_GET['key_id'] : null;

// Initialize $is_key_completed to false by default
$is_key_completed = false;

// Check key progress only if $key_id is set
if ($key_id) {
    $key_check_stmt = $conn->prepare("SELECT questions_answered, correct_answers, total_questions, completed FROM key_progress WHERE user_id = ? AND key_id = ?");
    $key_check_stmt->execute([$user_id, $key_id]);
    $key_progress = $key_check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$key_progress && !$is_daily) {
        $total_questions = $key_id * 5;
        $conn->prepare("INSERT INTO key_progress (user_id, key_id, total_questions) VALUES (?, ?, ?)")->execute([$user_id, $key_id, $total_questions]);
        $key_progress = ['questions_answered' => 0, 'correct_answers' => 0, 'total_questions' => $total_questions, 'completed' => false];
    } elseif ($key_progress && $key_progress['completed']) {
        $is_key_completed = true;
    }
} else {
    $key_progress = null;
}

$stmt = $conn->prepare("SELECT correct_answers, wrong_answers FROM progress WHERE user_id = ?");
$stmt->execute([$user_id]);
$progress = $stmt->fetch();
$difficulty = ($progress['correct_answers'] > $progress['wrong_answers'] + 5) ? 2 : 1;

if (isset($_POST['answer']) && $key_id && !$is_key_completed) {
    $question_id = $_POST['question_id'];
    $selected_answer = $_POST['answer'];
    $stmt = $conn->prepare("SELECT correct_answer FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $correct_answer = $stmt->fetchColumn();

    $questions_answered = $key_progress['questions_answered'] + 1;
    $correct_answers = $key_progress['correct_answers'] + ($selected_answer === $correct_answer ? 1 : 0);

    $conn->prepare("UPDATE progress SET " . ($selected_answer === $correct_answer ? "correct_answers" : "wrong_answers") . " = " . ($selected_answer === $correct_answer ? "correct_answers" : "wrong_answers") . " + 1 WHERE user_id = ?")->execute([$user_id]);
    $conn->prepare("UPDATE key_progress SET questions_answered = ?, correct_answers = ? WHERE user_id = ? AND key_id = ?")->execute([$questions_answered, $correct_answers, $user_id, $key_id]);

    if ($questions_answered >= $key_progress['total_questions']) {
        $score = ($correct_answers == $key_progress['total_questions']) ? $key_id * 2 : 0;
        $conn->prepare("UPDATE key_progress SET completed = TRUE WHERE user_id = ? AND key_id = ?")->execute([$user_id, $key_id]);
        $conn->prepare("INSERT INTO user_keys (user_id, key_id, score) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE score = ?")->execute([$user_id, $key_id, $score, $score]);

        if ($score > 0) {
            $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$score, $user_id]);
        }

        unset($_SESSION['quiz_in_progress']);
        echo "<script>alert('Key #$key_id completed! Score: $score Birr'); window.location.href = 'dashboard.php';</script>";
        exit();
    }
} elseif (isset($_POST['answer']) && $is_daily) {
    $question_id = $_POST['question_id'];
    $selected_answer = $_POST['answer'];
    $stmt = $conn->prepare("SELECT correct_answer FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $correct_answer = $stmt->fetchColumn();

    if ($selected_answer === $correct_answer) {
        $_SESSION['daily_correct'] = isset($_SESSION['daily_correct']) ? $_SESSION['daily_correct'] + 1 : 1;
        $conn->prepare("UPDATE progress SET correct_answers = correct_answers + 1 WHERE user_id = ?")->execute([$user_id]);
        if ($_SESSION['daily_correct'] >= 5) {
            $conn->prepare("INSERT INTO daily_challenges (user_id, challenge_date, completed) VALUES (?, ?, TRUE) ON DUPLICATE KEY UPDATE completed = TRUE")->execute([$user_id, date('Y-m-d')]);
            $conn->prepare("UPDATE users SET balance = balance + 2 WHERE id = ?")->execute([$user_id]);
            unset($_SESSION['daily_correct']);
            unset($_SESSION['quiz_in_progress']);
            echo "<script>alert('Daily Challenge completed! Score: 2 Birr'); window.location.href = 'dashboard.php';</script>";
            exit();
        }
    } else {
        $conn->prepare("UPDATE progress SET wrong_answers = wrong_answers + 1 WHERE user_id = ?")->execute([$user_id]);
        unset($_SESSION['daily_correct']);
        unset($_SESSION['quiz_in_progress']);
        header("Location: dashboard.php");
        exit();
    }
}

$stmt = $conn->prepare("SELECT * FROM questions WHERE difficulty_level = ? ORDER BY RAND() LIMIT 1");
$stmt->execute([$difficulty]);
$question = $stmt->fetch();
?>

<div class="quiz-card card">
    <h3>
        <?php
        if ($key_id && !$is_key_completed) {
            echo "Key #$key_id - Question " . ($key_progress['questions_answered'] + 1) . " of " . $key_progress['total_questions'];
        } elseif ($is_daily) {
            echo "Daily Challenge - Question " . (isset($_SESSION['daily_correct']) ? $_SESSION['daily_correct'] + 1 : 1) . " of 5";
        } else {
            echo "Practice Question";
        }
        ?>
    </h3>
    <?php if ($key_id && $is_key_completed): ?>
        <p class="text-success">Key #<?php echo $key_id; ?> has already been completed. Return to <a href="dashboard.php">Dashboard</a>.</p>
    <?php else: ?>
        <p><?php echo $question['question_text']; ?></p>
        <div class="timer" id="timer">30</div>
        <progress id="timeProgress" value="30" max="30"></progress>
        <form method="POST" id="quizForm">
            <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
            <div class="form-check">
                <input type="radio" name="answer" value="A" class="form-check-input" required>
                <label><?php echo $question['option_a']; ?></label>
            </div>
            <div class="form-check">
                <input type="radio" name="answer" value="B" class="form-check-input">
                <label><?php echo $question['option_b']; ?></label>
            </div>
            <div class="form-check">
                <input type="radio" name="answer" value="C" class="form-check-input">
                <label><?php echo $question['option_c']; ?></label>
            </div>
            <div class="form-check">
                <input type="radio" name="answer" value="D" class="form-check-input">
                <label><?php echo $question['option_d']; ?></label>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Submit</button>
        </form>
        <?php if ($key_id && !$is_key_completed): ?>
            <p>Correct Answers: <?php echo $key_progress['correct_answers']; ?> / <?php echo $key_progress['total_questions']; ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!$is_daily && !$is_key_completed || $is_daily): ?>
<script>
    let time = 30;
    const timer = setInterval(() => {
        time--;
        document.getElementById('timer').innerText = time;
        document.getElementById('timeProgress').value = time;
        if (time <= 0) {
            clearInterval(timer);
            document.getElementById('quizForm').submit();
        }
    }, 1000);
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>