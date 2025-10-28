<?php
session_start();
header('Content-Type: application/json');

require __DIR__.'/vendor/autoload.php';

use Kreait\Firebase\Factory;

$projectId = 'money-f26a3';
$serviceAccountPath = __DIR__.'/alien-drake-476419-b9-a8edb0adffa1.json';

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (!$token) {
    http_response_code(400);
    echo json_encode(['error' => 'No token sent']);
    exit;
}

try {
    // 1. Verify ID token
    $factory = (new Factory)
        ->withServiceAccount($serviceAccountPath)
        ->withProjectId($projectId);

    $auth = $factory->createAuth();
    $verifiedToken = $auth->verifyIdToken($token);

    $uid = $verifiedToken->claims()->get('sub');
    $email = $verifiedToken->claims()->get('email');

    $_SESSION['token'] = $token;

    // 2. Save to Firestore via REST
    $data = [
        'fields' => [
            'email' => ['stringValue' => $email],
            'earned' => ['integerValue' => 0],
            'createdAt' => ['timestampValue' => date('c')]
        ]
    ];

    $ch = curl_init("https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/users/$uid?mask.fieldPaths=email");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    echo json_encode([
        'status' => 'ok',
        'uid' => $uid,
        'email' => $email
    ]);

} catch (\Throwable $e) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Token verification failed',
        'details' => $e->getMessage()
    ]);
}
