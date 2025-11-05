<?php
session_start();

/**
 * Konfiguráció
 */
$BASE_DIR = 'C:\\Users\\user\\Desktop\\server1\\'; // alapkönyvtár (Windows)
$PASSWORD = 'admin'; // jelenlegi jelszó

// segédfüggvények
function join_path($base, $rel) {
    // biztosítjuk a backslashokat
    $base = rtrim($base, "/\\") . DIRECTORY_SEPARATOR;
    $rel = ltrim($rel, "/\\");
    // a felhasználó megadhat akár /ekke/album.jpg vagy ekke/album.jpg formátumot
    // windows környezetben helyettesítjük a /-t \
    $rel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    return $base . $rel;
}

function is_within_base($path, $base) {
    $baseReal = realpath($base);
    if ($baseReal === false) return false;
    $pathReal = realpath($path);
    if ($pathReal === false) return false;
    // normalizálás
    $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $pathReal = rtrim($pathReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strpos($pathReal, $baseReal) === 0;
}

function ensure_parent_within_base($target, $base) {
    // realpath nem működik ha a fájl még nem létezik -> ellenőrizzük a szülő mappát
    $parent = dirname($target);
    $parentReal = realpath($parent);
    if ($parentReal === false) return false;
    $baseReal = realpath($base);
    if ($baseReal === false) return false;
    $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $parentReal = rtrim($parentReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strpos($parentReal, $baseReal) === 0;
}

// CSRF token egyszerűen
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// kijelentkezés
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// belépés kezelése
$login_error = '';
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $pw = $_POST['password'] ?? '';
    if ($pw === $PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        // frissítsük a CSRF tokent
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
        header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    } else {
        $login_error = 'Hibás jelszó.';
    }
}

// jogosultság ellenőrzés
$logged_in = !empty($_SESSION['admin_logged_in']);

// műveletek: csak bejelentkezett felhasználónak
$message = '';
$error = '';

if ($logged_in && isset($_POST['action']) && isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    $action = $_POST['action'];

    if ($action === 'upload') {
        // feltöltés: megadott relatív célútvonal + file input name="uploadfile"
        $relTarget = $_POST['target'] ?? '';
        if (!isset($_FILES['uploadfile']) || $_FILES['uploadfile']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Nincs kiválasztott fájl vagy feltöltési hiba.';
        } else {
            $targetFull = join_path($BASE_DIR, $relTarget);
            // ellenőrizzük, hogy a célkönyvtár létezik és a base alatt van
            $parent = dirname($targetFull);
            if (!is_dir($parent)) {
                $error = 'A célkönyvtár nem létezik: ' . htmlspecialchars($parent);
            } else {
                if (!ensure_parent_within_base($targetFull, $BASE_DIR)) {
                    $error = 'A megadott célkönyvtár nincs az alapkönyvtár alatt (biztonsági ellenőrzés).';
                } else {
                    // mentés
                    $tmp = $_FILES['uploadfile']['tmp_name'];
                    // ha a user adott csak mappát (pl "ekke/"), akkor mentsük az eredeti fájlnévhez
                    if (is_dir($targetFull)) {
                        $filename = basename($_FILES['uploadfile']['name']);
                        $targetFull = rtrim($targetFull, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
                    }
                    if (move_uploaded_file($tmp, $targetFull)) {
                        // alapüzenet
                        $message = 'Feltöltés sikeres: ' . htmlspecialchars($relTarget);

                        // próbálja beállítani az olvasási jogokat (nix rendszereken)
                        @chmod($targetFull, 0644);

                        // Windows (IIS) alatt: próbáljuk meg NTFS ACL-t beállítani IUSR-nek
                        if (stripos(PHP_OS, 'WIN') === 0) {
                            // escapeshellarg biztonságos bemenetvédelem
                            $escapedPath = escapeshellarg($targetFull);
                            // PowerShell parancs: hozzáad egy ReadAndExecute jogosultságot az IUSR-hez
                            $cmd = 'powershell -Command "try { $acl = Get-Acl ' . $escapedPath . '; $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(\'IUSR\', \'ReadAndExecute\', \'Allow\'); $acl.AddAccessRule($rule); Set-Acl ' . $escapedPath . ' $acl } catch {}"';
                            @exec($cmd);
                        }
                    } else {
                        $error = 'Fájl mentése sikertelen.';
                    }
                }
            }
        }
    } elseif ($action === 'rename') {
        // átnevezés: source_rel (teljes relatív út), new_name (csak fájlnév vagy új relatív út)
        $sourceRel = $_POST['source_rel'] ?? '';
        $newRel = $_POST['new_rel'] ?? '';
        if ($sourceRel === '' || $newRel === '') {
            $error = 'Mindkét mezőt ki kell tölteni.';
        } else {
            $sourceFull = join_path($BASE_DIR, $sourceRel);
            $destFull = join_path($BASE_DIR, $newRel);
            if (!file_exists($sourceFull)) {
                $error = 'Forrásfájl/mappa nem található.';
            } else {
                // ha cél még nem létezik, győződjünk meg, hogy parent létezik és a base alatt van
                if (!ensure_parent_within_base($destFull, $BASE_DIR)) {
                    $error = 'Az új hely nincs az alapkönyvtár alatt vagy a célkönyvtár nem létezik.';
                } else {
                    if (@rename($sourceFull, $destFull)) {
                        $message = 'Átnevezés sikeres.';
                    } else {
                        $error = 'Átnevezés sikertelen. Jogosultság vagy path probléma lehet.';
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        // törlés: target_rel
        $targetRel = $_POST['delete_rel'] ?? '';
        if ($targetRel === '') {
            $error = 'Add meg a törölni kívánt relatív elérési utat.';
        } else {
            $targetFull = join_path($BASE_DIR, $targetRel);
            if (!file_exists($targetFull)) {
                $error = 'A fájl/mappa nem található.';
            } else {
                if (!is_within_base($targetFull, $BASE_DIR)) {
                    $error = 'A cél nincs az alapkönyvtár alatt (biztonsági ellenőrzés).';
                } else {
                    // fájl vagy üres mappa törlése
                    if (is_dir($targetFull)) {
                        // csak üres mappa törölhető ezzel a szkripttel (biztonság kedvéért)
                        $files = array_diff(scandir($targetFull), ['.', '..']);
                        if (count($files) > 0) {
                            $error = 'A mappa nem üres — törlés előtt ürítsd ki.';
                        } else {
                            if (@rmdir($targetFull)) {
                                $message = 'Mappa törölve.';
                            } else {
                                $error = 'Mappa törlése sikertelen.';
                            }
                        }
                    } else {
                        if (@unlink($targetFull)) {
                            $message = 'Fájl törölve.';
                        } else {
                            $error = 'Fájl törlése sikertelen (jogosultság?).';
                        }
                    }
                }
            }
        }
    } else {
        $error = 'Ismeretlen művelet.';
    }
} elseif (isset($_POST['action']) && $_POST['action'] !== 'login') {
    $error = 'Érvénytelen CSRF token vagy nincs bejelentkezve.';
}

// HTML megjelenítés
?>
<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin panel</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; margin: 20px; }
    .box { border:1px solid #ddd; padding:12px; margin-bottom:12px; border-radius:6px; max-width:900px; }
    .success { background:#e8f7e8; border-color:#b6e0b6; padding:8px; }
    .error { background:#fde8e8; border-color:#f1b6b6; padding:8px; }
    label { display:block; margin-top:6px; }
    input[type="text"], input[type="password"], input[type="file"] { width:100%; padding:6px; box-sizing:border-box; }
    button { padding:8px 12px; margin-top:8px; }
    small { color:#666; }
    pre { background:#f8f8f8; padding:8px; overflow:auto; }
</style>
</head>
<body>
<h2>Admin panel</h2>

<?php if (!$logged_in): ?>
    <div class="box">
        <h3>Bejelentkezés</h3>
        <?php if ($login_error): ?><div class="error"><?=htmlspecialchars($login_error)?></div><?php endif; ?>
        <form method="post">
            <label>Jelszó:
                <input type="password" name="password" required>
            </label>
            <input type="hidden" name="action" value="login">
            <button type="submit">Bejelentkezés</button>
        </form>
    </div>
<?php else: ?>
    <p>Bejelentkezve - <a href="?logout=1">Kijelentkezés</a></p>

    <?php if ($message): ?><div class="success"><?=htmlspecialchars($message)?></div><?php endif; ?>
    <?php if ($error): ?><div class="error"><?=htmlspecialchars($error)?></div><?php endif; ?>

    <div class="box">
        <h3>Feltöltés</h3>
        <form method="post" enctype="multipart/form-data">
            <label>Elérési út:
                <input type="text" name="target" placeholder="teszt/ vagy teszt/ujnev.jpg" required>
            </label>
            <label>Fájl:
                <input type="file" name="uploadfile" required>
            </label>
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'])?>">
            <button type="submit">Feltöltés</button>
        </form>
    </div>

    <div class="box">
        <h3>Átnevezés / Áthelyezés</h3>
        <form method="post">
            <label>Forrás elérési út:
                <input type="text" name="source_rel" placeholder="teszt/old.jpg" required>
            </label>
            <label>Új elérési út / név:
                <input type="text" name="new_rel" placeholder="teszt/ujnev.jpg vagy teszt/sub/ujnev.jpg" required>
            </label>
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'])?>">
            <button type="submit">Átnevezés / Áthelyezés</button>
        </form>
    </div>

    <div class="box">
        <h3>Törlés</h3>
        <form method="post" onsubmit="return confirm('Biztosan törlöd? Ez visszafordíthatatlan!');">
            <label>Elérési út:
                <input type="text" name="delete_rel" placeholder="teszt/old.jpg vagy teszt/uresmappa" required>
            </label>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'])?>">
            <button type="submit">Törlés</button>
        </form>
        <small>Figyelem: mappát csak akkor tud törölni a rendszer, ha az üres.</small>
    </div>

    <div class="box">
        <h3>Fájllista megtekintése</h3>
        <form method="get">
            <label>Elérési út:
                <input type="text" name="ls" placeholder="teszt/ vagy üres az alaphoz" value="<?=isset($_GET['ls'])?htmlspecialchars($_GET['ls']):''?>">
            </label>
            <button type="submit">Lista</button>
        </form>

        <?php
        if (isset($_GET['ls'])) {
            $rel = $_GET['ls'];
            $full = join_path($BASE_DIR, $rel);
            $real = realpath($full);
            if ($real === false) {
                echo '<div class="error">Az elérési út nem található vagy nem létezik.</div>';
            } else {
                if (!is_within_base($real, $BASE_DIR)) {
                    echo '<div class="error">Az út nincs az alapkönyvtár alatt (biztonsági ellenőrzés).</div>';
                } else {
                    echo '<h4>Lista: ' . htmlspecialchars($rel) . '</h4><pre>';
                    $items = array_diff(scandir($real), ['.', '..']);
                    foreach ($items as $it) {
                        $p = $real . DIRECTORY_SEPARATOR . $it;
                        $type = is_dir($p) ? '[DIR]' : '[FILE]';
                        echo $type . ' ' . $it . PHP_EOL;
                    }
                    echo '</pre>';
                }
            }
        }
        ?>
    </div>

<?php endif; ?>

</body>
</html>
