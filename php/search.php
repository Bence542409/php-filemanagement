<?php
$baseDir = realpath(__DIR__ . '/..');
if ($baseDir === false) {
    http_response_code(500);
    exit("Hiba: a baseDir nem található.");
}

// alapértelmezett keresési mappa
$ALLOWED_DIRS = ['ekke', 'public'];

// admin jelszó
$ADMIN_PASSWORD = 'admin'; // ide állítsd a jelszavad

// ha admin bejelentkezett, akkor az egész baseDir engedélyezett
session_start();
if (isset($_GET['admin_login'])) {
    $pw = $_GET['pw'] ?? '';
    if ($pw === $ADMIN_PASSWORD) {
        $_SESSION['is_admin'] = true;
    } else {
        echo "<script>alert('Hibás jelszó!');</script>";
    }
}

// kijelentkezés kezelése
if (isset($_GET['admin_logout'])) {
    unset($_SESSION['is_admin']);
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

if (!empty($_SESSION['is_admin'])) {
    $ALLOWED_DIRS = ['.'];
}

function safeRelativePath($fullPath, $baseDir) {
    $real = realpath($fullPath);
    if ($real === false) return false;
    if (strncmp($real, $baseDir, strlen($baseDir)) !== 0) return false;
    $rel = substr($real, strlen($baseDir));
    return ltrim(str_replace('\\', '/', $rel), '/');
}

function searchFilesRecursive($dir, $baseDir, $query, &$out, $depth = 0, $allowedDirs = []) {
    $items = @scandir($dir);
    if ($items === false) return;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $full = $dir . DIRECTORY_SEPARATOR . $item;
        $rel = safeRelativePath($full, $baseDir);
        if ($rel === false) continue;

        $isAllowed = false;
        foreach ($allowedDirs as $ad) {
            if ($ad === '.' || $rel === $ad || str_starts_with($rel, $ad . '/')) {
                $isAllowed = true;
                break;
            }
        }
        if (!$isAllowed) continue;

        $match = ($query !== '' && stripos(strtolower($rel), strtolower($query)) !== false);

        if (is_dir($full)) {
            if ($match || ($query === '' && isset($_GET['q']))) {
                $out[] = ['type' => 'dir', 'full' => $full, 'rel' => $rel, 'depth' => $depth];
            }
            searchFilesRecursive($full, $baseDir, $query, $out, $depth + 1, $allowedDirs);
        } else {
            if ($match || ($query === '' && isset($_GET['q']))) {
                $out[] = ['type' => 'file', 'full' => $full, 'rel' => $rel, 'depth' => $depth];
            }
        }
    }
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if (isset($_GET['q'])) {
    searchFilesRecursive($baseDir, $baseDir, $query, $results, 0, $ALLOWED_DIRS);
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<title>Fájlkereső</title>
<style>
body { font-family: Arial, sans-serif; background:#fafafa; padding:20px; }
.card { background:#fff; padding:20px; border-radius:10px; max-width:1000px; margin:auto; }
input[type=text] { width:70%; padding:8px; border-radius:4px; border:1px solid #ccc; font-size:14px; }
button { padding:8px 12px; border-radius:4px; border:none; background:#007bff; color:#fff; cursor:pointer; }
ul { list-style:none; padding-left:0; margin-top:10px; }
li { display:flex; gap:10px; align-items:center; padding:6px 0; border-bottom:1px solid #eee; word-break:break-all; }
.icon { width:24px; text-align:center; }
.path { flex:1; font-family:monospace; }
a { color:#007bff; text-decoration:none; }
a:hover { text-decoration:underline; }
.depth { display:inline-block; width:calc(var(--depth) * 20px); }
.hidden { display:none; }
.admin-btn { margin-top:10px; padding:6px 10px; background:#28a745; color:#fff; border:none; border-radius:4px; cursor:pointer; }
</style>
</head>
<body>
<div class="card">
    <h2>Fájlkereső</h2>
    <p style="font-size:13px; color:#555;">Engedélyezett útvonalak: <br />
        <?php 
        foreach ($ALLOWED_DIRS as $dir) {
            echo htmlspecialchars($baseDir . DIRECTORY_SEPARATOR . $dir) . '<br>';
        }
        ?>
    </p>

    <form method="get" style="margin-bottom:10px;">
        <input type="text" id="search" name="q" placeholder="Keresés név vagy útvonal alapján..." value="<?php echo htmlspecialchars($query); ?>" autofocus>
        <button>Keresés</button>
    </form>

    <?php if (empty($_SESSION['is_admin'])): ?>
    <button class="admin-btn" onclick="adminLogin()">Admin bejelentkezés</button>
    <script>
    function adminLogin() {
        const pw = prompt('Add meg az admin jelszót:');
        if(pw !== null){
            window.location.href = '?admin_login=1&pw=' + encodeURIComponent(pw);
        }
    }
    </script>
    <?php else: ?>
        <button class="admin-btn" onclick="window.location.href='?admin_logout=1'">Admin kijelentkezés</button>
    <?php endif; ?>


    <?php if (isset($_GET['q'])): ?>
        <?php if (empty($results)): ?>
            <p style="font-size:13px; color:#555; margin-top:10px;">Nincs találat.</p>
        <?php else: ?>
            <h3 style="margin-top:15px;">Találatok: <?php echo count($results); ?></h3>
            <ul>
                <?php foreach ($results as $r): ?>
                    <li class="folder-row" style="--depth: <?php echo $r['depth']; ?>;">
                        <span class="depth"></span>
                        <span class="icon"><?php echo $r['type']==='dir'?'📁':'📄'; ?></span>
                        <span class="path"><a class="folder-link" href="../<?php echo htmlspecialchars($r['rel']); ?>" target="_blank"><?php echo htmlspecialchars($r['rel']); ?></a></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
