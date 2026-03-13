let currentLots = [];
let editingLotId = null;
let currentPage = 1;
const rowsPerPage = 20;
let searchQuery = '';
let statusFilter = '';
let sectionFilter = '';
let blockFilter = '';

async function loadCemeteryLots(page = 1) {
    const tbody = document.querySelector('.table tbody');
    if (!tbody) return;

    // Show loading state
    tbody.innerHTML = `
        <tr>
            <td colspan="6" style="text-align:center; padding: 40px; color:#6b7280;">
                <div class="loading-spinner" style="display: inline-block; width: 30px; height: 30px; border: 3px solid rgba(0,0,0,0.1); border-radius: 50%; border-top-color: #3b82f6; animation: spin 1s ease-in-out infinite;"></div>
                <div style="margin-top: 10px;">Loading cemetery lots...</div>
            </td>
        </tr>
    `;

    try {
        const result = await API.fetchLots(page, rowsPerPage, searchQuery, statusFilter, sectionFilter, blockFilter);
        
        if (result.success && result.data) {
            currentLots = result.data;
            currentPage = page;
            renderLots(result.data);
            renderPagination(result.pagination);
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#ef4444;">Failed to load cemetery lots</td></tr>';
        }
    } catch (error) {
        console.error('Error loading lots:', error);
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#ef4444;">Error loading data</td></tr>';
    }
}

function renderPagination(pagination) {
    const controls = document.querySelector('.pagination-controls');
    const rangeDisplay = document.getElementById('paginationRange');
    const totalDisplay = document.getElementById('paginationTotal');
    
    if (!controls || !rangeDisplay || !totalDisplay) return;

    const { total, page, pages, limit } = pagination;
    
    // Update info
    const start = total === 0 ? 0 : (page - 1) * limit + 1;
    const end = Math.min(page * limit, total);
    rangeDisplay.textContent = `${start}-${end}`;
    totalDisplay.textContent = total;

    // Build buttons
    let html = '';
    
    // Previous
    html += `<button class="pagination-btn" ${page === 1 ? 'disabled' : ''} onclick="loadCemeteryLots(${page - 1})" title="Previous Page">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
    </button>`;
    
    // Page numbers (simplified: show current, first, last, and neighbors)
    const delta = 2;
    for (let i = 1; i <= pages; i++) {
        if (i === 1 || i === pages || (i >= page - delta && i <= page + delta)) {
            html += `<button class="pagination-btn ${i === page ? 'active' : ''}" onclick="loadCemeteryLots(${i})">${i}</button>`;
        } else if (i === page - delta - 1 || i === page + delta + 1) {
            html += `<span class="pagination-ellipsis">...</span>`;
        }
    }
    
    // Next
    html += `<button class="pagination-btn" ${page === pages ? 'disabled' : ''} onclick="loadCemeteryLots(${page + 1})" title="Next Page">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </button>`;
    
    controls.innerHTML = html;
}

function renderLots(lots) {
    const tbody = document.querySelector('.table tbody');
    if (!tbody) return;

    if (lots.length === 0) {
        const msg = searchQuery ? 'No matching lots found' : 'No cemetery lots found';
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 60px; color:#94a3b8;">${msg}</td></tr>`;
        return;
    }

    tbody.innerHTML = lots.map(lot => {
        const statusClass = lot.status.toLowerCase();
        const badgeClass = statusClass === 'occupied' ? 'active' : statusClass;
        
        return `
        <tr data-lot-id="${lot.id}">
            <td>${lot.id}</td>
            <td>
                <div class="lot-name-cell">
                    <div class="lot-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:#fff"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </div>
                    <div class="lot-name-info">
                        <span class="name">Lot ${lot.lot_number}</span>
                        <span class="sub">${lot.section}${lot.block ? ' • ' + lot.block : ''}</span>
                    </div>
                </div>
            </td>
            <td>${lot.position || '—'}</td>
            <td class="${lot.deceased_name ? '' : 'muted'}">${lot.deceased_name || '—'}</td>
            <td><span class="status-badge ${badgeClass}">${lot.status}</span></td>
            <td>
                <div class="actions">
                    ${(lot.occupied_layers_count < lot.total_layers_count || !lot.deceased_name) ? `
                    <button class="btn-action btn-assign" data-action="assign-burial" data-lot-id="${lot.id}" title="Assign Burial">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="8.5" cy="7" r="4" />
                                <line x1="20" y1="8" x2="20" y2="14" />
                                <line x1="23" y1="11" x2="17" y2="11" />
                            </svg>
                        </span>
                    </button>
                    ` : ''}
                    <button class="btn-action btn-edit" data-action="edit" data-lot-id="${lot.id}" title="Edit Lot">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                            </svg>
                        </span>
                    </button>
                    ${(lot.map_x !== null && lot.map_y !== null) ? `
                    <button class="btn-action btn-map" data-action="map" data-lot-id="${lot.id}" data-lot-number="${lot.lot_number}" title="View on Map">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                <circle cx="12" cy="10" r="3" />
                            </svg>
                        </span>
                    </button>
                    ` : ''}
                    <button class="btn-action btn-delete" data-action="delete" data-lot-id="${lot.id}" title="Delete Lot">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 6h18" />
                                <path d="M8 6V4h8v2" />
                                <path d="M19 6l-1 14H6L5 6" />
                                <path d="M10 11v6" />
                                <path d="M14 11v6" />
                            </svg>
                        </span>
                    </button>
                </div>
            </td>
        </tr>
    `}).join('');
}

