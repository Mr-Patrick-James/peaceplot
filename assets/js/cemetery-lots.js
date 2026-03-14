let currentLots = [];
let editingLotId = null;
let currentPage = 1;
const rowsPerPage = 20;
let searchQuery = '';
let statusFilter = '';
let occupancyFilter = '';
let sectionFilter = '';
let blockFilter = '';
let sortOrder = 'ASC';

// Advanced Filter Control Logic
function toggleCategory(btn) {
    btn.parentElement.classList.toggle('active');
}

function updateFilters() {
    const activeBlocks = Array.from(document.querySelectorAll('input[name="block"]:checked')).map(cb => cb.value);
    const activeSections = Array.from(document.querySelectorAll('input[name="section"]:checked')).map(cb => cb.value);
    const activeStatuses = Array.from(document.querySelectorAll('input[name="status"]:checked')).map(cb => cb.value);
    const activeOccupancy = Array.from(document.querySelectorAll('input[name="occupancy"]:checked')).map(cb => cb.value);
    const activeSortOrder = document.querySelector('input[name="sort_order"]:checked')?.value || 'ASC';

    blockFilter = activeBlocks.join(',');
    sectionFilter = activeSections.join(',');
    statusFilter = activeStatuses.join(',');
    occupancyFilter = activeOccupancy.join(',');
    sortOrder = activeSortOrder;

    // Update badge
    const filterBadge = document.getElementById('filterBadge');
    const totalActive = activeBlocks.length + activeSections.length + activeStatuses.length + activeOccupancy.length;
    if (filterBadge) {
        filterBadge.textContent = totalActive;
        filterBadge.style.display = totalActive > 0 ? 'flex' : 'none';
    }

    // Update chips
    const activeFiltersRow = document.getElementById('activeFilters');
    if (activeFiltersRow) {
        const allFilters = [
            ...activeBlocks.map(v => ({ name: 'block', value: v })),
            ...activeSections.map(v => ({ name: 'section', value: v })),
            ...activeStatuses.map(v => ({ name: 'status', value: v })),
            ...activeOccupancy.map(v => ({ name: 'occupancy', value: v }))
        ];

        activeFiltersRow.innerHTML = allFilters.map(filter => `
            <div class="filter-chip">
                ${filter.value}
                <span class="remove" onclick="removeFilter('${filter.name}', '${filter.value}')">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </span>
            </div>
        `).join('');
    }

    loadCemeteryLots(1);
}

function removeFilter(name, value) {
    const cb = document.querySelector(`.filter-popover input[name="${name}"][value="${value}"]`);
    if (cb) {
        cb.checked = false;
        updateFilters();
    }
}

