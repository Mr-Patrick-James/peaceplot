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

    // Close Modal on outside click
    window.onclick = (event) => {
        if (event.target == modal) {
            closeModal();
        }
    };

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
                location.reload(); // Simple refresh to show changes
            } else {
                alert(result.message || 'Something went wrong');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        }
    });

    // Delete Block
    window.deleteBlock = async (id) => {
        if (!confirm('Are you sure you want to delete this block?')) return;

        try {
            const result = await API.deleteBlock(id);

            if (result.success) {
                location.reload();
            } else {
                alert(result.message || 'Something went wrong');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        }
    };
});