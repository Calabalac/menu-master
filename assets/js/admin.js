/**
 * Menu Master Admin JavaScript - Modern ES6+ Implementation
 */

class MenuMasterAdmin {
    constructor() {
        this.init();
    }

    init() {
        this.initThemeToggle();
        this.initNotifications();
        this.initModals();
        this.initFormValidation();
        this.initAjaxHandlers();
        this.initTooltips();
        this.initImageManager();
        this.initImportFunctionality();
        this.bindEvents();
        
        // Page-specific initialization
        this.initPageSpecific();
        
        console.log('🍽️ Menu Master Admin initialized');
    }

    /**
     * Initialize theme toggle functionality
     */
    initThemeToggle() {
        // Prevent multiple initializations
        if (this.themeInitialized) return;
        this.themeInitialized = true;

        const themeToggle = document.getElementById('theme-toggle');
        if (!themeToggle) return;

        // Load saved theme
        const savedTheme = localStorage.getItem('menu-master-theme') || 'dark';
        this.setTheme(savedTheme);
        themeToggle.checked = savedTheme === 'light';

        // Add event listener only once
        themeToggle.addEventListener('change', (e) => {
            const isLight = e.target.checked;
            this.setTheme(isLight ? 'light' : 'dark');
            localStorage.setItem('menu-master-theme', isLight ? 'light' : 'dark');
            
            // Smooth transition
            document.body.style.transition = 'all 0.3s ease';
            setTimeout(() => {
                document.body.style.transition = '';
            }, 300);
        });
    }

    /**
     * Set theme
     */
    setTheme(theme) {
        document.body.classList.toggle('menu-master-light', theme === 'light');
    }

    /**
     * Initialize notification system
     */
    initNotifications() {
        // Auto-hide notifications after 5 seconds
        document.querySelectorAll('.notification').forEach(notification => {
            setTimeout(() => {
                this.hideNotification(notification);
            }, 5000);
        });

        // Add close button functionality
        document.querySelectorAll('.notification .delete').forEach(button => {
            button.addEventListener('click', (e) => {
                this.hideNotification(e.target.closest('.notification'));
            });
        });
    }

    /**
     * Hide notification with animation
     */
    hideNotification(notification) {
        if (!notification) return;
        
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-10px)';
        
