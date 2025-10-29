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
    $factory = (new Factory)
        ->withServiceAccount($serviceAccountPath)
        ->withProjectId($projectId);
    $auth = $factory->createAuth();
    $verified = $auth->verifyIdToken($token);
    $uid = $verified->claims()->get('sub');

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

<style>
html, body {
  margin: 0;
  padding: 0;
}

#adModal {
  position: fixed !important;
  top: 0 !important;
  left: 0 !important;
  width: 100vw !important;
  height: 100vh !important;
  background: rgba(0,0,0,0.85) !important;
  display: none; /* ← FIXEN! INGET !important */
  justify-content: center !important;
  align-items: center !important;
  z-index: 2147483647 !important;
}

#adModal video {
  width: 480px !important;
  height: auto !important;
  display: block !important;
}
</style>

</head>
<body>

<!-- Modal direkt efter <body> -->
<div id="adModal">
  <video id="adVideo" controls muted playsinline></video>
</div>

<h1>Din profil</h1>

<p>Email: <?php echo htmlspecialchars($email); ?></p>
<p>Poäng: <?php echo htmlspecialchars($earned); ?></p>

<button id="earnBtn">Se reklam och tjäna 1 poäng</button>

<p><a href="logout.php">Logga ut</a></p>

<script>
console.log("Script startar");

const earnBtn = document.getElementById('earnBtn');
const modal = document.getElementById('adModal');
const video = document.getElementById('adVideo');

earnBtn.addEventListener('click', showAd);

function showAd() {
  console.log("Knappen klickad");

  modal.style.display = "flex";
  console.log("Modal visas");

video.src = "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerJoyrides.mp4";


  video.play().then(() => {
    console.log("Videon startade");
  }).catch(err => {
    console.log("Video play error:", err);
  });

  video.onended = () => {
    console.log("Video slut → +1 poäng");
    modal.style.display = "none";
    earnPoint();
  };
}

async function earnPoint() {
  const res = await fetch('earn.php');
  const data = await res.json();
  console.log("Earn svar:", data);

  if (data.earned !== undefined) {
    location.reload();
  } else {
    alert("Fel: " + JSON.stringify(data));
  }
}
</script>

</body>
</html>
