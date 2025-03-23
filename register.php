<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

if (isset($_POST['register'])) {
    $name = $_POST['name'];
    $grade = $_POST['grade'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name, grade, password) VALUES (?, ?, ?)");
    $stmt->execute([$name, $grade, $password]);
    $user_id = $conn->lastInsertId();
    $conn->prepare("INSERT INTO progress (user_id) VALUES (?)")->execute([$user_id]);
    header("Location: index.php");
    exit();
}
?>

<h2>Register</h2>
<form method="POST">
    <div class="mb-3">
        <label>Name</label>
        <input type="text" name="name" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Grade</label>
        <select name="grade" class="form-control" required>
            <option value="4">Grade 4</option>
            <option value="7">Grade 7</option>
        </select>
    </div>
    <div class="mb-3">
        <label>Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" name="register" class="btn btn-primary">Register</button>
</form>

<?php include 'includes/footer.php'; ?>