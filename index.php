<?php
session_start();
include 'config/db.php';
include 'includes/header.php';

if (isset($_POST['login'])) {
    $name = $_POST['name'];
    $password = $_POST['password'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE name = ?");
    $stmt->execute([$name]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: dashboard.php");
        exit();
    } else {
        echo "<div class='alert alert-danger'>Invalid credentials</div>";
    }
}
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h2>Login</h2>
            <form method="POST">
                <div class="mb-3">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary">Login</button>
                <p>Not registered? <a href="register.php">Register here</a></p>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>