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

async function loadBurialRecords() {
    const tbody = document.querySelector('.table tbody');
    if (!tbody) return;

    try {
        const result = await BurialAPI.fetchRecords();
        
        if (result.success && result.data) {
            currentRecords = result.data;
            renderRecords(result.data);
        } else {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#ef4444;">Failed to load burial records</td></tr>';
        }
    } catch (error) {
        console.error('Error loading records:', error);
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#ef4444;">Error loading data</td></tr>';
    }
}

function renderRecords(records) {
    const tbody = document.querySelector('.table tbody');
    if (!tbody) return;

    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#6b7280;">No burial records found</td></tr>';
        return;
    }

    tbody.innerHTML = records.map(record => {
        const deathDate = record.date_of_death ? new Date(record.date_of_death).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
        const burialDate = record.date_of_burial ? new Date(record.date_of_burial).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
        
        return `
        <tr data-record-id="${record.id}">
            <td>${record.full_name}</td>
            <td>${record.lot_number || '—'}</td>
            <td>${record.section || '—'}</td>
            <td>${deathDate}</td>
            <td>${burialDate}</td>
            <td>${record.age || '—'}</td>
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
}

async function handleDelete(recordId) {
    const record = currentRecords.find(r => r.id == recordId);
    const name = record ? record.full_name : recordId;
    
    if (!confirm(`Are you sure you want to delete the burial record for ${name}?`)) {
        return;
    }

    const result = await BurialAPI.deleteRecord(recordId);
    
    if (result.success) {
        alert('Burial record deleted successfully!');
        loadBurialRecords();
    } else {
        alert('Failed to delete record: ' + result.message);
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
        alert('Error opening form: ' + error.message);
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
        alert('Record not found');
        return;
    }

    try {
        editingRecordId = recordId;
        const modal = createRecordModal(record);
        document.body.appendChild(modal);
        modal.style.display = 'flex';
    } catch (error) {
        console.error('Error creating edit modal:', error);
        alert('Error opening form: ' + error.message);
    }
}

function createViewModal(record) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    
    const deathDate = record.date_of_death ? new Date(record.date_of_death).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
    const birthDate = record.date_of_birth ? new Date(record.date_of_birth).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
    const burialDate = record.date_of_burial ? new Date(record.date_of_burial).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : 'N/A';
    
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h2>Burial Record Details</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
                    <div>
                        <h3 style="margin:0 0 15px; font-size:16px; color:var(--text);">Personal Information</h3>
                        <p><strong>Full Name:</strong> ${record.full_name}</p>
                        <p><strong>Date of Birth:</strong> ${birthDate}</p>
                        <p><strong>Date of Death:</strong> ${deathDate}</p>
                        <p><strong>Age:</strong> ${record.age || 'N/A'}</p>
                        <p><strong>Cause of Death:</strong> ${record.cause_of_death || 'N/A'}</p>
                    </div>
                    <div>
                        <h3 style="margin:0 0 15px; font-size:16px; color:var(--text);">Burial Information</h3>
                        <p><strong>Date of Burial:</strong> ${burialDate}</p>
                        <p><strong>Lot Number:</strong> ${record.lot_number || 'N/A'}</p>
                        <p><strong>Section:</strong> ${record.section || 'N/A'}</p>
                        <p><strong>Block:</strong> ${record.block || 'N/A'}</p>
                    </div>
                </div>
                <div style="margin-top:20px;">
                    <h3 style="margin:0 0 15px; font-size:16px; color:var(--text);">Next of Kin</h3>
                    <p><strong>Name:</strong> ${record.next_of_kin || 'N/A'}</p>
                    <p><strong>Contact:</strong> ${record.next_of_kin_contact || 'N/A'}</p>
                </div>
                ${record.remarks ? `
                <div style="margin-top:20px;">
                    <h3 style="margin:0 0 15px; font-size:16px; color:var(--text);">Remarks</h3>
                    <p>${record.remarks}</p>
                </div>
                ` : ''}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary modal-close">Close</button>
            </div>
        </div>
    `;

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
                            ${(window.availableLots || []).map(lot => `
                                <option value="${lot.id}" ${record?.lot_id == lot.id ? 'selected' : ''}>
                                    ${lot.lot_number} - ${lot.section}${lot.block ? ' - ' + lot.block : ''}
                                </option>
                            `).join('')}
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
                        <label>Remarks</label>
                        <textarea name="remarks">${record?.remarks || ''}</textarea>
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
    
    modal.querySelector('#recordForm').onsubmit = async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData.entries());
        
        const result = isEdit 
            ? await BurialAPI.updateRecord(editingRecordId, data)
            : await BurialAPI.createRecord(data);
        
        if (result.success) {
            alert(isEdit ? 'Record updated successfully!' : 'Record created successfully!');
            closeModal(modal);
            loadBurialRecords();
            editingRecordId = null;
        } else {
            alert('Error: ' + result.message);
        }
    };

    return modal;
}

function closeModal(modal) {
    modal.style.display = 'none';
    modal.remove();
}

document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname;
    if (path.includes('burial-records')) {
        loadBurialRecords();
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
