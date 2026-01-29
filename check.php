<?php
/**
 * AdMaster Pro - Verzió Ellenőrző
 * 
 * Töröld ezt a fájlt használat után!
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 AdMaster Pro - Verzió Ellenőrző</h1>";
echo "<style>body{font-family:sans-serif;padding:20px;max-width:800px;margin:0 auto}table{width:100%;border-collapse:collapse}td,th{padding:10px;border:1px solid #ddd;text-align:left}.ok{color:green}.error{color:red}pre{background:#f5f5f5;padding:10px;overflow:auto}</style>";

// Config ellenőrzés
echo "<h2>📋 Config</h2>";
if (file_exists('config.php')) {
    require_once 'config.php';
    echo "<p>APP_VERSION: <strong>" . (defined('APP_VERSION') ? APP_VERSION : 'NEM DEFINIÁLT') . "</strong></p>";
    echo "<p>APP_NAME: " . (defined('APP_NAME') ? APP_NAME : 'NEM DEFINIÁLT') . "</p>";
    echo "<p>REQUIRE_LOGIN: " . (defined('REQUIRE_LOGIN') ? (REQUIRE_LOGIN ? 'true' : 'false') : 'NEM DEFINIÁLT') . "</p>";
} else {
    echo "<p class='error'>❌ config.php NEM TALÁLHATÓ!</p>";
}

// Fájlok ellenőrzése
echo "<h2>📁 Fájlok</h2>";
echo "<table>";
echo "<tr><th>Fájl</th><th>Létezik</th><th>Méret</th><th>Módosítva</th></tr>";

$files = [
    'index.php',
    'api.php',
    'config.php',
    'login.php',
    'settings.php',
    'update.php',
    'install.php',
    'includes/Security.php',
    'includes/ClientManager.php',
    'includes/RelevanceChecker.php',
    'includes/VisionAnalyzer.php',
    'assets/style.css'
];

foreach ($files as $file) {
    $exists = file_exists($file);
    $size = $exists ? filesize($file) : 0;
    $modified = $exists ? date('Y-m-d H:i:s', filemtime($file)) : '-';
    
    echo "<tr>";
    echo "<td>{$file}</td>";
    echo "<td class='" . ($exists ? 'ok' : 'error') . "'>" . ($exists ? '✅ Van' : '❌ HIÁNYZIK') . "</td>";
    echo "<td>" . ($exists ? number_format($size) . ' byte' : '-') . "</td>";
    echo "<td>{$modified}</td>";
    echo "</tr>";
}
echo "</table>";

// Adatbázis ellenőrzés
echo "<h2>🗄️ Adatbázis</h2>";
if (defined('DB_HOST') && !empty(DB_HOST)) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        echo "<p class='ok'>✅ Kapcsolat OK</p>";
        
        // Verzió
        try {
            $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'db_version'");
            $dbVersion = $stmt->fetchColumn();
            echo "<p>DB Verzió: <strong>" . ($dbVersion ?: 'nincs beállítva') . "</strong></p>";
        } catch (Exception $e) {
            echo "<p class='error'>Settings tábla nem elérhető</p>";
        }
        
        // Táblák
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Táblák: " . implode(', ', $tables) . "</p>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ DB Hiba: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>❌ DB nincs konfigurálva</p>";
}

// PHP info
echo "<h2>🐘 PHP</h2>";
echo "<p>Verzió: " . PHP_VERSION . "</p>";
echo "<p>Extensions: " . implode(', ', ['curl', 'json', 'pdo', 'pdo_mysql']) . "</p>";

// Cache tipp
echo "<h2>💡 Ha régi verzió jelenik meg</h2>";
echo "<ol>";
echo "<li>Töröld a böngésző cache-t (Ctrl+Shift+R vagy Cmd+Shift+R)</li>";
echo "<li>Ellenőrizd, hogy az FTP feltöltés sikeres volt-e</li>";
echo "<li>Ellenőrizd a fájl jogosultságokat (755 mappák, 644 fájlok)</li>";
echo "</ol>";

echo "<hr>";
echo "<p><strong>⚠️ Töröld ezt a fájlt (check.php) miután végeztél!</strong></p>";
?>