async function handleDelete(lotId) {
    const lot = currentLots.find(l => l.id == lotId);
    const lotNumber = lot ? lot.lot_number : lotId;
    
    if (!confirm(`Are you sure you want to delete lot ${lotNumber}?`)) {
        return;
    }

    const result = await API.deleteLot(lotId);
    
    if (result.success) {
        loadCemeteryLots(currentPage);
    } else {
        console.error('Failed to delete lot:', result.message);
    }
}

function showAddModal() {
    showLotModal();
}

function showEditModal(lotId) {
    const lot = currentLots.find(l => l.id == lotId);
    if (!lot) return;

    editingLotId = lotId;
    showLotModal(lot);
}

async function showLotModal(lot = null) {
    const isEdit = lot !== null;
    
    // Fetch sections and blocks for dropdowns
    const [sectionsRes, blocksRes] = await Promise.all([
        fetch('../api/sections.php').then(r => r.json()),
        API.fetchBlocks()
    ]);
    
    const sections = Array.isArray(sectionsRes) ? sectionsRes : (sectionsRes.data || []);
    const blocks = blocksRes.success ? blocksRes.data : [];

    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
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
                    <label>Block *</label>
                    <select name="block" required>
                        <option value="">Select Block</option>
                        ${blocks.map(b => `<option value="${b.name}" ${lot?.block === b.name ? 'selected' : ''}>${b.name}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Section *</label>
                    <select name="section" required>
                        <option value="">Select Section</option>
                        ${sections.map(s => `<option value="${s.name}" ${lot?.section === s.name ? 'selected' : ''}>${s.name}</option>`).join('')}
                    </select>
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
            </form>
            <div class="modal-footer">
                <button type="button" class="btn-secondary modal-cancel">Cancel</button>
                <button type="submit" form="lotForm" class="btn-primary">${isEdit ? 'Update' : 'Create'}</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

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
            loadCemeteryLots(isEdit ? currentPage : 1);
            editingLotId = null;
        } else {
            alert('Error: ' + result.message);
            console.error('Error:', result.message);
        }
    };
}

function createLotModal(lot = null) {
    // This function is replaced by showLotModal
    return null;
}

function closeModal(modal) {
    modal.style.display = 'none';
    modal.remove();
}

function handleMapRedirect(lotId, lotNumber) {
    // Redirect to cemetery map page with highlighted lot parameter
    window.location.href = `cemetery-map.php?highlight_lot=${lotId}`;
}

document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname;
    // Check for index.php, index.html, or root path
    if (path.includes('index.html') || path.includes('index.php') || path.endsWith('/public/') || path.endsWith('/public')) {
        loadCemeteryLots(1);
        const searchInput = document.getElementById('lotSearch');
        if (searchInput) {
            let timeout = null;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    searchQuery = e.target.value.trim();
                    loadCemeteryLots(1);
                }, 300);
            });
        }

        const statusFilterSelect = document.getElementById('statusFilter');
        if (statusFilterSelect) {
            statusFilterSelect.addEventListener('change', (e) => {
                statusFilter = e.target.value;
                loadCemeteryLots(1);
            });
        }

        const sectionFilterSelect = document.getElementById('sectionFilter');
        if (sectionFilterSelect) {
            sectionFilterSelect.addEventListener('change', (e) => {
                sectionFilter = e.target.value;
                loadCemeteryLots(1);
            });
        }

        const blockFilterSelect = document.getElementById('blockFilter');
        if (blockFilterSelect) {
            blockFilterSelect.addEventListener('change', (e) => {
                blockFilter = e.target.value;
                loadCemeteryLots(1);
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
    } else if (action === 'assign-burial' && lotId) {
        showAssignBurialModal(lotId);
    }
});

async function showAssignBurialModal(lotId) {
    const lot = currentLots.find(l => l.id == lotId);
    if (!lot) return;

    // Fetch layers for this lot
    const layersResult = await API.fetchLotLayers(lotId);
    const layers = layersResult.success ? layersResult.data : [];
    
    // Only show vacant layers
    const vacantLayers = layers.filter(l => !l.is_occupied);

    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2>Assign Burial to Lot ${lot.lot_number}</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Search Burial Record</label>
                    <input type="text" id="burialSearchInput" placeholder="Search by name..." style="margin-bottom: 15px;">
                    <div id="burialSearchResults" style="max-height: 300px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <div style="padding: 20px; text-align: center; color: #64748b;">Type to search for unassigned burial records...</div>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label>Select Layer</label>
                    <select id="layerSelect" required>
                        ${vacantLayers.map(l => `<option value="${l.layer_number}">Layer ${l.layer_number}</option>`).join('')}
                        ${vacantLayers.length === 0 ? '<option value="" disabled selected>No vacant layers available</option>' : ''}
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary modal-cancel">Cancel</button>
                <button type="button" id="confirmAssignBtn" class="btn-primary" disabled>Assign Selected Burial</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    modal.style.display = 'flex';

    let selectedBurialId = null;
    const searchInput = modal.querySelector('#burialSearchInput');
    const resultsContainer = modal.querySelector('#burialSearchResults');
    const confirmBtn = modal.querySelector('#confirmAssignBtn');
    const layerSelect = modal.querySelector('#layerSelect');

    const performSearch = async (query) => {
        resultsContainer.innerHTML = '<div style="padding: 20px; text-align: center;"><div class="loading-spinner" style="display: inline-block; width: 20px; height: 20px; border: 2px solid rgba(0,0,0,0.1); border-radius: 50%; border-top-color: #3b82f6; animation: spin 1s ease-in-out infinite;"></div></div>';
        
        try {
            const result = await API.fetchBurialRecords(1, 50, query);
            if (result.success) {
                // Filter for unassigned records
                const unassigned = result.data.filter(r => !r.lot_id);
                
                if (unassigned.length === 0) {
                    resultsContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #64748b;">No unassigned records found</div>';
                } else {
                    resultsContainer.innerHTML = unassigned.map(r => `
                        <div class="burial-item" data-id="${r.id}" style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s;">
                            <div style="font-weight: 600; color: #1e293b;">${r.full_name}</div>
                            <div style="font-size: 12px; color: #64748b;">Died: ${r.date_of_death || 'N/A'} | Age: ${r.age || 'N/A'}</div>
                        </div>
                    `).join('');

                    resultsContainer.querySelectorAll('.burial-item').forEach(item => {
                        item.onclick = () => {
                            resultsContainer.querySelectorAll('.burial-item').forEach(i => i.style.background = 'transparent');
                            item.style.background = '#eff6ff';
                            selectedBurialId = item.getAttribute('data-id');
                            confirmBtn.disabled = !selectedBurialId || !layerSelect.value;
                        };
                    });
                }
            }
        } catch (error) {
            resultsContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #ef4444;">Error searching records</div>';
        }
    };

    let searchTimeout = null;
    searchInput.oninput = (e) => {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();
        if (query.length >= 2) {
            searchTimeout = setTimeout(() => performSearch(query), 300);
        } else {
            resultsContainer.innerHTML = '<div style="padding: 20px; text-align: center; color: #64748b;">Type at least 2 characters to search...</div>';
            selectedBurialId = null;
            confirmBtn.disabled = true;
        }
    };

    layerSelect.onchange = () => {
        confirmBtn.disabled = !selectedBurialId || !layerSelect.value;
    };

    modal.querySelector('.modal-close').onclick = () => closeModal(modal);
    modal.querySelector('.modal-cancel').onclick = () => closeModal(modal);
    
    confirmBtn.onclick = async () => {
        if (!selectedBurialId || !layerSelect.value) return;
        
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = 'Assigning...';
        
        // We need to fetch the full burial record first to update it with new lot info
        const burialResult = await fetch(`${API_BASE_URL}/burial_records.php?id=${selectedBurialId}`).then(r => r.json());
        
        if (burialResult.success) {
            const burialData = burialResult.data;
            burialData.lot_id = lotId;
            burialData.layer = layerSelect.value;
            
            const updateResult = await API.updateBurialRecord(selectedBurialId, burialData);
            
            if (updateResult.success) {
                closeModal(modal);
                loadCemeteryLots(currentPage);
            } else {
                alert('Error assigning burial: ' + updateResult.message);
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'Assign Selected Burial';
            }
        } else {
            alert('Error fetching burial record details');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = 'Assign Selected Burial';
        }
    };
}

