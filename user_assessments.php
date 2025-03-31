<?php
session_start();
include 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user_id']);
    exit();
}

try {
    $name_stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $name_stmt->execute([$user_id]);
    $user_name = $name_stmt->fetchColumn();
    if (!$user_name) {
        echo json_encode(['error' => 'User not found']);
        exit();
    }

    $keys_stmt = $conn->prepare("
        SELECT 
            key_id,
            correct_answers,
            total_questions,
            completed
        FROM key_progress 
        WHERE user_id = ?
        ORDER BY key_id ASC
    ");
    $keys_stmt->execute([$user_id]);
    $key_assessments = $keys_stmt->fetchAll(PDO::FETCH_ASSOC);

    $completed_keys_stmt = $conn->prepare("SELECT key_id, score FROM user_keys WHERE user_id = ?");
    $completed_keys_stmt->execute([$user_id]);
    $completed_keys = $completed_keys_stmt->fetchAll(PDO::FETCH_ASSOC);
    $completed_keys_map = array_column($completed_keys, 'score', 'key_id');

    $daily_stmt = $conn->prepare("
        SELECT 
            challenge_date,
            correct_answers,
            5 as total_questions,
            completed,
            CASE WHEN completed THEN 2 ELSE 0 END as score
        FROM daily_challenges 
        WHERE user_id = ?
        ORDER BY challenge_date ASC
    ");
    $daily_stmt->execute([$user_id]);
    $daily_assessments = $daily_stmt->fetchAll(PDO::FETCH_ASSOC);

    $assessments = [];
    foreach ($key_assessments as $key) {
        $score = $key['completed'] ? ($completed_keys_map[$key['key_id']] ?? 0) : 0;
        $assessments[] = [
            'assessment_name' => "Key #{$key['key_id']}" . ($key['completed'] ? " (Completed)" : ""),
            'questions_answered' => $key['correct_answers'],
            'total_questions' => $key['total_questions'],
            'score' => $score,
            'user_name' => $user_name
        ];
    }

    foreach ($daily_assessments as $daily) {
        $assessments[] = [
            'assessment_name' => "Daily Challenge ({$daily['challenge_date']})" . ($daily['completed'] ? " (Completed)" : ""),
            'questions_answered' => $daily['correct_answers'],
            'total_questions' => $daily['total_questions'],
            'score' => $daily['score'],
            'user_name' => $user_name
        ];
    }

    if (empty($assessments)) {
        $assessments[] = [
            'assessment_name' => 'No assessments started yet',
            'questions_answered' => 0,
            'total_questions' => 0,
            'score' => 0,
            'user_name' => $user_name
        ];
    }

    // Debug: Log raw data
    error_log("User ID: $user_id, Keys: " . print_r($key_assessments, true) . ", Daily: " . print_r($daily_assessments, true));

    header('Content-Type: application/json');
    echo json_encode($assessments);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>