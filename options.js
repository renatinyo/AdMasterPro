// AdMaster Pro Chrome Extension - Options Script

document.addEventListener('DOMContentLoaded', loadSettings);

// Load saved settings
async function loadSettings() {
  const stored = await chrome.storage.sync.get([
    'serverUrl', 
    'apiKey', 
    'showFab', 
    'autoSync'
  ]);
  
  document.getElementById('serverUrl').value = stored.serverUrl || '';
  document.getElementById('apiKey').value = stored.apiKey || '';
  document.getElementById('showFab').checked = stored.showFab !== false;
  document.getElementById('autoSync').checked = stored.autoSync !== false;
}

// Save settings
document.getElementById('saveBtn').addEventListener('click', async () => {
  const settings = {
    serverUrl: document.getElementById('serverUrl').value.trim().replace(/\/$/, ''),
    apiKey: document.getElementById('apiKey').value.trim(),
    showFab: document.getElementById('showFab').checked,
    autoSync: document.getElementById('autoSync').checked
  };
  
  await chrome.storage.sync.set(settings);
  
  showAlert('Beállítások mentve!', 'success');
});

// Test connection
document.getElementById('testConnection').addEventListener('click', async () => {
  const serverUrl = document.getElementById('serverUrl').value.trim().replace(/\/$/, '');
  const apiKey = document.getElementById('apiKey').value.trim();
  const resultEl = document.getElementById('testResult');
  
  if (!serverUrl) {
    resultEl.className = 'test-result error';
    resultEl.textContent = '❌ Add meg a szerver URL-t!';
    resultEl.classList.remove('hidden');
    return;
  }
  
  resultEl.className = 'test-result';
  resultEl.textContent = '⏳ Kapcsolódás...';
  resultEl.classList.remove('hidden');
  
  try {
    const response = await fetch(`${serverUrl}/api.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: `action=extension_ping&api_key=${encodeURIComponent(apiKey)}`
    });
    
    if (response.ok) {
      const data = await response.json();
      if (data.success) {
        resultEl.className = 'test-result success';
        resultEl.textContent = `✅ Kapcsolat OK! AdMaster Pro v${data.version || '?'}`;
      } else {
        throw new Error(data.error || 'Ismeretlen hiba');
      }
    } else {
      throw new Error(`HTTP ${response.status}`);
    }
  } catch (err) {
    resultEl.className = 'test-result error';
    resultEl.textContent = `❌ Hiba: ${err.message}`;
  }
});

// Import data
document.getElementById('importBtn').addEventListener('click', async () => {
  const importText = document.getElementById('importData').value.trim();
  
  if (!importText) {
    showAlert('Nincs mit importálni!', 'error');
    return;
  }
  
  try {
    const data = JSON.parse(importText);
    
    const adData = {
      headlines: data.headlines || [],
      descriptions: data.descriptions || [],
      callonly: data.callonly || []
    };
    
    await chrome.storage.local.set({
      adData: adData,
      lastSync: Date.now()
    });
    
    showAlert(`Importálva: ${adData.headlines.length} headline, ${adData.descriptions.length} description`, 'success');
    document.getElementById('importData').value = '';
    
  } catch (err) {
    showAlert('Érvénytelen JSON formátum!', 'error');
  }
});

// Reset settings
document.getElementById('resetBtn').addEventListener('click', async () => {
  if (confirm('Biztosan törlöd az összes beállítást és adatot?')) {
    await chrome.storage.sync.clear();
    await chrome.storage.local.clear();
    
    document.getElementById('serverUrl').value = '';
    document.getElementById('apiKey').value = '';
    document.getElementById('showFab').checked = true;
    document.getElementById('autoSync').checked = true;
    document.getElementById('importData').value = '';
    
    showAlert('Minden törölve, alaphelyzet visszaállítva.', 'success');
  }
});

// Show alert
function showAlert(message, type) {
  const alertEl = document.getElementById('alert');
  alertEl.className = `alert alert-${type}`;
  alertEl.textContent = message;
  alertEl.classList.remove('hidden');
  
  setTimeout(() => {
    alertEl.classList.add('hidden');
  }, 3000);
}
