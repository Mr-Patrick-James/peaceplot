document.addEventListener('DOMContentLoaded', () => {
    // UI Elements
    const modal = document.getElementById('sectionModal');
    const form = document.getElementById('sectionForm');
    const tableBody = document.getElementById('sectionsTableBody');
    const modalTitle = document.getElementById('modalTitle');
    const sectionId = document.getElementById('sectionId');
    const nameInput = document.getElementById('name');
    const blockIdInput = document.getElementById('block_id');
    const descInput = document.getElementById('description');

    // Filter Elements
    const filterBtn = document.getElementById('filterBtn');
    const filterPopover = document.getElementById('filterPopover');
    const filterBadge = document.getElementById('filterBadge');
    const activeFiltersRow = document.getElementById('activeFiltersRow');
    const sectionSearch = document.getElementById('sectionSearch');
    const clearAllBtn = document.getElementById('clearAllFilters');
    const addSectionBtn = document.getElementById('addSectionBtn');
    
    // Filter Inputs
    const blockCheckboxes = document.querySelectorAll('input[name="block_filter"]');
    const lotMinInput = document.getElementById('lotMin');
    const lotMaxInput = document.getElementById('lotMax');
    const dateRangeInput = document.getElementById('dateRange');
    const sortBySelect = document.getElementById('sortBy');
    const sortOrderRadios = document.querySelectorAll('input[name="sortOrder"]');

    let datePicker = null;

    // Initialize Flatpickr
    if (dateRangeInput) {
        datePicker = flatpickr(dateRangeInput, {
            mode: "range",
            dateFormat: "Y-m-d",
            onClose: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    loadSections();
                }
            }
        });
    }

    // Filter State
    let filters = {
        search: '',
        blocks: [],
        lotMin: '',
        lotMax: '',
        startDate: '',
        endDate: '',
        sortBy: 'name',
        sortOrder: 'ASC'
    };

    // Toggle Filter Popover
    if (filterBtn) {
        filterBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            filterPopover.classList.toggle('active');
        });

        // Close popover when clicking outside
        document.addEventListener('click', (e) => {
            if (filterPopover && !filterPopover.contains(e.target) && e.target !== filterBtn) {
                filterPopover.classList.remove('active');
            }
        });
    }

    // Category Toggles in Popover
    const categories = document.querySelectorAll('.filter-category');
    categories.forEach(cat => {
        cat.addEventListener('click', () => {
            categories.forEach(c => c.classList.remove('active'));
            cat.classList.add('active');
            
            // Hide all content, show selected
            document.querySelectorAll('.category-content').forEach(content => {
                content.style.display = 'none';
            });
            const catId = cat.getAttribute('data-category');
            document.getElementById(`cat-${catId}`).style.display = 'block';
        });
    });

    // Load Sections with Filters
    async function loadSections() {
        // Build query params
        const params = new URLSearchParams();
        if (filters.search) params.append('search', filters.search);
        if (filters.blocks.length > 0) params.append('block_id', filters.blocks.join(','));
        if (filters.lotMin) params.append('lot_min', filters.lotMin);
        if (filters.lotMax) params.append('lot_max', filters.lotMax);
        if (filters.startDate) params.append('start_date', filters.startDate);
        if (filters.endDate) params.append('end_date', filters.endDate);
        params.append('sort_by', filters.sortBy);
        params.append('sort_order', filters.sortOrder);

        try {
            const response = await fetch(`../api/sections.php?${params.toString()}`);
            if (!response.ok) throw new Error('Failed to fetch sections');
            
            const sections = await response.json();
            renderTable(sections);
            updateFilterUI();
        } catch (error) {
            console.error('Error loading sections:', error);
            showNotification('Failed to load sections', 'error');
        }
    }

    // Render Table Body
    function renderTable(sections) {
        if (!tableBody) return;
        
        if (sections.length === 0) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 60px; color: #94a3b8;">
                        <div style="margin-bottom: 12px; opacity: 0.5;">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
                        </div>
                        No sections found matching your filters.
                    </td>
                </tr>
            `;
            return;
        }

        tableBody.innerHTML = sections.map(section => {
            const sectionJson = JSON.stringify(section).replace(/'/g, "&apos;");
            return `
            <tr>
                <td>
                    <div class="section-name-cell">
                        <div class="section-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" /></svg>
                        </div>
                        <div class="section-info">
                            <span class="name">${escapeHtml(section.name)}</span>
                            <span class="sub">ID: #${section.id}</span>
                        </div>
                    </div>
                </td>
                <td>
                    ${section.block_name ? `<span style="color: #1e293b; font-weight: 500;">${escapeHtml(section.block_name)}</span>` : `<span style="color: #94a3b8; font-style: italic;">No Block</span>`}
                </td>
                <td>${escapeHtml(section.description || 'No description provided')}</td>
                <td align="center">
                    <span style="background: #eff6ff; color: #3b82f6; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 12px;">
                        ${section.lot_count} Lots
                    </span>
                </td>
                <td>${formatDate(section.created_at)}</td>
                <td align="right">
                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                        <button class="btn-action btn-map" onclick="window.location.href='cemetery-map.php?highlight_section=${section.id}'" title="View on Map" style="background: #eff6ff; color: #3b82f6; border: none;">
                            <span class="icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                            </span>
                        </button>
                        <button class="btn-action btn-edit" onclick='openEditModal(${sectionJson})' title="Edit Section">
                            <span class="icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 20h9" />
                                    <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                                </svg>
                            </span>
                        </button>
                        <button class="btn-action btn-delete" onclick='deleteSection(${sectionJson})' title="Delete Section">
                            <span class="icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="21 8 21 21 3 21 3 8"></polyline><rect x="1" y="3" width="22" height="5"></rect><line x1="10" y1="12" x2="14" y2="12"></line>
                                </svg>
                            </span>
                        </button>
                    </div>
                </td>
            </tr>
        `}).join('');
    }

    // Update Filter Badge and Chips
    function updateFilterUI() {
        let count = 0;
        activeFiltersRow.innerHTML = '';

        // Search Chip
        if (filters.search) {
            addFilterChip('Search: ' + filters.search, () => {
                filters.search = '';
                sectionSearch.value = '';
                loadSections();
            });
            count++;
        }

        // Block Chips
        filters.blocks.forEach(blockId => {
            const checkbox = document.querySelector(`input[name="block_filter"][value="${blockId}"]`);
            const name = checkbox ? checkbox.getAttribute('data-name') : 'Block ' + blockId;
            addFilterChip('Block: ' + name, () => {
                filters.blocks = filters.blocks.filter(id => id !== blockId);
                if (checkbox) checkbox.checked = false;
                loadSections();
            });
            count++;
        });

        // Lot Range Chip
        if (filters.lotMin || filters.lotMax) {
            const label = `Lots: ${filters.lotMin || 0} - ${filters.lotMax || 'Any'}`;
            addFilterChip(label, () => {
                filters.lotMin = '';
                filters.lotMax = '';
                lotMinInput.value = '';
                lotMaxInput.value = '';
                loadSections();
            });
            count++;
        }

        // Date Range Chip
        if (filters.startDate) {
            const label = `Date: ${filters.startDate} to ${filters.endDate}`;
            addFilterChip(label, () => {
                filters.startDate = '';
                filters.endDate = '';
                datePicker.clear();
                loadSections();
            });
            count++;
        }

        // Update Badge
        if (count > 0) {
            filterBadge.innerText = count;
            filterBadge.style.display = 'flex';
        } else {
            filterBadge.style.display = 'none';
        }
    }

    function addFilterChip(text, onRemove) {
        const chip = document.createElement('div');
        chip.className = 'filter-chip';
        chip.innerHTML = `
            <span>${text}</span>
            <span class="remove">&times;</span>
        `;
        chip.querySelector('.remove').addEventListener('click', onRemove);
        activeFiltersRow.appendChild(chip);
    }

    // Event Listeners for Filters
    if (sectionSearch) {
        let searchTimeout;
        sectionSearch.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filters.search = e.target.value;
                loadSections();
            }, 300);
        });
    }

    blockCheckboxes.forEach(cb => {
        cb.addEventListener('change', () => {
            filters.blocks = Array.from(blockCheckboxes)
                .filter(c => c.checked)
                .map(c => c.value);
            loadSections();
        });
    });

    [lotMinInput, lotMaxInput].forEach(input => {
        if (input) {
            input.addEventListener('change', () => {
                filters.lotMin = lotMinInput.value;
                filters.lotMax = lotMaxInput.value;
                loadSections();
            });
        }
    });

    if (sortBySelect) {
        sortBySelect.addEventListener('change', () => {
            filters.sortBy = sortBySelect.value;
            loadSections();
        });
    }

    sortOrderRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            filters.sortOrder = radio.value;
            loadSections();
        });
    });

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', () => {
            filters = {
                search: '',
                blocks: [],
                lotMin: '',
                lotMax: '',
                startDate: '',
                endDate: '',
                sortBy: 'name',
                sortOrder: 'ASC'
            };
            
            // Reset UI
            sectionSearch.value = '';
            blockCheckboxes.forEach(cb => cb.checked = false);
            lotMinInput.value = '';
            lotMaxInput.value = '';
            if (datePicker) datePicker.clear();
            sortBySelect.value = 'name';
            sortOrderRadios[0].checked = true;
            
            loadSections();
        });
    }

    if (addSectionBtn) {
        addSectionBtn.addEventListener('click', () => {
            openAddModal();
        });
    }

    // Modal Logic
    window.openAddModal = () => {
        if (!modal) return;
        modalTitle.innerText = 'Add New Section';
        sectionId.value = '';
        form.reset();
        if (blockIdInput) blockIdInput.value = '';
        modal.style.display = 'flex';
    };

    window.openEditModal = (section) => {
        if (!modal) return;
        modalTitle.innerText = 'Edit Section';
        sectionId.value = section.id;
        nameInput.value = section.name;
        if (blockIdInput) blockIdInput.value = section.block_id || '';
        descInput.value = section.description;
        modal.style.display = 'flex';
    };

    window.closeModal = () => {
        if (modal) modal.style.display = 'none';
    };

    const confirmModal = document.getElementById('confirmModal');
    const confirmMessage = document.getElementById('confirmMessage');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    window.closeConfirmModal = () => {
        if (confirmModal) confirmModal.style.display = 'none';
    };

    window.onclick = (event) => {
        if (event.target == modal) closeModal();
        if (event.target == confirmModal) closeConfirmModal();
    };

    // Form Submission
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const data = {
            id: sectionId.value,
            name: nameInput.value,
            block_id: blockIdInput ? blockIdInput.value : null,
            description: descInput.value
        };

        const method = sectionId.value ? 'PUT' : 'POST';
        
        try {
            const response = await fetch('../api/sections.php', {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (response.ok) {
                closeModal();
                showNotification(sectionId.value ? 'Section updated successfully!' : 'Section added successfully!', 'success');
                setTimeout(() => loadSections(), 1000);
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
        const lotCount = parseInt(section.lot_count) || 0;
        if (lotCount > 0) {
            showNotification(`Cannot delete Section '${section.name}' because it contains ${lotCount} lot(s).`, 'warning');
            return;
        }

        confirmMessage.innerText = `Are you sure you want to delete Section '${section.name}'? This action cannot be undone.`;
        confirmModal.style.display = 'flex';

        confirmDeleteBtn.onclick = async () => {
            closeConfirmModal();
            try {
                const response = await fetch(`../api/sections.php?id=${section.id}`, { method: 'DELETE' });
                if (response.ok) {
                    showNotification('Section deleted successfully!', 'success');
                    setTimeout(() => loadSections(), 1000);
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

    // Helper Functions
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        const iconMap = { success: '✓', error: '✕', warning: '!', info: 'i' };
        const titleMap = { success: 'Success', error: 'Error', warning: 'Warning', info: 'Info' };

        notification.innerHTML = `
            <div class="notification-icon">${iconMap[type]}</div>
            <div class="notification-content">
                <div class="notification-title">${titleMap[type]}</div>
                <div class="notification-message">${message}</div>
            </div>
            ${type === 'error' ? '<button class="notification-close" onclick="this.parentElement.remove()">&times;</button>' : ''}
        `;
        document.body.appendChild(notification);
        setTimeout(() => notification.classList.add('show'), 10);
        if (type !== 'error') {
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 400);
            }, 4000);
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
    }

    // Initial Load
    loadSections();
});