function clearAllFilters() {
    const checkboxes = document.querySelectorAll('.filter-popover input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
    
    const ascRadio = document.querySelector('input[name="sort_order"][value="ASC"]');
    if (ascRadio) ascRadio.checked = true;

    updateFilters();
}

async function loadCemeteryLots(page = 1) {
    const tbody = document.querySelector('.table tbody');
    if (!tbody) return;

    // Show loading state
    tbody.innerHTML = `
        <tr>
            <td colspan="7" style="text-align:center; padding: 40px; color:#6b7280;">
                <div class="loading-spinner" style="display: inline-block; width: 30px; height: 30px; border: 3px solid rgba(0,0,0,0.1); border-radius: 50%; border-top-color: #3b82f6; animation: spin 1s ease-in-out infinite;"></div>
                <div style="margin-top: 10px;">Loading cemetery lots...</div>
            </td>
        </tr>
    `;

    try {
        const result = await API.fetchLots(page, rowsPerPage, searchQuery, statusFilter, sectionFilter, blockFilter, occupancyFilter, sortOrder);
        
        if (result.success && result.data) {
            currentLots = result.data;
            currentPage = page;
            renderLots(result.data);
            renderPagination(result.pagination);
        } else {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#ef4444;">Failed to load cemetery lots</td></tr>';
        }
    } catch (error) {
        console.error('Error loading lots:', error);
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#ef4444;">Error loading data</td></tr>';
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
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 60px; color:#94a3b8;">${msg}</td></tr>`;
        return;
    }

    tbody.innerHTML = lots.map(lot => {
        const statusClass = lot.status.toLowerCase();
        const badgeClass = statusClass === 'occupied' ? 'active' : statusClass;
        
        const total = parseInt(lot.total_layers_count) || 1;
        const occupied = parseInt(lot.occupied_layers_count) || 0;
        const available = total - occupied;
        
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
            <td class="${lot.deceased_name ? '' : 'muted'}" style="${!lot.deceased_name ? 'color: #94a3b8; font-style: italic;' : ''}">
                ${lot.deceased_name || 'Unassigned'}
            </td>
            <td><span class="status-badge ${badgeClass}">${lot.status}</span></td>
            <td>
                <div style="font-size: 13px; font-weight: 600; color: #475569;">
                    ${occupied} / ${total} layers
                </div>
                <div style="font-size: 11px; color: #94a3b8;">
                    ${occupied === 0 ? 'Vacant' : (occupied < total ? 'Partially Occupied' : 'Occupied')}
                </div>
            </td>
            <td>
                <div class="actions">
                    <button class="btn-action btn-assign" data-action="assign-burial" data-lot-id="${lot.id}" title="Assign Burial / Add Ash Burial">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                                <circle cx="8.5" cy="7" r="4" />
                                <line x1="20" y1="8" x2="20" y2="14" />
                                <line x1="23" y1="11" x2="17" y2="11" />
                            </svg>
                        </span>
                    </button>
                    <button class="btn-action btn-edit" data-action="edit" data-lot-id="${lot.id}" title="Edit Lot">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                            </svg>
                        </span>
                    </button>
                    <button class="btn-action btn-map ${ (lot.map_x === null || lot.map_y === null) ? 'unassigned' : '' }" 
                            data-action="map" 
                            data-lot-id="${lot.id}" 
                            data-lot-number="${lot.lot_number}" 
                            data-assigned="${lot.map_x !== null && lot.map_y !== null}"
                            title="${ (lot.map_x === null || lot.map_y === null) ? 'Not assigned on map - Click to assign' : 'View on Map' }">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                <circle cx="12" cy="10" r="3" />
                            </svg>
                        </span>
                    </button>
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

// Notification System
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    const iconMap = {
        success: '✓',
        error: '✕',
        warning: '!',
        info: 'i'
    };

    const titleMap = {
        success: 'Success',
        error: 'Error',
        warning: 'Warning',
        info: 'Info'
    };

    notification.innerHTML = `
        <div class="notification-icon">${iconMap[type]}</div>
        <div class="notification-content">
            <div class="notification-title">${titleMap[type]}</div>
            <div class="notification-message">${message}</div>
        </div>
        ${type === 'error' ? '<button class="notification-close" onclick="this.parentElement.remove()">&times;</button>' : ''}
    `;
    
    document.body.appendChild(notification);
    
    // Trigger animation
    setTimeout(() => notification.classList.add('show'), 10);

    // Auto-remove unless it's an error
    if (type !== 'error') {
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 400);
        }, 4000);
    } else {
        // Errors stay longer (10s) or until closed
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 400);
            }
        }, 10000);
    }
}

// Confirmation Modal
const confirmModal = document.getElementById('confirmModal');
const confirmMessage = document.getElementById('confirmMessage');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

function closeConfirmModal() {
    if (confirmModal) confirmModal.style.display = 'none';
}

