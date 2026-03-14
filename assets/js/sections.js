document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('sectionModal');
    const form = document.getElementById('sectionForm');
    const tableBody = document.getElementById('sectionsTableBody');
    const modalTitle = document.getElementById('modalTitle');
    const sectionId = document.getElementById('sectionId');
    const nameInput = document.getElementById('name');
    const descInput = document.getElementById('description');

    // Open Modal for Add
    window.openAddModal = () => {
        modalTitle.innerText = 'Add New Section';
        sectionId.value = '';
        form.reset();
        modal.style.display = 'flex';
    };

    // Open Modal for Edit
    window.openEditModal = (section) => {
        modalTitle.innerText = 'Edit Section';
        sectionId.value = section.id;
        nameInput.value = section.name;
        descInput.value = section.description;
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
        
        const data = {
            id: sectionId.value,
            name: nameInput.value,
            description: descInput.value
        };

        const method = sectionId.value ? 'PUT' : 'POST';
        
        try {
            const response = await fetch('../api/sections.php', {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                closeModal();
                showNotification(sectionId.value ? 'Section updated successfully!' : 'Section added successfully!', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                const result = await response.json();
                showNotification(result.error || 'Something went wrong', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        }
    });

    // Delete Section
    window.deleteSection = async (section) => {
        // Validation check: check if section has lots
        const lotCount = parseInt(section.lot_count) || 0;
        
        if (lotCount > 0) {
            showNotification(`Cannot delete Section '${section.name}' because it contains ${lotCount} lot(s). Please reassign or delete the lots first.`, 'warning');
            return;
        }

        // Show confirmation modal
        confirmMessage.innerText = `Are you sure you want to delete Section '${section.name}'? This action cannot be undone.`;
        confirmModal.style.display = 'flex';

        // Set up one-time click handler for delete button
        confirmDeleteBtn.onclick = async () => {
            closeConfirmModal();
            try {
                const response = await fetch(`../api/sections.php?id=${section.id}`, {
                    method: 'DELETE'
                });

                if (response.ok) {
                    showNotification('Section deleted successfully!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    const result = await response.json();
                    showNotification(result.error || 'Something went wrong', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            }
        };
    };
});