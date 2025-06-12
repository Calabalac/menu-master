<?php
/**
 * Admin interface class
 * 
 * @package MenuMaster
 * @version 0.1.0
 */

// Suppress IDE warnings for WordPress functions
/** @noinspection PhpUndefinedFunctionInspection */

if (!defined('ABSPATH')) {
    exit;
}

class MenuMaster_Admin {
    
    public function __construct() {
        MenuMaster_Logger::info('Admin interface initialized');
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
        
        // AJAX handlers
        add_action('wp_ajax_menu_master_cleanup_test_image', array($this, 'ajax_cleanup_test_image'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Menu Master',
            'Menu Master',
            'manage_options',
            'menu-master',
            array($this, 'admin_page_catalogs'),
            'dashicons-grid-view',
            30
        );
          add_submenu_page(
            'menu-master',
            'All Menus',
            'All Menus',
            'manage_options',
            'menu-master',
            array($this, 'admin_page_catalogs')
        );
        
        add_submenu_page(
            'menu-master',
            'Add Menu',
            'Add Menu',
            'manage_options',
            'menu-master-add',
            array($this, 'admin_page_add_catalog')
        );
        
        add_submenu_page(
            null, // Hidden from menu
            'Edit Menu',
            'Edit Menu',
            'manage_options',
            'menu-master-edit',
            array($this, 'admin_page_edit_catalog')
        );
        
        // Add debug/logs page (only visible when debug is enabled)
        if (MenuMaster_Logger::is_debug_enabled()) {
            add_submenu_page(
                'menu-master',
                'Logs and Debug',
                'Logs and Debug',
                'manage_options',
                'menu-master-debug',
                array($this, 'admin_page_debug')
            );
        }
    }    public function enqueue_admin_scripts($hook) {
        // Only load scripts on plugin pages
        if (!strpos($hook, 'menu-master')) {
            return;
        }

        MenuMaster_Logger::debug('Loading admin scripts on page: ' . $hook);

        wp_enqueue_style(
            'menu-master-admin',
            MENU_MASTER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MENU_MASTER_VERSION
        );

        wp_enqueue_script(
            'menu-master-admin',
            MENU_MASTER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MENU_MASTER_VERSION,
            true
        );

        wp_localize_script('menu-master-admin', 'mmVars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('menu_master_nonce'),
            'plugin_url' => MENU_MASTER_PLUGIN_URL,
            'messages' => array(
                'enter_sheet_url' => 'Please enter Google Sheets URL',
                'mapping_failed' => 'Failed to get column mapping',
                'using_previous_mapping' => 'Using previous column mapping. You can adjust it if needed.',
                'all_reviewed' => 'All images have been reviewed!',
                'review_load_failed' => 'Failed to load image review',
                'review_resolve_failed' => 'Failed to resolve review',
                'import_failed' => 'Import failed: ',
                'export_failed' => 'Export failed: ',
                'column_mapping' => 'Column Mapping',
                'confirm_import' => 'Confirm & Import',
                'cancel' => 'Cancel',
                'confirm_delete' => 'Are you sure you want to delete this item?',
                'loading' => 'Loading...',
                'error_occurred' => 'An error occurred. Please try again.',
                'success' => 'Operation completed successfully.'
            )
        ));
    }
    
