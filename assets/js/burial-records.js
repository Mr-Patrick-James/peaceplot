const BurialAPI = {
    async fetchRecords() {
        try {
            const response = await fetch(`${API_BASE_URL}/burial_records.php`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching records:', error);
            return { success: false, message: error.message };
        }
    },

    async fetchRecord(id) {
        try {
            const response = await fetch(`${API_BASE_URL}/burial_records.php?id=${id}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching record:', error);
            return { success: false, message: error.message };
        }
    },

    async fetchImages(burialRecordId) {
        try {
            const response = await fetch(`${API_BASE_URL}/burial_images.php?burial_record_id=${burialRecordId}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching images:', error);
            return { success: false, message: error.message };
        }
    },

    async uploadImage(formData) {
        try {
            const response = await fetch(`${API_BASE_URL}/upload_image.php`, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error uploading image:', error);
            return { success: false, message: error.message };
        }
    },

    async deleteImage(imageId) {
        try {
            const response = await fetch(`${API_BASE_URL}/burial_images.php`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: imageId })
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error deleting image:', error);
            return { success: false, message: error.message };
        }
    },

    async fetchLots() {
        try {
            const response = await fetch(`${API_BASE_URL}/cemetery_lots.php`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching lots:', error);
            return { success: false, message: error.message };
        }
    },

    async createRecord(recordData) {
        try {
            const response = await fetch(`${API_BASE_URL}/burial_records.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(recordData)
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error creating record:', error);
            return { success: false, message: error.message };
        }
    },

    async updateRecord(id, recordData) {
        try {
            const response = await fetch(`${API_BASE_URL}/burial_records.php`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ ...recordData, id })
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error updating record:', error);
            return { success: false, message: error.message };
        }
    },

    async deleteRecord(id) {
        try {
            const response = await fetch(`${API_BASE_URL}/burial_records.php`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id })
            });
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error deleting record:', error);
            return { success: false, message: error.message };
        }
    }
};

let currentRecords = [];
let editingRecordId = null;

function filterRecords(query) {
    const q = (query || '').trim().toLowerCase();
    if (!q) return currentRecords.slice();
    return currentRecords.filter(record => {
        const fields = [
            record.full_name,
            record.lot_number,
            record.section,
            record.block,
            record.layer,
            record.age,
            record.date_of_death,
            record.date_of_burial,
            record.next_of_kin,
            record.next_of_kin_contact,
            record.cause_of_death,
            record.deceased_info,
            record.remarks
        ];
        return fields.some(val => (val || '').toString().toLowerCase().includes(q));
    });
}

// Add layer loading function
async function loadLotLayers(lotId, layerSelect, preselectedLayer = null) {
    try {
        const response = await fetch(`${API_BASE_URL}/lot_layers.php?lot_id=${lotId}`);
        const data = await response.json();
        
        if (data.success && data.data) {
            const layers = data.data;
            
            if (layers.length > 0) {
                // Show layer selection
                layerSelect.parentElement.style.display = 'block';
                
                // Clear existing options
                layerSelect.innerHTML = '<option value="">Select a layer...</option>';
                
                // Add layer options
                layers.forEach(layer => {
                    const isOccupied = layer.is_occupied;
                    const option = document.createElement('option');
                    option.value = layer.layer_number;
                    option.textContent = `Layer ${layer.layer_number} - ${isOccupied ? `Occupied by ${layer.deceased_name || 'Unknown'}` : 'Vacant'}`;
                    
                    if (isOccupied) {
                        option.disabled = true;
                        option.style.color = '#f97316';
                    } else {
                        option.style.color = '#16a34a';
                    }
                    
                    if (preselectedLayer && preselectedLayer == layer.layer_number) {
                        option.selected = true;
                    }
                    
                    layerSelect.appendChild(option);
                });
                
            } else {
                // No layers available
                layerSelect.parentElement.style.display = 'none';
                layerSelect.innerHTML = '<option value="">No layers available</option>';
            }
        } else {
            layerSelect.parentElement.style.display = 'none';
            layerSelect.innerHTML = '<option value="">Error loading layers</option>';
        }
    } catch (error) {
        console.error('Error loading layers:', error);
        layerSelect.parentElement.style.display = 'none';
        layerSelect.innerHTML = '<option value="">Error loading layers</option>';
    }
}


async function loadBurialRecords() {
    console.log('loadBurialRecords() called');
    const tbody = document.querySelector('.table tbody');
    console.log('tbody found:', !!tbody);
    if (!tbody) return;

    try {
        console.log('Fetching burial records...');
        const result = await BurialAPI.fetchRecords();
        console.log('API result:', result);
        
        if (result.success && result.data) {
            console.log('Records loaded successfully, count:', result.data.length);
            currentRecords = result.data;
            renderRecords(result.data);
        } else {
            console.log('API returned error:', result.message);
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; color:#ef4444;">Failed to load burial records: ' + (result.message || 'Unknown error') + '</td></tr>';
        }
    } catch (error) {
        console.error('Error loading records:', error);
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; color:#ef4444;">Error loading data: ' + error.message + '</td></tr>';
    }
}

function renderRecords(records) {
    console.log('renderRecords() called with', records.length, 'records');
    const tbody = document.querySelector('.table tbody');
    console.log('tbody found in renderRecords:', !!tbody);
    if (!tbody) return;

    if (records.length === 0) {
        const q = (document.getElementById('recordSearch')?.value || '').trim();
        const msg = q ? 'No matching records found' : 'No burial records found';
        tbody.innerHTML = `<tr><td colspan="10" style="text-align:center; color:#6b7280;">${msg}</td></tr>`;
        return;
    }

    console.log('Rendering records...');
    const html = records.map(record => {
        const deathDate = record.date_of_death ? new Date(record.date_of_death).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
        const burialDate = record.date_of_burial ? new Date(record.date_of_burial).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
        
        return `
        <tr data-record-id="${record.id}">
            <td>${record.full_name}</td>
            <td>${record.lot_number || '—'}</td>
            <td>
                ${record.layer ? `
                    <span style="background: #e3f2fd; color: #1976d2; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                        Layer ${record.layer}
                    </span>
                ` : '—'}
            </td>
            <td>${record.section || '—'}</td>
            <td>${deathDate}</td>
            <td>${burialDate}</td>
            <td>${record.age || '—'}</td>
            <td style="max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #6b7280; font-size: 13px;">
                ${record.deceased_info || '—'}
            </td>
            <td style="max-width: 120px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #6b7280; font-size: 13px;">
                ${record.remarks || '—'}
            </td>
            <td>
                <div class="actions">
                    <button class="btn-action btn-edit" data-action="view" data-record-id="${record.id}">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </span>
                        <span>View</span>
                    </button>
                    <button class="btn-action btn-edit" data-action="edit" data-record-id="${record.id}">
                        <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                            </svg>
                        </span>
                        <span>Edit</span>
                    </button>
                    <button class="btn-action btn-delete" data-action="delete" data-record-id="${record.id}">
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
    `;
    }).join('');
    
    console.log('HTML generated, setting innerHTML');
    tbody.innerHTML = html;
    console.log('innerHTML set successfully');
}

async function handleDelete(recordId) {
    const record = currentRecords.find(r => r.id == recordId);
    const name = record ? record.full_name : recordId;
    
    if (!confirm(`Are you sure you want to delete the burial record for ${name}?`)) {
        return;
    }

    const result = await BurialAPI.deleteRecord(recordId);
    
    if (result.success) {
        loadBurialRecords();
    } else {
        console.error('Failed to delete record:', result.message);
    }
}

function showAddModal() {
    console.log('showAddModal called');
    console.log('Available lots:', window.availableLots);
    try {
        const modal = createRecordModal();
        document.body.appendChild(modal);
        modal.style.display = 'flex';
    } catch (error) {
        console.error('Error creating modal:', error);
    }
}

function showViewModal(recordId) {
    const record = currentRecords.find(r => r.id == recordId);
    if (!record) return;

    const modal = createViewModal(record);
    document.body.appendChild(modal);
    modal.style.display = 'flex';
}

function showEditModal(recordId) {
    console.log('showEditModal called for record:', recordId);
    const record = currentRecords.find(r => r.id == recordId);
    if (!record) {
        console.error('Record not found:', recordId);
        return;
    }

    try {
        editingRecordId = recordId;
        const modal = createRecordModal(record);
        document.body.appendChild(modal);
        modal.style.display = 'flex';
    } catch (error) {
        console.error('Error creating edit modal:', error);
    }
}

function createViewModal(record) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    
    const deathDate = record.date_of_death ? new Date(record.date_of_death).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
    const birthDate = record.date_of_birth ? new Date(record.date_of_birth).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
    const burialDate = record.date_of_burial ? new Date(record.date_of_burial).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
    
    modal.innerHTML = `
        <div class="modal-content" style="max-width:900px;">
            <div class="modal-header">
                <h2>Burial Record Details</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px; margin-bottom:25px;">
                    <div class="info-section">
                        <div class="section-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <h3>Personal Information</h3>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Full Name</label>
                                <span>${record.full_name}</span>
                            </div>
                            <div class="info-item">
                                <label>Date of Birth</label>
                                <span>${birthDate}</span>
                            </div>
                            <div class="info-item">
                                <label>Date of Death</label>
                                <span>${deathDate}</span>
                            </div>
                            <div class="info-item">
                                <label>Age</label>
                                <span>${record.age || 'N/A'}</span>
                            </div>
                            <div class="info-item full-width">
                                <label>Cause of Death</label>
                                <span>${record.cause_of_death || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <div class="section-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            <h3>Burial Information</h3>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Date of Burial</label>
                                <span>${burialDate}</span>
                            </div>
                            <div class="info-item">
                                <label>Lot Number</label>
                                <span>${record.lot_number || 'N/A'}</span>
                            </div>
                            <div class="info-item">
                                <label>Section</label>
                                <span>${record.section || 'N/A'}</span>
                            </div>
                            <div class="info-item">
                                <label>Block</label>
                                <span>${record.block || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="info-section" style="margin-bottom:25px;">
                    <div class="section-header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                        <h3>Next of Kin</h3>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Name</label>
                            <span>${record.next_of_kin || 'N/A'}</span>
                        </div>
                        <div class="info-item">
                            <label>Contact</label>
                            <span>${record.next_of_kin_contact || 'N/A'}</span>
                        </div>
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px; margin-bottom:25px;">
                    <div class="info-section">
                        <div class="section-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 20h9" />
                                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                            </svg>
                            <h3>Deceased Info</h3>
                        </div>
                        <div class="remarks-content">
                            ${record.deceased_info || 'N/A'}
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <div class="section-header">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                            <h3>Relationship</h3>
                        </div>
                        <div class="remarks-content">
                            ${record.remarks || 'N/A'}
                        </div>
                    </div>
                </div>
                
                <div class="info-section">
                    <div class="section-header">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        <h3>Grave Images</h3>
                        ${record.images && record.images.length > 0 ? `
                            <button class="btn-primary btn-sm" onclick="showImageGallery('${record.id}')" style="margin-left:auto;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                                View Gallery (${record.images.length})
                            </button>
                        ` : ''}
                    </div>
                    <div id="graveImages" class="images-grid">
                        ${record.images && record.images.length > 0 ? record.images.map(img => `
                            <div class="image-card" onclick="showImageGallery('${record.id}', '${img.id}')">
                                <div class="image-wrapper">
                                    <img src="../${img.image_path}" alt="${img.image_caption || 'Grave image'}">
                                    ${img.is_primary ? '<span class="primary-badge">PRIMARY</span>' : ''}
                                </div>
                                <div class="image-info">
                                    <p class="image-caption">${img.image_caption || 'No caption'}</p>
                                </div>
                            </div>
                        `).join('') : `
                            <div class="no-images">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                                <p>No images available</p>
                            </div>
                        `}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary modal-close">Close</button>
                <button type="button" class="btn-primary" onclick="showEditModal('${record.id}')">Edit Record</button>
            </div>
        </div>
    `;
    
    // Add modern styles
    const style = document.createElement('style');
    style.textContent = `
        .info-section {
            background: var(--page);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: var(--primary);
        }
        
        .section-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .info-item.full-width {
            grid-column: 1 / -1;
        }
        
        .info-item label {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item span {
            font-size: 14px;
            color: var(--text);
            font-weight: 500;
        }
        
        .remarks-content {
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border);
            line-height: 1.6;
        }
        
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .image-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .image-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: var(--primary);
        }
        
        .image-wrapper {
            position: relative;
            height: 150px;
            overflow: hidden;
        }
        
        .image-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .image-card:hover .image-wrapper img {
            transform: scale(1.05);
        }
        
        .primary-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--primary);
            color: white;
            font-size: 10px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .image-info {
            padding: 12px;
        }
        
        .image-caption {
            margin: 0;
            font-size: 13px;
            color: var(--text);
            line-height: 1.4;
        }
        
        .no-images {
            grid-column: 1 / -1;
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }
        
        .no-images svg {
            margin-bottom: 10px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Layer Selection Styles */
        .layer-options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 8px;
        }
        
        .layer-option {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            background: white;
        }
        
        .layer-option:hover {
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .layer-option.vacant {
            border-color: #22c55e;
            background: rgba(34, 197, 94, 0.05);
        }
        
        .layer-option.vacant:hover {
            border-color: #16a34a;
            background: rgba(34, 197, 94, 0.1);
        }
        
        .layer-option.occupied {
            border-color: #f97316;
            background: rgba(249, 115, 22, 0.05);
            cursor: not-allowed;
            opacity: 0.7;
        }
        
        .layer-option.selected {
            border-color: var(--primary);
            background: rgba(47, 109, 246, 0.1);
            box-shadow: 0 0 0 3px rgba(47, 109, 246, 0.2);
        }
        
        .layer-number {
            font-weight: 600;
            font-size: 14px;
            color: var(--text);
            margin-bottom: 4px;
        }
        
        .layer-status {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.3;
        }
        
        .layer-locked {
            position: absolute;
            top: 8px;
            right: 8px;
            font-size: 16px;
            opacity: 0.6;
        }
        
        .layer-option.vacant .layer-status {
            color: #16a34a;
            font-weight: 500;
        }
        
        .layer-option.occupied .layer-status {
            color: #ea580c;
        }
    `;
    document.head.appendChild(style);

    modal.querySelector('.modal-close').onclick = () => closeModal(modal);
    modal.querySelectorAll('.modal-close').forEach(btn => {
        btn.onclick = () => closeModal(modal);
    });
    modal.onclick = (e) => { if (e.target === modal) closeModal(modal); };

    return modal;
}

function createRecordModal(record = null) {
    const isEdit = record !== null;
    
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content" style="max-width:700px;">
            <div class="modal-header">
                <h2>${isEdit ? 'Edit Burial Record' : 'Add New Burial Record'}</h2>
                <button class="modal-close">&times;</button>
            </div>
            <form id="recordForm" class="modal-body">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" value="${record?.full_name || ''}" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Cemetery Lot *</label>
                        <select name="lot_id" required>
                            <option value="">Select a lot</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="layerSelectionGroup" style="display: none;">
                        <label>Burial Layer *</label>
                        <select name="layer" id="selectedLayer" required>
                            <option value="">Select a layer...</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Age</label>
                        <input type="number" name="age" value="${record?.age || ''}" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" value="${record?.date_of_birth || ''}">
                    </div>
                    
                    <div class="form-group">
                        <label>Date of Death</label>
                        <input type="date" name="date_of_death" value="${record?.date_of_death || ''}">
                    </div>
                    
                    <div class="form-group">
                        <label>Date of Burial</label>
                        <input type="date" name="date_of_burial" value="${record?.date_of_burial || ''}">
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Cause of Death</label>
                        <input type="text" name="cause_of_death" value="${record?.cause_of_death || ''}">
                    </div>
                    
                    <div class="form-group">
                        <label>Next of Kin</label>
                        <input type="text" name="next_of_kin" value="${record?.next_of_kin || ''}">
                    </div>
                    
                    <div class="form-group">
                        <label>Next of Kin Contact</label>
                        <input type="text" name="next_of_kin_contact" value="${record?.next_of_kin_contact || ''}">
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Notes</label>
                        <textarea name="deceased_info" placeholder="Enter brief info about the deceased person (e.g., 'Family, Relative, Moved with')">${record?.deceased_info || ''}</textarea>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Relationship / Family Notes</label>
                        <textarea name="remarks" placeholder="Enter relationship to others in this lot (e.g., 'Juan moved with Martinez')">${record?.remarks || ''}</textarea>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Grave Images</label>
                        <div id="existingImagesContainer" style="margin-bottom:15px;">
                            <!-- Existing images will be loaded here -->
                        </div>
                        <div id="imageUploadArea" style="border:2px dashed var(--border); border-radius:8px; padding:20px; text-align:center; margin-bottom:15px;">
                            <input type="file" id="imageInput" accept="image/*" multiple style="display:none;">
                            <div id="uploadPrompt">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--muted)" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="17 8 12 3 7 8"/>
                                    <line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                                <p style="margin:10px 0 5px; color:var(--muted);">Click to upload images or drag and drop</p>
                                <p style="margin:0; font-size:12px; color:var(--muted);">JPEG, PNG, GIF, WebP (max 10MB)</p>
                            </div>
                        </div>
                        <div id="imagePreviewContainer" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap:10px;"></div>
                    </div>
                </div>
            </form>
            <div class="modal-footer">
                <button type="button" class="btn-secondary modal-cancel">Cancel</button>
                <button type="submit" form="recordForm" class="btn-primary">${isEdit ? 'Update' : 'Create'}</button>
            </div>
        </div>
    `;

    modal.querySelector('.modal-close').onclick = () => closeModal(modal);
    modal.querySelector('.modal-cancel').onclick = () => closeModal(modal);
    modal.onclick = (e) => { if (e.target === modal) closeModal(modal); };
    
    // Fetch lots and populate dropdown
    const lotSelect = modal.querySelector('select[name="lot_id"]');
    const layerSelect = modal.querySelector('#selectedLayer');
    
    // Add lot selection change handler
    lotSelect.addEventListener('change', async (e) => {
        const lotId = e.target.value;
        
        if (lotId) {
            // Load layers for this lot
            await loadLotLayers(lotId, layerSelect, record?.layer);
        } else {
            // Hide layer selection
            layerSelect.parentElement.style.display = 'none';
            layerSelect.value = '';
        }
    });
    
    BurialAPI.fetchLots().then(result => {
        if (result.success && result.data) {
            lotSelect.innerHTML = '<option value="">Select a lot</option>' + 
                result.data.map(lot => `
                    <option value="${lot.id}" ${record?.lot_id == lot.id ? 'selected' : ''}>
                        ${lot.lot_number} - ${lot.section}${lot.block ? ' - ' + lot.block : ''} (${lot.status})
                    </option>
                `).join('');
            
            // If editing, load layers for the selected lot
            if (isEdit && record?.lot_id) {
                loadLotLayers(record.lot_id, layerSelect, record?.layer);
            }
        } else {
            lotSelect.innerHTML = '<option value="">No lots available</option>';
            console.error('Failed to load lots:', result.message);
        }
    }).catch(error => {
        lotSelect.innerHTML = '<option value="">Error loading lots</option>';
        console.error('Error loading lots:', error);
    });
    
    // Load existing images if editing
    if (isEdit && record) {
        // Delay loading to ensure modal is fully rendered
        setTimeout(() => {
            loadExistingImages(record.id);
        }, 100);
    }
    
    // Image upload functionality
    const imageInput = modal.querySelector('#imageInput');
    const imageUploadArea = modal.querySelector('#imageUploadArea');
    const imagePreviewContainer = modal.querySelector('#imagePreviewContainer');
    const uploadPrompt = modal.querySelector('#uploadPrompt');
    
    // Click to upload
    imageUploadArea.addEventListener('click', () => {
        imageInput.click();
    });
    
    // Drag and drop
    imageUploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        imageUploadArea.style.borderColor = 'var(--primary)';
        imageUploadArea.style.backgroundColor = 'rgba(47, 109, 246, 0.05)';
    });
    
    imageUploadArea.addEventListener('dragleave', (e) => {
        e.preventDefault();
        imageUploadArea.style.borderColor = 'var(--border)';
        imageUploadArea.style.backgroundColor = 'transparent';
    });
    
    imageUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        imageUploadArea.style.borderColor = 'var(--border)';
        imageUploadArea.style.backgroundColor = 'transparent';
        handleFiles(e.dataTransfer.files);
    });
    
    // File selection
    imageInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });
    
    async function handleFiles(files) {
        for (let file of files) {
            if (!file.type.startsWith('image/')) {
                console.warn('Only image files are allowed:', file.name);
                continue;
            }
            
            if (file.size > 10 * 1024 * 1024) {
                console.warn('File size must be less than 10MB:', file.name);
                continue;
            }
            
            // Create preview
            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = createImagePreview(e.target.result, file.name);
                imagePreviewContainer.appendChild(preview);
            };
            reader.readAsDataURL(file);
        }
    }
    
    function createImagePreview(src, filename) {
        const preview = document.createElement('div');
        preview.style.cssText = 'position:relative; border:1px solid var(--border); border-radius:8px; overflow:hidden;';
        preview.innerHTML = `
            <img src="${src}" style="width:100%; height:120px; object-fit:cover;">
            <div style="padding:8px; font-size:12px; color:var(--muted); word-break:break-all;">${filename}</div>
            <button type="button" style="position:absolute; top:5px; right:5px; background:rgba(239,68,68,0.9); color:white; border:none; border-radius:50%; width:24px; height:24px; cursor:pointer; display:flex; align-items:center; justify-content:center;" onclick="this.parentElement.remove()">×</button>
        `;
        return preview;
    }
    
    modal.querySelector('#recordForm').onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        
        // Validate layer selection for new records
        if (!isEdit && !data.layer) {
            alert('Please select a burial layer for the deceased.');
            return;
        }
        
        console.log('Form submitted with data:', data);
        
        // First create/update the burial record
        const result = isEdit 
            ? await BurialAPI.updateRecord(editingRecordId, data)
            : await BurialAPI.createRecord(data);
        
        console.log('Burial record result:', result);
        
        if (result.success) {
            const burialRecordId = isEdit ? editingRecordId : result.id;
            console.log('Burial record ID:', burialRecordId);
            
            // Upload images if any
            const imageFiles = [];
            const previews = imagePreviewContainer.querySelectorAll('img');
            console.log('Found image previews:', previews.length);
            
            // Convert data URLs to files synchronously
            for (let img of previews) {
                const filename = img.nextElementSibling.textContent;
                if (img.src.startsWith('data:')) {
                    try {
                        // Convert data URL to blob synchronously
                        const response = await fetch(img.src);
                        const blob = await response.blob();
                        const file = new File([blob], filename, { type: blob.type });
                        imageFiles.push(file);
                        console.log('Converted file:', file.name, file.size, file.type);
                    } catch (error) {
                        console.error('Error converting image:', error);
                    }
                }
            }
            
            // Upload images immediately after conversion
            console.log('Uploading', imageFiles.length, 'images');
            
            for (let i = 0; i < imageFiles.length; i++) {
                const file = imageFiles[i];
                console.log(`Uploading image ${i + 1}:`, file.name, file.size, file.type);
                
                const imageFormData = new FormData();
                imageFormData.append('image', file);
                imageFormData.append('burial_record_id', burialRecordId);
                imageFormData.append('image_caption', `Image for ${data.full_name}`);
                imageFormData.append('image_type', 'grave_photo');
                imageFormData.append('is_primary', imageFiles.length === 1 ? 'true' : 'false');
                
                const uploadResult = await BurialAPI.uploadImage(imageFormData);
                console.log('Upload result for image', i + 1, ':', uploadResult);
                
                if (!uploadResult.success) {
                    console.error('Failed to upload image:', uploadResult.message);
                }
            }
            
            closeModal(modal);
            loadBurialRecords();
            editingRecordId = null;
        } else {
            console.error('Error:', result.message);
        }
    };

    return modal;
}

