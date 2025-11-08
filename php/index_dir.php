<?php

// --- DOWNLOAD HANDLER ---
if (isset($_GET['download'])) {
    $requested = rawurldecode($_GET['download']);
    if (strpos($requested, '..') !== false) {
        http_response_code(400);
        exit("Hibás kérés.");
    }

    $requested = ltrim($requested, "/\\");
    $targetPath = $baseDir . DIRECTORY_SEPARATOR . $requested;
    $realTarget = realpath($targetPath);
    $realBase = realpath($baseDir);

    if ($realTarget === false || strpos($realTarget, $realBase) !== 0) {
        http_response_code(403);
        exit("Hozzáférés megtagadva.");
    }

    if (is_dir($realTarget)) {
        $zipName = basename($realTarget) . '.zip';
        $tmpZip = tempnam(sys_get_temp_dir(), 'dlzip_');
        $zip = new ZipArchive();
        $zip->open($tmpZip, ZipArchive::OVERWRITE);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realTarget, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($realTarget) + 1);
            $zip->addFile($filePath, $relativePath);
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . rawurlencode($zipName) . '"');
        header('Content-Length: ' . filesize($tmpZip));
        readfile($tmpZip);
        unlink($tmpZip);
        exit;
    } elseif (is_file($realTarget)) {
        $filename = basename($realTarget);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($realTarget));
        readfile($realTarget);
        exit;
    } else {
        http_response_code(404);
        exit("A kért elem nem található.");
    }
}
// --- /DOWNLOAD HANDLER ---


$parentDirName = basename($baseDir);
$allItems = array_diff(scandir($baseDir), ['.', '..', basename(__FILE__)]);

// kizárt rendszerfájlok és ponttal kezdődő fájlok
$excludedSystem = ['$RECYCLE.BIN','System Volume Information','web.config','index.php', 'admin.php'];

$items = [];
foreach ($allItems as $item) {
    if (in_array($item, $excludedSystem)) continue;
    if (strpos($item, '.') === 0) continue;

    $fullPath = $baseDir . DIRECTORY_SEPARATOR . $item;
    $items[] = [
        'name' => $item,
        'path' => $item,
        'is_dir' => is_dir($fullPath),
        'ctime' => filectime($fullPath),
        'mtime' => filemtime($fullPath),
        'size'  => is_file($fullPath) ? filesize($fullPath) : 0
    ];
}

