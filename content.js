// AdMaster Pro Chrome Extension - Content Script
// Runs on Google Ads pages

console.log('AdMaster Pro extension loaded');

// Listen for messages from popup
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  console.log('AdMaster message received:', request.action);
  
  switch (request.action) {
    case 'fillField':
      fillNextEmptyField(request.type, request.text);
      break;
      
    case 'fillAllHeadlines':
      fillAllHeadlines(request.headlines);
      break;
      
    case 'fillAllDescriptions':
      fillAllDescriptions(request.descriptions);
      break;
      
    case 'ping':
      sendResponse({ status: 'ok' });
      break;
  }
  
  return true;
});

// Find and fill the next empty headline/description field
function fillNextEmptyField(type, text) {
  let selectors = [];
  
  if (type === 'headline') {
    // Google Ads headline input selectors
    selectors = [
      'input[aria-label*="Headline"]',
      'input[aria-label*="headline"]',
      'input[aria-label*="Címsor"]',
      'input[placeholder*="Headline"]',
      '[data-field-type="headline"] input',
      '.headline-input input',
      // RSA editor
      'material-input[aria-label*="Headline"] input'
    ];
  } else if (type === 'description') {
    // Google Ads description input selectors
    selectors = [
      'textarea[aria-label*="Description"]',
      'textarea[aria-label*="description"]',
      'textarea[aria-label*="Leírás"]',
      'input[aria-label*="Description"]',
      '[data-field-type="description"] textarea',
      '[data-field-type="description"] input',
      '.description-input textarea',
      'material-input[aria-label*="Description"] input'
    ];
  }
  
  // Find empty field
  for (const selector of selectors) {
    const fields = document.querySelectorAll(selector);
    for (const field of fields) {
      if (!field.value || field.value.trim() === '') {
        fillField(field, text);
        return true;
      }
    }
  }
  
  // If no empty field found, try to fill focused field
  const focused = document.activeElement;
  if (focused && (focused.tagName === 'INPUT' || focused.tagName === 'TEXTAREA')) {
    fillField(focused, text);
    return true;
  }
  
  showNotification('Nem találtam üres mezőt!', 'warning');
  return false;
}

// Fill all headline fields
function fillAllHeadlines(headlines) {
  const selectors = [
    'input[aria-label*="Headline"]',
    'input[aria-label*="headline"]',
    'input[aria-label*="Címsor"]',
    '[data-field-type="headline"] input',
    'material-input[aria-label*="Headline"] input'
  ];
  
  let fields = [];
  for (const selector of selectors) {
    fields = fields.concat([...document.querySelectorAll(selector)]);
  }
  
  // Remove duplicates
  fields = [...new Set(fields)];
  
  let filled = 0;
  fields.forEach((field, index) => {
    if (index < headlines.length) {
      fillField(field, headlines[index]);
      filled++;
    }
  });
  
  showNotification(`${filled} headline beillesztve`, 'success');
}

// Fill all description fields
function fillAllDescriptions(descriptions) {
  const selectors = [
    'textarea[aria-label*="Description"]',
    'textarea[aria-label*="description"]',
    'input[aria-label*="Description"]',
    '[data-field-type="description"] textarea',
    '[data-field-type="description"] input'
  ];
  
  let fields = [];
  for (const selector of selectors) {
    fields = fields.concat([...document.querySelectorAll(selector)]);
  }
  
  // Remove duplicates
  fields = [...new Set(fields)];
  
  let filled = 0;
  fields.forEach((field, index) => {
    if (index < descriptions.length) {
      fillField(field, descriptions[index]);
      filled++;
    }
  });
  
  showNotification(`${filled} description beillesztve`, 'success');
}

