</div>
    <!-- Bottom Navigation for Mobile/Tablet (Visible only if logged in) -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="bottom-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link" onclick="return checkAuth('dashboard.php')">
                    <i class="bi bi-house-door"></i><br>Home
                </a>
            </div>
            <div class="nav-item">
                <a href="quiz.php" class="nav-link" onclick="return checkAuth('quiz.php')">
                    <i class="bi bi-play-circle"></i><br>Play
                </a>
            </div>
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link" onclick="return checkAuth('dashboard.php')">
                    <i class="bi bi-person"></i><br>Profile
                </a>
            </div>
        </div>
        <script>
            // Client-side auth check (optional, as server-side handles it)
            function checkAuth(page) {
                return true; // Navigation proceeds if PHP allows
            }
        </script>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
</body>
</html>