function loadExistingImages(burialRecordId) {
    const existingImagesContainer = document.getElementById('existingImagesContainer');
    
    // Check if container exists
    if (!existingImagesContainer) {
        console.warn('Existing images container not found');
        return;
    }
    
    BurialAPI.fetchImages(burialRecordId).then(result => {
        if (result.success && result.data && result.data.length > 0) {
            existingImagesContainer.innerHTML = `
                <div style="margin-bottom:10px;">
                    <h4 style="margin:0 0 10px; font-size:14px; color:var(--text);">Current Images (${result.data.length})</h4>
                    <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap:10px;">
                        ${result.data.map(img => `
                            <div class="existing-image-item" style="position:relative; border:1px solid var(--border); border-radius:8px; overflow:hidden;">
                                <img src="../${img.image_path}" alt="${img.image_caption || 'Grave image'}" style="width:100%; height:100px; object-fit:cover;">
                                <div style="padding:8px; font-size:11px; color:var(--muted); word-break:break-all;">${img.image_caption || 'No caption'}</div>
                                <button type="button" onclick="removeExistingImage(${img.id}, '${burialRecordId}')" style="position:absolute; top:5px; right:5px; background:rgba(239,68,68,0.9); color:white; border:none; border-radius:50%; width:20px; height:20px; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:12px;">×</button>
                                ${img.is_primary ? '<span style="position:absolute; top:5px; left:5px; background:var(--primary); color:white; font-size:9px; padding:2px 4px; border-radius:3px;">PRIMARY</span>' : ''}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        } else {
            existingImagesContainer.innerHTML = '';
        }
    }).catch(error => {
        console.error('Error loading existing images:', error);
        if (existingImagesContainer) {
            existingImagesContainer.innerHTML = '';
        }
    });
}