usort($items, fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
$totalItems = count($items);

function formatSize($bytes){
    if ($bytes >= 1024**3) return round($bytes/(1024**3),2).' GB';
    if ($bytes >= 1024**2) return round($bytes/(1024**2),2).' MB';
    if ($bytes >= 1024) return round($bytes/1024,2).' KB';
    return $bytes.' B';
}

function getRelativePath($url, $baseDir = "/server1/", $parentDirName = "server1") {
    // URL útvonal részének kinyerése
    $path = parse_url($url, PHP_URL_PATH);
    
    // Base dir eltávolítása
    $relative = str_replace($baseDir, "", $path);
    
    // Levágjuk a kezdő "/"-t, ha van
    $relative = ltrim($relative, "/");

    // Ha üres, akkor a parentDirName-t adjuk vissza
    if (empty($relative)) {
        return $parentDirName;
    }

    return $relative;
}

// Aktuális URL összeállítása
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$path = $_SERVER['REQUEST_URI'];
$currentUrl = $protocol . $host . $path;

// Relatív út
$relativePath = getRelativePath($currentUrl);
?>
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($parentDirName) ?></title>
<style>

/* Chrome, Edge, Safari */
body::-webkit-scrollbar {
    width: 0;                    /* scrollbar szélesség 0 */
    background: transparent;      /* háttér átlátszó */
}
body{ 
    font-family:Arial,sans-serif;
    padding:20px;
    background:#f9f9f9;
}
.controls {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
    align-items: center;
}

.controls input{flex:1;
    padding:8px;
    border:1px solid #ddd;
    border-radius:4px;
}
table{
    width:100%;
    border-collapse:collapse;
    background:#fff;
    box-shadow:0 2px 6px rgba(0,0,0,0.1);}
th, td{
    text-align:left;
    padding:8px 10px;
    border-bottom:1px solid #ddd;
}
th{
    background:#007BFF;
    color:#fff;
    position:sticky;
    top:0;
}
a.folder-link{
    color:dodgerblue;
    text-decoration:none;
}
a.folder-link:hover{
    text-decoration:underline;
}
tr.hidden{
    display:none;
}
.header-container {
    display:flex;
    justify-content:
        space-between;
    align-items:center;
    flex-wrap:wrap;
    margin-bottom:20px;
}
.header-stats {
    font-size:0.9em;
    color:#666;
    white-space:nowrap;
}

.download-icon {
    display:inline-block;
    width:18px;
    height:18px;
    vertical-align:middle;
    text-decoration:none;
    opacity:1;
    text-align: center;
}
.download-icon:hover, .controls a:hover {
    opacity:0.5;
}
    
@media(min-width:768px) {
    .middle {
        text-align: center;
    }
}
    
/* Mobil nézet: kártyás lista */
@media(max-width:768px){
    table, thead, tbody, th, td, tr {
        display:block;
        width:100%;
    }
    thead { display:none; }
    
    .folder-row {
        background:#fff;
        margin-bottom:12px;
        padding:8px; /* kisebb padding, hogy kiférjen */
        border-radius:8px;
        box-shadow:0 1px 3px rgba(0,0,0,0.1);
        box-sizing:border-box; /* fontos, hogy a padding ne növelje a szélességet */
        word-break: break-word; /* hosszú nevek tördelése */
        margin-bottom: 15px;
    }

    .folder-row td {
        display:block;
        padding:4px 0;
        border:none;
    }

    .folder-row td a.folder-link {
        font-weight:bold;
        color:#007BFF;
        text-decoration:none;
        display:block;
        margin-bottom:4px;
        word-break: break-word; /* hosszú nevek ne lógjanak ki */
    }

    .folder-row td a.folder-link:hover { text-decoration:underline; }

    .folder-row td::before {
        content: attr(data-label)": ";
        font-weight:bold;
        color:#333;
        display:inline-block;
        margin-right:5px;
    }

    .title { font-size:28px; margin-bottom:10px; }
    .header-stats { display:none; }
    
    .icons {
        display: none;
    }
}

</style>
</head>
<body>
<div class="header-container">
    <h1 class="title"><?php echo htmlspecialchars($relativePath); ?></h1>
    <div class="header-stats">
        Elemek: <?= $totalItems ?>
    </div>
</div>
    
<div class="controls">
    <input type="text" id="search" placeholder="Keresés...">
    
    <a href="/server1/php/search.php?q=<?php echo htmlspecialchars($relativePath); ?>" title="Keresés az összes almappában" style="text-decoration:none; color:inherit; margin-right: 7px" class="icons">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
    </svg>
    </a>
    <a href="/server1/php/download.php?<?php echo htmlspecialchars($relativePath); ?>" title="Letöltés" style="text-decoration:none; color:inherit; margin-right: 7px" class="icons">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
        <polyline points="7 10 12 15 17 10"></polyline>
        <line x1="12" y1="15" x2="12" y2="3"></line>
    </svg>
    </a>
    <a href="/server1/php/admin.php" title="Bejelentkezés" style="text-decoration:none; color:inherit;" class="icons">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="8" r="4"></circle>
        <path d="M6 20v-2a6 6 0 0 1 12 0v2"></path>
        <line x1="16" y1="11" x2="20" y2="11"></line>
        <polyline points="18 9 20 11 18 13"></polyline>
    </svg>
    </a>
</div>

<table id="folderTable">
<thead>
<tr>
    <th>Fájl</th>
    <th>Létrehozva</th>
    <th>Módosítva</th>
    <th>Méret</th>
    <th>Típus</th>
    <th style="padding: 0" class="middle">Letöltés</th>
</tr>
</thead>
<tbody>
<?php foreach($items as $item):
    $link = $item['is_dir'] ? $item['name'] . '/' : $item['name'];

    // fájl kiterjesztés
    $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
    $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp','bmp','svg']);
    $isVideo = in_array($ext, ['mp4','avi','mkv','mov','wmv','flv','webm']);

    // típus címke (egyszerű, "fájl" szó nélkül)
    if ($item['is_dir']) {
        $typeLabel = 'Mappa';
    } else {
        $typeLabel = $ext ? strtoupper($ext) : 'Ismeretlen';
    }
?>


<tr class="folder-row">
    <td data-label="Fájl">
        <a 
            href="<?= htmlspecialchars($link) ?>" 
            class="folder-link"
            <?php if(!$item['is_dir'] && !$isImage && !$isVideo && $ext !== 'pdf' && $ext !== 'php'): ?> download <?php endif; ?>>
            <?= htmlspecialchars($item['name']) ?>
        </a>
    </td>
    <td data-label="Létrehozva"><?= date('Y-m-d H:i:s',$item['ctime']) ?></td>
    <td data-label="Módosítva"><?= date('Y-m-d H:i:s',$item['mtime']) ?></td>
    <td data-label="Méret"><?= $item['is_dir'] ? '&mdash;' : formatSize($item['size']) ?></td>
    <td data-label="Típus"><?= htmlspecialchars($typeLabel) ?></td>
    <td data-label="Letöltés" class="middle">
        <a class="download-icon" title="Letöltés" href="?download=<?= rawurlencode($item['path']) ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"> <circle cx="12" cy="12" r="10"/><path d="M16 12l-4 4-4-4M12 8v7"/></svg>
        </a>
    </td>
</tr>

<?php endforeach; ?>
</tbody>
</table>

<script>
const searchInput = document.getElementById('search');
searchInput.addEventListener('input', () => {
    const filter = searchInput.value.trim().toLowerCase();
    document.querySelectorAll('.folder-row').forEach(row => {
        const text = row.querySelector('td[data-label="Fájl"]').innerText.toLowerCase();
        row.classList.toggle('hidden', !text.includes(filter));
    });
});
    
// billentyűzet
document.addEventListener('keydown', e => {
    const searchInput = document.getElementById("search");

    // TAB gomb – mindig törli az input mezőt
    if(e.key === "Tab"){
        e.preventDefault(); 
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input'));
        return;
    }

    // BACKSPACE → visszalépés, ha nem inputban vagyunk
    if(e.key === "Backspace" && document.activeElement !== searchInput){
        e.preventDefault();
        history.back();
        return;
    }

    // ESC → egy directoryval feljebb, ha nem inputban vagyunk
    if(e.key === "Escape" && document.activeElement !== searchInput){
        e.preventDefault();
        window.location.href = '../';
        return;
    }

    // ESC → kilépés az inputból, ha inputban vagyunk
    if(e.key === "Escape" && document.activeElement === searchInput){
        e.preventDefault();
        searchInput.blur();
        return;
    }

    // ENTER kezelése
    if(e.key === "Enter"){
        const visibleRows = Array.from(document.querySelectorAll('.folder-row'))
            .filter(row => !row.classList.contains('hidden'));

        if(document.activeElement === searchInput){
            // ENTER inputban → első elemre ugrás
            e.preventDefault();
            if(visibleRows.length > 0){
                const link = visibleRows[0].querySelector('a.folder-link');
                if(link) window.location.href = link.href;
            }
        } else {
            // ENTER inputon kívül → fókusz a keresőre
            e.preventDefault();
            searchInput.focus();
        }
        return;
    }

    // számgombok 0–9
    if(/^[0-9]$/.test(e.key)){
        const visibleRows = Array.from(document.querySelectorAll('.folder-row'))
            .filter(row => !row.classList.contains('hidden'));

        if(e.key === '0'){
            // 0 → egy directoryval feljebb
            window.location.href = '../';
        } else {
            // 1–9 → megfelelő elemre ugrás
            const index = parseInt(e.key, 10) - 1;
            if(index < visibleRows.length){
                const link = visibleRows[index].querySelector('a.folder-link');
                if(link) window.location.href = link.href;
            }
        }
        e.preventDefault();
        return;
    }
});

</script>

<footer style="margin-top:40px;text-align:center;font-size:0.9em;color:#666;">
    Server is powered by: 
    <a href="https://nemeth-bence.com" target="_blank" style="color:dodgerblue;text-decoration:none;">
        Németh Bence
    </a>
</footer>
</body>
</html>