        setTimeout(() => {
            notification.remove();
        }, 300);
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `<p>${message}</p>`;
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '10000';
        notification.style.minWidth = '300px';
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        notification.style.transition = 'all 0.3s ease';
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 10);
        
        // Auto remove
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    /**
     * Initialize modal functionality
     */
    initModals() {
        // Modal triggers
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-modal]')) {
                const modalId = e.target.dataset.modal;
                this.openModal(modalId);
            }

            if (e.target.matches('.modal-background, .modal .delete, .modal .cancel')) {
                this.closeModal(e.target.closest('.modal'));
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.is-active');
                if (activeModal) {
                    this.closeModal(activeModal);
                }
            }
        });
    }

    /**
     * Open modal
     */
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('is-active');
            document.body.style.overflow = 'hidden';
        }
    }

    /**
     * Close modal
     */
    closeModal(modal) {
        if (modal) {
            modal.classList.remove('is-active');
            document.body.style.overflow = '';
        }
    }

    /**
     * Initialize form validation
     */
    initFormValidation() {
        // Google Sheets URL validation
        const urlInputs = document.querySelectorAll('input[name="google_sheet_url"]');
        urlInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                this.validateGoogleSheetsUrl(e.target);
            });
        });

        // Form submission validation
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
    }

    /**
     * Validate Google Sheets URL
     */
    validateGoogleSheetsUrl(input) {
        const url = input.value.trim();
        const helpElement = input.parentElement.querySelector('.help');
        
        if (!url) {
            this.setFieldState(input, helpElement, '', '');
            return true;
        }

        // Check if it's a valid Google Sheets URL
        const googleSheetsPattern = /^https:\/\/docs\.google\.com\/spreadsheets\/d\/[a-zA-Z0-9-_]+/;
        
        if (googleSheetsPattern.test(url)) {
            this.setFieldState(input, helpElement, 'is-success', '✅ Valid Google Sheets URL');
            return true;
        } else {
            this.setFieldState(input, helpElement, 'is-danger', '❌ Please enter a valid Google Sheets URL');
            return false;
        }
    }

    /**
     * Set field validation state
     */
    setFieldState(input, helpElement, className, message) {
        // Remove existing classes
        input.classList.remove('is-success', 'is-danger');
        if (helpElement) {
            helpElement.classList.remove('is-success', 'is-danger');
        }

        // Add new class and message
        if (className) {
            input.classList.add(className);
            if (helpElement) {
                helpElement.classList.add(className);
                helpElement.textContent = message;
            }
        }
    }

    /**
     * Validate entire form
     */
    validateForm(form) {
        let isValid = true;
        
        // Validate required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                this.setFieldState(field, field.parentElement.querySelector('.help'), 'is-danger', 'This field is required');
                isValid = false;
            }
        });

        // Validate Google Sheets URLs
        const urlFields = form.querySelectorAll('input[name="google_sheet_url"]');
        urlFields.forEach(field => {
            if (field.value.trim() && !this.validateGoogleSheetsUrl(field)) {
                isValid = false;
            }
        });

        return isValid;
    }

    /**
     * Initialize AJAX handlers
     */
    initAjaxHandlers() {
        // Test Google Sheets connection
        document.addEventListener('click', (e) => {
            if (e.target.matches('#test-sheets-connection, #test-sheets-connection-create')) {
                e.preventDefault();
                this.testSheetsConnection(e.target);
            }
        });

        // URL input changes
        document.addEventListener('input', (e) => {
            if (e.target.name === 'google_sheet_url') {
                const testBtn = document.querySelector('#test-sheets-connection, #test-sheets-connection-create');
                if (testBtn) {
                    testBtn.disabled = !e.target.value.trim();
                }
            }
        });
    }

    /**
     * Test Google Sheets connection
     */
    async testSheetsConnection(button) {
        const form = button.closest('form');
        const urlInput = form.querySelector('input[name="google_sheet_url"]');
        const sheetNameInput = form.querySelector('input[name="sheet_name"]');
        const resultDiv = document.querySelector('#connection-test-result, #connection-test-result-create');

        if (!urlInput.value.trim()) {
            this.showNotification('Please enter a Google Sheets URL first', 'warning');
            return;
        }

        // Update button state
        const originalText = button.textContent;
        button.textContent = '⏳ Testing...';
        button.disabled = true;

        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'menu_master_test_sheets_connection',
                    sheet_url: urlInput.value,
                    sheet_name: sheetNameInput ? sheetNameInput.value : 'Sheet1',
                    nonce: menu_master_vite_params.nonce
                })
            });

            const data = await response.json();

            if (resultDiv) {
                resultDiv.style.display = 'block';
                
                if (data.success) {
                    resultDiv.className = 'connection-status-message is-success';
                    resultDiv.innerHTML = `
                        <strong>✅ Connection successful!</strong><br>
                        Found ${data.data.headers.length} columns and ${data.data.row_count} rows.
                    `;
                } else {
                    resultDiv.className = 'connection-status-message is-danger';
                    resultDiv.innerHTML = `
                        <strong>❌ Connection failed:</strong><br>
                        ${data.data || 'Unknown error'}
                    `;
                }
            }

        } catch (error) {
            console.error('Connection test error:', error);
            if (resultDiv) {
                resultDiv.style.display = 'block';
                resultDiv.className = 'connection-status-message is-danger';
                resultDiv.innerHTML = `
                    <strong>❌ Network error:</strong><br>
                    ${error.message}
                `;
            }
        } finally {
            button.textContent = originalText;
            button.disabled = false;
        }
    }

    /**
     * Initialize tooltips
     */
    initTooltips() {
        // Simple tooltip implementation
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                const tooltip = document.createElement('div');
                tooltip.className = 'tooltip';
                tooltip.textContent = e.target.dataset.tooltip;
                document.body.appendChild(tooltip);

                const rect = e.target.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';

                e.target._tooltip = tooltip;
            });

            element.addEventListener('mouseleave', (e) => {
                if (e.target._tooltip) {
                    e.target._tooltip.remove();
                    delete e.target._tooltip;
                }
            });
        });
    }

    /**
     * Initialize page-specific functionality
     */
    initPageSpecific() {
        const currentPage = this.getCurrentPage();

        switch (currentPage) {
            case 'menu-master':
                this.initMenusPage();
                break;
            case 'menu-master-add':
                this.initAddMenuPage();
                break;
            case 'menu-master-edit':
                this.initEditMenuPage();
                break;
            case 'menu-master-import-preview':
                this.initImportPreviewPage();
                break;
            case 'menu-master-images':
                this.initImagesPage();
                break;
        }
    }

    /**
     * Get current page
     */
    getCurrentPage() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('page');
    }

    /**
     * Initialize menus page
     */
    initMenusPage() {
        console.log('📋 Initializing menus page');
        // Menu-specific functionality already handled in HTML
    }

    /**
     * Initialize add menu page
     */
    initAddMenuPage() {
        console.log('➕ Initializing add menu page');
        
        // Progress steps
        this.updateProgressSteps(1);
        
        // Form submission
        const form = document.getElementById('create-catalog-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                if (this.validateForm(form)) {
                    this.updateProgressSteps(2);
                    this.showNotification('Creating menu...', 'info');
                }
            });
        }
    }

    /**
     * Initialize edit menu page
     */
    initEditMenuPage() {
        console.log('✏️ Initializing edit menu page');
        // Edit-specific functionality
    }

    /**
     * Initialize import preview page
     */
    initImportPreviewPage() {
        console.log('📊 Initializing import preview page');
        // Import preview functionality already handled in HTML
    }

    /**
     * Initialize images page
     */
    initImagesPage() {
        console.log('🖼️ Initializing images page');
        // Images functionality already handled in HTML
    }

    /**
     * Update progress steps
     */
    updateProgressSteps(currentStep) {
        document.querySelectorAll('.progress-step').forEach((step, index) => {
            if (index + 1 <= currentStep) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });
    }

    /**
     * Utility: Make AJAX request
     */
    async makeAjaxRequest(action, data = {}) {
        const formData = new URLSearchParams({
            action: action,
            nonce: menu_master_vite_params.nonce,
            ...data
        });

        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            });

            return await response.json();
        } catch (error) {
            console.error('AJAX request failed:', error);
            throw error;
        }
    }

    /**
     * Utility: Format file size
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    /**
     * Utility: Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Initialize image manager functionality
     */
    initImageManager() {
        // View toggle
        const viewToggleButtons = document.querySelectorAll('.view-toggle button');
        const tableView = document.querySelector('.image-table-view');
        const gridView = document.querySelector('.image-grid-view');
        
        viewToggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const view = this.dataset.view;
                
                // Update active button
                viewToggleButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Toggle views
                if (tableView && gridView) {
                    if (view === 'table') {
                        tableView.style.display = 'block';
                        gridView.style.display = 'none';
                    } else {
                        tableView.style.display = 'none';
                        gridView.style.display = 'grid';
                    }
                }
            });
        });
        
        // Grid size selector
        const gridSizeSelect = document.getElementById('grid-size-select');
        if (gridSizeSelect && gridView) {
            gridSizeSelect.addEventListener('change', function() {
                const size = this.value;
                gridView.className = gridView.className.replace(/grid-\d+/, `grid-${size}`);
            });
        }
        
        // Copy URL functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('copy-url-btn')) {
                const url = e.target.dataset.url;
                navigator.clipboard.writeText(url).then(() => {
                    showNotification('✅ URL copied to clipboard!', 'success');
                }).catch(() => {
                    showNotification('❌ Failed to copy URL', 'error');
                });
            }
        });
        
        // Download functionality
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('download-btn')) {
                const url = e.target.dataset.url;
                const filename = e.target.dataset.filename;
                
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                link.click();
                
                showNotification('⬇️ Download started!', 'info');
            }
        });
    }

    /**
     * Initialize import functionality
     */
    initImportFunctionality() {
        // Import preview
        const previewBtn = document.getElementById('preview-import-btn');
        if (previewBtn) {
            previewBtn.addEventListener('click', () => {
                this.previewImport();
            });
        }

        // Import data
        const importBtn = document.getElementById('import-data-btn');
        if (importBtn) {
            importBtn.addEventListener('click', () => {
                this.importData();
            });
        }

        // Column mapping
        this.initColumnMapping();
    }

    /**
     * Preview import data
     */
    async previewImport() {
        const urlInput = document.querySelector('input[name="google_sheet_url"]');
        if (!urlInput || !urlInput.value.trim()) {
            this.showNotification('❌ Please enter a Google Sheets URL', 'danger');
            return;
        }

        const url = urlInput.value.trim();
        if (!this.validateGoogleSheetsUrl(urlInput)) {
            this.showNotification('❌ Please enter a valid Google Sheets URL', 'danger');
            return;
        }

        const previewBtn = document.getElementById('preview-import-btn');
        const originalText = previewBtn.textContent;
        
        try {
            previewBtn.textContent = '⏳ Loading Preview...';
            previewBtn.disabled = true;

            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'menu_master_preview_import',
                    nonce: mmVars.nonce,
                    google_sheet_url: url
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.displayImportPreview(data.data);
                this.showNotification('✅ Preview loaded successfully!', 'success');
            } else {
                throw new Error(data.data || 'Failed to load preview');
            }
        } catch (error) {
            console.error('Preview error:', error);
            this.showNotification(`❌ Error: ${error.message}`, 'danger');
        } finally {
            previewBtn.textContent = originalText;
            previewBtn.disabled = false;
        }
    }

    /**
     * Display import preview
     */
    displayImportPreview(data) {
        const container = document.getElementById('import-preview-container');
        if (!container) return;

        const { headers, rows, total_rows } = data;
        
        let html = `
            <div class="import-preview-container">
                <div class="import-preview-header">
                    <h3>Import Preview</h3>
                    <span class="tag is-info">${total_rows} rows found</span>
                </div>
                <div class="import-preview-content">
                    <table class="import-preview-table">
                        <thead>
                            <tr>
                                ${headers.map(header => `<th>${header}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${rows.slice(0, 10).map(row => `
                                <tr>
                                    ${row.map(cell => `<td>${cell || ''}</td>`).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    ${total_rows > 10 ? `<p class="text-muted text-center mt-2">Showing first 10 rows of ${total_rows} total rows</p>` : ''}
                </div>
            </div>
        `;
        
        container.innerHTML = html;
        container.style.display = 'block';

        // Enable column mapping
        this.setupColumnMapping(headers);
    }

    /**
     * Setup column mapping
     */
    setupColumnMapping(headers) {
        const mappingContainer = document.getElementById('column-mapping-container');
        if (!mappingContainer) return;

        const requiredFields = ['name', 'description', 'price', 'category'];
        const optionalFields = ['image_url', 'ingredients', 'allergens', 'calories'];

        let html = `
            <div class="menu-master-card">
                <h3>Column Mapping</h3>
                <p class="text-muted mb-3">Map your spreadsheet columns to menu fields:</p>
                <div class="menu-master-grid grid-2">
        `;

        [...requiredFields, ...optionalFields].forEach(field => {
            const isRequired = requiredFields.includes(field);
            const fieldLabel = field.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
            
            html += `
                <div class="field">
                    <label class="label">
                        ${fieldLabel} ${isRequired ? '<span class="text-danger">*</span>' : ''}
                    </label>
                    <div class="select">
                        <select name="mapping[${field}]" ${isRequired ? 'required' : ''}>
                            <option value="">-- Select Column --</option>
                            ${headers.map((header, index) => `
                                <option value="${index}" ${this.autoMapColumn(field, header) ? 'selected' : ''}>
                                    ${header}
                                </option>
                            `).join('')}
                        </select>
                    </div>
                </div>
            `;
        });

        html += `
                </div>
                <div class="d-flex justify-between align-center mt-3">
                    <button type="button" id="auto-map-btn" class="button">
                        🎯 Auto Map Columns
                    </button>
                    <button type="button" id="import-data-btn" class="button is-primary">
                        📥 Import Data
                    </button>
                </div>
            </div>
        `;

        mappingContainer.innerHTML = html;
        mappingContainer.style.display = 'block';

        // Bind auto-map button
        document.getElementById('auto-map-btn').addEventListener('click', () => {
            this.autoMapColumns(headers);
        });

        // Bind import button
        document.getElementById('import-data-btn').addEventListener('click', () => {
            this.importData();
        });
    }

    /**
     * Auto-map column based on field name and header
     */
    autoMapColumn(field, header) {
        const fieldLower = field.toLowerCase();
        const headerLower = header.toLowerCase();
        
        const mappings = {
            'name': ['name', 'title', 'dish', 'item', 'product'],
            'description': ['description', 'desc', 'details', 'info'],
            'price': ['price', 'cost', 'amount', 'value'],
            'category': ['category', 'type', 'section', 'group'],
            'image_url': ['image', 'photo', 'picture', 'img', 'url'],
            'ingredients': ['ingredients', 'composition', 'contents'],
            'allergens': ['allergens', 'allergies', 'warnings'],
            'calories': ['calories', 'kcal', 'energy']
        };

        const keywords = mappings[fieldLower] || [];
        return keywords.some(keyword => headerLower.includes(keyword));
    }

    /**
     * Auto-map all columns
     */
    autoMapColumns(headers) {
        const selects = document.querySelectorAll('#column-mapping-container select');
        
        selects.forEach(select => {
            const field = select.name.match(/mapping\[(.+)\]/)[1];
            
            headers.forEach((header, index) => {
                if (this.autoMapColumn(field, header)) {
                    select.value = index;
                }
            });
        });

        this.showNotification('🎯 Columns auto-mapped!', 'success');
    }

    /**
     * Initialize column mapping for edit page
     */
    initColumnMapping() {
        // This will be called on edit page to setup mapping
        const mappingContainer = document.getElementById('column-mapping-container');
        if (!mappingContainer) return;

        // Check if we need to load headers for mapping
        const urlInput = document.querySelector('input[name="google_sheet_url"]');
        if (urlInput && urlInput.value.trim()) {
            // Auto-load headers when URL is present
            this.loadHeadersForMapping(urlInput.value.trim());
        }
    }

    /**
     * Load headers for mapping (used in edit page)
     */
    async loadHeadersForMapping(url) {
        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'menu_master_get_headers',
                    nonce: menuMasterAjax.nonce,
                    google_sheet_url: url
                })
            });

            const data = await response.json();
            
            if (data.success && data.data.headers) {
                this.setupColumnMapping(data.data.headers);
            }
        } catch (error) {
            console.error('Failed to load headers:', error);
        }
    }

    /**
     * Import data
     */
    async importData() {
        const form = document.querySelector('form');
        if (!form) return;

        const formData = new FormData(form);
        const importBtn = document.getElementById('import-data-btn');
        const originalText = importBtn.textContent;

        try {
            importBtn.textContent = '⏳ Importing...';
            importBtn.disabled = true;

            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'menu_master_import_data',
                    nonce: mmVars.nonce,
                    ...Object.fromEntries(formData)
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification(`✅ Successfully imported ${data.data.imported_count} items!`, 'success');
                
                // Redirect to menu list after successful import
                setTimeout(() => {
                    window.location.href = 'admin.php?page=menu-master';
                }, 2000);
            } else {
                throw new Error(data.data || 'Import failed');
            }
        } catch (error) {
            console.error('Import error:', error);
            this.showNotification(`❌ Import failed: ${error.message}`, 'danger');
        } finally {
            importBtn.textContent = originalText;
            importBtn.disabled = false;
        }
    }

    /**
     * Bind additional events
     */
    bindEvents() {
        // GitHub update functionality
        const updateBtn = document.getElementById('github-update-btn');
        if (updateBtn) {
            updateBtn.addEventListener('click', () => {
                this.updateFromGitHub();
            });
        }

        // Load saved preferences
        this.loadSavedPreferences();
    }

    /**
     * Update from GitHub
     */
    async updateFromGitHub() {
        const updateBtn = document.getElementById('github-update-btn');
        const originalText = updateBtn.textContent;

        if (!confirm('Are you sure you want to update Menu Master from GitHub? This will overwrite any local changes.')) {
            return;
        }

        try {
            updateBtn.textContent = '⏳ Updating...';
            updateBtn.disabled = true;

            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'menu_master_github_update',
                    nonce: mmVars.nonce
                })
            });

            const data = await response.json();
            
            if (data.success) {
                this.showNotification('✅ Plugin updated successfully! Please refresh the page.', 'success');
                
                // Auto-refresh after 3 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
                throw new Error(data.data || 'Update failed');
            }
        } catch (error) {
            console.error('Update error:', error);
            this.showNotification(`❌ Update failed: ${error.message}`, 'danger');
        } finally {
            updateBtn.textContent = originalText;
            updateBtn.disabled = false;
        }
    }

    /**
     * Load saved preferences
     */
    loadSavedPreferences() {
        // Load image view preference
        const savedView = localStorage.getItem('menu-master-image-view') || 'table';
        this.switchImageView(savedView);

        // Load grid size preference
        const savedGridSize = localStorage.getItem('menu-master-grid-size') || '5';
        this.changeGridSize(savedGridSize);
        
        const gridSizeSelect = document.getElementById('grid-size-select');
        if (gridSizeSelect) {
            gridSizeSelect.value = savedGridSize;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.MenuMasterAdmin = new MenuMasterAdmin();
});

