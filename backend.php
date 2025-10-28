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
    $factory = (new Factory)
        ->withServiceAccount($serviceAccountPath)
        ->withProjectId($projectId);

    $auth = $factory->createAuth();
    $verifiedToken = $auth->verifyIdToken($token);

    $uid = $verifiedToken->claims()->get('sub');
    $email = $verifiedToken->claims()->get('email');

    $_SESSION['token'] = $token;

    // Firestore REST
    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/users/$uid";

    // Hämta användaren
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    $userData = json_decode($result, true);

    if (!isset($userData['fields'])) {
        // Skapa ny user
        $newUser = [
            'fields' => [
                'email' => ['stringValue' => $email],
                'earned' => ['integerValue' => 0],
                'createdAt' => ['timestampValue' => date('c')]
            ]
        ];

        $ch2 = curl_init($url);
        curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($newUser));
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch2);
        curl_close($ch2);
    } else {
        // Uppdatera enbart email om user finns
        $updateData = [
            'fields' => [
                'email' => ['stringValue' => $email]
            ]
        ];

        $updateUrl = $url . "?updateMask.fieldPaths=email";
        $ch3 = curl_init($updateUrl);
        curl_setopt($ch3, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch3, CURLOPT_POSTFIELDS, json_encode($updateData));
        curl_setopt($ch3, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch3);
        curl_close($ch3);
    }

    echo json_encode([
        'status' => 'ok',
        'uid' => $uid,
        'email' => $email
    ]);

} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Token verification failed',
        'details' => $e->getMessage()
    ]);
}
