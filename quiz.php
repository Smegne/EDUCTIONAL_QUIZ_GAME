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

$is_key_completed = false;

if ($key_id) {
    // Validate key_id range
    if ($key_id < 1 || $key_id > 20) {
        header("Location: dashboard.php");
        exit();
    }

    // Check key progress
    $key_check_stmt = $conn->prepare("SELECT questions_answered, correct_answers, total_questions, completed FROM key_progress WHERE user_id = ? AND key_id = ?");
    $key_check_stmt->execute([$user_id, $key_id]);
    $key_progress = $key_check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$key_progress && !$is_daily) {
        $total_questions = $key_id * 5; // Assuming 5 questions per key
        $conn->prepare("INSERT INTO key_progress (user_id, key_id, total_questions) VALUES (?, ?, ?)")->execute([$user_id, $key_id, $total_questions]);
        $key_progress = ['questions_answered' => 0, 'correct_answers' => 0, 'total_questions' => $total_questions, 'completed' => false];
    } elseif ($key_progress && $key_progress['completed']) {
        $is_key_completed = true;
    }
} else {
    $key_progress = null;
}

// Determine question source
if ($is_daily) {
    // Daily challenge: random question
    $stmt = $conn->prepare("SELECT correct_answers, wrong_answers FROM progress WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $progress = $stmt->fetch();
    $difficulty = ($progress['correct_answers'] > $progress['wrong_answers'] + 5) ? 2 : 1;

    $stmt = $conn->prepare("SELECT * FROM questions WHERE difficulty_level = ? ORDER BY RAND() LIMIT 1");
    $stmt->execute([$difficulty]);
    $question = $stmt->fetch();
} elseif ($key_id && !$is_key_completed) {
    // Key-based quiz: fetch questions for this key
    $stmt = $conn->prepare("SELECT * FROM questions WHERE key_id = ? ORDER BY id LIMIT 1 OFFSET ?");
    $stmt->execute([$key_id, $key_progress['questions_answered']]);
    $question = $stmt->fetch();

    if (!$question) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM questions WHERE key_id = ?");
        $stmt->execute([$key_id]);
        $question_count = $stmt->fetchColumn();
        if ($question_count == 0) {
            $question = null; // No questions for this key
        } else {
            // Reset if all questions answered but not completed (edge case)
            $conn->prepare("UPDATE key_progress SET questions_answered = 0 WHERE user_id = ? AND key_id = ?")->execute([$user_id, $key_id]);
            $stmt->execute([$key_id, 0]);
            $question = $stmt->fetch();
        }
    }
} else {
    // Practice mode: random question
    $stmt = $conn->prepare("SELECT * FROM questions ORDER BY RAND() LIMIT 1");
    $stmt->execute();
    $question = $stmt->fetch();
}

$time_limit = $question['time_limit'] ?? 30;

