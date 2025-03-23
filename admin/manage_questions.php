<?php
session_start();
include '../config/db.php';
include '../includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

if (isset($_POST['add_question'])) {
    $question_text = $_POST['question_text'];
    $option_a = $_POST['option_a'];
    $option_b = $_POST['option_b'];
    $option_c = $_POST['option_c'];
    $option_d = $_POST['option_d'];
    $correct_answer = $_POST['correct_answer'];
    $difficulty_level = $_POST['difficulty_level'];

    $stmt = $conn->prepare("INSERT INTO questions (question_text, option_a, option_b, option_c, option_d, correct_answer, difficulty_level) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $difficulty_level]);
}
?>

<h2>Manage Questions</h2>
<form method="POST">
    <div class="mb-3">
        <label>Question</label>
        <textarea name="question_text" class="form-control" required></textarea>
    </div>
    <div class="mb-3">
        <label>Option A</label>
        <input type="text" name="option_a" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Option B</label>
        <input type="text" name="option_b" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Option C</label>
        <input type="text" name="option_c" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Option D</label>
        <input type="text" name="option_d" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Correct Answer</label>
        <select name="correct_answer" class="form-control" required>
            <option value="A">A</option>
            <option value="B">B</option>
            <option value="C">C</option>
            <option value="D">D</option>
        </select>
    </div>
    <div class="mb-3">
        <label>Difficulty Level</label>
        <input type="number" name="difficulty_level" class="form-control" required>
    </div>
    <button type="submit" name="add_question" class="btn btn-primary">Add Question</button>
</form>

<?php include '../includes/footer.php'; ?>