function removeExistingImage(imageId, burialRecordId) {
    if (confirm('Are you sure you want to remove this image?')) {
        BurialAPI.deleteImage(imageId).then(result => {
            if (result.success) {
                loadExistingImages(burialRecordId);
            } else {
                console.error('Failed to remove image:', result.message);
            }
        });
    }
}

function closeModal(modal) {
    modal.style.display = 'none';
    modal.remove();
}

function showImageGallery(burialRecordId, currentImageId = null) {
    // Fetch all images for the burial record
    BurialAPI.fetchImages(burialRecordId).then(result => {
        if (result.success && result.data && result.data.length > 0) {
            const modal = createImageGalleryModal(result.data, currentImageId);
            document.body.appendChild(modal);
            modal.style.display = 'flex';
        } else {
            console.log('No images available for this burial record');
        }
    });
}

function createImageGalleryModal(images, currentImageId = null) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.style.cssText = 'background:rgba(0,0,0,0.9);';
    
    const currentIndex = currentImageId ? images.findIndex(img => img.id == currentImageId) : 0;
    const currentImage = images[currentIndex] || images[0];
    
    modal.innerHTML = `
        <div class="modal-content" style="max-width:90vw; max-height:90vh; background:transparent; box-shadow:none; border:none;">
            <div class="modal-header" style="border:none; padding:10px 20px;">
                <h2 style="color:white; margin:0;">Grave Images</h2>
                <button class="modal-close" style="color:white; font-size:24px;">&times;</button>
            </div>
            <div class="modal-body" style="padding:0; text-align:center;">
                <div style="position:relative; display:inline-block;">
                    <img src="../${currentImage.image_path}" alt="${currentImage.image_caption || 'Grave image'}" style="max-width:100%; max-height:70vh; object-fit:contain; border-radius:8px;">
                    <div style="position:absolute; bottom:0; left:0; right:0; background:linear-gradient(transparent, rgba(0,0,0,0.8)); color:white; padding:20px; border-radius:0 0 8px 8px;">
                        <h3 style="margin:0 0 5px;">${currentImage.image_caption || 'Grave Image'}</h3>
                        <p style="margin:0; opacity:0.8;">${currentImage.image_type || 'grave_photo'}</p>
                    </div>
                </div>
                
                ${images.length > 1 ? `
                <div style="display:flex; justify-content:center; gap:10px; margin-top:20px; flex-wrap:wrap;">
                    ${images.map((img, index) => `
                        <img src="../${img.image_path}" alt="${img.image_caption || 'Grave image'}" 
                             style="width:80px; height:60px; object-fit:cover; border:2px solid ${index === currentIndex ? 'white' : 'transparent'}; border-radius:4px; cursor:pointer; opacity:${index === currentIndex ? '1' : '0.6'};"
                             onclick="updateGalleryImage(${index})">
                    `).join('')}
                </div>
                
                <button onclick="navigateGallery(-1)" style="position:absolute; left:20px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.2); color:white; border:none; border-radius:50%; width:50px; height:50px; cursor:pointer; font-size:20px;">‹</button>
                <button onclick="navigateGallery(1)" style="position:absolute; right:20px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.2); color:white; border:none; border-radius:50%; width:50px; height:50px; cursor:pointer; font-size:20px;">›</button>
                ` : ''}
            </div>
        </div>
    `;
    
    // Store images and current index for navigation
    modal.galleryImages = images;
    modal.galleryIndex = currentIndex;
    
    modal.querySelector('.modal-close').onclick = () => closeModal(modal);
    modal.onclick = (e) => { if (e.target === modal) closeModal(modal); };
    
    // Add navigation functions to global scope
    window.updateGalleryImage = function(index) {
        modal.galleryIndex = index;
        updateGalleryDisplay(modal);
    };
    
    window.navigateGallery = function(direction) {
        modal.galleryIndex = (modal.galleryIndex + direction + modal.galleryImages.length) % modal.galleryImages.length;
        updateGalleryDisplay(modal);
    };
    
    // Keyboard navigation
    const handleKeydown = (e) => {
        if (e.key === 'ArrowLeft') window.navigateGallery(-1);
        if (e.key === 'ArrowRight') window.navigateGallery(1);
        if (e.key === 'Escape') closeModal(modal);
    };
    
    document.addEventListener('keydown', handleKeydown);
    modal.addEventListener('close', () => document.removeEventListener('keydown', handleKeydown));
    
    return modal;
}

