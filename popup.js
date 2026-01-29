// AdMaster Pro Chrome Extension - Popup Script

document.addEventListener('DOMContentLoaded', async () => {
  // Load settings and data
  await loadSettings();
  await loadStoredData();
  setupEventListeners();
});

// Settings
let settings = {
  serverUrl: '',
  apiKey: ''
};

// Stored ad data
let adData = {
  headlines: [],
  descriptions: [],
  callonly: []
};

// Load settings from storage
async function loadSettings() {
  const stored = await chrome.storage.sync.get(['serverUrl', 'apiKey']);
  settings.serverUrl = stored.serverUrl || '';
  settings.apiKey = stored.apiKey || '';
  
  updateStatus();
}

// Load stored ad data
async function loadStoredData() {
  const stored = await chrome.storage.local.get(['adData', 'lastSync']);
  
  if (stored.adData) {
    adData = stored.adData;
    renderAllLists();
  }
  
  if (stored.lastSync) {
    const date = new Date(stored.lastSync);
    document.querySelector('.status-text').textContent = 
      `Utolsó szinkron: ${date.toLocaleTimeString('hu-HU')}`;
  }
}

// Update connection status
function updateStatus() {
  const statusEl = document.getElementById('status');
  
  if (!settings.serverUrl) {
    statusEl.className = 'status error';
    statusEl.querySelector('.status-text').textContent = 'Nincs szerver beállítva';
    return;
  }
  
  statusEl.className = 'status connected';
  statusEl.querySelector('.status-text').textContent = 'Kapcsolódva';
}

// Render all lists
function renderAllLists() {
  renderHeadlines();
  renderDescriptions();
  renderCallOnly();
}

// Render headlines list
function renderHeadlines() {
  const list = document.getElementById('headlines-list');
  const header = document.querySelector('#headlines-tab .content-header span');
  
  header.textContent = `📝 Headlines (${adData.headlines.length})`;
  
  if (adData.headlines.length === 0) {
    list.innerHTML = '<p class="empty">Nincs adat. Generálj az AdMaster-ben!</p>';
    return;
  }
  
  list.innerHTML = adData.headlines.map((h, i) => {
    const text = typeof h === 'string' ? h : h.text;
    const len = text.length;
    const overClass = len > 30 ? 'over' : '';
    
    return `
      <div class="item" data-index="${i}" data-type="headline">
        <span class="item-text">${escapeHtml(text)}</span>
        <span class="item-chars ${overClass}">${len}/30</span>
        <div class="item-actions">
          <button class="btn-icon copy-btn" title="Másolás">📋</button>
          <button class="btn-icon fill-btn" title="Beillesztés">📥</button>
        </div>
      </div>
    `;
  }).join('');
}

// Render descriptions list
function renderDescriptions() {
  const list = document.getElementById('descriptions-list');
  const header = document.querySelector('#descriptions-tab .content-header span');
  
  header.textContent = `📝 Descriptions (${adData.descriptions.length})`;
  
  if (adData.descriptions.length === 0) {
    list.innerHTML = '<p class="empty">Nincs adat.</p>';
    return;
  }
  
  list.innerHTML = adData.descriptions.map((d, i) => {
    const text = typeof d === 'string' ? d : d.text;
    const len = text.length;
    const overClass = len > 90 ? 'over' : '';
    
    return `
      <div class="item" data-index="${i}" data-type="description">
        <span class="item-text">${escapeHtml(text)}</span>
        <span class="item-chars ${overClass}">${len}/90</span>
        <div class="item-actions">
          <button class="btn-icon copy-btn" title="Másolás">📋</button>
          <button class="btn-icon fill-btn" title="Beillesztés">📥</button>
        </div>
      </div>
    `;
  }).join('');
}

// Render Call-Only list
function renderCallOnly() {
  const list = document.getElementById('callonly-list');
  const header = document.querySelector('#callonly-tab .content-header span');
  
  header.textContent = `📞 Call-Only Ads (${adData.callonly.length})`;
  
  if (adData.callonly.length === 0) {
    list.innerHTML = '<p class="empty">Nincs adat.</p>';
    return;
  }
  
  list.innerHTML = adData.callonly.map((c, i) => `
    <div class="callonly-card" data-index="${i}">
      <div class="co-business">${escapeHtml(c.business || '')}</div>
      <div class="co-desc">${escapeHtml(c.desc1 || '')} ${escapeHtml(c.desc2 || '')}</div>
      <div class="co-actions">
        <button class="btn-icon copy-btn" title="Másolás">📋</button>
        <button class="btn-icon fill-btn" title="Beillesztés">📥</button>
      </div>
    </div>
  `).join('');
}

