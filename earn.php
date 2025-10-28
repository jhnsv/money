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

// Enkel anti-fusk: minst 10 sekunder mellan earnings
if (isset($_SESSION['lastEarn']) && time() - $_SESSION['lastEarn'] < 10) {
    echo json_encode(['error' => 'Too soon']);
    exit;
}

try {
    // Verifiera token
    $factory = (new Factory)
        ->withServiceAccount($serviceAccountPath)
        ->withProjectId($projectId);

    $auth = $factory->createAuth();
    $verified = $auth->verifyIdToken($token);

    $uid = $verified->claims()->get('sub');

    // Hämta nuvarande Poäng
    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/users/$uid";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);

    $userData = json_decode($res, true);
    $earned = intval($userData['fields']['earned']['integerValue'] ?? 0);

    // +1 poäng
    $earned++;


// Spara till Firestore med MERGE (behåll andra fält)
$updateData = [
    'fields' => [
        'earned' => ['integerValue' => $earned]
    ]
];

$updateUrl = $url . "?updateMask.fieldPaths=earned";

$updateCh = curl_init($updateUrl);
curl_setopt($updateCh, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($updateCh, CURLOPT_POSTFIELDS, json_encode($updateData));
curl_setopt($updateCh, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer '.$token,
    'Content-Type: application/json'
]);
curl_setopt($updateCh, CURLOPT_RETURNTRANSFER, true);
curl_exec($updateCh);
curl_close($updateCh);



    // Spara anti-fusk timestamp
    $_SESSION['lastEarn'] = time();

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