// Fill a single field with proper event triggering
function fillField(field, text) {
  // Focus the field
  field.focus();
  
  // Clear existing value
  field.value = '';
  
  // Set new value
  field.value = text;
  
  // Trigger events that Google Ads might be listening to
  field.dispatchEvent(new Event('input', { bubbles: true }));
  field.dispatchEvent(new Event('change', { bubbles: true }));
  field.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
  
  // For Angular/React components
  const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
    window.HTMLInputElement.prototype, 'value'
  ).set;
  
  if (field.tagName === 'INPUT') {
    nativeInputValueSetter.call(field, text);
    field.dispatchEvent(new Event('input', { bubbles: true }));
  }
  
  // Highlight briefly
  field.style.backgroundColor = '#dcfce7';
  setTimeout(() => {
    field.style.backgroundColor = '';
  }, 500);
  
  console.log('AdMaster: Field filled with:', text.substring(0, 30) + '...');
}

// Show notification on page
function showNotification(message, type = 'info') {
  // Remove existing notification
  const existing = document.getElementById('admaster-notification');
  if (existing) existing.remove();
  
  const notification = document.createElement('div');
  notification.id = 'admaster-notification';
  notification.className = `admaster-notification admaster-${type}`;
  notification.innerHTML = `
    <span class="admaster-icon">🚀</span>
    <span class="admaster-message">${message}</span>
  `;
  
  document.body.appendChild(notification);
  
  // Animate in
  setTimeout(() => notification.classList.add('show'), 10);
  
  // Remove after 3 seconds
  setTimeout(() => {
    notification.classList.remove('show');
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Add floating button to page
function addFloatingButton() {
  if (document.getElementById('admaster-fab')) return;
  
  const fab = document.createElement('div');
  fab.id = 'admaster-fab';
  fab.innerHTML = '🚀';
  fab.title = 'AdMaster Pro';
  
  fab.addEventListener('click', () => {
    // Toggle panel
    togglePanel();
  });
  
  document.body.appendChild(fab);
}

// Create floating panel
let panelVisible = false;

function togglePanel() {
  let panel = document.getElementById('admaster-panel');
  
  if (panel) {
    panel.remove();
    panelVisible = false;
    return;
  }
  
  panel = document.createElement('div');
  panel.id = 'admaster-panel';
  panel.innerHTML = `
    <div class="admaster-panel-header">
      <span>🚀 AdMaster Pro</span>
      <button class="admaster-close">×</button>
    </div>
    <div class="admaster-panel-content">
      <p>Használd a bővítmény popup-ot a hirdetésszövegek beillesztéséhez!</p>
      <button class="admaster-panel-btn" id="admaster-paste-headlines">📋 Headlines beillesztése</button>
      <button class="admaster-panel-btn" id="admaster-paste-descriptions">📋 Descriptions beillesztése</button>
    </div>
  `;
  
  document.body.appendChild(panel);
  panelVisible = true;
  
  // Close button
  panel.querySelector('.admaster-close').addEventListener('click', () => {
    panel.remove();
    panelVisible = false;
  });
  
  // Paste buttons - request data from storage
  panel.querySelector('#admaster-paste-headlines').addEventListener('click', async () => {
    const stored = await chrome.storage.local.get(['adData']);
    if (stored.adData && stored.adData.headlines) {
      fillAllHeadlines(stored.adData.headlines.map(h => typeof h === 'string' ? h : h.text));
    } else {
      showNotification('Nincs mentett headline. Szinkronizálj a popup-ban!', 'warning');
    }
  });
  
  panel.querySelector('#admaster-paste-descriptions').addEventListener('click', async () => {
    const stored = await chrome.storage.local.get(['adData']);
    if (stored.adData && stored.adData.descriptions) {
      fillAllDescriptions(stored.adData.descriptions.map(d => typeof d === 'string' ? d : d.text));
    } else {
      showNotification('Nincs mentett description. Szinkronizálj a popup-ban!', 'warning');
    }
  });
}

// Initialize
if (window.location.href.includes('ads.google.com')) {
  addFloatingButton();
}
