document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('blockModal');
    const form = document.getElementById('blockForm');
    const modalTitle = document.getElementById('modalTitle');
    const blockIdInput = document.getElementById('blockId');
    const nameInput = document.getElementById('name');
    const descInput = document.getElementById('description');

    // Open Modal for Add
    window.openAddModal = () => {
        modalTitle.innerText = 'Add New Block';
        blockIdInput.value = '';
        form.reset();
        modal.style.display = 'flex';
    };

    // Open Modal for Edit
    window.openEditModal = (id, name, description) => {
        modalTitle.innerText = 'Edit Block';
        blockIdInput.value = id;
        nameInput.value = name;
        descInput.value = description || '';
        modal.style.display = 'flex';
    };

    // Close Modal
    window.closeModal = () => {
        modal.style.display = 'none';
    };

    // Confirmation Modal
    const confirmModal = document.getElementById('confirmModal');
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    window.closeConfirmModal = () => {
        confirmModal.style.display = 'none';
    };

    // Form Submission Modal on outside click
    window.onclick = (event) => {
        if (event.target == modal) {
            closeModal();
        }
    };

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

    // Form Submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const id = blockIdInput.value;
        const data = {
            name: nameInput.value,
            description: descInput.value
        };

        try {
            let result;
            if (id) {
                result = await API.updateBlock(id, data);
            } else {
                result = await API.createBlock(data);
            }

            if (result.success) {
                closeModal();
                showNotification(id ? 'Block updated successfully!' : 'Block added successfully!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showNotification(result.message || 'Something went wrong', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        }
    });

    // Delete Block
    window.deleteBlock = async (block) => {
        // Validation check: check if block has lots
        const lotCount = parseInt(block.lot_count) || 0;
        
        if (lotCount > 0) {
            showNotification(`Cannot delete Block '${block.name}' because it contains ${lotCount} lot(s). Please reassign or delete the lots first.`, 'warning');
            return;
        }

        // Show confirmation modal
        confirmMessage.innerText = `Are you sure you want to delete Block '${block.name}'? This action cannot be undone.`;
        confirmModal.style.display = 'flex';

        // Set up one-time click handler for delete button
        confirmDeleteBtn.onclick = async () => {
            closeConfirmModal();
            try {
                const result = await API.deleteBlock(block.id);

                if (result.success) {
                    showNotification('Block deleted successfully!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(result.message || 'Something went wrong', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            }
        };
    };
});