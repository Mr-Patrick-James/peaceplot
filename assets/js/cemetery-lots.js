let currentLots = [];
let editingLotId = null;

async function loadCemeteryLots() {
    const tbody = document.querySelector('.table tbody');
    if (!tbody) return;

    try {
        const result = await API.fetchLots();
        
        if (result.success && result.data) {
            currentLots = result.data;
            renderLots(result.data);
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#ef4444;">Failed to load cemetery lots</td></tr>';
        }
    } catch (error) {
        console.error('Error loading lots:', error);
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#ef4444;">Error loading data</td></tr>';
    }
}

function filterLots(query) {
    const q = (query || '').trim().toLowerCase();
    if (!q) return currentLots.slice();
    return currentLots.filter(lot => {
        const fields = [
            lot.lot_number,
            lot.section,
            lot.block,
            lot.position,
            lot.status,
            lot.deceased_name
        ];
        return fields.some(val => (val || '').toString().toLowerCase().includes(q));
    });
}

function renderLots(lots) {
    const tbody = document.querySelector('.table tbody');
    if (!tbody) return;

    if (lots.length === 0) {
        const q = (document.getElementById('lotSearch')?.value || '').trim();
        const msg = q ? 'No matching lots found' : 'No cemetery lots found';
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:#6b7280;">${msg}</td></tr>`;
        return;
    }

    tbody.innerHTML = lots.map(lot => `
        <tr data-lot-id="${lot.id}">
            <td>${lot.lot_number}</td>
            <td>${lot.section}</td>
            <td>${lot.block || lot.position || '—'}</td>
            <td><span class="badge ${lot.status.toLowerCase()}">${lot.status}</span></td>
            <td class="${lot.deceased_name ? '' : 'muted'}">${lot.deceased_name || '—'}</td>
            <td>
                <div class="actions">
                    <button class="btn-action btn-edit" data-action="edit" data-lot-id="${lot.id}">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                            </svg>
                        </span>
                        <span>Edit</span>
                    </button>
                    <button class="btn-action btn-map" data-action="map" data-lot-id="${lot.id}" data-lot-number="${lot.lot_number}">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" fill="currentColor" fill-opacity="0.2"/>
                                <circle cx="12" cy="10" r="3" fill="currentColor"/>
                                <path d="M12 2v20" stroke-width="1" opacity="0.3"/>
                            </svg>
                        </span>
                        <span>View on Map</span>
                    </button>
                    <button class="btn-action btn-delete" data-action="delete" data-lot-id="${lot.id}">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18" />
                                <path d="M8 6V4h8v2" />
                                <path d="M19 6l-1 14H6L5 6" />
                                <path d="M10 11v6" />
                                <path d="M14 11v6" />
                            </svg>
                        </span>
                        <span>Delete</span>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function handleDelete(lotId) {
    const lot = currentLots.find(l => l.id == lotId);
    const lotNumber = lot ? lot.lot_number : lotId;
    
    if (!confirm(`Are you sure you want to delete lot ${lotNumber}?`)) {
        return;
    }

    const result = await API.deleteLot(lotId);
    
    if (result.success) {
        loadCemeteryLots();
    } else {
        console.error('Failed to delete lot:', result.message);
    }
}

function showAddModal() {
    const modal = createLotModal();
    document.body.appendChild(modal);
    modal.style.display = 'flex';
}

function showEditModal(lotId) {
    const lot = currentLots.find(l => l.id == lotId);
    if (!lot) return;

    editingLotId = lotId;
    const modal = createLotModal(lot);
    document.body.appendChild(modal);
    modal.style.display = 'flex';
}

function createLotModal(lot = null) {
    const isEdit = lot !== null;
    
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>${isEdit ? 'Edit Cemetery Lot' : 'Add New Cemetery Lot'}</h2>
                <button class="modal-close">&times;</button>
            </div>
            <form id="lotForm" class="modal-body">
                <div class="form-group">
                    <label>Lot Number *</label>
                    <input type="text" name="lot_number" value="${lot?.lot_number || ''}" required>
                </div>
                <div class="form-group">
                    <label>Section *</label>
                    <input type="text" name="section" value="${lot?.section || ''}" required>
                </div>
                <div class="form-group">
                    <label>Block</label>
                    <input type="text" name="block" value="${lot?.block || ''}">
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" value="${lot?.position || ''}">
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" required>
                        <option value="Vacant" ${lot?.status === 'Vacant' ? 'selected' : ''}>Vacant</option>
                        <option value="Occupied" ${lot?.status === 'Occupied' ? 'selected' : ''}>Occupied</option>
                        <option value="Maintenance" ${lot?.status === 'Maintenance' ? 'selected' : ''}>Maintenance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Size (sqm)</label>
                    <input type="number" step="0.01" name="size_sqm" value="${lot?.size_sqm || ''}">
                </div>
            </form>
            <div class="modal-footer">
                <button type="button" class="btn-secondary modal-cancel">Cancel</button>
                <button type="submit" form="lotForm" class="btn-primary">${isEdit ? 'Update' : 'Create'}</button>
            </div>
        </div>
    `;

    modal.querySelector('.modal-close').onclick = () => closeModal(modal);
    modal.querySelector('.modal-cancel').onclick = () => closeModal(modal);
    modal.onclick = (e) => { if (e.target === modal) closeModal(modal); };
    
    modal.querySelector('#lotForm').onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        
        const result = isEdit 
            ? await API.updateLot(editingLotId, data)
            : await API.createLot(data);
        
        if (result.success) {
            closeModal(modal);
            loadCemeteryLots();
            editingLotId = null;
        } else {
            console.error('Error:', result.message);
        }
    };

    return modal;
}

function closeModal(modal) {
    modal.style.display = 'none';
    modal.remove();
}

function handleMapRedirect(lotId, lotNumber) {
    // Store the lot to highlight in sessionStorage
    sessionStorage.setItem('highlightLot', lotId);
    sessionStorage.setItem('highlightLotNumber', lotNumber);
    
    // Redirect to cemetery map page
    window.location.href = 'cemetery-map.php';
}

document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname;
    if (path.includes('index.html') || path.includes('index.php')) {
        loadCemeteryLots();
        const searchInput = document.getElementById('lotSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const filtered = filterLots(e.target.value);
                renderLots(filtered);
            });
        }
    }
});

document.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    
    const action = btn.getAttribute('data-action');
    const lotId = btn.getAttribute('data-lot-id');
    
    if (action === 'delete' && lotId) {
        handleDelete(lotId);
    } else if (action === 'edit' && lotId) {
        showEditModal(lotId);
    } else if (action === 'map' && lotId) {
        handleMapRedirect(lotId, btn.getAttribute('data-lot-number'));
    } else if (action === 'add') {
        showAddModal();
    }
});