function updateGalleryDisplay(modal) {
    const images = modal.galleryImages;
    const index = modal.galleryIndex;
    const currentImage = images[index];
    
    const mainImg = modal.querySelector('.modal-body img');
    const caption = modal.querySelector('.modal-body h3');
    const type = modal.querySelector('.modal-body p');
    
    mainImg.src = `../${currentImage.image_path}`;
    mainImg.alt = currentImage.image_caption || 'Grave image';
    caption.textContent = currentImage.image_caption || 'Grave Image';
    type.textContent = currentImage.image_type || 'grave_photo';
    
    // Update thumbnails
    const thumbnails = modal.querySelectorAll('.modal-body div[style*="flex-wrap"] img');
    thumbnails.forEach((thumb, i) => {
        thumb.style.borderColor = i === index ? 'white' : 'transparent';
        thumb.style.opacity = i === index ? '1' : '0.6';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname;
    if (path.includes('burial-records')) {
        loadBurialRecords();
        const searchInput = document.getElementById('recordSearch');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                const filtered = filterRecords(e.target.value);
                renderRecords(filtered);
            });
        }
    }
});

document.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-action]');
    if (!btn) return;
    
    const action = btn.getAttribute('data-action');
    const recordId = btn.getAttribute('data-record-id');
    
    if (action === 'delete' && recordId) {
        handleDelete(recordId);
    } else if (action === 'edit' && recordId) {
        showEditModal(recordId);
    } else if (action === 'view' && recordId) {
        showViewModal(recordId);
    } else if (action === 'add') {
        showAddModal();
    }
});
