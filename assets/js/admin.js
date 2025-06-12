/**
 * Menu Master Admin JavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize Menu Master Admin
    const MenuMasterAdmin = {
        
        init: function() {
            this.initThemeSwitcher();
            this.initGlobalEvents();
            this.initPageSpecificFeatures();
            this.applyGlassmorphismEffects();
        },
        
        // Theme Switcher
        initThemeSwitcher: function() {
            this.createThemeSwitcher();
            this.loadSavedTheme();
        },
        
        createThemeSwitcher: function() {
            // Find header or create one
            let header = document.querySelector('.menu-master-header');
            if (!header) {
                header = document.createElement('div');
                header.className = 'menu-master-header';
                
                const title = document.createElement('h1');
                title.textContent = 'Menu Master';
                title.className = 'title is-2 text-shadow';
                header.appendChild(title);
                
                // Insert at the beginning of admin content
                const adminWrap = document.querySelector('.menu-master-admin') || 
                                 document.querySelector('.catalog-master-wrap') ||
                                 document.querySelector('.wrap');
                if (adminWrap) {
                    adminWrap.insertBefore(header, adminWrap.firstChild);
                }
            }
            
            // Create theme switcher
            const switcher = document.createElement('div');
            switcher.className = 'theme-switch glass-effect';
            switcher.innerHTML = `
                <label for="theme-toggle">🌙</label>
                <input type="checkbox" id="theme-toggle" />
                <label for="theme-toggle">☀️</label>
            `;
            
            header.appendChild(switcher);
            
            // Add event listener
            const toggle = switcher.querySelector('#theme-toggle');
            toggle.addEventListener('change', this.handleThemeChange.bind(this));
        },
        
        loadSavedTheme: function() {
            const savedTheme = localStorage.getItem('menuMasterTheme') || 'light';
            const toggle = document.querySelector('#theme-toggle');
            
            if (toggle) {
                toggle.checked = savedTheme === 'dark';
                this.applyTheme(savedTheme);
            }
        },
        
        handleThemeChange: function(e) {
            const theme = e.target.checked ? 'dark' : 'light';
            localStorage.setItem('menuMasterTheme', theme);
            this.applyTheme(theme);
        },
        
        applyTheme: function(theme) {
            if (theme === 'dark') {
                document.body.classList.add('menu-master-dark');
            } else {
                document.body.classList.remove('menu-master-dark');
            }
            
            // Add fade-in animation to elements
            this.animateThemeChange();
        },
        
        animateThemeChange: function() {
            const elements = document.querySelectorAll('.menu-master-card, .settings-section, .cm-table-container');
            elements.forEach((el, index) => {
                el.style.animation = 'none';
                setTimeout(() => {
                    el.style.animation = `fadeIn 0.5s ease-in ${index * 0.1}s both`;
                }, 10);
            });
        },
        
        // Global Events
        initGlobalEvents: function() {
            // Add glassmorphism class to admin wrapper
            const adminWrap = document.querySelector('.menu-master-admin') || 
                             document.querySelector('.catalog-master-wrap');
            if (adminWrap) {
                adminWrap.classList.add('menu-master-admin');
            }
            
            // Enhance all cards with glassmorphism
            const cards = document.querySelectorAll('.postbox, .metabox-holder, .wrap > .card');
            cards.forEach(card => {
                card.classList.add('menu-master-card', 'glass-effect');
            });
            
            // Enhance buttons
            const buttons = document.querySelectorAll('.button:not(.theme-switch .button)');
            buttons.forEach(button => {
                button.classList.add('hover-lift');
            });
        },
        
        // Page-specific features
        initPageSpecificFeatures: function() {
            // Import functionality
            if (document.getElementById('mm-import-btn')) {
                this.initImportFeatures();
            }
            
            // Export functionality
            if (document.getElementById('mm-export-btn')) {
                this.initExportFeatures();
            }
            
            // Data table functionality
            if (document.getElementById('mm-data-table')) {
                this.initDataTable();
            }
            
            // GitHub update functionality
            if (document.getElementById('mm-github-update-btn')) {
                this.initGitHubUpdate();
            }
        },
        
        // Import Features
        initImportFeatures: function() {
            const importBtn = document.getElementById('mm-import-btn');
            const sheetUrlInput = document.getElementById('mm-sheet-url');
            
            if (importBtn) {
                importBtn.addEventListener('click', this.handleImport.bind(this));
            }
            
            // Auto-validate Google Sheets URL
            if (sheetUrlInput) {
                sheetUrlInput.addEventListener('blur', this.validateSheetUrl.bind(this));
            }
        },
        
        validateSheetUrl: function(e) {
            const url = e.target.value.trim();
            const isValid = this.isValidGoogleSheetsUrl(url);
            
            const feedback = e.target.parentNode.querySelector('.help') || 
                           this.createHelpElement(e.target.parentNode);
            
            if (url && !isValid) {
                e.target.classList.add('is-danger');
                feedback.textContent = 'Please enter a valid Google Sheets URL';
                feedback.className = 'help is-danger';
            } else if (url && isValid) {
                e.target.classList.remove('is-danger');
                e.target.classList.add('is-success');
                feedback.textContent = 'Valid Google Sheets URL';
                feedback.className = 'help is-success';
            } else {
                e.target.classList.remove('is-danger', 'is-success');
                feedback.textContent = '';
            }
        },
        
        isValidGoogleSheetsUrl: function(url) {
            return /docs\.google\.com\/spreadsheets\/d\/[a-zA-Z0-9-_]+/.test(url);
        },
        
        createHelpElement: function(parent) {
            const help = document.createElement('p');
            help.className = 'help';
            parent.appendChild(help);
            return help;
        },
        
        handleImport: function(e) {
            e.preventDefault();
            
            const sheetUrl = document.getElementById('mm-sheet-url')?.value;
            if (!sheetUrl) {
                this.showNotification('Please enter a Google Sheets URL', 'warning');
                return;
            }
            
            if (!this.isValidGoogleSheetsUrl(sheetUrl)) {
                this.showNotification('Please enter a valid Google Sheets URL', 'danger');
                return;
            }
            
            this.startImportProcess(sheetUrl);
        },
        
        startImportProcess: function(sheetUrl) {
            this.showLoader('Connecting to Google Sheets...');
            
            // Test connection first
            this.ajaxRequest('menu_master_test_sheets_connection', {
                sheet_url: sheetUrl,
                sheet_name: 'Sheet1'
            })
            .then(response => {
                if (response.success) {
                    this.showColumnMapping(response.data.headers, sheetUrl);
                } else {
                    throw new Error(response.data || 'Connection failed');
                }
            })
            .catch(error => {
                this.showNotification('Connection failed: ' + error.message, 'danger');
            })
            .finally(() => {
                this.hideLoader();
            });
        },
        
        showColumnMapping: function(headers, sheetUrl) {
            // Create or show mapping modal
            let modal = document.getElementById('mm-mapping-modal');
            if (!modal) {
                modal = this.createMappingModal();
                document.body.appendChild(modal);
            }
            
            this.populateMappingModal(modal, headers, sheetUrl);
            modal.classList.add('is-active');
        },
        
        createMappingModal: function() {
            const modal = document.createElement('div');
            modal.id = 'mm-mapping-modal';
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-background"></div>
                <div class="modal-card glass-effect">
                    <header class="modal-card-head">
                        <p class="modal-card-title">Column Mapping</p>
                        <button class="delete" aria-label="close"></button>
                    </header>
                    <section class="modal-card-body">
                        <div class="notification is-info">
                            <p>Map your Google Sheets columns to menu fields:</p>
                        </div>
                        <div id="mapping-fields"></div>
                    </section>
                    <footer class="modal-card-foot">
                        <button class="button is-primary" id="confirm-mapping">
                            <span class="icon"><i class="fas fa-check"></i></span>
                            <span>Confirm & Import</span>
                        </button>
                        <button class="button" id="cancel-mapping">Cancel</button>
                    </footer>
                </div>
            `;
            
            // Add event listeners
            modal.querySelector('.delete').addEventListener('click', () => {
                modal.classList.remove('is-active');
            });
            
            modal.querySelector('#cancel-mapping').addEventListener('click', () => {
                modal.classList.remove('is-active');
            });
            
            modal.querySelector('#confirm-mapping').addEventListener('click', () => {
                this.confirmMapping(modal);
            });
            
            return modal;
        },
        
        populateMappingModal: function(modal, headers, sheetUrl) {
            const fieldsContainer = modal.querySelector('#mapping-fields');
            fieldsContainer.innerHTML = '';
            
            const menuFields = [
                { value: '', text: '-- Skip this column --' },
                { value: 'name', text: 'Item Name' },
                { value: 'description', text: 'Description' },
                { value: 'price', text: 'Price' },
                { value: 'category', text: 'Category' },
                { value: 'image_url', text: 'Image URL' },
                { value: 'ingredients', text: 'Ingredients' },
                { value: 'allergens', text: 'Allergens' },
                { value: 'calories', text: 'Calories' },
                { value: 'tags', text: 'Tags' }
            ];
            
            headers.forEach(header => {
                const field = document.createElement('div');
                field.className = 'field is-horizontal';
                
                const options = menuFields.map(field => 
                    `<option value="${field.value}">${field.text}</option>`
                ).join('');
                
                field.innerHTML = `
                    <div class="field-label is-normal">
                        <label class="label">${header}</label>
                    </div>
                    <div class="field-body">
                        <div class="field">
                            <div class="control">
                                <div class="select is-fullwidth">
                                    <select name="mapping[${header}]">
                                        ${options}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                fieldsContainer.appendChild(field);
            });
            
            // Store sheet URL for later use
            modal.dataset.sheetUrl = sheetUrl;
        },
        
        confirmMapping: function(modal) {
            const mappings = {};
            const selects = modal.querySelectorAll('select[name^="mapping"]');
            
            selects.forEach(select => {
                const sheetColumn = select.name.match(/mapping\[(.*)\]/)[1];
                const menuField = select.value;
                if (menuField) {
                    mappings[sheetColumn] = { catalog_column: menuField };
                }
            });
            
            if (Object.keys(mappings).length === 0) {
                this.showNotification('Please map at least one column', 'warning');
                return;
            }
            
            const sheetUrl = modal.dataset.sheetUrl;
            modal.classList.remove('is-active');
            
            this.executeImport(sheetUrl, mappings);
        },
        
        executeImport: function(sheetUrl, mappings) {
            this.showLoader('Importing data...');
            
            this.ajaxRequest('menu_master_import_data', {
                sheet_url: sheetUrl,
                mappings: JSON.stringify(mappings),
                catalog_id: this.getCurrentCatalogId()
            })
            .then(response => {
                if (response.success) {
                    this.showNotification('Import completed successfully!', 'success');
                    // Refresh page or update table
                    setTimeout(() => location.reload(), 2000);
                } else {
                    throw new Error(response.data || 'Import failed');
                }
            })
            .catch(error => {
                this.showNotification('Import failed: ' + error.message, 'danger');
            })
            .finally(() => {
                this.hideLoader();
            });
        },
        
        // GitHub Update
        initGitHubUpdate: function() {
            const updateBtn = document.getElementById('mm-github-update-btn');
            if (updateBtn) {
                updateBtn.addEventListener('click', this.handleGitHubUpdate.bind(this));
            }
        },
        
        handleGitHubUpdate: function(e) {
            e.preventDefault();
            
            if (!confirm('This will update the plugin from GitHub. Continue?')) {
                return;
            }
            
            this.showLoader('Downloading update from GitHub...');
            
            this.ajaxRequest('menu_master_update_from_github', {})
            .then(response => {
                if (response.success) {
                    this.showNotification('Plugin updated successfully! Please refresh the page.', 'success');
                    setTimeout(() => location.reload(), 3000);
                } else {
                    throw new Error(response.data || 'Update failed');
                }
            })
            .catch(error => {
                this.showNotification('Update failed: ' + error.message, 'danger');
            })
            .finally(() => {
                this.hideLoader();
            });
        },
        
        // Data Table
        initDataTable: function() {
            const table = document.getElementById('mm-data-table');
            if (table) {
                this.enhanceTable(table);
            }
        },
        
        enhanceTable: function(table) {
            // Add hover effects and glassmorphism
            table.classList.add('table', 'is-hoverable', 'is-fullwidth');
            
            const container = table.parentNode;
            if (container) {
                container.classList.add('table-container', 'glass-effect');
            }
        },
        
        // Utility Functions
        getCurrentCatalogId: function() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('id') || document.querySelector('[name="catalog_id"]')?.value || 0;
        },
        
        ajaxRequest: function(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', mmVars?.nonce || '');
            
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });
            
            return fetch(mmVars?.ajax_url || ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json());
        },
        
        showLoader: function(message = 'Loading...') {
            let loader = document.getElementById('mm-loader');
            if (!loader) {
                loader = document.createElement('div');
                loader.id = 'mm-loader';
                loader.className = 'modal is-active';
                loader.innerHTML = `
                    <div class="modal-background"></div>
                    <div class="modal-content">
                        <div class="box glass-effect has-text-centered">
                            <div class="loader"></div>
                            <p class="mt-4" id="loader-message">${message}</p>
                        </div>
                    </div>
                `;
                document.body.appendChild(loader);
            } else {
                loader.classList.add('is-active');
                const messageEl = loader.querySelector('#loader-message');
                if (messageEl) messageEl.textContent = message;
            }
        },
        
        hideLoader: function() {
            const loader = document.getElementById('mm-loader');
            if (loader) {
                loader.classList.remove('is-active');
            }
        },
        
        showNotification: function(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification is-${type} glass-effect slide-up`;
            notification.style.cssText = `
                position: fixed;
                top: 2rem;
                right: 2rem;
                z-index: 9999;
                max-width: 400px;
                animation: slideUp 0.3s ease-out;
            `;
            
            notification.innerHTML = `
                <button class="delete"></button>
                ${message}
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'fadeOut 0.3s ease-in';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
            
            // Manual close
            notification.querySelector('.delete').addEventListener('click', () => {
                notification.remove();
            });
        },
        
        applyGlassmorphismEffects: function() {
            // Apply glassmorphism to existing WordPress elements
            const wpElements = document.querySelectorAll('.postbox, .metabox-holder > .meta-box-sortables > div');
            wpElements.forEach(el => {
                el.classList.add('glass-effect', 'hover-lift');
            });
            
            // Enhance form elements
            const inputs = document.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]), select, textarea');
            inputs.forEach(input => {
                input.classList.add('input');
            });
            
            // Enhance buttons
            const buttons = document.querySelectorAll('input[type="submit"], .button, .btn');
            buttons.forEach(button => {
                if (!button.classList.contains('delete')) {
                    button.classList.add('button', 'hover-lift');
                }
            });
        }
    };
    
    // Initialize the admin interface
    MenuMasterAdmin.init();
    
    // Make it globally available for other scripts
    window.MenuMasterAdmin = MenuMasterAdmin;
});