    public function handle_form_submissions() {
        if (!current_user_can('manage_options')) {
            MenuMaster_Logger::warning('Unauthorized access attempt to admin form submission');
            return;
        }
        
        // Handle catalog creation
        if (isset($_POST['action']) && $_POST['action'] === 'create_catalog' && wp_verify_nonce($_POST['_wpnonce'], 'menu_master_create')) {
            MenuMaster_Logger::info('Starting catalog creation process');
            $this->handle_create_catalog();
        }
        
        // Handle catalog update
        if (isset($_POST['action']) && $_POST['action'] === 'update_catalog' && wp_verify_nonce($_POST['_wpnonce'], 'menu_master_update')) {
            MenuMaster_Logger::info('Starting catalog update process');
            $this->handle_update_catalog();
        }
        
        // Handle catalog deletion
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'menu_master_delete')) {
            MenuMaster_Logger::info('Starting catalog deletion process');
            $this->handle_delete_catalog();
        }
    }
    
    private function handle_create_catalog() {
        MenuMaster_Logger::debug('Starting catalog creation process');
        
        // Log received data
        $post_data = array(
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'google_sheet_url' => $_POST['google_sheet_url'] ?? '',
            'sheet_name' => $_POST['sheet_name'] ?? ''
        );
        
        MenuMaster_Logger::debug('Received POST data', $post_data);
        
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'google_sheet_url' => esc_url_raw($_POST['google_sheet_url']),
            'sheet_name' => sanitize_text_field($_POST['sheet_name'])
        );
        
        MenuMaster_Logger::debug('Sanitized data', $data);
        
        $catalog_id = MenuMaster_Database::create_catalog($data);
        
        if ($catalog_id) {
            MenuMaster_Logger::info('Catalog creation successful, redirecting to edit page with ID: ' . $catalog_id);
            wp_redirect(admin_url('admin.php?page=menu-master-edit&id=' . $catalog_id . '&created=1'));
            exit;
        } else {
            MenuMaster_Logger::error('Catalog creation failed - create_catalog returned false/0');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Error creating menu. Please check logs for details.</p></div>';
            });
        }
    }
    
    private function handle_update_catalog() {
        $id = intval($_POST['catalog_id']);
        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'description' => sanitize_textarea_field($_POST['description']),
            'google_sheet_url' => esc_url_raw($_POST['google_sheet_url']),
            'sheet_name' => sanitize_text_field($_POST['sheet_name'])
        );
        
        $result = MenuMaster_Database::update_catalog($id, $data);
        
        if ($result !== false) {
            wp_redirect(admin_url('admin.php?page=menu-master-edit&id=' . $id . '&updated=1'));
            exit;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Error updating menu.</p></div>';
            });
        }
    }
    
    private function handle_delete_catalog() {
        $id = intval($_GET['id']);
        $result = MenuMaster_Database::delete_catalog($id);
        
        if ($result) {
            wp_redirect(admin_url('admin.php?page=menu-master&deleted=1'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=menu-master&error=1'));
            exit;
        }
    }
    
    public function admin_page_catalogs() {
        // Handle messages
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Menu deleted successfully.</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>An error occurred.</p></div>';
        }
        
        $catalogs = MenuMaster_Database::get_catalogs();
        ?>
        <div class="wrap">
            <h1>Menus <a href="<?php echo admin_url('admin.php?page=menu-master-add'); ?>" class="page-title-action">Add New</a></h1>
            
            <?php if (empty($catalogs)): ?>
                <div class="notice notice-info">
                    <p>You don't have any menus yet. <a href="<?php echo admin_url('admin.php?page=menu-master-add'); ?>">Create your first menu</a></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Google Sheets URL</th>
                            <th>Sheet</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($catalogs as $catalog): ?>
                            <tr>
                                <td><strong><?php echo esc_html($catalog->name); ?></strong></td>
                                <td><?php echo esc_html(wp_trim_words($catalog->description, 10)); ?></td>
                                <td>
                                    <?php if ($catalog->google_sheet_url): ?>
                                        <a href="<?php echo esc_url($catalog->google_sheet_url); ?>" target="_blank">View</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($catalog->sheet_name); ?></td>
                                <td><?php echo date_i18n('d.m.Y H:i', strtotime($catalog->created_at)); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=menu-master-edit&id=' . $catalog->id); ?>" class="button button-small">Edit</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=menu-master&action=delete&id=' . $catalog->id), 'menu_master_delete'); ?>" 
                                       class="button button-small button-link-delete" 
                                       onclick="return confirm('Are you sure you want to delete this menu? This action cannot be undone.')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function admin_page_add_catalog() {
        ?>
        <div class="wrap menu-master-admin">
            <div class="add-catalog-header">
                <h1>🆕 Create New Menu</h1>
                <p class="add-catalog-subtitle">Create a new menu to manage your items from Google Sheets</p>
            </div>
            
            <!-- Progress Steps -->
            <div class="creation-progress">
                <div class="progress-step active">
                    <div class="step-number">1</div>
                    <div class="step-info">
                        <div class="step-title">Basic Information</div>
                        <div class="step-description">Menu Name & Description</div>
                    </div>
                </div>
                <div class="progress-separator"></div>
                <div class="progress-step">
                    <div class="step-number">2</div>
                    <div class="step-info">
                        <div class="step-title">Data Connection</div>
                        <div class="step-description">Google Sheets & Settings</div>
                    </div>
                </div>
                <div class="progress-separator"></div>
                <div class="progress-step">
                    <div class="step-number">3</div>
                    <div class="step-info">
                        <div class="step-title">Ready</div>
                        <div class="step-description">Menu Created</div>
                    </div>
                </div>
            </div>

            <form method="post" action="" id="create-catalog-form" class="create-catalog-form">
                <?php wp_nonce_field('menu_master_create'); ?>
                <input type="hidden" name="action" value="create_catalog">
                
                <!-- Basic Information Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>📝 Basic Information</h3>
                        <p class="settings-section-description">Enter the name and description of your menu for easy identification</p>
                    </div>
                    
                    <div class="settings-fields-grid">
                        <div class="settings-field-group">
                            <label for="name" class="settings-field-label">
                                Menu Name <span class="label-required">*</span>
                            </label>
                            <div class="settings-field-wrapper">
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       class="settings-field-input" 
                                       required
                                       placeholder="E.g., Product Menu 2025"
                                       autocomplete="off">
                                <div class="field-hint">
                                    💡 Use a clear name that helps distinguish this menu from others
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-field-group full-width">
                            <label for="description" class="settings-field-label">
                                Menu Description
                            </label>
                            <div class="settings-field-wrapper">
                                <textarea id="description" 
                                          name="description" 
                                          class="settings-field-textarea" 
                                          rows="3"
                                          placeholder="Detailed description of the menu, its purpose, and features (optional)"></textarea>
                                <div class="field-hint">
                                    📄 A description helps you and other users understand the menu's purpose
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Google Sheets Connection Section -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>🔗 Connect to Google Sheets</h3>
                        <p class="settings-section-description">Configure the data source for automatic item import</p>
                    </div>
                    
                    <!-- Google Sheets Instructions -->
                    <div class="google-sheets-instructions-compact">
                        <p>To connect a Google Sheet, follow these steps:</p>
                        <ol>
                            <li>**Prepare your sheet:** Ensure the first row contains column headers.</li>
                            <li>**Set access:** Make the sheet accessible via link (File → Share → Anyone with the link can view).</li>
                            <li>**Copy URL:** Paste the link below. The plugin will automatically process it.</li>
                        </ol>
                    </div>
                    
                    <div class="settings-fields-grid">
                        <div class="settings-field-group full-width">
                            <label for="google_sheet_url" class="settings-field-label">
                                Google Sheets URL <span class="label-required">*</span>
                            </label>
                            <div class="settings-field-wrapper">
                                <div class="settings-field-with-button">
                                    <input type="url" 
                                           id="google_sheet_url" 
                                           name="google_sheet_url" 
                                           class="settings-field-input" 
                                           required
                                           placeholder="https://docs.google.com/spreadsheets/d/1ABC...xyz/edit">
                                    <button type="button" 
                                            id="test-sheets-connection-create" 
                                            class="button button-secondary settings-test-btn"
                                            disabled>
                                        🔍 Test Connection
                                    </button>
                                </div>
                                <div class="field-hint">
                                    🔗 Paste the full URL to your Google Sheets spreadsheet.
                                </div>
                                <div id="connection-test-result-create" class="connection-status-message" style="display: none;"></div>
                            </div>
                        </div>
                        
                        <div class="settings-field-group">
                            <label for="sheet_name" class="settings-field-label">
                                Sheet Name
                            </label>
                            <div class="settings-field-wrapper">
                                <input type="text" 
                                       id="sheet_name" 
                                       name="sheet_name" 
                                       class="settings-field-input" 
                                       value="Sheet1"
                                       placeholder="Sheet1">
                                <div class="field-hint">
                                    📋 Default: "Sheet1". Change if your data is on a different sheet.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Connection Status Preview -->
                    <div class="connection-preview" id="connection-preview" style="display: none;">
                        <h4>📊 Data Preview</h4>
                        <div class="preview-content" id="preview-content">
                            <!-- Populated via JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Next Steps Information (Simplified) -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>🚀 What's Next?</h3>
                        <p class="settings-section-description">After creating the menu, you will be able to:</p>
                    </div>
                    
                    <ul class="next-steps-list">
                        <li>Configure column mapping from Google Sheets to menu fields.</li>
                        <li>Automatically import and process data.</li>
                        <li>Upload and optimize images (up to 1000x1000px).</li>
                    </ul>
                </div>

                <!-- Action Buttons -->
                <div class="settings-actions">
                    <div class="settings-actions-primary">
                        <button type="submit" class="button button-primary button-large settings-save-btn" id="create-catalog-btn">
                            ✨ Create Menu
                        </button>
                    </div>
                    
                    <div class="settings-actions-secondary">
                        <a href="<?php echo admin_url('admin.php?page=menu-master'); ?>" 
                           class="button button-secondary">
                            ← Back to List
                        </a>
                        
                        <button type="button" 
                                class="button button-secondary" 
                                id="save-draft-btn"
                                style="display: none;">
                            💾 Save as Draft
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Additional JavaScript for enhanced UX -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlInput = document.getElementById('google_sheet_url');
            const testBtn = document.getElementById('test-sheets-connection-create');
            const createCatalogBtn = document.getElementById('create-catalog-btn');
            const previewDiv = document.getElementById('connection-preview');
            const previewContent = document.getElementById('preview-content');
            const resultDiv = document.getElementById('connection-test-result-create');
            
            // Enable test button when URL is entered
            urlInput.addEventListener('input', function() {
                testBtn.disabled = !this.value.trim();
            });
            
            // Test connection functionality
            testBtn.addEventListener('click', function() {
                const url = urlInput.value.trim();
                const sheetName = document.getElementById('sheet_name').value.trim() || 'Sheet1';
                
                if (!url) return;
                
                testBtn.disabled = true;
                createCatalogBtn.disabled = true; // Disable create button
                testBtn.innerHTML = '⏳ Testing...'; // Add loading indicator
                resultDiv.style.display = 'none';
                previewDiv.style.display = 'none';
                
                const formData = new FormData();
                formData.append('action', 'menu_master_test_sheets_connection');
                formData.append('sheet_url', url);
                formData.append('sheet_name', sheetName);
                formData.append('nonce', menu_master_vite_params.nonce);
                
                fetch(menu_master_vite_params.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    testBtn.disabled = !urlInput.value.trim(); // Re-enable based on input
                    createCatalogBtn.disabled = false; // Re-enable create button
                    testBtn.innerHTML = '🔍 Test Connection'; // Reset button text
                    
                    if (data.success) {
                        resultDiv.className = 'connection-status-message success';
                        resultDiv.innerHTML = `
                            <div class="status-icon">✅</div>
                            <div class="status-content">
                                <strong>Connection Successful!</strong><br>
                                Found ${data.data.row_count} rows with ${data.data.headers.length} columns
                            </div>
                        `;
                        
                        // Show preview
                        previewContent.innerHTML = `
                            <div class="preview-stats">
                                <span class="preview-stat">📊 Rows: ${data.data.row_count}</span>
                                <span class="preview-stat">📋 Columns: ${data.data.headers.length}</span>
                            </div>
                            <div class="preview-headers">
                                <strong>Column Headers:</strong>
                                ${data.data.headers.map(header => `<span class="header-tag">${header}</span>`).join('')}
                            </div>
                        `;
                        previewDiv.style.display = 'block';
                        
                    } else {
                        resultDiv.className = 'connection-status-message error';
                        resultDiv.innerHTML = `
                            <div class="status-icon">❌</div>
                            <div class="status-content">
                                <strong>Connection Error</strong><br>
                                ${data.data || 'Unknown error. Please check your Google Sheet link and access.'}
                            </div>
                        `;
                    }
                    
                    resultDiv.style.display = 'block';
                })
                .catch(error => {
                    testBtn.disabled = !urlInput.value.trim(); // Re-enable based on input
                    createCatalogBtn.disabled = false; // Re-enable create button
                    testBtn.innerHTML = '🔍 Test Connection'; // Reset button text
                    
                    resultDiv.className = 'connection-status-message error';
                    resultDiv.innerHTML = `
                        <div class="status-icon">❌</div>
                        <div class="status-content">
                            <strong>Network or Server Error</strong><br>
                            Could not connect. Please check your internet connection or try again later.
                        </div>
                    `;
                    resultDiv.style.display = 'block';
                });
            });
        });
        </script>
        
        <style>
        /* Modern Create Catalog Styles */
        .add-catalog-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }
        
        .add-catalog-header h1 {
            font-size: 2.2em;
            margin: 0 0 10px 0;
            color: #1d2327;
        }
        
        .add-catalog-subtitle {
            font-size: 1.1em;
            color: #646970;
            margin: 0;
        }
        
        /* Progress Steps */
        .creation-progress {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 30px 0 40px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .progress-step {
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 0.5;
            transition: opacity 0.3s ease;
        }
        
        .progress-step.active {
            opacity: 1;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #0073aa;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
        }
        
        .progress-step:not(.active) .step-number {
            background: #c3c4c7;
        }
        
        .step-info {
            display: flex;
            flex-direction: column;
        }
        
        .step-title {
            font-weight: 600;
            font-size: 14px;
            color: #1d2327;
        }
        
        .step-description {
            font-size: 12px;
            color: #646970;
        }
        
        .progress-separator {
            width: 60px;
            height: 2px;
            background: #c3c4c7;
            margin: 0 20px;
        }
        
        /* Google Sheets Instructions */
        .google-sheets-instructions-compact {
            background: #fff8e1;
            border: 1px solid #ffcc02;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .google-sheets-instructions-compact p {
            margin-top: 0;
            font-size: 0.95em;
            color: #4a4a4a;
        }
        
        .google-sheets-instructions-compact ol {
            margin: 10px 0 0 15px;
            padding: 0;
            list-style: decimal;
            font-size: 0.9em;
            line-height: 1.6;
        }
        
        .google-sheets-instructions-compact ol li {
            margin-bottom: 5px;
        }
        
        /* Settings Sections */
        .settings-section {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .settings-section-header {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .settings-section-header h3 {
            margin: 0 0 5px 0;
            font-size: 1.5em;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .settings-section-header p {
            margin: 0;
            color: #666;
            font-size: 0.95em;
        }
        
        .settings-fields-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
        }
        
        .settings-field-group.full-width {
            grid-column: 1 / -1;
        }
        
        .settings-field-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 0.95em;
        }
        
        .label-required {
            color: #e74c3c;
            font-size: 0.8em;
            margin-left: 5px;
            vertical-align: super;
        }
        
        .settings-field-input,
        .settings-field-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 1em;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.07);
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .settings-field-input:focus,
        .settings-field-textarea:focus {
            border-color: #0073aa;
            box-shadow: 0 0 0 1px #0073aa;
            outline: none;
        }
        
        .settings-field-textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .field-hint {
            font-size: 0.85em;
            color: #888;
            margin-top: 5px;
            line-height: 1.4;
        }
        
        .settings-field-with-button {
            display: flex;
            gap: 10px;
        }
        
        .settings-field-with-button .settings-field-input {
            flex-grow: 1;
        }
        
        .settings-test-btn {
            flex-shrink: 0;
            padding: 8px 15px;
            font-size: 0.9em;
        }
        
        .connection-status-message {
            margin-top: 10px;
            padding: 10px 15px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .connection-status-message.success {
            background-color: #e6ffe6;
            border: 1px solid #4CAF50;
            color: #2e7d32;
        }
        
        .connection-status-message.error {
            background-color: #ffe6e6;
            border: 1px solid #f44336;
            color: #c62828;
        }
        
        .status-icon {
            font-size: 1.5em;
        }
        
        .status-content strong {
            display: block;
            margin-bottom: 3px;
        }
        
        .preview-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 0.95em;
            color: #555;
        }
        
        .preview-headers {
            font-size: 0.9em;
            color: #666;
            line-height: 1.5;
        }
        
        .header-tag {
            display: inline-block;
            background: #e0e0e0;
            padding: 4px 8px;
            border-radius: 3px;
            margin: 3px;
            font-family: monospace;
            font-size: 0.85em;
        }
        
        /* Next Steps List */
        .next-steps-list {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        
        .next-steps-list li {
            background: #f9f9f9;
            border: 1px solid #eee;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            font-size: 0.95em;
            color: #444;
            line-height: 1.4;
        }
        
        .next-steps-list li:last-child {
            margin-bottom: 0;
        }

        /* Action Buttons */
        .settings-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .settings-actions-primary .button {
            min-width: 180px;
            text-align: center;
        }

        .settings-actions-secondary {
            display: flex;
            gap: 10px;
        }

        /* Responsive adjustments */
        @media (max-width: 782px) {
            .settings-fields-grid {
                grid-template-columns: 1fr;
            }
            
            .creation-progress {
                flex-wrap: wrap;
            }
            
            .progress-separator {
                width: 2px;
                height: 40px;
                margin: 10px 0;
            }
            
            .settings-actions {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
            
            .settings-actions-primary,
            .settings-actions-secondary {
                width: 100%;
                justify-content: flex-start;
            }
            
            .settings-actions-primary .button {
                width: 100%;
            }
            
            .settings-field-with-button {
                flex-direction: column;
                gap: 5px;
            }
            
            .settings-test-btn {
                width: 100%;
            }
        }
        </style>
        <?php
    }
    
    public function admin_page_edit_catalog() {
        $catalog_id = intval($_GET['id']);
        $catalog = MenuMaster_Database::get_catalog($catalog_id);
        
        if (!$catalog) {
            wp_die('Menu not found');
        }
        
        // Handle messages
        if (isset($_GET['created'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Menu created successfully!</p></div>';
        }
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Menu updated successfully!</p></div>';
        }
        
        $mappings = MenuMaster_Database::get_column_mapping($catalog_id);
        $items_count = MenuMaster_Database::get_catalog_items_count($catalog_id);
        ?>
        <div class="wrap menu-master-admin menu-master-glass">
            <h1><?php echo esc_html($catalog->name); ?> <small>(ID: <?php echo $catalog->id; ?>)</small></h1>
            
            <div class="menu-master-tabs">
                <ul class="menu-master-tab-nav">
                    <li><a href="#tab-settings" class="active">Settings</a></li>
                    <li><a href="#tab-mapping">Column Mapping</a></li>
                    <li><a href="#tab-import">Import Data</a></li>
                    <li><a href="#tab-data">View Data (<?php echo $items_count; ?>)</a></li>
                    <li><a href="#tab-export">Export</a></li>
                </ul>
            </div>
            
            <!-- Settings Tab -->
            <div id="tab-settings" class="menu-master-tab-content active">
                <!-- Catalog Overview Stats -->
                <div class="settings-overview-grid">
                    <div class="settings-overview-card">
                        <div class="settings-card-content">
                            <h4>Items in Menu</h4>
                            <span class="settings-card-value"><?php echo number_format($items_count); ?></span>
                        </div>
                    </div>
                    
                    <div class="settings-overview-card">
                        <div class="settings-card-content">
                            <h4>Mapping Settings</h4>
                            <span class="settings-card-value"><?php echo count($mappings); ?></span>
                        </div>
                    </div>
                    
                    <div class="settings-overview-card">
                        <div class="settings-card-content">
                            <h4>Creation Date</h4>
                            <span class="settings-card-value"><?php echo date_i18n('d.m.Y', strtotime($catalog->created_at)); ?></span>
                        </div>
                    </div>
                    
                    <div class="settings-overview-card">
                        <div class="settings-card-content">
                            <h4>Connection Status</h4>
                            <span class="settings-card-value connection-status" id="connection-status">
                                <?php echo !empty($catalog->google_sheet_url) ? 'Configured' : 'Not Configured'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <form method="post" action="" id="catalog-settings-form">
                    <?php wp_nonce_field('menu_master_update'); ?>
                    <input type="hidden" name="action" value="update_catalog">
                    <input type="hidden" name="catalog_id" value="<?php echo $catalog->id; ?>">
                    
                    <!-- Basic Information Section -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <h3>Basic Information</h3>
                            <p class="settings-section-description">Configure menu name and description</p>
                        </div>
                        
                        <div class="settings-fields-grid">
                            <div class="settings-field-group">
                                <label for="name" class="settings-field-label">
                                    Menu Name <span class="label-required">*</span>
                                </label>
                                <div class="settings-field-wrapper">
                                    <input type="text" 
                                           id="name" 
                                           name="name" 
                                           class="settings-field-input" 
                                           value="<?php echo esc_attr($catalog->name); ?>" 
                                           required
                                           placeholder="Enter menu name">
                                    <div class="field-hint">
                                        A short, clear name to identify your menu
                                    </div>
                                </div>
                            </div>
                            
                            <div class="settings-field-group full-width">
                                <label for="description" class="settings-field-label">
                                    Menu Description
                                </label>
                                <div class="settings-field-wrapper">
                                    <textarea id="description" 
                                              name="description" 
                                              class="settings-field-textarea" 
                                              rows="3"
                                              placeholder="Detailed description of the menu (optional)"><?php echo esc_textarea($catalog->description); ?></textarea>
                                    <div class="field-hint">
                                        A description helps you and other users understand the menu's purpose
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Google Sheets Connection Section -->
                    <div class="settings-section">
                        <div class="settings-section-header">
                            <h3>Connect to Google Sheets</h3>
                            <p class="settings-section-description">Configure data source for import</p>
                        </div>
                        
                        <div class="settings-fields-grid">
                            <div class="settings-field-group full-width">
                                <label for="google_sheet_url" class="settings-field-label">
                                    Google Sheets URL <span class="label-required">*</span>
                                </label>
                                <div class="settings-field-wrapper">
                                    <div class="settings-field-with-button">
                                        <input type="url" 
                                               id="google_sheet_url" 
                                               name="google_sheet_url" 
                                               class="settings-field-input" 
                                               value="<?php echo esc_attr($catalog->google_sheet_url); ?>"
                                               required
                                               placeholder="https://docs.google.com/spreadsheets/d/...">
                                        <button type="button" 
                                                id="test-sheets-connection" 
                                                class="button button-secondary settings-test-btn">
                                            Test Connection
                                        </button>
                                    </div>
                                    <div class="field-hint">
                                        Enter the full URL to your Google Sheet. It will be automatically processed.
                                    </div>
                                    <div id="connection-test-result" class="connection-status-message" style="display: none;"></div>
                                </div>
                            </div>
                            
                            <div class="settings-field-group">
                                <label for="sheet_name" class="settings-field-label">
                                    Sheet Name
                                </label>
                                <div class="settings-field-wrapper">
                                    <input type="text" 
                                           id="sheet_name" 
                                           name="sheet_name" 
                                           class="settings-field-input" 
                                           value="<?php echo esc_attr($catalog->sheet_name ?: 'Sheet1'); ?>"
                                           placeholder="Sheet1">
                                    <div class="field-hint">
                                        Default: "Sheet1". Change if your data is on another sheet.
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    <!-- Action Buttons -->
                    <div class="settings-actions">
                        <div class="settings-actions-primary">
                            <button type="submit" class="button button-primary button-large settings-save-btn" id="update-catalog-btn">
                                Update Menu
                            </button>
                        </div>
                        
                        <div class="settings-actions-secondary">
                            <a href="<?php echo admin_url('admin.php?page=menu-master'); ?>" 
                               class="button button-secondary">
                                ← Back to List
                            </a>
                            
                            <button type="button" 
                                    class="button button-danger" 
                                    id="delete-catalog-btn">
                                🗑️ Delete Menu
                            </button>
                        </div>
                    </div>
                </form>
                        </div>
                        
            <!-- Column Mapping Tab -->
            <div id="tab-mapping" class="menu-master-tab-content" style="display: none;">
                <h2>Column Mapping Settings</h2>
                <p>Map your Google Sheet columns to the plugin's data fields. The plugin will attempt to auto-match similar names.</p>
                <form method="post" action="" id="column-mapping-form">
                    <?php wp_nonce_field('menu_master_update_mapping'); ?>
                    <input type="hidden" name="action" value="update_column_mapping">
                    <input type="hidden" name="catalog_id" value="<?php echo $catalog->id; ?>">
                    
                    <div style="margin-bottom: 20px;">
                        <button type="button" id="get-sheets-headers" class="button button-secondary" <?php echo empty($catalog->google_sheet_url) ? 'disabled' : ''; ?>>
                            Load Google Sheet Headers
                        </button>
                        <button type="button" id="auto-match-columns" class="button button-secondary" style="margin-left: 10px;" disabled>
                            Auto-Match Columns
                        </button>
                            </div>
                            
                    <div id="mapping-fields-container">
                        <!-- Mappings will be loaded and dynamically managed here via JavaScript -->
                        <p>Please load Google Sheet Headers to begin mapping.</p>
                            </div>
                            
                    <div class="settings-actions">
                        <button type="submit" class="button button-primary settings-save-btn" id="save-column-mapping" disabled>
                            Save Mappings
                        </button>
                    </div>
                </form>
                            </div>
                            
            <!-- Import Data Tab -->
            <div id="tab-import" class="menu-master-tab-content" style="display: none;">
                <h2>Import Data from Google Sheet</h2>
                <p>Click the button below to import data from your connected Google Sheet.</p>
                <p class="description">This process might take a few moments depending on the size of your sheet and the number of images.</p>
                
                <div class="import-status-area">
                    <button type="button" class="button button-primary button-large" id="start-import-btn">
                        📥 Start Import
                    </button>
                    <div id="import-progress" style="display: none;">
                        <p>Importing... Please do not close this page.</p>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: 0%;"></div>
                            </div>
                        <p id="import-status-text">Starting import...</p>
                    </div>
                    <div id="import-result-message" class="notice" style="display: none;"></div>
                        </div>
                    </div>

            <!-- View Data Tab -->
            <div id="tab-data" class="menu-master-tab-content" style="display: none;">
                <h2>View Menu Data</h2>
                <p>Displaying currently imported menu items.</p>
                <div id="menu-data-container">
                    <!-- Menu items will be loaded here via AJAX -->
                    <p>Loading menu data...</p>
                </div>
                        </div>
                        
            <!-- Export Tab -->
            <div id="tab-export" class="menu-master-tab-content" style="display: none;">
                <h2>Export Menu Data</h2>
                <p>Choose an export format and click the button to download your menu data.</p>
                
                <div class="export-options">
                    <label for="export_format">Select Format:</label>
                    <select id="export_format" name="export_format">
                        <option value="csv">CSV</option>
                        <option value="json">JSON</option>
                    </select>
                    <button type="button" class="button button-primary" id="start-export-btn">
                        ⬇️ Export Data
                            </button>
                        </div>
                <div id="export-status-message" class="notice" style="display: none;"></div>
                    </div>

            </div>
            
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlInput = document.getElementById('google_sheet_url');
            const testBtn = document.getElementById('test-sheets-connection');
            const connectionStatusSpan = document.getElementById('connection-status');
            const resultDiv = document.getElementById('connection-test-result');

            // Test connection functionality
            testBtn.addEventListener('click', function() {
                const url = urlInput.value.trim();
                const sheetName = document.getElementById('sheet_name').value.trim() || 'Sheet1';
                
                if (!url) {
                    resultDiv.className = 'connection-status-message error';
                    resultDiv.innerHTML = `
                        <div class="status-icon">❌</div>
                        <div class="status-content">
                            <strong>Error:</strong><br>
                            Google Sheets URL cannot be empty.
                        </div>
                    `;
                    resultDiv.style.display = 'block';
                    return;
                }
                
                testBtn.disabled = true;
                testBtn.innerHTML = '⏳ Testing...';
                connectionStatusSpan.textContent = 'Checking...';
                resultDiv.style.display = 'none';
                
                const formData = new FormData();
                formData.append('action', 'menu_master_test_sheets_connection');
                formData.append('sheet_url', url);
                formData.append('sheet_name', sheetName);
                formData.append('nonce', menu_master_vite_params.nonce);
                
                fetch(menu_master_vite_params.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    testBtn.disabled = false;
                    testBtn.innerHTML = 'Test Connection';
                    
                    if (data.success) {
                        connectionStatusSpan.textContent = 'Configured';
                        resultDiv.className = 'connection-status-message success';
                        resultDiv.innerHTML = `
                            <div class="status-icon">✅</div>
                            <div class="status-content">
                                <strong>Connection Successful!</strong><br>
                                Found ${data.data.row_count} rows with ${data.data.headers.length} columns.
                            </div>
                        `;
                    } else {
                        connectionStatusSpan.textContent = 'Not Configured';
                        resultDiv.className = 'connection-status-message error';
                        resultDiv.innerHTML = `
                            <div class="status-icon">❌</div>
                            <div class="status-content">
                                <strong>Connection Error</strong><br>
                                ${data.data || 'Unknown error. Please check your Google Sheet link and access.'}
                            </div>
                        `;
                    }
                    
                    resultDiv.style.display = 'block';
                })
                .catch(error => {
                    testBtn.disabled = false;
                    testBtn.innerHTML = 'Test Connection';
                    connectionStatusSpan.textContent = 'Not Configured';
                    
                    resultDiv.className = 'connection-status-message error';
                    resultDiv.innerHTML = `
                        <div class="status-icon">❌</div>
                        <div class="status-content">
                            <strong>Network or Server Error</strong><br>
                            Could not connect. Please check your internet connection or try again later.
                        </div>
                    `;
                    resultDiv.style.display = 'block';
                });
            });

            // Tab switching logic
            const tabs = document.querySelectorAll('.menu-master-tab-nav li a');
            const tabContents = document.querySelectorAll('.menu-master-tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    tabs.forEach(item => item.classList.remove('active'));
                    this.classList.add('active');
                    
                    tabContents.forEach(content => content.style.display = 'none');
                    const targetTabId = this.getAttribute('href');
                    document.querySelector(targetTabId).style.display = 'block';

                    // Load mappings if on mapping tab
                    if (targetTabId === '#tab-mapping') {
                        loadColumnMappings();
                    }
                });
            });

            // Initial load check for connection status
            if (urlInput.value.trim() === '') {
                connectionStatusSpan.textContent = 'Not Configured';
            }

            // Handle delete button click
            const deleteButton = document.getElementById('delete-catalog-btn');
            if (deleteButton) {
                deleteButton.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this menu? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            }

            // Column Mapping Functionality
            const getSheetsHeadersBtn = document.getElementById('get-sheets-headers');
            const autoMatchColumnsBtn = document.getElementById('auto-match-columns');
            const mappingFieldsContainer = document.getElementById('mapping-fields-container');
            const saveColumnMappingBtn = document.getElementById('save-column-mapping');
            let availableGoogleHeaders = [];
            let savedMappings = [];

            // Function to fetch and display column mappings
            function loadColumnMappings() {
                const catalogId = '<?php echo $catalog->id; ?>';
                const formData = new FormData();
                formData.append('action', 'menu_master_get_column_mapping_data');
                formData.append('catalog_id', catalogId);
                formData.append('nonce', menu_master_vite_params.nonce);

                fetch(menu_master_vite_params.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        availableGoogleHeaders = data.data.google_headers;
                        savedMappings = data.data.saved_mappings;
                        renderMappingFields();
                    } else {
                        mappingFieldsContainer.innerHTML = '<p style="color: red;">Error loading mappings: ' + (data.data || 'Unknown error') + '</p>';
                    }
                })
                .catch(error => {
                    mappingFieldsContainer.innerHTML = '<p style="color: red;">Network error during mapping load.</p>';
                });
            }

            // Function to render mapping fields
            function renderMappingFields() {
                if (availableGoogleHeaders.length === 0) {
                    mappingFieldsContainer.innerHTML = '<p>No Google Sheet headers loaded. Please load headers first.</p>';
                    autoMatchColumnsBtn.disabled = true;
                    saveColumnMappingBtn.disabled = true;
                    return;
                }

                let html = '';
                html += '<div class="column-mapping-header">';
                html += '<div>Google Sheet Column</div>';
                html += '<div>Menu Field</div>';
                html += '<div>Actions</div>';
                html += '</div>';

                availableGoogleHeaders.forEach(googleHeader => {
                    const savedCatalogColumn = savedMappings.find(m => m.google_column === googleHeader)?.catalog_column || '';
                    html += `<div class="column-mapping-row">
                                <span class="google-column-label">${googleHeader}</span>
                                <select class="column-mapping-select catalog-column-select" name="mappings[${googleHeader}][catalog_column]">
                                    <option value="">-- Select Menu Field --</option>
                    <?php 
                    $catalog_columns = array(
                        'product_id' => 'Product ID',
                        'product_name' => 'Product Name', 
                        'product_price' => 'Product Price',
                        'product_qty' => 'Product Quantity',
                        'product_image_url' => 'Product Image',
                                        'product_sort_order' => 'Product Sort Order',
                        'product_description' => 'Product Description',
                        'category_id_1' => 'Category ID 1',
                        'category_id_2' => 'Category ID 2', 
                        'category_id_3' => 'Category ID 3',
                        'category_name_1' => 'Category Name 1',
                        'category_name_2' => 'Category Name 2',
                        'category_name_3' => 'Category Name 3',
                        'category_image_1' => 'Category Image 1',
                        'category_image_2' => 'Category Image 2',
                        'category_image_3' => 'Category Image 3',
                                        'category_sort_order_1' => 'Category Sort Order 1',
                                        'category_sort_order_2' => 'Category Sort Order 2',
                                        'category_sort_order_3' => 'Category Sort Order 3'
                                    );
                                    foreach ($catalog_columns as $value => $label) {
                                        echo '<option value="' . $value . '">' . $label . '</option>';
                                    }
                                    ?>
                                </select>
                                <button type="button" class="button button-small remove-mapping-btn">Remove</button>
                            </div>`;
                });
                mappingFieldsContainer.innerHTML = html;
                
                // Set saved values
                savedMappings.forEach(mapping => {
                    const selectElement = mappingFieldsContainer.querySelector(`select[name="mappings[${mapping.google_column}][catalog_column]"]`);
                    if (selectElement) {
                        selectElement.value = mapping.catalog_column;
                    }
                });

                autoMatchColumnsBtn.disabled = false;
                saveColumnMappingBtn.disabled = false;
            }

            // Event listener for Load Google Sheet Headers button
            getSheetsHeadersBtn.addEventListener('click', function() {
                const url = urlInput.value.trim();
                const sheetName = document.getElementById('sheet_name').value.trim() || 'Sheet1';
                
                if (!url) {
                    alert('Please enter a Google Sheets URL first.');
                    return;
                }

                getSheetsHeadersBtn.disabled = true;
                getSheetsHeadersBtn.textContent = 'Loading...';
                mappingFieldsContainer.innerHTML = '<p>Fetching headers...</p>';
                autoMatchColumnsBtn.disabled = true;
                saveColumnMappingBtn.disabled = true;

                const formData = new FormData();
                formData.append('action', 'menu_master_get_sheets_headers');
                formData.append('sheet_url', url);
                formData.append('sheet_name', sheetName);
                formData.append('nonce', menu_master_vite_params.nonce);

                fetch(menu_master_vite_params.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    getSheetsHeadersBtn.disabled = false;
                    getSheetsHeadersBtn.textContent = 'Load Google Sheet Headers';

                    if (data.success) {
                        availableGoogleHeaders = data.data;
                        renderMappingFields();
                    } else {
                        mappingFieldsContainer.innerHTML = '<p style="color: red;">Error loading headers: ' + (data.data || 'Unknown error') + '</p>';
                    }
                })
                .catch(error => {
                    getSheetsHeadersBtn.disabled = false;
                    getSheetsHeadersBtn.textContent = 'Load Google Sheet Headers';
                    mappingFieldsContainer.innerHTML = '<p style="color: red;">Network error during header load.</p>';
                });
            });

            // Event listener for Auto-Match Columns button
            autoMatchColumnsBtn.addEventListener('click', function() {
                const currentMappings = Array.from(mappingFieldsContainer.querySelectorAll('.column-mapping-row')).map(row => {
                    return {
                        google_column: row.querySelector('.google-column-label').textContent,
                        catalog_column: row.querySelector('.catalog-column-select').value
                    };
                });

                autoMatchColumnsBtn.disabled = true;
                autoMatchColumnsBtn.textContent = 'Matching...';

                const formData = new FormData();
                formData.append('action', 'menu_master_auto_match_columns');
                formData.append('google_headers', JSON.stringify(availableGoogleHeaders));
                formData.append('catalog_id', '<?php echo $catalog->id; ?>');
                formData.append('current_mappings', JSON.stringify(currentMappings));
                formData.append('nonce', menu_master_vite_params.nonce);

                fetch(menu_master_vite_params.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    autoMatchColumnsBtn.disabled = false;
                    autoMatchColumnsBtn.textContent = 'Auto-Match Columns';

                    if (data.success) {
                        savedMappings = data.data;
                        renderMappingFields(); // Re-render with new matches
                    } else {
                        alert('Auto-matching failed: ' + (data.data || 'Unknown error'));
                    }
                })
                .catch(error => {
                    autoMatchColumnsBtn.disabled = false;
                    autoMatchColumnsBtn.textContent = 'Auto-Match Columns';
                    alert('Network error during auto-matching.');
                });
            });

            // Event listener for Remove button on mapping rows (delegated)
            mappingFieldsContainer.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-mapping-btn')) {
                    e.target.closest('.column-mapping-row').remove();
                }
            });

            // Handle form submission for mapping
            const columnMappingForm = document.getElementById('column-mapping-form');
            columnMappingForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                saveColumnMappingBtn.disabled = true;
                saveColumnMappingBtn.textContent = 'Saving...';

                const formData = new FormData(this);
                formData.append('action', 'menu_master_save_column_mapping');
                formData.append('catalog_id', '<?php echo $catalog->id; ?>');
                formData.append('nonce', menu_master_vite_params.nonce);

                fetch(menu_master_vite_params.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    saveColumnMappingBtn.disabled = false;
                    saveColumnMappingBtn.textContent = 'Save Mappings';
                    if (data.success) {
                        alert('Mappings saved successfully!');
                        savedMappings = data.data.saved_mappings; // Update saved mappings
                        renderMappingFields(); // Re-render to ensure UI reflects saved state
                    } else {
                        alert('Error saving mappings: ' + (data.data || 'Unknown error'));
                    }
                })
                .catch(error => {
                    saveColumnMappingBtn.disabled = false;
                    saveColumnMappingBtn.textContent = 'Save Mappings';
                    alert('Network error during saving mappings.');
                });
            });

            // Import functionality
            const startImportBtn = document.getElementById('start-import-btn');
            const importProgressDiv = document.getElementById('import-progress');
            const importStatusText = document.getElementById('import-status-text');
            const importResultMessage = document.getElementById('import-result-message');
            const progressBar = importProgressDiv.querySelector('.progress-bar');
            let currentImportOffset = 0;
            let totalItemsToImport = 0;

            if (startImportBtn) {
                startImportBtn.addEventListener('click', function() {
                    startImportBtn.disabled = true;
                    importProgressDiv.style.display = 'block';
                    importResultMessage.style.display = 'none';
                    currentImportOffset = 0;
                    totalItemsToImport = 0;
                    importDataBatch(true); // Start the import with the first batch
                });
            }

            function importDataBatch(isFirstBatch = false) {
                const catalogId = '<?php echo $catalog->id; ?>';
                const formData = new FormData();
                formData.append('action', 'menu_master_import_data');
                formData.append('catalog_id', catalogId);
                formData.append('nonce', menu_master_vite_params.nonce);
                formData.append('offset', currentImportOffset);
                formData.append('is_first_batch', isFirstBatch ? '1' : '0');

                importStatusText.textContent = `Processing items ${currentImportOffset + 1} - ${currentImportOffset + 25} (approx)...`;
                progressBar.style.width = '0%';

                fetch(menu_master_vite_params.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        totalItemsToImport = data.data.total_items_in_sheet;
                        currentImportOffset = data.data.next_offset;

                        const progress = (currentImportOffset / totalItemsToImport) * 100;
                        progressBar.style.width = `${progress}%`;
                        importStatusText.textContent = `Importing: ${currentImportOffset} of ${totalItemsToImport} items processed.`;

                        if (data.data.is_complete) {
                            importProgressDiv.style.display = 'none';
                            importResultMessage.className = 'notice notice-success';
                            importResultMessage.innerHTML = `<p>${data.data.message}</p>`;
                            importResultMessage.style.display = 'block';
                            startImportBtn.disabled = false;
                            // Refresh count after import
                            location.reload(); 
                        } else {
                            // Continue with the next batch
                            importDataBatch();
                        }
                    } else {
                        importProgressDiv.style.display = 'none';
                        importResultMessage.className = 'notice notice-error';
                        importResultMessage.innerHTML = `<p>Import failed: ${data.data || 'An unknown error occurred.'}</p>`;
                        importResultMessage.style.display = 'block';
                        startImportBtn.disabled = false;
                    }
                })
                .catch(error => {
                    importProgressDiv.style.display = 'none';
                    importResultMessage.className = 'notice notice-error';
                    importResultMessage.innerHTML = '<p>Network error or server issue during import.</p>';
                    importResultMessage.style.display = 'block';
                    startImportBtn.disabled = false;
                    console.error('Import AJAX error:', error);
                });
            }

            // Export functionality
            const startExportBtn = document.getElementById('start-export-btn');
            const exportFormatSelect = document.getElementById('export_format');
            const exportStatusMessage = document.getElementById('export-status-message');

            if (startExportBtn) {
                startExportBtn.addEventListener('click', function() {
                    const format = exportFormatSelect.value;
                    startExportBtn.disabled = true;
                    exportStatusMessage.style.display = 'none';

                    const formData = new FormData();
                    formData.append('action', 'menu_master_export_data');
                    formData.append('catalog_id', '<?php echo $catalog->id; ?>');
                    formData.append('format', format);
                    formData.append('nonce', menu_master_vite_params.nonce);

                    fetch(menu_master_vite_params.ajax_url, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        startExportBtn.disabled = false;
                        exportStatusMessage.style.display = 'block';
                        if (data.success) {
                            exportStatusMessage.className = 'notice notice-success';
                            exportStatusMessage.innerHTML = '<p>' + data.data.message + '</p>';
                            // Trigger file download
                            window.location.href = data.data.file_url;
                        } else {
                            exportStatusMessage.className = 'notice notice-error';
                            exportStatusMessage.innerHTML = '<p>' + (data.data || 'An unknown error occurred during export.') + '</p>';
                        }
                    })
                    .catch(error => {
                        startExportBtn.disabled = false;
                        exportStatusMessage.className = 'notice notice-error';
                        exportStatusMessage.innerHTML = '<p>Network error or server issue during export.</p>';
                        exportStatusMessage.style.display = 'block';
                    });
                });
            }
        });
        </script>
        
        <style>
        /* New Styles for Tabs and General Layout */
        .menu-master-admin {
            max-width: 1200px;
            margin: 20px auto;
            background: #f0f2f5;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .menu-master-admin h1 {
            font-size: 2.5em;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .menu-master-tabs {
            margin-bottom: 30px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .menu-master-tab-nav {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            border-bottom: 1px solid #eee;
        }

        .menu-master-tab-nav li a {
            padding: 15px 25px;
            display: block;
            text-decoration: none;
            color: #555;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .menu-master-tab-nav li a:hover,
        .menu-master-tab-nav li a.active {
            color: #0073aa;
            border-color: #0073aa;
            background-color: #f9f9f9;
        }

        .menu-master-tab-content {
            padding: 30px 0;
        }

        .menu-master-tab-content h2 {
            font-size: 2em;
            margin-bottom: 20px;
            color: #333;
            text-align: center;
        }

        .settings-overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .settings-overview-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            text-align: center;
        }

        .settings-card-content h4 {
            margin: 0 0 10px 0;
            font-size: 1.1em;
            color: #555;
        }

        .settings-card-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #0073aa;
            display: block;
        }

        .connection-status.configured {
            color: #28a745;
        }
        .connection-status.not-configured {
            color: #dc3545;
        }

        /* Reusing styles from create catalog page for consistency */
        /* .settings-section, .settings-section-header, etc. should be reused */

        .import-status-area {
            text-align: center;
            margin-top: 30px;
        }

        .import-status-area .button {
            margin-bottom: 20px;
        }

        .progress-bar-container {
            background: #e0e0e0;
            border-radius: 5px;
            height: 20px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-bar {
            height: 100%;
            background: #0073aa;
            width: 0%;
            border-radius: 5px;
            transition: width 0.4s ease-in-out;
        }

        #import-status-text {
            font-style: italic;
            color: #666;
        }

        .export-options {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
        }

        .export-options label {
            font-weight: 600;
            color: #333;
        }

        .export-options select,
        .export-options .button {
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 1em;
        }

        .button-danger {
            background: #dc3545 !important;
            border-color: #dc3545 !important;
            color: #fff !important;
        }

        .button-danger:hover {
            background: #c82333 !important;
            border-color: #bd2130 !important;
        }

        /* Responsive adjustments for edit page */
        @media (max-width: 782px) {
            .settings-overview-grid {
                grid-template-columns: 1fr;
            }

            .menu-master-tab-nav {
                flex-wrap: wrap;
                border-bottom: none;
            }
            .menu-master-tab-nav li {
                width: 50%; /* Two tabs per row */
                text-align: center;
                border-bottom: 1px solid #eee;
            }
            .menu-master-tab-nav li:nth-child(odd) {
                border-right: 1px solid #eee;
            }
            .menu-master-tab-nav li:last-child {
                border-right: none;
            }

            .menu-master-tab-nav li a {
                padding: 10px 15px;
                border-bottom: none;
            }
            .menu-master-tab-nav li a.active {
                border-bottom: none;
                position: relative;
            }
            .menu-master-tab-nav li a.active::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 50%;
                transform: translateX(-50%);
                width: 80%;
                height: 3px;
                background: #0073aa;
            }
        }
        </style>
        <?php
    }
    
    public function admin_page_debug() {
        // Handle debug actions
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'enable_debug' && wp_verify_nonce($_POST['_wpnonce'], 'menu_master_debug')) {
                MenuMaster_Logger::enable_debug();
                echo '<div class="notice notice-success"><p>Debug mode enabled</p></div>';
            } elseif ($_POST['action'] === 'disable_debug' && wp_verify_nonce($_POST['_wpnonce'], 'menu_master_debug')) {
                MenuMaster_Logger::disable_debug();
                echo '<div class="notice notice-success"><p>Debug mode disabled</p></div>';
            } elseif ($_POST['action'] === 'clear_logs' && wp_verify_nonce($_POST['_wpnonce'], 'menu_master_debug')) {
                MenuMaster_Logger::clear_logs();
                echo '<div class="notice notice-success"><p>Logs cleared</p></div>';
            }
        }
        
        $debug_enabled = MenuMaster_Logger::is_debug_enabled();
        $log_file = MenuMaster_Logger::get_log_file();
        $recent_logs = MenuMaster_Logger::get_recent_logs(100);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'menu_master_catalogs';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        ?>
        <div class="wrap">
            <h1>Logs and Debug - Menu Master</h1>
            
            <div class="menu-master-card">
                <h3>System Status</h3>
                <table class="form-table">
                    <tr>
                        <th>Debug Mode:</th>
                        <td>
                            <strong style="color: <?php echo $debug_enabled ? 'green' : 'red'; ?>">
                            <?php echo $debug_enabled ? 'Enabled' : 'Disabled'; ?>
                            </strong>
                            
                            <form method="post" style="display: inline; margin-left: 15px;">
                                <?php wp_nonce_field('menu_master_debug'); ?>
                                <?php if ($debug_enabled): ?>
                                    <input type="hidden" name="action" value="disable_debug">
                                    <button type="submit" class="button button-secondary">Disable Debug</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="enable_debug">
                                    <button type="submit" class="button button-primary">Enable Debug</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <tr>
                        <th>WP_DEBUG:</th>
                        <td>
                            <strong style="color: <?php echo (defined('WP_DEBUG') && WP_DEBUG) ? 'green' : 'red'; ?>">
                            <?php echo (defined('WP_DEBUG') && WP_DEBUG) ? 'Enabled' : 'Disabled'; ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <th>Menu Tables:</th>
                        <td>
                            <?php if ($table_exists): ?>
                                <span style="color: green;">✅ Exists</span>
                            <?php else: ?>
                                <span style="color: red;">❌ Missing</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Log File:</th>
                        <td>
                            <?php if (file_exists($log_file)): ?>
                                <span style="color: green;">✅ Exists</span>
                                <br>Size: <?php echo human_readable_bytes(filesize($log_file)); ?>
                                <br>Path: <code><?php echo $log_file; ?></code>
                            <?php else: ?>
                                <span style="color: orange;">⚠️ Not created yet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="menu-master-card">
                <h3>Recent Logs</h3>
                <div style="margin-bottom: 15px;">
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('menu_master_debug'); ?>
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="button button-secondary" onclick="return confirm('Are you sure you want to clear all logs?')">Clear Logs</button>
                    </form>
                    
                    <?php if (file_exists($log_file)): ?>
                        <a href="<?php echo wp_upload_dir()['baseurl'] . '/menu-master-debug.log'; ?>" target="_blank" class="button button-secondary">Open Full Log File</a>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($recent_logs)): ?>
                    <div class="notice notice-info inline">
                        <p>No logs recorded yet.</p>
                </div>
                <?php else: ?>
                    <div style="max-height: 400px; overflow-y: auto; background: #f5f5f5; padding: 10px; font-family: monospace; font-size: 12px;">
                        <?php foreach ($recent_logs as $log): ?>
                            <div style="margin-bottom: 5px; padding: 3px; border-bottom: 1px solid #eee;">
                                <span style="color: #999;">[<?php echo $log['timestamp']; ?>]</span>
                                <span style="color: <?php 
                                    echo $log['level'] === 'ERROR' ? 'red' : 
                                        ($log['level'] === 'WARNING' ? 'orange' : 
                                            ($log['level'] === 'INFO' ? 'blue' : 'green')); 
                                ?>;">
                                    [<?php echo $log['level']; ?>]
                                </span>
                                <?php echo $log['message']; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Image Processing Diagnostics -->
            <div class="menu-master-card">
                <h3>🖼️ Image Processing Diagnostics</h3>
                <?php $this->render_image_diagnostics(); ?>
            </div>
            
            <!-- Image Upload Testing -->
            <div class="menu-master-card">
                <h3>🧪 Image Upload Testing</h3>
                <?php $this->render_image_upload_test(); ?>
            </div>
            
            <div class="menu-master-card">
                <h3>System Testing</h3>
                <p>Try creating a menu now to see detailed process logs.</p>
                <a href="<?php echo admin_url('admin.php?page=menu-master-add'); ?>" class="button button-primary">Create Test Menu</a>
            </div>
            
            <div class="menu-master-card">
                <h3>PHP Configuration</h3>
                <table class="form-table">
                    <tr>
                        <th>allow_url_fopen:</th>
                        <td>
                            <strong style="color: <?php echo ini_get('allow_url_fopen') ? 'green' : 'red'; ?>">
                                <?php echo ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled'; ?>
                            </strong>
                            
                            <?php if (!ini_get('allow_url_fopen')): ?>
                                <span style="color: red;"> - Required for Google Sheets!</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>file_uploads:</th>
                        <td>
                            <strong style="color: <?php echo ini_get('file_uploads') ? 'green' : 'red'; ?>">
                                <?php echo ini_get('file_uploads') ? 'Enabled' : 'Disabled'; ?>
                            </strong>
                        </td>
                    </tr>
                    <tr>
                        <th>max_execution_time:</th>
                        <td>
                            <?php 
                            $max_time = ini_get('max_execution_time');
                            $color = $max_time >= 300 ? 'green' : ($max_time >= 120 ? 'orange' : 'red');
                            ?>
                            <strong style="color: <?php echo $color; ?>">
                                <?php echo $max_time; ?> seconds
                            </strong>
                            <?php if ($max_time < 300): ?>
                                <span style="color: orange;"> - Recommended minimum 300 sec</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>memory_limit:</th>
                        <td>
                            <?php 
                            $memory_limit = ini_get('memory_limit');
                            $memory_bytes = $this->parse_size($memory_limit);
                            $recommended_bytes = 512 * 1024 * 1024; // 512MB
                            $color = $memory_bytes >= $recommended_bytes ? 'green' : 'orange';
                            ?>
                            <strong style="color: <?php echo $color; ?>">
                                <?php echo $memory_limit; ?>
                            </strong>
                            <?php if ($memory_bytes < $recommended_bytes): ?>
                                <span style="color: orange;"> - Recommended minimum 512M</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <script>
        // Auto-refresh logs every 30 seconds when on debug page
        setTimeout(function() {
            if (window.location.href.includes('menu-master-debug')) {
                window.location.reload();
            }
        }, 30000);
        </script>
        <?php
        // GitHub auto-update button and script
        echo '<button id="mm-github-update-btn" class="button button-primary" style="margin-top: 20px;">🔄 Update from GitHub</button>';
        echo '<script>