async function handleDelete(lotId) {
    const lot = currentLots.find(l => l.id == lotId);
    if (!lot) return;

    // Check if lot is occupied or has burials
    if (lot.status === 'Occupied' || (lot.deceased_name && lot.deceased_name.trim() !== '')) {
        showNotification(`Cannot delete Lot ${lot.lot_number} because it is currently occupied. Please archive or remove the burial records first.`, 'warning');
        return;
    }

    // Show confirmation modal
    if (confirmMessage && confirmModal) {
        confirmMessage.innerText = `Are you sure you want to delete Lot ${lot.lot_number}? This action cannot be undone.`;
        confirmModal.style.display = 'flex';

        confirmDeleteBtn.onclick = async () => {
            closeConfirmModal();
            try {
                const result = await API.deleteLot(lotId);
                if (result.success) {
                    showNotification('Lot deleted successfully', 'success');
                    loadCemeteryLots(currentPage);
                } else {
                    showNotification(result.message || 'Failed to delete lot', 'error');
                }
            } catch (error) {
                showNotification('An error occurred. Please try again.', 'error');
            }
        };
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
    const isAssigned = isEdit && lot.map_x !== null && lot.map_y !== null;
    
    // Show loading state or modal immediately
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <h2>${isEdit ? 'Edit Cemetery Lot' : 'Add New Cemetery Lot'}</h2>
                    ${isAssigned ? `
                        <button type="button" class="btn-action btn-map" onclick="window.location.href='cemetery-map.php?highlight_lot=${lot.id}'" title="View on Map" style="padding: 6px 10px; border-radius: 8px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg>
                            View on Map
                        </button>
                    ` : ''}
                </div>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="lotForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Lot Number *</label>
                            <input type="text" name="lot_number" value="${lot?.lot_number || ''}" required placeholder="e.g. A-101">
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
                            <label>Section *</label>
                            <select name="section" id="modalSection" required>
                                <option value="">Loading...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Block *</label>
                            <select name="block" id="modalBlock" required>
                                <option value="">Loading...</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label>Position / Landmark</label>
                            <input type="text" name="position" value="${lot?.position || ''}" placeholder="e.g. Near the main gate">
                        </div>
                    </div>
                </form>

                <div id="burialInfoContainer"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary modal-cancel">Cancel</button>
                <button type="submit" form="lotForm" class="btn-primary">${isEdit ? 'Save Changes' : 'Create Lot'}</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Close handlers
    const close = () => {
        modal.classList.add('closing');
        setTimeout(() => modal.remove(), 300);
    };
    modal.querySelector('.modal-close').onclick = close;
    modal.querySelector('.modal-cancel').onclick = close;
    modal.onclick = (e) => { if (e.target === modal) close(); };

    // Fetch dropdown data and burial info in parallel
    const fetches = [
        fetch('../api/sections.php').then(r => r.json()),
        API.fetchBlocks()
    ];
    
    if (isEdit) {
        fetches.push(fetch(`../api/lot_layers.php?lot_id=${lot.id}`).then(r => r.json()));
    }

    try {
        const [sectionsRes, blocksRes, layersRes] = await Promise.all(fetches);
        
        // Populate Sections
        const sections = Array.isArray(sectionsRes) ? sectionsRes : (sectionsRes.data || []);
        const sectionSelect = modal.querySelector('#modalSection');
        sectionSelect.innerHTML = '<option value="">Select Section</option>' + 
            sections.map(s => `<option value="${s.name}" ${lot?.section === s.name ? 'selected' : ''}>${s.name}</option>`).join('');

        // Populate Blocks
        const blocks = blocksRes.success ? blocksRes.data : [];
        const blockSelect = modal.querySelector('#modalBlock');
        blockSelect.innerHTML = '<option value="">Select Block</option>' + 
            blocks.map(b => `<option value="${b.name}" ${lot?.block === b.name ? 'selected' : ''}>${b.name}</option>`).join('');

        // Populate Burial Info if it exists
        if (isEdit && layersRes && layersRes.success) {
            const layers = layersRes.data || [];
            const container = modal.querySelector('#burialInfoContainer');
            
            if (layers.length > 0) {
                container.innerHTML = `
                    <div class="burial-info-section">
                        <div class="section-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            Burial Details by Layer
                        </div>
                        <div class="layers-list">
                            ${layers.map(layer => `
                                <div class="layer-card">
                                    <div class="layer-number">${layer.layer_number}</div>
                                    <div class="layer-details">
                                        <div class="layer-name">${layer.deceased_name || 'Vacant Layer'}</div>
                                        <div class="layer-sub">
                                            ${layer.deceased_name ? (layer.date_of_burial ? `Buried on ${new Date(layer.date_of_burial).toLocaleDateString()}` : 'Burial record assigned') : 'No burial record assigned'}
                                        </div>
                                    </div>
                                    ${layer.is_occupied ? `
                                        <div style="display: flex; gap: 8px;">
                                            <button type="button" class="btn-action" onclick="showBurialDetailModal('${layer.burial_record_id}', '${lot.lot_number}', ${layer.layer_number})" title="View Burial Details" style="padding: 6px 10px; font-size: 12px; border-radius: 8px; background: #eff6ff; color: #3b82f6; border: none; font-weight: 600;">
                                                View Details
                                            </button>
                                            <button type="button" class="btn-action" onclick="unassignBurial('${layer.burial_record_id}', '${lot.id}')" title="Unassign Burial" style="padding: 6px 10px; font-size: 12px; border-radius: 8px; background: #fee2e2; color: #ef4444; border: none; font-weight: 600;">
                                                Unassign
                                            </button>
                                        </div>
                                    ` : ''}
                                    <div class="layer-status ${layer.is_occupied ? 'occupied' : 'vacant'}" style="margin-left: 8px;">
                                        ${layer.is_occupied ? 'Occupied' : 'Vacant'}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
        }
    } catch (error) {
        console.error('Error loading modal data:', error);
        showNotification('Error loading some lot details', 'warning');
    }
    
    modal.querySelector('#lotForm').onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        
        const result = isEdit 
            ? await API.updateLot(editingLotId, data)
            : await API.createLot(data);
        
        if (result.success) {
            closeModal(modal);
            showNotification(isEdit ? 'Lot updated successfully' : 'Lot created successfully', 'success');
            loadCemeteryLots(isEdit ? currentPage : 1);
            editingLotId = null;
        } else {
            showNotification(result.message || 'Something went wrong', 'error');
            console.error('Error:', result.message);
        }
    };
}

/**
 * Modern Burial Detail Modal
 */
async function showBurialDetailModal(burialId, lotNumber, layerNumber) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.style.zIndex = '2000'; // Higher than lot modal
    
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 style="font-size: 18px; font-weight: 700; color: #1e293b;">Layer ${layerNumber} - Burial Details</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <div id="burialDetailContent">
                    <div style="padding: 40px; text-align: center;">
                        <div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid rgba(59, 130, 246, 0.1); border-radius: 50%; border-top-color: #3b82f6; animation: spin 1s linear infinite;"></div>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const close = () => {
        modal.classList.add('closing');
        setTimeout(() => modal.remove(), 300);
    };
    modal.querySelector('.modal-close').onclick = close;
    modal.onclick = (e) => { if (e.target === modal) close(); };

    try {
        const response = await fetch(`../api/burial_records.php?id=${burialId}`);
        const result = await response.json();

        if (result.success) {
            const data = result.data;
            const content = modal.querySelector('#burialDetailContent');
            
            // Format dates
            const dob = data.date_of_birth ? new Date(data.date_of_birth).toLocaleDateString() : 'N/A';
            const dod = data.date_of_death ? new Date(data.date_of_death).toLocaleDateString() : 'N/A';
            
            // Handle images
            let imagesHtml = `
                <div class="image-grid-placeholder">
                    <div style="font-size: 20px; margin-bottom: 8px;">📷</div>
                    <div style="font-size: 14px; font-weight: 500;">No images available</div>
                </div>
            `;

            if (data.images && data.images.length > 0) {
                imagesHtml = `
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px;">
                        ${data.images.map(img => `
                            <div style="aspect-ratio: 1; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">
                                <img src="../${img.image_path}" style="width: 100%; height: 100%; object-fit: cover; cursor: pointer;" onclick="window.open('../${img.image_path}', '_blank')">
                            </div>
                        `).join('')}
                    </div>
                `;
            }

            content.innerHTML = `
                <div class="burial-detail-card">
                    <div class="burial-detail-header">
                        <div class="burial-name-info">
                            <h3>${data.full_name}</h3>
                            <div class="burial-location">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg>
                                Lot ${lotNumber} - Layer ${layerNumber}
                            </div>
                        </div>
                        <div class="status-pill">Occupied</div>
                    </div>
                    
                    <div class="burial-grid">
                        <div class="info-item">
                            <div class="info-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div class="info-content">
                                <label>Age</label>
                                <span>${data.age || 'N/A'} years old</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div class="info-content">
                                <label>Date of Birth</label>
                                <span>${dob}</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <div class="info-content">
                                <label>Date of Death</label>
                                <span>${dod}</span>
                            </div>
                        </div>
                    </div>

                    <div class="image-section">
                        <div class="image-section-header">
                            <div class="image-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                Grave Photos
                            </div>
                            <span style="font-size: 12px; color: #64748b; font-weight: 600;">Grave Images</span>
                        </div>
                        
                        ${imagesHtml}
                    </div>
                </div>
                
                <div style="margin-top: 20px; text-align: right;">
                    <button class="btn-primary" onclick="window.location.href='burial-records.php?id=${burialId}'">
                        Edit Full Record
                    </button>
                </div>
            `;
        } else {
            modal.querySelector('#burialDetailContent').innerHTML = `
                <div style="padding: 40px; text-align: center; color: #ef4444;">
                    ${result.message || 'Error loading burial record'}
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading burial details:', error);
        showNotification('Error loading burial details', 'error');
    }
}

/**
 * Unassign a burial from a lot
 */
async function unassignBurial(burialId, lotId) {
    showConfirmModal({
        title: 'Unassign Burial',
        message: 'Are you sure you want to unassign this burial from the lot? The record will be kept but will no longer be linked to this location.',
        type: 'danger',
        confirmText: 'Unassign',
        onConfirm: async () => {
            try {
                const response = await fetch('../api/burial_records.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: burialId, lot_id: null })
                });
                
                const result = await response.json();
                if (result.success) {
                    showNotification('Burial unassigned successfully', 'success');
                    
                    // Real-time refresh:
                    // 1. Refresh the main lot list in the background
                    loadCemeteryLots(currentPage);
                    
                    // 2. Refresh the current edit modal's burial info
                    const lot = currentLots.find(l => l.id == lotId);
                    if (lot) {
                        // We need to wait a bit for the backend to finish its work
                        setTimeout(() => {
                            // Find the existing modal and refresh its layer list
                            const container = document.getElementById('burialInfoContainer');
                            if (container) {
                                fetch(`../api/lot_layers.php?lot_id=${lotId}`)
                                    .then(r => r.json())
                                    .then(layersRes => {
                                        if (layersRes.success) {
                                            const layers = layersRes.data || [];
                                            container.innerHTML = `
                                                <div class="burial-info-section">
                                                    <div class="section-title">
                                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                                        Burial Details by Layer
                                                    </div>
                                                    <div class="layers-list">
                                                        ${layers.map(layer => `
                                                            <div class="layer-card">
                                                                <div class="layer-number">${layer.layer_number}</div>
                                                                <div class="layer-details">
                                                                    <div class="layer-name">${layer.deceased_name || 'Vacant Layer'}</div>
                                                                    <div class="layer-sub">
                                                                        ${layer.deceased_name ? (layer.date_of_burial ? `Buried on ${new Date(layer.date_of_burial).toLocaleDateString()}` : 'Burial record assigned') : 'No burial record assigned'}
                                                                    </div>
                                                                </div>
                                                                ${layer.is_occupied ? `
                                                                    <div style="display: flex; gap: 8px;">
                                                                        <button type="button" class="btn-action" onclick="showBurialDetailModal('${layer.burial_record_id}', '${lot.lot_number}', ${layer.layer_number})" title="View Burial Details" style="padding: 6px 10px; font-size: 12px; border-radius: 8px; background: #eff6ff; color: #3b82f6; border: none; font-weight: 600;">
                                                                            View Details
                                                                        </button>
                                                                        <button type="button" class="btn-action" onclick="unassignBurial('${layer.burial_record_id}', '${lot.id}')" title="Unassign Burial" style="padding: 6px 10px; font-size: 12px; border-radius: 8px; background: #fee2e2; color: #ef4444; border: none; font-weight: 600;">
                                                                            Unassign
                                                                        </button>
                                                                    </div>
                                                                ` : ''}
                                                                <div class="layer-status ${layer.is_occupied ? 'occupied' : 'vacant'}" style="margin-left: 8px;">
                                                                    ${layer.is_occupied ? 'Occupied' : 'Vacant'}
                                                                </div>
                                                            </div>
                                                        `).join('')}
                                                    </div>
                                                </div>
                                            `;
                                        }
                                    });
                            }
                        }, 300);
                    }
                } else {
                    showNotification(result.message || 'Error unassigning burial', 'error');
                }
            } catch (error) {
                console.error('Error unassigning burial:', error);
                showNotification('An error occurred. Please try again.', 'error');
            }
        }
    });
}

function createLotModal(lot = null) {
    // This function is replaced by showLotModal
    return null;
}

function closeModal(modal) {
    modal.style.display = 'none';
    modal.remove();
}

async function handleMapRedirect(lotId, lotNumber, isAssigned) {
    if (isAssigned === 'true') {
        // Redirect to cemetery map page with highlighted lot parameter
        window.location.href = `cemetery-map.php?highlight_lot=${lotId}`;
    } else {
        // If not assigned, ask user if they want to assign it in the map editor
        const confirmed = await showMapAssignModal(lotNumber);
        if (confirmed) {
            // Redirect to map editor with the lot ID to assign
            window.location.href = `map-editor.php?assign_lot=${lotId}`;
        }
    }
}

function showMapAssignModal(lotNumber) {
    return new Promise((resolve) => {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.style.zIndex = '3000';
        
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 450px; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.2);">
                <div class="modal-header" style="background: #fff; border-bottom: 1px solid #f1f5f9; padding: 24px 32px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; background: #eff6ff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #3b82f6;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg>
                        </div>
                        <h2 style="margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;">Assign to Map</h2>
                    </div>
                    <button class="modal-close" style="background: #f8fafc; border: none; width: 32px; height: 32px; border-radius: 8px; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
                </div>
                <div class="modal-body" style="padding: 32px;">
                    <p style="margin: 0 0 12px 0; color: #1e293b; font-size: 15px; font-weight: 600;">
                        Lot ${lotNumber} is not yet assigned on the map.
                    </p>
                    <p style="margin: 0; color: #64748b; font-size: 14px; line-height: 1.6;">
                        Would you like to open the Map Editor to assign its location now?
                    </p>
                </div>
                <div class="modal-footer" style="background: #f8fafc; padding: 20px 32px; display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #f1f5f9;">
                    <button type="button" class="btn-outline modal-cancel" style="padding: 10px 20px; font-weight: 600;">Not Now</button>
                    <button type="button" id="confirmAssignBtn" class="btn-primary" style="padding: 10px 24px; background: #3b82f6; border: none; font-weight: 600;">Open Map Editor</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        modal.style.display = 'flex';

        const close = (val) => {
            modal.remove();
            resolve(val);
        };

        modal.querySelector('.modal-close').onclick = () => close(false);
        modal.querySelector('.modal-cancel').onclick = () => close(false);
        modal.querySelector('#confirmAssignBtn').onclick = () => close(true);
        
        // Close on background click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) close(false);
        });
    });
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

        const filterBtn = document.getElementById('filterBtn');
        const filterPopover = document.getElementById('filterPopover');
        if (filterBtn) {
            filterBtn.addEventListener('click', () => {
                filterPopover.classList.toggle('active');
            });
        }
    }
});

document.addEventListener('click', (e) => {
    const filterBtn = document.getElementById('filterBtn');
    const filterPopover = document.getElementById('filterPopover');
    if (filterBtn && filterPopover && !filterBtn.contains(e.target) && !filterPopover.contains(e.target)) {
        filterPopover.classList.remove('active');
    }

    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    
    const action = btn.getAttribute('data-action');
    const lotId = btn.getAttribute('data-lot-id');
    
    if (action === 'delete' && lotId) {
        handleDelete(lotId);
    } else if (action === 'edit' && lotId) {
        showEditModal(lotId);
    } else if (action === 'map' && lotId) {
        handleMapRedirect(lotId, btn.getAttribute('data-lot-number'), btn.getAttribute('data-assigned'));
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
    
    // Sort layers by number
    layers.sort((a, b) => a.layer_number - b.layer_number);

    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.display = 'flex';
    modal.innerHTML = `
        <div class="modal-content" style="max-width: 550px;">
            <div class="modal-header">
                <h2>Assign Burial to Lot ${lot.lot_number}</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 24px;">
                    <label>Search Burial Record</label>
                    <div style="position: relative;">
                        <input type="text" id="burialSearchInput" placeholder="Search by name..." style="width: 100%; padding-left: 40px;">
                        <svg style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </div>
                    <div id="burialSearchResults" style="max-height: 250px; overflow-y: auto; border: 1.5px solid #e2e8f0; border-radius: 12px; margin-top: 12px; background: #f8fafc;">
                        <div style="padding: 32px; text-align: center; color: #64748b;">
                            <div style="font-size: 24px; margin-bottom: 8px;">🔍</div>
                            Type to search for unassigned burial records...
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Select Target Layer</label>
                    <select id="layerSelect" required style="width: 100%;">
                        ${layers.map(l => {
                            const isOccupied = l.is_occupied == 1;
                            const statusText = isOccupied ? '(Occupied - Ash Burial Allowed)' : '(Vacant)';
                            return `<option value="${l.layer_number}">Layer ${l.layer_number} ${statusText}</option>`;
                        }).join('')}
                        ${layers.length === 0 ? '<option value="1">Layer 1 (Vacant)</option>' : ''}
                    </select>
                    <p style="font-size:11px; color:#64748b; margin-top:4px;">You can assign multiple burials (like ashes) to the same layer.</p>
                </div>

                <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #f1f5f9; text-align: center;">
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 12px;">Don't see the record? Create a new one for this lot:</p>
                    <button type="button" class="btn-outline" style="margin: 0 auto; width: auto; padding: 10px 20px;" 
                            onclick="window.location.href='burial-records.php?lot_id=${lot.id}&layer=' + document.getElementById('layerSelect').value">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right: 8px;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        Create New Burial Record
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary modal-cancel">Cancel</button>
                <button type="button" id="confirmAssignBtn" class="btn-primary" disabled>Assign Record</button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    const close = () => {
        modal.classList.add('closing');
        setTimeout(() => modal.remove(), 300);
    };

    modal.querySelector('.modal-close').onclick = close;
    modal.querySelector('.modal-cancel').onclick = close;
    modal.onclick = (e) => { if (e.target === modal) close(); };

    let selectedBurialId = null;
    const searchInput = modal.querySelector('#burialSearchInput');
    const resultsContainer = modal.querySelector('#burialSearchResults');
    const confirmBtn = modal.querySelector('#confirmAssignBtn');
    const layerSelect = modal.querySelector('#layerSelect');

    const performSearch = async (query) => {
        resultsContainer.innerHTML = '<div style="padding: 32px; text-align: center;"><div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 3px solid rgba(59, 130, 246, 0.1); border-radius: 50%; border-top-color: #3b82f6; animation: spin 1s linear infinite;"></div></div>';
        
        try {
            const result = await API.fetchBurialRecords(1, 50, query);
            if (result.success) {
                const unassigned = result.data.filter(r => !r.lot_id);
                
                if (unassigned.length === 0) {
                    resultsContainer.innerHTML = '<div style="padding: 32px; text-align: center; color: #64748b;">No unassigned records found for "' + query + '"</div>';
                } else {
                    resultsContainer.innerHTML = unassigned.map(r => `
                        <div class="burial-item" data-id="${r.id}" style="padding: 16px 20px; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: all 0.2s; display: flex; align-items: center; gap: 12px;">
                            <div style="width: 36px; height: 36px; background: #eff6ff; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #3b82f6; flex-shrink: 0;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div style="flex-grow: 1;">
                                <div style="font-weight: 600; color: #1e293b; font-size: 14px;">${r.full_name}</div>
                                <div style="font-size: 12px; color: #64748b;">Died: ${r.date_of_death || 'N/A'} | Age: ${r.age || 'N/A'}</div>
                            </div>
                            <div class="check-icon" style="opacity: 0; color: #3b82f6;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </div>
                        </div>
                    `).join('');

                    resultsContainer.querySelectorAll('.burial-item').forEach(item => {
                        item.onclick = () => {
                            resultsContainer.querySelectorAll('.burial-item').forEach(i => {
                                i.style.background = 'transparent';
                                i.querySelector('.check-icon').style.opacity = '0';
                            });
                            item.style.background = '#eff6ff';
                            item.querySelector('.check-icon').style.opacity = '1';
                            selectedBurialId = item.getAttribute('data-id');
                            confirmBtn.disabled = !selectedBurialId || !layerSelect.value;
                        };
                    });
                }
            }
        } catch (error) {
            resultsContainer.innerHTML = '<div style="padding: 32px; text-align: center; color: #ef4444;">Error searching records. Please try again.</div>';
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
                showNotification('Burial assigned successfully', 'success');
                loadCemeteryLots(currentPage);
            } else {
                showNotification('Error assigning burial: ' + updateResult.message, 'error');
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = 'Assign Selected Burial';
            }
        } else {
            showNotification('Error fetching burial record details', 'error');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = 'Assign Selected Burial';
        }
    };
}

