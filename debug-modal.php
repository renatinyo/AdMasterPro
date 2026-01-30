<?php
/**
 * DEBUG - Modal és Mentés Teszt
 * TÖRÖLD HASZNÁLAT UTÁN!
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/ClientManager.php';

Security::initSession();

$cm = new ClientManager();
$clients = $cm->getClients();

$lastResultFile = __DIR__ . '/data/last_result.json';
$lastResult = file_exists($lastResultFile) ? json_decode(file_get_contents($lastResultFile), true) : null;

$historyFile = __DIR__ . '/data/generation_history.json';
$history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <title>Debug - Modal Teszt</title>
    <style>
        body { font-family: sans-serif; padding: 40px; max-width: 800px; margin: 0 auto; }
        .card { background: #f5f5f5; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .success { color: green; }
        .error { color: red; }
        button { padding: 15px 30px; font-size: 16px; margin: 5px; cursor: pointer; }
        .btn-primary { background: #3b82f6; color: white; border: none; border-radius: 8px; }
        .btn-success { background: #22c55e; color: white; border: none; border-radius: 8px; }
        pre { background: #1e1e1e; color: #0f0; padding: 15px; overflow: auto; max-height: 200px; font-size: 12px; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; }
        .modal { background: white; padding: 30px; border-radius: 12px; max-width: 400px; width: 90%; }
        .form-control { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        #debugLog { background: #333; color: #0f0; padding: 15px; font-family: monospace; font-size: 12px; max-height: 300px; overflow: auto; }
    </style>
</head>
<body>
    <h1>🔧 Debug - Modal és Mentés Teszt</h1>
    
    <div class="card">
        <h3>📊 Állapot</h3>
        <p><strong>last_result.json:</strong> 
            <?php if ($lastResult): ?>
                <span class="success">✅ Létezik (<?= count($lastResult['headlines'] ?? []) ?> headline)</span>
            <?php else: ?>
                <span class="error">❌ NEM létezik</span>
            <?php endif; ?>
        </p>
        <p><strong>Ügyfelek:</strong> <?= count($clients) ?> db</p>
        <p><strong>History:</strong> <?= count($history) ?> db</p>
    </div>
    
    <div class="card">
        <h3>🧪 Teszt Gombok</h3>
        <button class="btn-primary" onclick="testModal()">1. Modal Megnyitása</button>
        <button class="btn-success" onclick="testSave()">2. Mentés Tesztelése (AJAX)</button>
        <button class="btn-primary" onclick="checkElements()">3. DOM Elemek Ellenőrzése</button>
    </div>
    
    <div class="card">
        <h3>📋 Debug Log</h3>
        <div id="debugLog">Kattints egy gombra...</div>
    </div>
    
    <!-- TESZT MODAL -->
    <div class="modal-overlay" id="testSaveModal">
        <div class="modal">
            <h3>💾 Mentés Ügyfélhez (TESZT)</h3>
            <select id="testClientSelect" class="form-control">
                <option value="">-- Válassz ügyfelet --</option>
                <?php foreach ($clients as $client): ?>
                <option value="<?= htmlspecialchars($client['id']) ?>"><?= htmlspecialchars($client['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="testNewClientName" class="form-control" placeholder="Vagy új ügyfél neve...">
            <br>
            <button class="btn-success" onclick="doTestSave()">💾 Mentés</button>
            <button onclick="closeTestModal()">Mégse</button>
        </div>
    </div>
    
    <div class="card">
        <h3>📦 lastResult tartalom</h3>
        <pre><?= $lastResult ? json_encode($lastResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'NULL - Nincs last_result.json!' ?></pre>
    </div>
    
    <script>
    // CSRF token
    const csrfToken = '<?= Security::generateCsrfToken() ?>';
    
    // lastResultData
    const lastResultData = <?= $lastResult ? json_encode($lastResult) : 'null' ?>;
    
    function log(msg) {
        const el = document.getElementById('debugLog');
        el.innerHTML += new Date().toLocaleTimeString() + ' - ' + msg + '\n';
        el.scrollTop = el.scrollHeight;
    }
    
    function testModal() {
        log('🔵 Modal megnyitása...');
        const modal = document.getElementById('testSaveModal');
        if (modal) {
            modal.style.display = 'flex';
            log('✅ Modal megnyitva (display: flex)');
        } else {
            log('❌ Modal elem NEM TALÁLHATÓ!');
        }
    }
    
    function closeTestModal() {
        document.getElementById('testSaveModal').style.display = 'none';
        log('Modal bezárva');
    }
    
    function checkElements() {
        log('🔍 DOM elemek ellenőrzése...');
        
        // Ellenőrizzük a fő oldalon lévő elemeket
        const elements = [
            'saveToClientModal',
            'saveToClientSelect', 
            'newClientName',
            'testSaveModal',
            'testClientSelect'
        ];
        
        elements.forEach(id => {
            const el = document.getElementById(id);
            log(id + ': ' + (el ? '✅ MEGVAN' : '❌ HIÁNYZIK'));
        });
        
        log('lastResultData: ' + (lastResultData ? '✅ VAN (' + (lastResultData.headlines?.length || 0) + ' headline)' : '❌ NULL'));
        log('csrfToken: ' + (csrfToken ? '✅ VAN' : '❌ HIÁNYZIK'));
    }
    
    function testSave() {
        log('🔵 AJAX mentés teszt...');
        
        if (!lastResultData) {
            log('❌ lastResultData NULL - nincs mit menteni!');
            alert('Nincs lastResultData!');
            return;
        }
        
        testModal();
    }
    
    async function doTestSave() {
        const clientId = document.getElementById('testClientSelect').value;
        const newName = document.getElementById('testNewClientName').value.trim();
        
        log('Kiválasztott client_id: ' + (clientId || 'NINCS'));
        log('Új név: ' + (newName || 'NINCS'));
        
        if (!clientId && !newName) {
            log('❌ Nincs ügyfél kiválasztva és nincs új név!');
            alert('Válassz ügyfelet vagy adj meg nevet!');
            return;
        }
        
        let targetClientId = clientId;
        
        // Új ügyfél létrehozása ha kell
        if (!clientId && newName) {
            log('🔵 Új ügyfél létrehozása: ' + newName);
            
            const createForm = new FormData();
            createForm.append('action', 'save_client');
            createForm.append('name', newName);
            createForm.append('industry', lastResultData?.industry || 'general');
            createForm.append('csrf_token', csrfToken);
            
            try {
                const resp = await fetch('api.php', { method: 'POST', body: createForm });
                const text = await resp.text();
                log('API válasz (create): ' + text.substring(0, 200));
                
                const data = JSON.parse(text);
                if (data.success && data.client_id) {
                    targetClientId = data.client_id;
                    log('✅ Ügyfél létrehozva: ' + targetClientId);
                } else {
                    log('❌ Hiba: ' + (data.error || JSON.stringify(data)));
                    alert('Hiba: ' + (data.error || 'Ismeretlen'));
                    return;
                }
            } catch (e) {
                log('❌ Exception: ' + e.message);
                alert('Hiba: ' + e.message);
                return;
            }
        }
        
        // Generálás mentése
        log('🔵 Generálás mentése client_id=' + targetClientId);
        
        const saveForm = new FormData();
        saveForm.append('action', 'save_generation_to_client');
        saveForm.append('client_id', targetClientId);
        saveForm.append('generation', JSON.stringify(lastResultData));
        saveForm.append('csrf_token', csrfToken);
        
        try {
            const resp = await fetch('api.php', { method: 'POST', body: saveForm });
            const text = await resp.text();
            log('API válasz (save): ' + text.substring(0, 200));
            
            const data = JSON.parse(text);
            if (data.success) {
                log('✅ SIKER! Generálás mentve!');
                alert('✅ Mentés sikeres!');
                closeTestModal();
            } else {
                log('❌ Hiba: ' + (data.error || JSON.stringify(data)));
                alert('Hiba: ' + (data.error || 'Ismeretlen'));
            }
        } catch (e) {
            log('❌ Exception: ' + e.message);
            alert('Hiba: ' + e.message);
        }
    }
    
    // Induláskor
    log('🚀 Debug oldal betöltve');
    log('lastResultData: ' + (lastResultData ? 'OK' : 'NULL'));
    log('Ügyfelek: <?= count($clients) ?> db');
    </script>
    
    <hr>
    <p><a href="index.php">← Vissza a főoldalra</a> | <strong style="color:red">⚠️ TÖRÖLD EZT A FÁJLT (debug-modal.php) HASZNÁLAT UTÁN!</strong></p>
</body>
</html>
