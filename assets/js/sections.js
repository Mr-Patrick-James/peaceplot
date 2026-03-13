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
        modal.style.display = 'block';
    };

    // Open Modal for Edit
    window.openEditModal = (section) => {
        modalTitle.innerText = 'Edit Section';
        sectionId.value = section.id;
        nameInput.value = section.name;
        descInput.value = section.description;
        modal.style.display = 'block';
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
                location.reload(); // Simple refresh to show changes
            } else {
                const result = await response.json();
                alert(result.error || 'Something went wrong');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        }
    });

    // Delete Section
    window.deleteSection = async (id) => {
        if (!confirm('Are you sure you want to delete this section?')) return;

        try {
            const response = await fetch(`../api/sections.php?id=${id}`, {
                method: 'DELETE'
            });

            if (response.ok) {
                location.reload();
            } else {
                const result = await response.json();
                alert(result.error || 'Something went wrong');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        }
    };
});