/**
 * AdMaster Pro v6.0 - Frontend JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    initUspCheckboxes();
    initBidCards();
    initGoalCards();
    initDropdowns();
    initUploadArea();
    initFormSubmission();
});

// === Industry Selection ===
function selectIndustry(industry) {
    const url = new URL(window.location.href);
    url.searchParams.set('industry', industry);
    window.location.href = url.toString();
}

// === USP Checkboxes ===
function initUspCheckboxes() {
    document.querySelectorAll('.usp-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT') return;
            const checkbox = this.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            this.classList.toggle('selected', checkbox.checked);
        });
    });
}

// === Goal Cards ===
function initGoalCards() {
    document.querySelectorAll('.goal-card').forEach(card => {
        card.addEventListener('click', function(e) {
            document.querySelectorAll('.goal-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });
}

// === Bid Strategy Cards ===
function initBidCards() {
    document.querySelectorAll('.bid-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (e.target.tagName === 'INPUT' && e.target.type !== 'radio') return;
            document.querySelectorAll('.bid-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });
}

// === Dropdowns ===
function initDropdowns() {
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            this.closest('.dropdown').classList.toggle('open');
        });
    });

    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
    });
}

// === CSV Upload ===
function initUploadArea() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('csvFile');
    const fileInfo = document.getElementById('fileInfo');
    const analyzeBtn = document.getElementById('analyzeBtn');

    if (!uploadArea) return;

    uploadArea.addEventListener('click', () => fileInput.click());

    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelect(e.dataTransfer.files[0]);
        }
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length) {
            handleFileSelect(fileInput.files[0]);
        }
    });

    function handleFileSelect(file) {
        const maxSize = 5 * 1024 * 1024; // 5MB
        if (file.size > maxSize) {
            alert('A fájl túl nagy! Maximum 5 MB engedélyezett.');
            return;
        }

        fileInfo.innerHTML = `
            <strong>📄 ${escapeHtml(file.name)}</strong>
            <span style="color: var(--text-muted); margin-left: 12px;">
                ${(file.size / 1024).toFixed(1)} KB
            </span>
        `;
        fileInfo.classList.remove('hidden');
        analyzeBtn.disabled = false;
    }
}

// === Form Submission ===
function initFormSubmission() {
    const mainForm = document.getElementById('mainForm');
    const csvForm = document.getElementById('csvForm');
    const landingForm = document.getElementById('landingForm');
    const extensionsForm = document.getElementById('extensionsForm');
    const keywordsForm = document.getElementById('keywordsForm');
    const scriptForm = document.getElementById('scriptForm');

    if (mainForm) {
        mainForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            await submitForm(this, 'results', btn?.id || null, btn);
        });
    }

    if (csvForm) {
        csvForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            await submitForm(this, 'csvResults', 'analyzeBtn');
        });
    }

    if (landingForm) {
        landingForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            await submitForm(this, 'landingResults', 'landingBtn');
        });
    }

    if (extensionsForm) {
        extensionsForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            await submitForm(this, 'extensionsResults', btn.id || null, btn);
        });
    }

    if (keywordsForm) {
        keywordsForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            await submitForm(this, 'keywordsResults', btn.id || null, btn);
        });
    }

    if (scriptForm) {
        scriptForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            await submitForm(this, 'scriptResults', btn.id || null, btn);
        });
    }
    
    // Script type card selection
    document.querySelectorAll('.script-type-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.script-type-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            this.querySelector('input[type="radio"]').checked = true;
        });
    });
    
    // Load custom keywords from localStorage
    loadCustomKeywords();
}

async function submitForm(form, resultsId, btnId, btnElement = null) {
    const btn = btnElement || document.getElementById(btnId);
    const results = document.getElementById(resultsId);
    const originalText = btn.innerHTML;

    btn.innerHTML = '<div class="spinner"></div> Feldolgozás...';
    btn.disabled = true;

    try {
        const formData = new FormData(form);
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const html = await response.text();
        results.innerHTML = html;
        results.scrollIntoView({ behavior: 'smooth', block: 'start' });

    } catch (error) {
        results.innerHTML = `
            <div class="alert alert-error">
                <strong>Hiba!</strong> ${escapeHtml(error.message)}
            </div>
        `;
    } finally {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

// === Copy Functions ===
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('Másolva!');
    }).catch(err => {
        console.error('Copy failed:', err);
    });
}

function copyNegativeKeywords() {
    if (typeof negativeKeywords !== 'undefined') {
        const text = negativeKeywords.join('\n');
        navigator.clipboard.writeText(text).then(() => {
            showToast('Negatív kulcsszavak másolva!');
        });
    }
}

function copyAllHeadlines() {
    const headlines = document.querySelectorAll('#headlinesList .result-text');
    const text = Array.from(headlines).map(h => h.textContent).join('\n');
    navigator.clipboard.writeText(text).then(() => {
        showToast('Headlines másolva!');
    });
}

function copyAllDescriptions() {
    const descriptions = document.querySelectorAll('#descriptionsList .result-text');
    const text = Array.from(descriptions).map(d => d.textContent).join('\n');
    navigator.clipboard.writeText(text).then(() => {
        showToast('Descriptions másolva!');
    });
}

// === Project Save Modal ===
function openSaveModal() {
    document.getElementById('saveModal').classList.add('show');
    document.getElementById('projectName').focus();
}

function closeSaveModal() {
    document.getElementById('saveModal').classList.remove('show');
}

document.getElementById('saveProjectBtn')?.addEventListener('click', openSaveModal);

async function saveProject() {
    const name = document.getElementById('projectName').value.trim();
    if (!name) {
        alert('Adj meg egy projekt nevet!');
        return;
    }

    const form = document.getElementById('mainForm');
    const formData = new FormData(form);
    formData.set('action', 'save_project');
    formData.set('project_name', name);

    try {
        const response = await fetch('api.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            showToast('Projekt mentve!');
            closeSaveModal();
            // Reload to show in projects list
            setTimeout(() => window.location.reload(), 1000);
        } else {
            alert(result.error || 'Mentés sikertelen');
        }
    } catch (error) {
        alert('Hiba: ' + error.message);
    }
}

// === Toast Notification ===
function showToast(message, duration = 2000) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 24px;
        background: var(--accent-green);
        color: white;
        border-radius: 8px;
        font-weight: 500;
        z-index: 1000;
        animation: fadeIn 0.3s ease;
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// === Keyword List Copy ===
function copyKeywordList(type) {
    let listId;
    switch(type) {
        case 'exact': listId = 'exact-list'; break;
        case 'phrase': listId = 'phrase-list'; break;
        case 'broad': listId = 'broad-list'; break;
        case 'longtail': listId = 'longtail-list'; break;
        case 'negative': listId = 'negative-list'; break;
        default: return;
    }
    
    const textarea = document.getElementById(listId);
    if (textarea) {
        navigator.clipboard.writeText(textarea.value).then(() => {
            showToast('Lista másolva! 📋');
        }).catch(() => {
            textarea.select();
            document.execCommand('copy');
            showToast('Lista másolva! 📋');
        });
    }
}

// === Negative List Functions ===
function toggleNegativePreview(key) {
    const preview = document.getElementById('preview-' + key);
    if (preview) {
        preview.style.display = preview.style.display === 'none' ? 'block' : 'none';
    }
}

function copyNegativeList(key) {
    const textarea = document.getElementById('neglist-' + key);
    if (textarea) {
        navigator.clipboard.writeText(textarea.value).then(() => {
            showToast('Negatív lista másolva! 📋');
        });
    }
}

function copySelectedNegatives() {
    const checkboxes = document.querySelectorAll('.universal-negative-checkbox:checked');
    if (checkboxes.length === 0) {
        showToast('Válassz ki legalább egy listát!', 2000);
        return;
    }
    
    let allKeywords = [];
    checkboxes.forEach(cb => {
        const key = cb.dataset.list;
        const textarea = document.getElementById('neglist-' + key);
        if (textarea) {
            allKeywords = allKeywords.concat(textarea.value.split('\n'));
        }
    });
    
    // Deduplicate
    allKeywords = [...new Set(allKeywords)].filter(k => k.trim());
    
    navigator.clipboard.writeText(allKeywords.join('\n')).then(() => {
        showToast(`${allKeywords.length} negatív kulcsszó másolva! 📋`);
    });
}

// === Script Functions ===
function copyScriptCode() {
    const code = document.getElementById('scriptCode');
    if (code) {
        navigator.clipboard.writeText(code.textContent).then(() => {
            showToast('Script kód másolva! 📋');
        });
    }
}

// === Custom Keywords Storage ===
function saveCustomKeywords() {
    const working = document.getElementById('working_keywords')?.value || '';
    const negatives = document.getElementById('custom_negatives')?.value || '';
    
    localStorage.setItem('admaster_working_keywords', working);
    localStorage.setItem('admaster_custom_negatives', negatives);
    
    showToast('Kulcsszavak elmentve! 💾');
}

function loadCustomKeywords() {
    const working = localStorage.getItem('admaster_working_keywords');
    const negatives = localStorage.getItem('admaster_custom_negatives');
    
    if (working && document.getElementById('working_keywords')) {
        document.getElementById('working_keywords').value = working;
    }
    if (negatives && document.getElementById('custom_negatives')) {
        document.getElementById('custom_negatives').value = negatives;
    }
}

// === Call-Only Copy ===
function copyCallOnlyAd(index) {
    const cards = document.querySelectorAll('.callonly-card');
    if (cards[index]) {
        const business = cards[index].querySelector('.callonly-business')?.textContent || '';
        const desc1 = cards[index].querySelectorAll('.callonly-desc')[0]?.textContent || '';
        const desc2 = cards[index].querySelectorAll('.callonly-desc')[1]?.textContent || '';
        
        const text = `Business Name: ${business}\nDescription 1: ${desc1}\nDescription 2: ${desc2}`;
        navigator.clipboard.writeText(text).then(() => {
            showToast('Call-Only hirdetés másolva! 📋');
        });
    }
}

// === Utility ===
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Add animation keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
`;
document.head.appendChild(style);
