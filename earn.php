<?php
session_start();
header('Content-Type: application/json');

require __DIR__.'/vendor/autoload.php';

if (!isset($_SESSION['token'])) {
    echo json_encode(['error' => 'No session']);
    exit;
}

use Kreait\Firebase\Factory;

$projectId = 'money-f26a3';
$serviceAccountPath = __DIR__.'/alien-drake-476419-b9-a8edb0adffa1.json';
$token = $_SESSION['token'];

try {
    // Verifiera token
    $factory = (new Factory)
        ->withServiceAccount($serviceAccountPath)
        ->withProjectId($projectId);

    $auth = $factory->createAuth();
    $verified = $auth->verifyIdToken($token);

    $uid = $verified->claims()->get('sub');

    // Hämta nuvarande poäng
    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/users/$uid";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    $userData = json_decode($result, true);
    $earned = intval($userData['fields']['earned']['integerValue'] ?? 0);

    // +1 poäng
    $earned++;

    // Spara till Firestore
    $updateData = [
        'fields' => [
            'earned' => ['integerValue' => $earned]
        ]
    ];

    $ch = curl_init("https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/users/$uid");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);

    echo json_encode([
        'status' => 'earned',
        'earned' => $earned
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'error' => 'Error updating score',
        'details' => $e->getMessage()
    ]);
}