document.getElementById("mm-github-update-btn").addEventListener("click", function() {
    if (!confirm("Update plugin from GitHub?")) return;
    this.disabled = true;
    this.textContent = "Updating...";
    fetch(ajaxurl, {
        method: "POST",
        credentials: "same-origin",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=menu_master_update_from_github&_wpnonce=" + menu_master_vite_params.nonce
    })
    .then(r => r.json())
    .then(data => {
        alert(data.success ? "Плагин успешно обновлен!" : ("Ошибка: " + data.data));
        location.reload();
    })
    .catch(e => { alert("Ошибка: " + e); this.disabled = false; });
});
</script>';
    }
    
    /**
     * Parse size string (like "512M") to bytes
     */
    private function parse_size($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        } else {
            return round($size);
        }
    }
    
    /**
     * Render image processing diagnostics
     */
    private function render_image_diagnostics() {
        ?>
        <div class="diagnostics-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h4>📊 Server Information</h4>
                <table class="form-table">
                    <tr>
                        <th>PHP Version:</th>
                        <td><strong><?php echo PHP_VERSION; ?></strong></td>
                    </tr>
                    <tr>
                        <th>Operating System:</th>
                        <td><code><?php echo php_uname('s') . ' ' . php_uname('r'); ?></code></td>
                    </tr>
                    <tr>
                        <th>Memory Limit:</th>
                        <td><strong><?php echo ini_get('memory_limit'); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Max Execution Time:</th>
                        <td><strong><?php echo ini_get('max_execution_time'); ?> seconds</strong></td>
                    </tr>
                </table>
            </div>
            
            <div>
                <h4>📦 Image Extensions</h4>
                <table class="form-table">
                    <tr>
                        <th>GD Extension:</th>
                        <td>
                            <?php if (extension_loaded('gd')): ?>
                                <strong style="color: green;">✅ Installed</strong>
                                <?php 
                                $gd_info = gd_info();
                                if ($gd_info && is_array($gd_info) && isset($gd_info['GD Version'])) {
                                    echo '<br><small>Version: ' . $gd_info['GD Version'] . '</small>';
                                }
                                ?>
                            <?php else: ?>
                                <strong style="color: red;">❌ NOT installed</strong>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Supported formats:</th>
                        <td>
                            <?php
                            $formats = array();
                            if (extension_loaded('gd')) {
                                $gd_info = gd_info();
                                if ($gd_info && is_array($gd_info)) {
                                    if (isset($gd_info['JPEG Support']) && $gd_info['JPEG Support']) $formats[] = 'JPEG';
                                    if (isset($gd_info['PNG Support']) && $gd_info['PNG Support']) $formats[] = 'PNG';
                                    if (isset($gd_info['GIF Create Support']) && $gd_info['GIF Create Support']) $formats[] = 'GIF';
                                    if (isset($gd_info['WebP Support']) && $gd_info['WebP Support']) $formats[] = 'WebP';
                                    if (isset($gd_info['AVIF Support']) && $gd_info['AVIF Support']) $formats[] = 'AVIF';
                                }
                            }
                            echo implode(', ', $formats);
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <h4>🧪 Image Creation Testing</h4>
        <div class="diagnostics-tests">
            <?php
            // Test GD image creation
            if (extension_loaded('gd')) {
                echo '<div class="test-result">';
                echo '<strong>GD test:</strong> ';
                try {
                    $test_image = imagecreate(10, 10);
                    $bg_color = imagecolorallocate($test_image, 255, 255, 255);
                    
                    ob_start();
                    imagejpeg($test_image, null, 90);
                    $jpeg_data = ob_get_contents();
                    ob_end_clean();
                    imagedestroy($test_image);
                    
                    if ($jpeg_data !== false && strlen($jpeg_data) > 0) {
                        echo '<span style="color: green;">✅ JPEG creation works (' . strlen($jpeg_data) . ' bytes)</span>';
                    } else {
                        echo '<span style="color: red;">❌ CANNOT create JPEG</span>';
                    }
                } catch (Exception $e) {
                    echo '<span style="color: red;">❌ Error: ' . esc_html($e->getMessage()) . '</span>';
                }
                echo '</div>';
            }
            
            // Test WordPress Image Editor
            echo '<div class="test-result">';
            echo '<strong>WordPress Image Editor test:</strong> ';
            
            // Test available image editors correctly
            $editors = array();
            if (class_exists('WP_Image_Editor_GD') && WP_Image_Editor_GD::test()) {
                $editors[] = 'GD';
            }
              if (!empty($editors)) {
                echo '<span style="color: green;">✅ Available editors: ' . implode(', ', $editors) . '</span>';
            } else {
                echo '<span style="color: red;">❌ No available editors</span>';
                
                // Additional debug info
                echo '<br><small>GD test: ' . (class_exists('WP_Image_Editor_GD') ? 'class exists' : 'class not found');
                if (class_exists('WP_Image_Editor_GD')) {
                    echo ', test(): ' . (WP_Image_Editor_GD::test() ? 'passed' : 'failed');
                }
                echo '</small>';
            }
            echo '</div>';
            
            // Add CSS styles for diagnostics
            ?>
            <style type="text/css">
                .diagnostics-grid {
                    margin-bottom: 20px;
                }
                .test-result {
                    padding: 8px 12px;
                    margin: 5px 0;
                    background: #f9f9f9;
                    border-left: 4px solid #ddd;
                    border-radius: 0 4px 4px 0;                }
                .diagnostics-tests {
                    background: #f1f1f1;
                    padding: 15px;
                    border-radius: 4px;
                    margin: 15px 0;
                }
            </style>
            <?php
        }
    
    /**
     * Render image upload testing
     */
    private function render_image_upload_test() {
        // Handle test upload
        if (isset($_POST['test_image_upload']) && isset($_FILES['test_image'])) {
            $this->handle_test_image_upload();
        }
        ?>
        
        <p>Upload an image to test the entire processing workflow used in the plugin:</p>
        
        <form method="post" enctype="multipart/form-data" style="margin-bottom: 20px;">
            <?php wp_nonce_field('menu_master_debug_image', 'debug_image_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="test_image">Select an image:</label></th>
                    <td>
                        <input type="file" name="test_image" id="test_image" accept="image/*" required>
                        <p class="description">Supported formats: JPG, PNG, GIF, WebP, BMP, AVIF</p>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <button type="submit" name="test_image_upload" class="button button-primary">
                            🚀 Test Upload and Processing
                        </button>
                    </td>
                </tr>
            </table>
        </form>
        
        <div class="test-info">
            <h4>🔍 What will be tested:</h4>
            <ul>
                <li>✅ File upload through $_FILES</li>
                <li>✅ Verification of existence and reading of temporary file</li>
                <li>✅ getimagesize() function for image analysis</li>
                <li>✅ WordPress Image Editor (wp_get_image_editor)</li>
                <li>✅ **Forced** resize to 100x100 pixels (test; in real operation - 1000x1000)</li>
                <li>✅ Saving in JPEG format with 90% quality</li>
                <li>✅ Verification of access rights to uploads folder</li>
            </ul>
            
            <div class="notice notice-info inline">
                <p><strong>💡 Tip:</strong> If the test passes successfully but uploads in tables don't work, the problem might be in:</p>
                <ul>
                    <li>• Access rights to specific catalog folder</li>
                    <li>• WordPress security settings</li>
                    <li>• Conflicts with other plugins</li>
                </ul>
            </div>
        </div>
        
        <style>
        .test-info {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .test-info ul {
            margin: 10px 0;
        }
        .test-info li {
            margin: 5px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Handle test image upload
     */
    private function handle_test_image_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['debug_image_nonce'], 'menu_master_debug_image')) {
            echo '<div class="notice notice-error"><p>❌ Security error. Please try again.</p></div>';
            return;
        }
        
        $file = $_FILES['test_image'];
        
        echo '<div class="test-results" style="background: #f1f1f1; padding: 20px; border-radius: 4px; margin: 20px 0;">';
        echo '<h4>📊 Test Results</h4>';
        
        // Step 1: Basic file info
        echo '<div class="test-step">';
        echo '<h5>1️⃣ File Information</h5>';
        echo '<ul>';
        echo '<li><strong>Name:</strong> ' . esc_html($file['name']) . '</li>';
        echo '<li><strong>Size:</strong> ' . number_format($file['size']) . ' bytes (' . number_format($file['size']/1024, 1) . ' KB)</li>';
        echo '<li><strong>MIME type:</strong> ' . esc_html($file['type']) . '</li>';
        echo '<li><strong>Temporary file:</strong> ' . esc_html($file['tmp_name']) . '</li>';
        echo '<li><strong>Upload error:</strong> ' . ($file['error'] === UPLOAD_ERR_OK ? 'None ✅' : 'Code ' . $file['error'] . ' ❌') . '</li>';
        echo '</ul>';
        echo '</div>';
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo '<div class="notice notice-error inline"><p>❌ File did not upload correctly.</p></div>';
            echo '</div>';
            return;
        }
        
        // Step 2: File existence and readability
        echo '<div class="test-step">';
        echo '<h5>2️⃣ File Access Test</h5>';
        $file_exists = file_exists($file['tmp_name']);
        $file_readable = is_readable($file['tmp_name']);
        $file_size = $file_exists ? filesize($file['tmp_name']) : 0;
        
        echo '<ul>';
        echo '<li><strong>File exists:</strong> ' . ($file_exists ? 'Yes ✅' : 'No ❌') . '</li>';
        echo '<li><strong>File is readable:</strong> ' . ($file_readable ? 'Yes ✅' : 'No ❌') . '</li>';
        echo '<li><strong>File size:</strong> ' . number_format($file_size) . ' bytes</li>';
        echo '</ul>';
        echo '</div>';
        
        if (!$file_exists || !$file_readable) {
            echo '<div class="notice notice-error inline"><p>❌ Problems accessing the file.</p></div>';
            echo '</div>';
            return;
        }
        
        // Step 3: getimagesize test
        echo '<div class="test-step">';
        echo '<h5>3️⃣ getimagesize() Test</h5>';
        $image_info = getimagesize($file['tmp_name']);
        
        if ($image_info !== false) {
            echo '<p style="color: green;">✅ <strong>Success!</strong></p>';
            echo '<ul>';
            echo '<li><strong>Width:</strong> ' . $image_info[0] . ' px</li>';
            echo '<li><strong>Height:</strong> ' . $image_info[1] . ' px</li>';
            echo '<li><strong>MIME type:</strong> ' . $image_info['mime'] . '</li>';
            if (isset($image_info['channels'])) {
                echo '<li><strong>Channels:</strong> ' . $image_info['channels'] . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p style="color: red;">❌ <strong>Could not determine image size</strong></p>';
            $mime_type = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : 'unknown';
            echo '<p>MIME type from mime_content_type(): ' . $mime_type . '</p>';
        }
        echo '</div>';
        
        // Step 4: WordPress Image Editor test
        echo '<div class="test-step">';
        echo '<h5>4️⃣ WordPress Image Editor Test</h5>';
        
        $image_editor = wp_get_image_editor($file['tmp_name']);
        
        if (is_wp_error($image_editor)) {
            echo '<p style="color: red;">❌ <strong>wp_get_image_editor():</strong> ' . $image_editor->get_error_message() . '</p>';
        } else {
            echo '<p style="color: green;">✅ <strong>wp_get_image_editor():</strong> Successfully created</p>';
            
            // Get image size
            $size = $image_editor->get_size();
            echo '<p><strong>Size from editor:</strong> ' . $size['width'] . 'x' . $size['height'] . '</p>';
            
            // Test resize (always resize to test dimensions, regardless of current size)
            echo '<p><strong>Resize test:</strong> ' . $size['width'] . 'x' . $size['height'] . ' → 100x100 (test size; in catalog will be 1000x1000)</p>';
            $resized = $image_editor->resize(100, 100, true);
            if (is_wp_error($resized)) {
                echo '<p style="color: red;">❌ <strong>Resize:</strong> ' . $resized->get_error_message() . '</p>';
            } else {
                echo '<p style="color: green;">✅ <strong>Resize:</strong> Success (always resize regardless of original size)</p>';
                
                // Test save
                $upload_dir = wp_upload_dir();
                $temp_path = $upload_dir['basedir'] . '/test_image_' . time() . '.jpg';
                $saved = $image_editor->save($temp_path, 'image/jpeg');
                
                if (is_wp_error($saved)) {
                    echo '<p style="color: red;">❌ <strong>Save:</strong> ' . $saved->get_error_message() . '</p>';
                } else {
                    echo '<p style="color: green;">✅ <strong>Save:</strong> Success (' . number_format(filesize($saved['path'])) . ' bytes)</p>';
                    
                    // Show image if saved successfully
                    $temp_url = $upload_dir['baseurl'] . '/' . basename($saved['path']);
                    echo '<p><img src="' . esc_url($temp_url) . '" style="max-width: 100px; border: 1px solid #ddd;" alt="Test Image"></p>';
                    
                    // Clean up test file after a delay (via JavaScript)
                    echo '<script>setTimeout(function() { 
                        fetch("' . admin_url('admin-ajax.php') . '", {
                            method: "POST",
                            headers: {"Content-Type": "application/x-www-form-urlencoded"},
                            body: "action=menu_master_cleanup_test_image&path=' . urlencode($saved['path']) . '&nonce=' . wp_create_nonce('cleanup_test_image') . '"
                        });
                    }, 5000);</script>';
                }
            }
        }
        echo '</div>';
        
        // Step 5: Upload directory test
        echo '<div class="test-step">';
        echo '<h5>5️⃣ Uploads Directory Test</h5>';
        $upload_dir = wp_upload_dir();
        $writable = is_writable($upload_dir['basedir']);
        
        echo '<ul>';
        echo '<li><strong>Directory:</strong> ' . $upload_dir['basedir'] . '</li>';
        echo '<li><strong>URL:</strong> ' . $upload_dir['baseurl'] . '</li>';
        echo '<li><strong>Writable:</strong> ' . ($writable ? 'Yes ✅' : 'No ❌') . '</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</div>'; // .test-results
        
        echo '<style>';
        echo '.test-step { margin: 15px 0; padding: 10px; background: white; border-radius: 4px; border: 1px solid #ddd; }';
        echo '.test-step h5 { margin: 0 0 10px 0; color: #0073aa; }';
        echo '.test-step ul { margin: 5px 0; }';
        echo '.test-step li { margin: 3px 0; }';
        echo '.test-conclusion { margin-top: 20px; }';
        echo '</style>';
    }
    
    /**
     * AJAX handler for cleaning up test images
     */
    public function ajax_cleanup_test_image() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cleanup_test_image')) {
            wp_die('Security check failed');
        }
        
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $file_path = sanitize_text_field($_POST['path']);
        
        // Security check: file must be in uploads directory and be a test image
        $upload_dir = wp_upload_dir();
        if (strpos($file_path, $upload_dir['basedir']) !== 0 || strpos($file_path, 'test_image_') === false) {
            wp_die('Invalid file path');
        }
        
        // Delete the file if it exists
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                wp_send_json_success('Test image cleaned up successfully');
            } else {
                wp_send_json_error('Failed to delete test image');
            }
        } else {
            wp_send_json_success('Test image already cleaned up');
        }
    }
}

// Helper function for file size formatting
if (!function_exists('human_readable_bytes')) {
    function human_readable_bytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
}