// Global functions for backward compatibility
window.deleteCatalog = function(id, name) {
    if (!confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete_catalog">
        <input type="hidden" name="catalog_id" value="${id}">
        <input type="hidden" name="_wpnonce" value="${mmVars.nonce}">
    `;
    document.body.appendChild(form);
    form.submit();
};

window.downloadImages = function(catalogId, catalogName) {
    if (!confirm(`Download all images for "${catalogName}"? This may take some time.`)) {
        return;
    }
    
    // Show loading notification
    if (window.menuMasterAdmin) {
        window.menuMasterAdmin.showNotification('⏳ Starting image download...', 'info');
    }
    
    // Make AJAX request to download images
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'menu_master_download_images',
            nonce: mmVars.nonce,
            catalog_id: catalogId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (window.menuMasterAdmin) {
                window.menuMasterAdmin.showNotification(`✅ Successfully downloaded ${data.data.downloaded_count} images!`, 'success');
            }
        } else {
            throw new Error(data.data || 'Download failed');
        }
    })
    .catch(error => {
        console.error('Download error:', error);
        if (window.menuMasterAdmin) {
            window.menuMasterAdmin.showNotification(`❌ Download failed: ${error.message}`, 'danger');
        }
    });
};

// Add CSS for tooltips and other dynamic elements
const style = document.createElement('style');
style.textContent = `
    .tooltip {
        position: absolute;
        background: var(--mm-text-primary);
        color: var(--mm-bg-primary);
        padding: 0.5rem;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        z-index: 10000;
        pointer-events: none;
        opacity: 0;
        animation: fadeIn 0.2s ease forwards;
    }
    
    @keyframes fadeIn {
        to { opacity: 1; }
    }
    
    .connection-status-message {
        margin-top: 0.5rem;
        padding: 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
    }
    
    .connection-status-message.is-success {
        background: rgba(5, 150, 105, 0.1);
        border: 1px solid var(--mm-success);
        color: var(--mm-success);
    }
    
    .connection-status-message.is-danger {
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid var(--mm-danger);
        color: var(--mm-danger);
    }
    
    .progress-step {
        opacity: 0.5;
        transition: all 0.3s ease;
    }
    
    .progress-step.active {
        opacity: 1;
    }
    
    .progress-step.active .step-number {
        background: var(--mm-primary);
        color: white;
    }
`;
document.head.appendChild(style);
