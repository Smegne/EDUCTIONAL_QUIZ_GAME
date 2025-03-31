<?php
session_start();
include '../config/db.php';
include '../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $question_text = $_POST['question_text'];
    $options = json_encode([$_POST['option1'], $_POST['option2'], $_POST['option3'], $_POST['option4']]);
    $correct_answer = $_POST['correct_answer'];
    $hours = (int)$_POST['time_hours'];
    $minutes = (int)$_POST['time_minutes'];
    $seconds = (int)$_POST['time_seconds'];
    $time_limit = ($hours * 3600) + ($minutes * 60) + $seconds; // Convert to seconds
    $key_id = (int)$_POST['key_id'];

    if (isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
        // Update existing question
        $stmt = $conn->prepare("UPDATE questions SET question_text = ?, options = ?, correct_answer = ?, time_limit = ?, key_id = ? WHERE id = ?");
        $stmt->execute([$question_text, $options, $correct_answer, $time_limit, $key_id, $_POST['edit_id']]);
    } else {
        // Add new question
        $stmt = $conn->prepare("INSERT INTO questions (question_text, options, correct_answer, time_limit, key_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$question_text, $options, $correct_answer, $time_limit, $key_id]);
    }
    header("Location: manage_questions.php");
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: manage_questions.php");
    exit();
}

// Fetch all questions
$stmt = $conn->prepare("SELECT * FROM questions");
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch question to edit
$edit_question = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM questions WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_question = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container mt-4">
    <h2>Manage Questions</h2>

    <!-- Add/Edit Question Form -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <h4 class="card-title"><?php echo $edit_question ? 'Edit Question' : 'Add New Question'; ?></h4>
            <form method="POST">
                <input type="hidden" name="edit_id" value="<?php echo $edit_question['id'] ?? ''; ?>">
                <div class="mb-3">
                    <label for="question_text" class="form-label">Question Text</label>
                    <textarea class="form-control" id="question_text" name="question_text" required><?php echo $edit_question['question_text'] ?? ''; ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Options</label>
                    <?php
                    $options = $edit_question ? json_decode($edit_question['options'], true) : ['', '', '', ''];
                    for ($i = 1; $i <= 4; $i++):
                    ?>
                        <input type="text" class="form-control mb-2" name="option<?php echo $i; ?>" value="<?php echo htmlspecialchars($options[$i-1]); ?>" placeholder="Option <?php echo $i; ?>" required>
                    <?php endfor; ?>
                </div>
                <div class="mb-3">
                    <label for="correct_answer" class="form-label">Correct Answer</label>
                    <select class="form-select" id="correct_answer" name="correct_answer" required>
                        <option value="">Select Correct Answer</option>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($edit_question && $edit_question['correct_answer'] == $i) ? 'selected' : ''; ?>>Option <?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Time Limit</label>
                    <div class="row">
                        <div class="col">
                            <input type="number" class="form-control" name="time_hours" min="0" value="<?php echo $edit_question ? floor($edit_question['time_limit'] / 3600) : '0'; ?>" placeholder="Hours">
                        </div>
                        <div class="col">
                            <input type="number" class="form-control" name="time_minutes" min="0" max="59" value="<?php echo $edit_question ? floor(($edit_question['time_limit'] % 3600) / 60) : '0'; ?>" placeholder="Minutes">
                        </div>
                        <div class="col">
                            <input type="number" class="form-control" name="time_seconds" min="0" max="59" value="<?php echo $edit_question ? ($edit_question['time_limit'] % 60) : '0'; ?>" placeholder="Seconds">
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="key_id" class="form-label">Key Number</label>
                    <select class="form-select" id="key_id" name="key_id" required>
                        <option value="">Select Key</option>
                        <?php for ($i = 1; $i <= 20; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($edit_question && $edit_question['key_id'] == $i) ? 'selected' : ''; ?>>Key #<?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo $edit_question ? 'Update Question' : 'Add Question'; ?></button>
            </form>
        </div>
    </div>

    <!-- Questions List -->
    <div class="card shadow">
        <div class="card-body">
            <h4 class="card-title">Existing Questions</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Question</th>
                        <th>Time Limit</th>
                        <th>Key</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $question): ?>
                        <tr>
                            <td><?php echo $question['id']; ?></td>
                            <td><?php echo htmlspecialchars($question['question_text']); ?></td>
                            <td>
                                <?php
                                $time_limit = $question['time_limit'];
                                $hours = floor($time_limit / 3600);
                                $minutes = floor(($time_limit % 3600) / 60);
                                $seconds = $time_limit % 60;
                                echo ($hours ? "$hours h " : '') . ($minutes ? "$minutes m " : '') . ($seconds ? "$seconds s" : '');
                                ?>
                            </td>
                            <td><?php echo $question['key_id']; ?></td>
                            <td>
                                <a href="?edit=<?php echo $question['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="?delete=<?php echo $question['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>