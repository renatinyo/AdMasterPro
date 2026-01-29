<?php
/**
 * TESZT - Kampány mentés ügyfélhez
 * Töröld használat után!
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/ClientManager.php';

$cm = new ClientManager();
$clients = $cm->getClients();
$lastResultFile = __DIR__ . '/data/last_result.json';
$lastResult = file_exists($lastResultFile) ? json_decode(file_get_contents($lastResultFile), true) : null;

$message = '';

// Teszt mentés
if ($_POST['action'] ?? '' === 'test_save') {
    $clientId = $_POST['client_id'] ?? '';
    
    if (empty($clientId)) {
        $message = '❌ Válassz ügyfelet!';
    } elseif (!$lastResult) {
        $message = '❌ Nincs last_result.json!';
    } else {
        $result = $cm->saveGenerationToClient($clientId, $lastResult);
        $message = $result ? '✅ Mentés sikeres!' : '❌ Mentés sikertelen!';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Teszt - Mentés Ügyfélhez</title>
    <style>
        body { font-family: sans-serif; padding: 40px; max-width: 600px; margin: 0 auto; }
        .card { background: #f5f5f5; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .success { color: green; }
        .error { color: red; }
        select, button { padding: 10px 20px; font-size: 16px; }
        pre { background: #333; color: #0f0; padding: 15px; overflow: auto; max-height: 300px; }
    </style>
</head>
<body>
    <h1>🧪 Teszt - Kampány Mentés Ügyfélhez</h1>
    
    <?php if ($message): ?>
    <div class="card">
        <strong><?= $message ?></strong>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <h3>📁 last_result.json</h3>
        <?php if ($lastResult): ?>
        <p class="success">✅ Létezik!</p>
        <p>Cég: <?= htmlspecialchars($lastResult['company'] ?? 'N/A') ?></p>
        <p>Headlines: <?= count($lastResult['headlines'] ?? []) ?> db</p>
        <p>Descriptions: <?= count($lastResult['descriptions'] ?? []) ?> db</p>
        <?php else: ?>
        <p class="error">❌ NEM létezik vagy üres!</p>
        <p>Elvárt útvonal: <?= $lastResultFile ?></p>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h3>👥 Ügyfelek (<?= count($clients) ?> db)</h3>
        <?php if (empty($clients)): ?>
        <p class="error">❌ Nincsenek ügyfelek!</p>
        <?php else: ?>
        <ul>
        <?php foreach ($clients as $c): ?>
            <li><?= htmlspecialchars($c['name']) ?> (<?= $c['id'] ?>) - Generálások: <?= count($c['generations'] ?? []) ?></li>
        <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    
    <?php if ($lastResult && !empty($clients)): ?>
    <div class="card">
        <h3>🧪 Teszt Mentés</h3>
        <form method="POST">
            <input type="hidden" name="action" value="test_save">
            <select name="client_id">
                <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">💾 Mentés Tesztelése</button>
        </form>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <h3>📋 last_result.json tartalom</h3>
        <pre><?= $lastResult ? json_encode($lastResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'ÜRES' ?></pre>
    </div>
    
    <hr>
    <p><a href="index.php">← Vissza</a> | <strong>⚠️ Töröld ezt a fájlt (test-save.php) használat után!</strong></p>
</body>
</html>