// Handle quiz submission
if (isset($_POST['answer']) && !$is_key_completed) {
    $question_id = $_POST['question_id'];
    $selected_answer = $_POST['answer'];
    $stmt = $conn->prepare("SELECT correct_answer FROM questions WHERE id = ?");
    $stmt->execute([$question_id]);
    $correct_answer = $stmt->fetchColumn();

    if ($key_id) {
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
    } elseif ($is_daily) {
        $daily_stmt = $conn->prepare("SELECT questions_answered, correct_answers FROM daily_challenges WHERE user_id = ? AND challenge_date = ?");
        $daily_stmt->execute([$user_id, date('Y-m-d')]);
        $daily_progress = $daily_stmt->fetch(PDO::FETCH_ASSOC);

        $questions_answered = ($daily_progress['questions_answered'] ?? 0) + 1;
        $correct_answers = ($daily_progress['correct_answers'] ?? 0) + ($selected_answer === $correct_answer ? 1 : 0);

        $conn->prepare("UPDATE progress SET " . ($selected_answer === $correct_answer ? "correct_answers" : "wrong_answers") . " = " . ($selected_answer === $correct_answer ? "correct_answers" : "wrong_answers") . " + 1 WHERE user_id = ?")->execute([$user_id]);
        $conn->prepare("INSERT INTO daily_challenges (user_id, challenge_date, questions_answered, correct_answers, completed) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE questions_answered = ?, correct_answers = ?, completed = ?")->execute([
            $user_id, date('Y-m-d'), $questions_answered, $correct_answers, ($correct_answers >= 5),
            $questions_answered, $correct_answers, ($correct_answers >= 5)
        ]);

        if ($correct_answers >= 5) {
            $conn->prepare("UPDATE users SET balance = balance + 2 WHERE id = ?")->execute([$user_id]);
            unset($_SESSION['quiz_in_progress']);
            echo "<script>alert('Daily Challenge completed! Score: 2 Birr'); window.location.href = 'dashboard.php';</script>";
            exit();
        } elseif ($questions_answered >= 5) {
            unset($_SESSION['quiz_in_progress']);
            header("Location: dashboard.php");
            exit();
        }
    }
}
?>

<div class="quiz-card card">
    <h3>
        <?php
        if ($key_id && !$is_key_completed) {
            echo "Key #$key_id - Question " . ($key_progress['questions_answered'] + 1) . " of " . $key_progress['total_questions'];
        } elseif ($is_daily) {
            $daily_stmt = $conn->prepare("SELECT questions_answered FROM daily_challenges WHERE user_id = ? AND challenge_date = ?");
            $daily_stmt->execute([$user_id, date('Y-m-d')]);
            $daily_questions = $daily_stmt->fetchColumn() ?? 0;
            echo "Daily Challenge - Question " . ($daily_questions + 1) . " of 5";
        } else {
            echo "Practice Question";
        }
        ?>
    </h3>
    <?php if ($key_id && $is_key_completed): ?>
        <p class="text-success">Key #<?php echo $key_id; ?> has already been completed. Return to <a href="dashboard.php">Dashboard</a>.</p>
    <?php elseif ($key_id && !$question): ?>
        <p class="text-info">Nothing is yet</p>
        <a href="dashboard.php" class="btn btn-primary mt-3">Back to Dashboard</a>
    <?php elseif ($question): ?>
        <p><?php echo htmlspecialchars($question['question_text']); ?></p>
        <div class="timer" id="timer"><?php echo $time_limit; ?></div>
        <progress id="timeProgress" value="<?php echo $time_limit; ?>" max="<?php echo $time_limit; ?>"></progress>
        <form method="POST" id="quizForm">
            <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
            <div class="form-check">
                <input type="radio" name="answer" value="A" class="form-check-input" required>
                <label><?php echo htmlspecialchars($question['option_a']); ?></label>
            </div>
            <div class="form-check">
                <input type="radio" name="answer" value="B" class="form-check-input">
                <label><?php echo htmlspecialchars($question['option_b']); ?></label>
            </div>
            <div class="form-check">
                <input type="radio" name="answer" value="C" class="form-check-input">
                <label><?php echo htmlspecialchars($question['option_c']); ?></label>
            </div>
            <div class="form-check">
                <input type="radio" name="answer" value="D" class="form-check-input">
                <label><?php echo htmlspecialchars($question['option_d']); ?></label>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Submit</button>
        </form>
        <?php if ($key_id && !$is_key_completed): ?>
            <p>Correct Answers: <?php echo $key_progress['correct_answers']; ?> / <?php echo $key_progress['total_questions']; ?></p>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-info">No practice questions available.</p>
        <a href="dashboard.php" class="btn btn-primary mt-3">Back to Dashboard</a>
    <?php endif; ?>
</div>

<?php if ($question && (!$is_key_completed || $is_daily)): ?>
<script>
    let time = <?php echo $time_limit; ?>;
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