<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['token'])) {
    echo "Inte inloggad. <a href='index.html'>Logga in</a>";
    exit;
}

require __DIR__.'/vendor/autoload.php';

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

    // Hämta användardata via Firestore REST
    $url = "https://firestore.googleapis.com/v1/projects/$projectId/databases/(default)/documents/users/$uid";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($result, true);

    if (!isset($data['fields'])) {
        echo "Ingen data hittad. <a href='index.html'>Logga in igen</a>";
        exit;
    }

    $email = $data['fields']['email']['stringValue'] ?? 'Okänd';
    $earned = $data['fields']['earned']['integerValue'] ?? 0;

} catch (Throwable $e) {
    echo "Sessionen ogiltig. <a href='index.html'>Logga in igen</a><br>";
    echo htmlspecialchars($e->getMessage());
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Profil</title>
</head>
<body>

<h1>Din profil</h1>

<p>Email: <?php echo htmlspecialchars($email); ?></p>
<p>Poäng: <?php echo htmlspecialchars($earned); ?></p>

<button onclick="earn()">Se reklam och tjäna 1 poäng</button>

<script>
async function earn() {
  const res = await fetch('earn.php');
  const data = await res.json();
  if (data.earned !== undefined) {
    location.reload();
  } else {
    alert("Något gick fel");
  }
}
</script>


<p><a href="logout.php">Logga ut</a></p>

</body>
</html>
