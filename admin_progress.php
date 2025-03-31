<?php
session_start();
include 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    http_response_code(403);
    exit();
}

try {
    $stmt = $conn->prepare("
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
    $stmt->execute([$_SESSION['user_id']]);
    $progress = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $progress = [];
}

header('Content-Type: application/json');
echo json_encode($progress);
?>