// Setup event listeners
function setupEventListeners() {
  // Tab switching
  document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
      
      tab.classList.add('active');
      document.getElementById(`${tab.dataset.tab}-tab`).classList.add('active');
    });
  });
  
  // Copy buttons
  document.addEventListener('click', async (e) => {
    if (e.target.classList.contains('copy-btn')) {
      const item = e.target.closest('.item, .callonly-card');
      const index = parseInt(item.dataset.index);
      const type = item.dataset.type;
      
      let text = '';
      if (type === 'headline') {
        text = typeof adData.headlines[index] === 'string' ? adData.headlines[index] : adData.headlines[index].text;
      } else if (type === 'description') {
        text = typeof adData.descriptions[index] === 'string' ? adData.descriptions[index] : adData.descriptions[index].text;
      } else {
        const co = adData.callonly[index];
        text = `${co.business}\n${co.desc1}\n${co.desc2}`;
      }
      
      await navigator.clipboard.writeText(text);
      e.target.classList.add('success');
      e.target.textContent = '✓';
      setTimeout(() => {
        e.target.classList.remove('success');
        e.target.textContent = '📋';
      }, 1000);
    }
  });
  
  // Fill buttons - send to content script
  document.addEventListener('click', async (e) => {
    if (e.target.classList.contains('fill-btn')) {
      const item = e.target.closest('.item, .callonly-card');
      const index = parseInt(item.dataset.index);
      const type = item.dataset.type || 'callonly';
      
      let text = '';
      if (type === 'headline') {
        text = typeof adData.headlines[index] === 'string' ? adData.headlines[index] : adData.headlines[index].text;
      } else if (type === 'description') {
        text = typeof adData.descriptions[index] === 'string' ? adData.descriptions[index] : adData.descriptions[index].text;
      }
      
      // Send to content script
      const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
      if (tab.url.includes('ads.google.com')) {
        chrome.tabs.sendMessage(tab.id, {
          action: 'fillField',
          type: type,
          text: text
        });
        
        e.target.classList.add('success');
        e.target.textContent = '✓';
        setTimeout(() => {
          e.target.classList.remove('success');
          e.target.textContent = '📥';
        }, 1000);
      } else {
        alert('Ez a funkció csak a Google Ads oldalon működik!');
      }
    }
  });
  
  // Fill all headlines
  document.getElementById('fillAllHeadlines').addEventListener('click', async () => {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (tab.url.includes('ads.google.com')) {
      chrome.tabs.sendMessage(tab.id, {
        action: 'fillAllHeadlines',
        headlines: adData.headlines.map(h => typeof h === 'string' ? h : h.text)
      });
    } else {
      alert('Ez a funkció csak a Google Ads oldalon működik!');
    }
  });
  
  // Fill all descriptions
  document.getElementById('fillAllDescriptions').addEventListener('click', async () => {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
    if (tab.url.includes('ads.google.com')) {
      chrome.tabs.sendMessage(tab.id, {
        action: 'fillAllDescriptions',
        descriptions: adData.descriptions.map(d => typeof d === 'string' ? d : d.text)
      });
    } else {
      alert('Ez a funkció csak a Google Ads oldalon működik!');
    }
  });
  
  // Open AdMaster
  document.getElementById('openAdMaster').addEventListener('click', () => {
    if (settings.serverUrl) {
      chrome.tabs.create({ url: settings.serverUrl });
    } else {
      chrome.runtime.openOptionsPage();
    }
  });
  
  // Sync data
  document.getElementById('syncData').addEventListener('click', syncWithServer);
}

// Sync with AdMaster server
async function syncWithServer() {
  if (!settings.serverUrl) {
    alert('Állítsd be a szerver URL-t a beállításokban!');
    chrome.runtime.openOptionsPage();
    return;
  }
  
  const statusEl = document.getElementById('status');
  statusEl.querySelector('.status-text').textContent = 'Szinkronizálás...';
  
  try {
    const response = await fetch(`${settings.serverUrl}/api.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: `action=get_extension_data&api_key=${encodeURIComponent(settings.apiKey)}`
    });
    
    if (!response.ok) {
      throw new Error('Szerver hiba');
    }
    
    const data = await response.json();
    
    if (data.success) {
      adData = {
        headlines: data.headlines || [],
        descriptions: data.descriptions || [],
        callonly: data.callonly || []
      };
      
      // Save to storage
      await chrome.storage.local.set({
        adData: adData,
        lastSync: Date.now()
      });
      
      renderAllLists();
      
      statusEl.className = 'status connected';
      statusEl.querySelector('.status-text').textContent = 'Szinkronizálva ✓';
    } else {
      throw new Error(data.error || 'Ismeretlen hiba');
    }
  } catch (err) {
    statusEl.className = 'status error';
    statusEl.querySelector('.status-text').textContent = `Hiba: ${err.message}`;
  }
}

// Escape HTML
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
