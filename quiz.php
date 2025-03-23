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
$is_daily = isset($_GET['daily']) && $_GET['daily'] == 1;
$key_id = isset($_GET['key_id']) ? (int)$_GET['key_id'] : null;

// Initialize session variables for key progress
if ($key_id && !isset($_SESSION['key_progress'][$key_id])) {
    $_SESSION['key_progress'][$key_id] = [
        'questions_answered' => 0,
        'correct_answers' => 0,
        'total_questions' => $key_id * 5 // 5, 10, 15, ..., 100
    ];
    $_SESSION['quiz_in_progress'] = true;
}

// Adaptive difficulty
$stmt = $conn->prepare("SELECT correct_answers, wrong_answers FROM progress WHERE user_id = ?");
$stmt->execute([$user_id]);
$progress = $stmt->fetch();
$difficulty = ($progress['correct_answers'] > $progress['wrong_answers'] + 5) ? 2 : 1;

if (isset($_POST['answer']) && $key_id) {
    $question_id = $_POST['question_id'];
    $selected_answer = $_POST['answer'];
    $stmt = $conn->prepare("SELECT correct_answer FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $correct_answer = $stmt->fetchColumn();

    $_SESSION['key_progress'][$key_id]['questions_answered']++;

    if ($selected_answer === $correct_answer) {
        $_SESSION['key_progress'][$key_id]['correct_answers']++;
        $conn->prepare("UPDATE progress SET correct_answers = correct_answers + 1 WHERE user_id = ?")->execute([$user_id]);
    } else {
        $conn->prepare("UPDATE progress SET wrong_answers = wrong_answers + 1 WHERE user_id = ?")->execute([$user_id]);
    }

    // Check if all questions for this key are answered
    if ($_SESSION['key_progress'][$key_id]['questions_answered'] >= $_SESSION['key_progress'][$key_id]['total_questions']) {
        // Mark key as completed (regardless of correctness)
        $_SESSION['completed_keys'] = isset($_SESSION['completed_keys']) ? $_SESSION['completed_keys'] : [];
        $_SESSION['completed_keys'][] = $key_id;

        // Award balance only if all answers are correct
        if ($_SESSION['key_progress'][$key_id]['correct_answers'] == $_SESSION['key_progress'][$key_id]['total_questions']) {
            $balance_reward = $key_id * 2; // 2, 4, 6, ..., 40 Birr
            $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$balance_reward, $user_id]);
            echo "<script>confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });</script>";
        }

        // Reset key progress and quiz state
        unset($_SESSION['key_progress'][$key_id]);
        unset($_SESSION['quiz_in_progress']);
        header("Location: dashboard.php"); // Redirect to dashboard after completion
        exit();
    }
} elseif (isset($_POST['answer']) && $is_daily) {
    // Handle daily challenge separately
    $question_id = $_POST['question_id'];
    $selected_answer = $_POST['answer'];
    $stmt = $conn->prepare("SELECT correct_answer FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $correct_answer = $stmt->fetchColumn();

    if ($selected_answer === $correct_answer) {
        $_SESSION['daily_correct'] = isset($_SESSION['daily_correct']) ? $_SESSION['daily_correct'] + 1 : 1;
        $conn->prepare("UPDATE progress SET correct_answers = correct_answers + 1 WHERE user_id = ?")->execute([$user_id]);
        if ($_SESSION['daily_correct'] >= 5) {
            $conn->prepare("INSERT INTO daily_challenges (user_id, completed) VALUES (?, TRUE) ON DUPLICATE KEY UPDATE completed = TRUE")->execute([$user_id]);
            $conn->prepare("UPDATE users SET balance = balance + 2 WHERE id = ?")->execute([$user_id]);
            unset($_SESSION['daily_correct']);
            unset($_SESSION['quiz_in_progress']);
            echo "<script>confetti({ particleCount: 100, spread: 70, origin: { y: 0.6 } });</script>";
            header("Location: dashboard.php");
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
        if ($key_id) {
            $remaining = $_SESSION['key_progress'][$key_id]['total_questions'] - $_SESSION['key_progress'][$key_id]['questions_answered'];
            echo "Key #$key_id - Question " . ($_SESSION['key_progress'][$key_id]['questions_answered'] + 1) . " of " . $_SESSION['key_progress'][$key_id]['total_questions'];
        } elseif ($is_daily) {
            echo "Daily Challenge - Question " . (isset($_SESSION['daily_correct']) ? $_SESSION['daily_correct'] + 1 : 1) . " of 5";
        } else {
            echo "Practice Question";
        }
        ?>
    </h3>
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
    <?php if ($key_id): ?>
        <p>Correct Answers: <?php echo $_SESSION['key_progress'][$key_id]['correct_answers']; ?> / <?php echo $_SESSION['key_progress'][$key_id]['total_questions']; ?></p>
    <?php endif; ?>
</div>

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

<?php include 'includes/footer.php'; ?>