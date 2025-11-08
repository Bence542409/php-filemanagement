<?php
// ======= Beállítások =======
$uploadDir = '../upload/'; // ide menti a fájlokat
$maxFileSize = 20 * 1024 * 1024; // max 20 MB

// ======= Könyvtár létrehozása, ha nem létezik =======
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        die("Nem sikerült létrehozni a feltöltési mappát!");
    }
}

// ======= Fájl feltöltés feldolgozása =======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $targetPath = $uploadDir . basename($file['name']);

    if ($file['error'] !== UPLOAD_ERR_OK) {
        die("Hiba a feltöltés során: " . $file['error']);
    }

    if ($file['size'] > $maxFileSize) {
        die("A fájl túl nagy! (max " . ($maxFileSize / 1024 / 1024) . " MB)");
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo "<p style='color:green;'>Feltöltés sikeres: " . htmlspecialchars($file['name']) . "</p>";
    } else {
        echo "<p style='color:red;'>Sikertelen feltöltés.</p>";
    }
}
?>

<!-- ======= Egyszerű HTML űrlap ======= -->
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<title>Fájl feltöltése</title>
</head>
<body style="font-family:sans-serif; margin:40px;">
    <h2>Fájl feltöltése</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <button type="submit">Feltöltés</button>
    </form>
</body>
</html>
