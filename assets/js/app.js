(function(){
  const path = (location.pathname || "").split("/").pop() || "index.html";
  const links = document.querySelectorAll(".nav a");
  links.forEach(a => {
    const href = (a.getAttribute("href") || "").split("/").pop();
    if ((href || "") === path) a.classList.add("active");
  });

  document.addEventListener("click", (e) => {
    const btn = e.target.closest("button[data-action]");
    if(!btn) return;
    const action = btn.getAttribute("data-action");
    const lot = btn.getAttribute("data-lot") || "";

    /* 
    Generic listeners removed - individual pages (cemetery-lots.js, burial-records.js) 
    now handle their own specific actions.
    */
  });

  // Universal Global Search Logic
  const searchInput = document.getElementById('universalSearch');
  const searchResults = document.getElementById('searchResults');
  let searchTimeout = null;

  // Resolve base paths — works from any page
  const _base = window.location.pathname.toLowerCase().includes('/public/')
    ? window.location.pathname.substring(0, window.location.pathname.toLowerCase().indexOf('/public/'))
    : '';
  const PUBLIC_BASE = _base + '/public';
  const API_BASE = _base + '/api';

  // Check if there's a search query in the URL to persist it in the box
  const urlParams = new URLSearchParams(window.location.search);
  const existingQuery = urlParams.get('q') || urlParams.get('search');
  if (existingQuery && searchInput) {
    searchInput.value = existingQuery;
  }

  // Clear local page search and reload when global search is cleared
  function clearLocalSearch() {
    const localIds = ['recordSearch', 'lotSearch', 'sectionSearch', 'blockSearch', 'historySearch', 'burialSearch'];
    localIds.forEach(id => {
      const el = document.getElementById(id);
      if (el && el.value) {
        el.value = '';
        el.dispatchEvent(new Event('input'));
      }
    });
    // Clean URL without reloading
    const cleanUrl = window.location.pathname;
    window.history.replaceState({}, document.title, cleanUrl);
  }

  if (searchInput && searchResults) {
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const query = searchInput.value.trim();
        if (query.length >= 2) {
          window.location.href = `${PUBLIC_BASE}/search-results.php?q=${encodeURIComponent(query)}`;
        }
      }
    });

    searchInput.addEventListener('input', (e) => {
      const query = e.target.value.trim();
      clearTimeout(searchTimeout);

      // When cleared, reset local search
      if (query.length === 0) {
        searchResults.style.display = 'none';
        clearLocalSearch();
        return;
      }

      if (query.length < 2) {
        searchResults.style.display = 'none';
        return;
      }

      searchTimeout = setTimeout(async () => {
        try {
          const response = await fetch(`${API_BASE}/universal_search.php?q=${encodeURIComponent(query)}`);
          const result = await response.json();

          if (result.success && result.data && result.data.length > 0) {
            let resultsHtml = result.data.map(item => `
              <a href="${item.url}" class="search-result-item" data-query="${encodeURIComponent(item.title.replace(/^(Lot |Section: |Block: )/, ''))}">
                <div class="result-icon icon-${item.type}">
                  ${item.type === 'lot' ? 
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>' : 
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
                  }
                </div>
                <div class="result-info">
                  <span class="result-title">${item.title}</span>
                  <span class="result-subtitle">${item.subtitle}</span>
                </div>
              </a>
            `).join('');
            
            // Add a "View all results" option at the bottom
            resultsHtml += `
              <a href="${PUBLIC_BASE}/search-results.php?q=${encodeURIComponent(query)}" style="display: block; padding: 12px; text-align: center; background: #f8fafc; color: #3b82f6; font-size: 13px; font-weight: 600; text-decoration: none; border-top: 1px solid #f1f5f9;">
                View all results for "${query}"
              </a>
            `;
            
            searchResults.innerHTML = resultsHtml;
            searchResults.style.display = 'block';

            // When a result is clicked, populate global search box with the selected title
            searchResults.querySelectorAll('.search-result-item').forEach(link => {
              link.addEventListener('click', (e) => {
                const titleEl = link.querySelector('.result-title');
                if (titleEl) {
                  // Strip type prefix (Lot , Section: , Block: )
                  const raw = titleEl.textContent.replace(/^(Lot |Section: |Block: )/, '').trim();
                  searchInput.value = raw;
                }
                searchResults.style.display = 'none';
              });
            });

          } else {
            searchResults.innerHTML = '<div style="padding: 16px; text-align: center; color: #94a3b8; font-size: 13px;">No results found</div>';
            searchResults.style.display = 'block';
          }
        } catch (error) {
          console.error('Search error:', error);
        }
      }, 300);
    });

    // Close results when clicking outside
    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
        searchResults.style.display = 'none';
      }
    });
  }
})();

// ═══════════════════════════════════════════════════════════════
// Mobile Navigation - Sidebar Toggle
// ═══════════════════════════════════════════════════════════════
(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    // Create hamburger toggle button
    const toggle = document.createElement('button');
    toggle.className = 'mobile-menu-toggle';
    toggle.setAttribute('aria-label', 'Toggle navigation menu');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.innerHTML = '<span></span><span></span><span></span>';
    document.body.appendChild(toggle);

    // Create overlay
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    function openSidebar() {
      sidebar.classList.add('open');
      toggle.classList.add('active');
      overlay.classList.add('active');
      toggle.setAttribute('aria-expanded', 'true');
      document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
      sidebar.classList.remove('open');
      toggle.classList.remove('active');
      overlay.classList.remove('active');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }

    toggle.addEventListener('click', function() {
      if (sidebar.classList.contains('open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });

    overlay.addEventListener('click', closeSidebar);

    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) {
        closeSidebar();
      }
    });

    // Close sidebar when a nav link is clicked (on mobile)
    sidebar.querySelectorAll('.nav a:not(.dropdown-toggle)').forEach(function(link) {
      link.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
          closeSidebar();
        }
      });
    });

    // Handle window resize - close sidebar if resized to desktop
    window.addEventListener('resize', function() {
      if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
        closeSidebar();
      }
    });
  });
})();
