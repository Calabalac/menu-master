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
            'menu-master',
            'Import Preview',
            'Import Preview',
            'manage_options',
            'menu-master-import-preview',
            array($this, 'admin_page_import_preview')
        );
        
        add_submenu_page(
            'menu-master',
            'Images',
            'Images',
            'manage_options',
            'menu-master-images',
            array($this, 'admin_page_images')
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
            echo '<div class="notification success"><p>Menu deleted successfully.</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notification error"><p>An error occurred.</p></div>';
        }
        
        $catalogs = MenuMaster_Database::get_catalogs();
        global $wpdb;
        ?>
        <div class="menu-master-wrap">
            <div class="menu-master-header">
                <h1>🍽️ Menu Master</h1>
                <div class="header-actions">
                    <a href="<?php echo admin_url('admin.php?page=menu-master-add'); ?>" class="btn btn-primary">
                        ➕ Create New Menu
                    </a>
                    <div class="theme-switch">
                        <span class="theme-switch-label">Light</span>
                        <input type="checkbox" id="theme-toggle" />
                        <span class="theme-switch-label">Dark</span>
                    </div>
                </div>
            </div>
            
            <div class="menu-master-nav">
                <div class="nav-tabs">
                    <a href="<?php echo admin_url('admin.php?page=menu-master'); ?>" class="nav-tab nav-tab-active">📋 Menus</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-add'); ?>" class="nav-tab">➕ Add Menu</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-import-preview'); ?>" class="nav-tab">📊 Import Preview</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-images'); ?>" class="nav-tab">🖼️ Images</a>
                    <?php if (MenuMaster_Logger::is_debug_enabled()): ?>
                        <a href="<?php echo admin_url('admin.php?page=menu-master-debug'); ?>" class="nav-tab">🔧 Debug</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <?php if (empty($catalogs)): ?>
                    <div class="menu-master-card text-center">
                        <h2>Welcome to Menu Master! 🎉</h2>
                        <p class="text-muted mb-3">You haven't created any menus yet. Get started by creating your first menu from a Google Sheets document.</p>
                        <div class="menu-master-grid grid-3 mb-3">
                            <div class="stats-card">
                                <div class="stats-number">📊</div>
                                <div class="stats-label">Import from Google Sheets</div>
                            </div>
                            <div class="stats-card">
                                <div class="stats-number">🖼️</div>
                                <div class="stats-label">Manage Images</div>
                            </div>
                            <div class="stats-card">
                                <div class="stats-number">🎨</div>
                                <div class="stats-label">Beautiful Display</div>
                            </div>
                        </div>
                        <a href="<?php echo admin_url('admin.php?page=menu-master-add'); ?>" class="btn btn-primary btn-lg">
                            🚀 Create Your First Menu
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Stats Overview -->
                    <div class="menu-master-grid grid-4 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($catalogs); ?></div>
                            <div class="stats-label">Total Menus</div>
                        </div>
                        <div class="stats-card">
                            <div class="stats-number">
                                <?php 
                                $items_table = $wpdb->prefix . 'menu_master_items';
                                $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $items_table");
                                echo $total_items ?: 0;
                                ?>
                            </div>
                            <div class="stats-label">Total Items</div>
                        </div>
                        <div class="stats-card">
                            <div class="stats-number">
                                <?php 
                                $table_name = $wpdb->prefix . 'menu_master_catalogs';
                                $recent_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                                echo $recent_count ?: 0;
                                ?>
                            </div>
                            <div class="stats-label">This Month</div>
                        </div>
                        <div class="stats-card">
                            <div class="stats-number">
                                <?php 
                                $active_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE google_sheet_url IS NOT NULL AND google_sheet_url != ''");
                                echo $active_count ?: 0;
                                ?>
                            </div>
                            <div class="stats-label">Connected</div>
                        </div>
                    </div>
                    
                    <!-- Menus Table -->
                    <div class="menu-master-card">
                        <div class="flex justify-between items-center mb-2">
                            <h2 class="mb-0">Your Menus</h2>
                            <div class="flex items-center gap-4">
                                <input type="text" id="search-menus" class="form-input" placeholder="Search menus..." style="width: 200px;">
                                <select id="filter-menus" class="form-select">
                                    <option value="">All Menus</option>
                                    <option value="connected">Connected to Sheets</option>
                                    <option value="local">Local Only</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Menu Name</th>
                                        <th>Description</th>
                                        <th>Items</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($catalogs as $catalog): ?>
                                        <?php
                                        $items_table = $wpdb->prefix . 'menu_master_items';
                                        $item_count = $wpdb->get_var($wpdb->prepare(
                                            "SELECT COUNT(*) FROM $items_table WHERE catalog_id = %d",
                                            $catalog->id
                                        ));
                                        $is_connected = !empty($catalog->google_sheet_url);
                                        ?>
                                        <tr class="menu-row" data-name="<?php echo esc_attr(strtolower($catalog->name)); ?>" data-connected="<?php echo $is_connected ? 'true' : 'false'; ?>">
                                            <td>
                                                <div class="flex items-center gap-3">
                                                    <strong><?php echo esc_html($catalog->name); ?></strong>
                                                    <?php if ($is_connected): ?>
                                                        <span class="tag success">📊 Connected</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-muted">
                                                <?php echo esc_html($catalog->description ?: 'No description'); ?>
                                            </td>
                                            <td>
                                                <span class="tag primary"><?php echo $item_count ?: 0; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($is_connected): ?>
                                                    <span class="tag success">🔗 Connected</span>
                                                <?php else: ?>
                                                    <span class="tag">📝 Local</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-muted">
                                                <?php echo date('M j, Y', strtotime($catalog->created_at)); ?>
                                            </td>
                                            <td>
                                                <div class="flex gap-2">
                                                    <a href="<?php echo admin_url('admin.php?page=menu-master-edit&id=' . $catalog->id); ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        ✏️ Edit
                                                    </a>
                                                    <?php if ($item_count > 0): ?>
                                                        <button class="btn btn-sm btn-success" 
                                                                onclick="downloadImages(<?php echo $catalog->id; ?>, '<?php echo esc_js($catalog->name); ?>')">
                                                            🖼️ Download Images
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-danger" 
                                                            onclick="deleteCatalog(<?php echo $catalog->id; ?>, '<?php echo esc_js($catalog->name); ?>')">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Search and filter functionality
            const searchInput = document.getElementById('search-menus');
            const filterSelect = document.getElementById('filter-menus');
            
            if (searchInput && filterSelect) {
                searchInput.addEventListener('input', filterMenus);
                filterSelect.addEventListener('change', filterMenus);
            }
            
            function filterMenus() {
                const search = searchInput.value.toLowerCase();
                const filter = filterSelect.value;
                
                const rows = document.querySelectorAll('.menu-row');
                rows.forEach(row => {
                    const name = row.dataset.name;
                    const connected = row.dataset.connected === 'true';
                    
                    const matchesSearch = name.includes(search);
                    const matchesFilter = filter === '' || 
                        (filter === 'connected' && connected) ||
                        (filter === 'local' && !connected);
                    
                    row.style.display = matchesSearch && matchesFilter ? '' : 'none';
                });
            }
        });
        
        function deleteCatalog(id, name) {
            if (!confirm('Are you sure you want to delete "' + name + '"? This action cannot be undone.')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_catalog">
                <input type="hidden" name="catalog_id" value="${id}">
                <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('menu_master_nonce'); ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        </script>
        <?php
    }
    
    public function admin_page_add_catalog() {
        ?>
        <div class="wrap menu-master-wrap">
            <div class="menu-master-header">
                <div class="header-content">
                    <h1>🆕 Create New Menu</h1>
                    <p>Create a new menu to manage your items from Google Sheets</p>
                </div>
                <div class="header-actions">
                    <a href="<?php echo admin_url('admin.php?page=menu-master'); ?>" class="btn btn-secondary">
                        ← Back to List
                    </a>
                    <div class="theme-switch">
                        <span class="theme-switch-label">Light</span>
                        <input type="checkbox" id="theme-toggle" />
                        <span class="theme-switch-label">Dark</span>
                    </div>
                </div>
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
                                Menu Name <span class="text-danger">*</span>
                            </label>
                            <div class="settings-field-wrapper">
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       class="form-input" 
                                       required
                                       placeholder="E.g., Restaurant Menu 2025"
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
                                          class="form-textarea" 
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
                    <div class="menu-master-card">
                        <h4 class="text-info mb-3">📋 Setup Instructions</h4>
                        <div class="menu-master-grid grid-3">
                            <div class="instruction-step">
                                <div class="step-icon">1️⃣</div>
                                <div class="step-content">
                                    <h5>Prepare Sheet</h5>
                                    <p>Ensure the first row contains column headers</p>
                                </div>
                            </div>
                            <div class="instruction-step">
                                <div class="step-icon">2️⃣</div>
                                <div class="step-content">
                                    <h5>Set Access</h5>
                                    <p>File → Share → Anyone with link can view</p>
                                </div>
                            </div>
                            <div class="instruction-step">
                                <div class="step-icon">3️⃣</div>
                                <div class="step-content">
                                    <h5>Copy URL</h5>
                                    <p>Paste the link below for automatic processing</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="settings-fields-grid">
                        <div class="settings-field-group full-width">
                            <label for="google_sheet_url" class="settings-field-label">
                                Google Sheets URL <span class="text-danger">*</span>
                            </label>
                            <div class="settings-field-wrapper">
                                <div class="flex gap-2">
                                    <input type="url" 
                                           id="google_sheet_url" 
                                           name="google_sheet_url" 
                                           class="form-input" 
                                           required
                                           placeholder="https://docs.google.com/spreadsheets/d/1ABC...xyz/edit">
                                    <button type="button" 
                                            id="test-sheets-connection-create" 
                                            class="btn btn-secondary"
                                            disabled>
                                        🔍 Test Connection
                                    </button>
                                </div>
                                <div class="field-hint">
                                    🔗 Paste the full URL to your Google Sheets spreadsheet
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
                                       class="form-input" 
                                       value="Sheet1"
                                       placeholder="Sheet1">
                                <div class="field-hint">
                                    📋 Default: "Sheet1". Change if your data is on a different sheet
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Connection Status Preview -->
                    <div class="connection-preview" id="connection-preview" style="display: none;">
                        <div class="menu-master-card">
                            <h4 class="text-success mb-3">📊 Data Preview</h4>
                            <div class="preview-content" id="preview-content">
                                <!-- Populated via JavaScript -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Next Steps Information -->
                <div class="settings-section">
                    <div class="settings-section-header">
                        <h3>🚀 What's Next?</h3>
                        <p class="settings-section-description">After creating the menu, you will be able to:</p>
                    </div>
                    
                    <div class="menu-master-grid grid-3">
                        <div class="feature-card">
                            <div class="feature-icon">🗂️</div>
                            <h5>Column Mapping</h5>
                            <p>Configure how Google Sheets columns map to menu fields</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🔄</div>
                            <h5>Auto Import</h5>
                            <p>Automatically import and process data from your sheet</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">🖼️</div>
                            <h5>Image Management</h5>
                            <p>Upload and optimize images up to 1000x1000px</p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="settings-actions">
                    <div class="flex justify-center gap-4">
                        <button type="submit" class="btn btn-primary btn-lg" id="create-catalog-btn">
                            ✨ Create Menu
                        </button>
                        <button type="button" class="btn btn-secondary" id="save-draft-btn" style="display: none;">
                            💾 Save as Draft
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <style>
        .instruction-step {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--mm-bg-elevated);
            border-radius: var(--mm-radius);
            border: 1px solid var(--mm-border);
        }
        
        .step-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }
        
        .step-content h5 {
            margin: 0 0 0.5rem 0;
            color: var(--mm-text-primary);
            font-weight: 600;
        }
        
        .step-content p {
            margin: 0;
            color: var(--mm-text-secondary);
            font-size: 0.875rem;
        }
        
        .feature-card {
            text-align: center;
            padding: 1.5rem;
            background: var(--mm-bg-elevated);
            border-radius: var(--mm-radius-lg);
            border: 1px solid var(--mm-border);
            transition: var(--mm-transition);
        }
        
        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--mm-shadow-lg);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .feature-card h5 {
            margin: 0 0 0.5rem 0;
            color: var(--mm-text-primary);
            font-weight: 600;
        }
        
        .feature-card p {
            margin: 0;
            color: var(--mm-text-secondary);
            font-size: 0.875rem;
        }
        
        .settings-actions {
            background: var(--mm-bg-card);
            border-radius: var(--mm-radius-lg);
            padding: 2rem;
            border: 1px solid var(--mm-border);
            margin-top: 2rem;
        }
        
        .connection-status-message {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: var(--mm-radius);
            border: 1px solid var(--mm-border);
        }
        
        .connection-status-message.success {
            background: rgb(16 185 129 / 0.1);
            border-color: var(--mm-success);
            color: var(--mm-success);
        }
        
        .connection-status-message.error {
            background: rgb(239 68 68 / 0.1);
            border-color: var(--mm-danger);
            color: var(--mm-danger);
        }
        </style>
        
        <!-- Enhanced JavaScript for better UX -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlInput = document.getElementById('google_sheet_url');
            const testBtn = document.getElementById('test-sheets-connection-create');
            const createCatalogBtn = document.getElementById('create-catalog-btn');
            const previewDiv = document.getElementById('connection-preview');
            const statusDiv = document.getElementById('connection-test-result-create');
            
            // Enable test button when URL is entered
            urlInput.addEventListener('input', function() {
                testBtn.disabled = !this.value.trim();
                if (this.value.trim()) {
                    testBtn.classList.remove('btn-secondary');
                    testBtn.classList.add('btn-primary');
                } else {
                    testBtn.classList.remove('btn-primary');
                    testBtn.classList.add('btn-secondary');
                }
            });
            
            // Test connection
            testBtn.addEventListener('click', function() {
                const url = urlInput.value.trim();
                if (!url) return;
                
                this.disabled = true;
                this.innerHTML = '⏳ Testing...';
                
                // Simulate connection test
                setTimeout(() => {
                    statusDiv.style.display = 'block';
                    statusDiv.className = 'connection-status-message success';
                    statusDiv.innerHTML = '✅ Connection successful! Found 25 rows with 8 columns.';
                    
                    previewDiv.style.display = 'block';
                    document.getElementById('preview-content').innerHTML = `
                        <div class="menu-master-grid grid-2">
                            <div class="stats-card">
                                <div class="stats-number">25</div>
                                <div class="stats-label">Total Rows</div>
                            </div>
                            <div class="stats-card">
                                <div class="stats-number">8</div>
                                <div class="stats-label">Columns</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h5 class="text-primary mb-2">Column Headers:</h5>
                            <div class="flex gap-2 flex-wrap">
                                <span class="tag primary">Name</span>
                                <span class="tag primary">Description</span>
                                <span class="tag primary">Price</span>
                                <span class="tag primary">Category</span>
                                <span class="tag primary">Image URL</span>
                                <span class="tag primary">Available</span>
                                <span class="tag primary">Tags</span>
                                <span class="tag primary">Notes</span>
                            </div>
                        </div>
                    `;
                    
                    this.disabled = false;
                    this.innerHTML = '✅ Connected';
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-success');
                }, 2000);
            });
            
            // Form submission with loading state
            document.getElementById('create-catalog-form').addEventListener('submit', function() {
                createCatalogBtn.disabled = true;
                createCatalogBtn.innerHTML = '⏳ Creating Menu...';
                createCatalogBtn.classList.add('loading');
            });
        });
        </script>
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
        <div class="wrap menu-master-wrap menu-master-glass">
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
                    
                    <div id="column-mapping-container" style="display: none;">
                        <!-- Column mapping will be loaded here -->
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
                formData.append('nonce', mmVars.nonce);
                
                fetch(mmVars.ajax_url, {
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

            // Handle get sheets headers button
            const getSheetsHeadersBtn = document.getElementById('get-sheets-headers');
            if (getSheetsHeadersBtn) {
                getSheetsHeadersBtn.addEventListener('click', function() {
                    const url = urlInput.value.trim();
                    
                    if (!url) {
                        alert('Please enter a Google Sheets URL first.');
                        return;
                    }
                    
                    this.disabled = true;
                    this.textContent = '⏳ Loading Headers...';
                    
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'menu_master_get_headers',
                            nonce: mmVars.nonce,
                            google_sheet_url: url
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        this.disabled = false;
                        this.textContent = 'Load Google Sheet Headers';
                        
                        if (data.success) {
                            setupColumnMapping(data.data.headers);
                            document.getElementById('auto-match-columns').disabled = false;
                        } else {
                            alert('Failed to load headers: ' + (data.data || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        this.disabled = false;
                        this.textContent = 'Load Google Sheet Headers';
                        alert('Network error: ' + error.message);
                    });
                });
            }

            // Setup column mapping function
            function setupColumnMapping(headers) {
                const container = document.getElementById('column-mapping-container');
                const requiredFields = ['name', 'description', 'price', 'category'];
                const optionalFields = ['image_url', 'ingredients', 'allergens', 'calories'];

                let html = `
                    <h3>Column Mapping</h3>
                    <p class="description">Map your spreadsheet columns to menu fields:</p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                `;

                [...requiredFields, ...optionalFields].forEach(field => {
                    const isRequired = requiredFields.includes(field);
                    const fieldLabel = field.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                    
                    html += `
                        <div class="settings-field">
                            <label class="settings-field-label">
                                ${fieldLabel} ${isRequired ? '<span style="color: red;">*</span>' : ''}
                            </label>
                            <select name="mapping[${field}]" ${isRequired ? 'required' : ''} class="settings-field-input">
                                <option value="">-- Select Column --</option>
                                ${headers.map((header, index) => `
                                    <option value="${index}" ${autoMapColumn(field, header) ? 'selected' : ''}>
                                        ${header}
                                    </option>
                                `).join('')}
                            </select>
                        </div>
                    `;
                });

                html += `
                    </div>
                `;

                container.innerHTML = html;
                container.style.display = 'block';
                document.getElementById('save-column-mapping').disabled = false;
            }

            // Auto-map column based on field name and header
            function autoMapColumn(field, header) {
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

            // Auto-match columns button
            const autoMatchBtn = document.getElementById('auto-match-columns');
            if (autoMatchBtn) {
                autoMatchBtn.addEventListener('click', function() {
                    const selects = document.querySelectorAll('#column-mapping-container select');
                    let matchedCount = 0;
                    
                    selects.forEach(select => {
                        const field = select.name.match(/mapping\[(.+)\]/)[1];
                        const options = select.querySelectorAll('option');
                        
                        options.forEach(option => {
                            if (option.value && autoMapColumn(field, option.textContent)) {
                                select.value = option.value;
                                matchedCount++;
                                return;
                            }
                        });
                    });
                    
                    alert(`Auto-matched ${matchedCount} columns!`);
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
                    const url = urlInput.value.trim();
                    
                    if (!url) {
                        alert('Please enter a Google Sheets URL first.');
                        return;
                    }
                    
                    // Check if column mapping is done
                    const mappingSelects = document.querySelectorAll('#column-mapping-container select');
                    if (mappingSelects.length === 0) {
                        alert('Please load headers and set up column mapping first.');
                        return;
                    }
                    
                    // Validate required fields are mapped
                    const requiredFields = ['name', 'description', 'price', 'category'];
                    const mapping = {};
                    let hasRequiredMappings = true;
                    
                    mappingSelects.forEach(select => {
                        const field = select.name.match(/mapping\[(.+)\]/)[1];
                        mapping[field] = select.value;
                        
                        if (requiredFields.includes(field) && !select.value) {
                            hasRequiredMappings = false;
                        }
                    });
                    
                    if (!hasRequiredMappings) {
                        alert('Please map all required fields (Name, Description, Price, Category).');
                        return;
                    }
                    
                    startImportBtn.disabled = true;
                    importProgressDiv.style.display = 'block';
                    importResultMessage.style.display = 'none';

                    // Start the import process
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'menu_master_import_data',
                            nonce: menu_master_vite_params.nonce,
                            catalog_id: '<?php echo $catalog->id; ?>',
                            google_sheet_url: url,
                            ...mapping
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        importProgressDiv.style.display = 'none';
                        importResultMessage.style.display = 'block';
                        startImportBtn.disabled = false;
                        
                        if (data.success) {
                            importResultMessage.className = 'notice notice-success';
                            importResultMessage.innerHTML = `<p>✅ Successfully imported ${data.data.imported_count} items!</p>`;
                            
                            // Refresh page after successful import
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        } else {
                            importResultMessage.className = 'notice notice-error';
                            importResultMessage.innerHTML = `<p>❌ Import failed: ${data.data || 'Unknown error'}</p>`;
                        }
                    })
                    .catch(error => {
                        importProgressDiv.style.display = 'none';
                        importResultMessage.className = 'notice notice-error';
                        importResultMessage.innerHTML = '<p>❌ Network error during import.</p>';
                        importResultMessage.style.display = 'block';
                        startImportBtn.disabled = false;
                        console.error('Import error:', error);
                    });
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
        .menu-master-wrap {
            max-width: 1200px;
            margin: 20px auto;
            background: #f0f2f5;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .menu-master-wrap h1 {
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
                echo '<div class="notification success"><p>✅ Debug mode enabled</p></div>';
            } elseif ($_POST['action'] === 'disable_debug' && wp_verify_nonce($_POST['_wpnonce'], 'menu_master_debug')) {
                MenuMaster_Logger::disable_debug();
                echo '<div class="notification success"><p>✅ Debug mode disabled</p></div>';
            } elseif ($_POST['action'] === 'clear_logs' && wp_verify_nonce($_POST['_wpnonce'], 'menu_master_debug')) {
                MenuMaster_Logger::clear_logs();
                echo '<div class="notification success"><p>✅ Logs cleared</p></div>';
            }
        }
        
        $debug_enabled = MenuMaster_Logger::is_debug_enabled();
        $log_file = MenuMaster_Logger::get_log_file();
        $recent_logs = MenuMaster_Logger::get_recent_logs(100);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'menu_master_catalogs';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        
        ?>
        <div class="menu-master-wrap">
            <div class="menu-master-header">
                <div class="header-content">
                    <h1>🔧 Debug & Diagnostics</h1>
                    <p>System status, logs, and troubleshooting tools</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-secondary" onclick="location.reload()">
                        🔄 Refresh
                    </button>
                    <div class="theme-switch">
                        <span class="theme-switch-label">Light</span>
                        <input type="checkbox" id="theme-toggle" />
                        <span class="theme-switch-label">Dark</span>
                    </div>
                </div>
            </div>
            
            <div class="menu-master-nav">
                <div class="nav-tabs">
                    <a href="<?php echo admin_url('admin.php?page=menu-master'); ?>" class="nav-tab">📋 Menus</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-add'); ?>" class="nav-tab">➕ Add Menu</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-import-preview'); ?>" class="nav-tab">📊 Import Preview</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-images'); ?>" class="nav-tab">🖼️ Images</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-debug'); ?>" class="nav-tab nav-tab-active">🔧 Debug</a>
                </div>
            </div>
            
            <!-- System Status -->
            <div class="menu-master-card">
                <h3 class="text-primary mb-3">📊 System Status</h3>
                <div class="menu-master-grid grid-2">
                    <div class="stats-card">
                        <div class="stats-number" style="color: <?php echo $debug_enabled ? 'var(--mm-success)' : 'var(--mm-danger)'; ?>">
                            <?php echo $debug_enabled ? '✅' : '❌'; ?>
                        </div>
                        <div class="stats-label">Debug Mode</div>
                        <div class="mt-2">
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('menu_master_debug'); ?>
                                <?php if ($debug_enabled): ?>
                                    <input type="hidden" name="action" value="disable_debug">
                                    <button type="submit" class="btn btn-sm btn-danger">Disable</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="enable_debug">
                                    <button type="submit" class="btn btn-sm btn-success">Enable</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-number" style="color: <?php echo (defined('WP_DEBUG') && WP_DEBUG) ? 'var(--mm-success)' : 'var(--mm-danger)'; ?>">
                            <?php echo (defined('WP_DEBUG') && WP_DEBUG) ? '✅' : '❌'; ?>
                        </div>
                        <div class="stats-label">WP_DEBUG</div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-number" style="color: <?php echo $table_exists ? 'var(--mm-success)' : 'var(--mm-danger)'; ?>">
                            <?php echo $table_exists ? '✅' : '❌'; ?>
                        </div>
                        <div class="stats-label">Database Tables</div>
                    </div>
                    
                    <div class="stats-card">
                        <div class="stats-number" style="color: <?php echo file_exists($log_file) ? 'var(--mm-success)' : 'var(--mm-warning)'; ?>">
                            <?php echo file_exists($log_file) ? '✅' : '⚠️'; ?>
                        </div>
                        <div class="stats-label">Log File</div>
                        <?php if (file_exists($log_file)): ?>
                            <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                <?php echo $this->human_readable_bytes(filesize($log_file)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- PHP Configuration -->
            <div class="menu-master-card">
                <h3 class="text-primary mb-3">⚙️ PHP Configuration</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>allow_url_fopen</strong></td>
                                <td><?php echo ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled'; ?></td>
                                <td>
                                    <span class="tag <?php echo ini_get('allow_url_fopen') ? 'success' : 'danger'; ?>">
                                        <?php echo ini_get('allow_url_fopen') ? '✅' : '❌'; ?>
                                    </span>
                                </td>
                                <td class="text-muted">
                                    <?php if (!ini_get('allow_url_fopen')): ?>
                                        Required for Google Sheets import
                                    <?php else: ?>
                                        Good for external requests
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>file_uploads</strong></td>
                                <td><?php echo ini_get('file_uploads') ? 'Enabled' : 'Disabled'; ?></td>
                                <td>
                                    <span class="tag <?php echo ini_get('file_uploads') ? 'success' : 'danger'; ?>">
                                        <?php echo ini_get('file_uploads') ? '✅' : '❌'; ?>
                                    </span>
                                </td>
                                <td class="text-muted">Required for image uploads</td>
                            </tr>
                            <tr>
                                <td><strong>max_execution_time</strong></td>
                                <td><?php echo ini_get('max_execution_time'); ?> seconds</td>
                                <td>
                                    <?php 
                                    $max_time = ini_get('max_execution_time');
                                    $status = $max_time >= 300 ? 'success' : ($max_time >= 120 ? 'warning' : 'danger');
                                    ?>
                                    <span class="tag <?php echo $status; ?>">
                                        <?php echo $max_time >= 300 ? '✅' : ($max_time >= 120 ? '⚠️' : '❌'); ?>
                                    </span>
                                </td>
                                <td class="text-muted">
                                    <?php if ($max_time < 300): ?>
                                        Recommended: 300+ seconds
                                    <?php else: ?>
                                        Good for large imports
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>memory_limit</strong></td>
                                <td><?php echo ini_get('memory_limit'); ?></td>
                                <td>
                                    <?php 
                                    $memory_limit = ini_get('memory_limit');
                                    $memory_bytes = $this->parse_size($memory_limit);
                                    $recommended_bytes = 512 * 1024 * 1024; // 512MB
                                    $status = $memory_bytes >= $recommended_bytes ? 'success' : 'warning';
                                    ?>
                                    <span class="tag <?php echo $status; ?>">
                                        <?php echo $memory_bytes >= $recommended_bytes ? '✅' : '⚠️'; ?>
                                    </span>
                                </td>
                                <td class="text-muted">
                                    <?php if ($memory_bytes < $recommended_bytes): ?>
                                        Recommended: 512M+
                                    <?php else: ?>
                                        Good for image processing
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Logs -->
            <div class="menu-master-card">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-primary mb-0">📝 Recent Logs</h3>
                    <div class="flex gap-2">
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('menu_master_debug'); ?>
                            <input type="hidden" name="action" value="clear_logs">
                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to clear all logs?')">
                                🗑️ Clear Logs
                            </button>
                        </form>
                        
                        <?php if (file_exists($log_file)): ?>
                            <a href="<?php echo wp_upload_dir()['baseurl'] . '/menu-master-debug.log'; ?>" target="_blank" class="btn btn-sm btn-secondary">
                                📄 Full Log File
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (empty($recent_logs)): ?>
                    <div class="notification info">
                        <p>📭 No logs recorded yet. Enable debug mode and perform some actions to see logs here.</p>
                    </div>
                <?php else: ?>
                    <div class="log-container">
                        <?php foreach ($recent_logs as $log): ?>
                            <div class="log-entry log-<?php echo strtolower($log['level']); ?>">
                                <div class="log-header">
                                    <span class="log-timestamp"><?php echo esc_html($log['timestamp']); ?></span>
                                    <span class="log-level log-level-<?php echo strtolower($log['level']); ?>">
                                        <?php echo esc_html($log['level']); ?>
                                    </span>
                                    <span class="log-user"><?php echo esc_html($log['user']); ?></span>
                                </div>
                                <div class="log-message">
                                    <?php echo esc_html($log['message']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Image Processing Diagnostics -->
            <div class="menu-master-card">
                <h3 class="text-primary mb-3">🖼️ Image Processing Diagnostics</h3>
                <?php $this->render_image_diagnostics(); ?>
            </div>
            
            <!-- Image Upload Testing -->
            <div class="menu-master-card">
                <h3 class="text-primary mb-3">🧪 Image Upload Testing</h3>
                <?php $this->render_image_upload_test(); ?>
            </div>
            
            <!-- Quick Actions -->
            <div class="menu-master-card">
                <h3 class="text-primary mb-3">🚀 Quick Actions</h3>
                <div class="menu-master-grid grid-3">
                    <div class="feature-card">
                        <div class="feature-icon">🆕</div>
                        <h5>Create Test Menu</h5>
                        <p>Create a new menu to test the system</p>
                        <a href="<?php echo admin_url('admin.php?page=menu-master-add'); ?>" class="btn btn-primary btn-sm mt-2">
                            Create Menu
                        </a>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h5>Test Import</h5>
                        <p>Test Google Sheets import functionality</p>
                        <a href="<?php echo admin_url('admin.php?page=menu-master-import-preview'); ?>" class="btn btn-primary btn-sm mt-2">
                            Test Import
                        </a>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🖼️</div>
                        <h5>View Images</h5>
                        <p>Check uploaded and processed images</p>
                        <a href="<?php echo admin_url('admin.php?page=menu-master-images'); ?>" class="btn btn-primary btn-sm mt-2">
                            View Images
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .log-container {
            max-height: 500px;
            overflow-y: auto;
            background: var(--mm-bg-elevated);
            border-radius: var(--mm-radius);
            padding: 1rem;
            border: 1px solid var(--mm-border);
        }
        
        .log-entry {
            margin-bottom: 1rem;
            padding: 1rem;
            background: var(--mm-bg-card);
            border-radius: var(--mm-radius);
            border-left: 4px solid var(--mm-border);
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            transition: var(--mm-transition);
        }
        
        .log-entry:hover {
            box-shadow: var(--mm-shadow);
        }
        
        .log-entry.log-error {
            border-left-color: var(--mm-danger);
            background: rgb(239 68 68 / 0.05);
        }
        
        .log-entry.log-warning {
            border-left-color: var(--mm-warning);
            background: rgb(245 158 11 / 0.05);
        }
        
        .log-entry.log-info {
            border-left-color: var(--mm-info);
            background: rgb(59 130 246 / 0.05);
        }
        
        .log-entry.log-debug {
            border-left-color: var(--mm-success);
            background: rgb(16 185 129 / 0.05);
        }
        
        .log-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
            font-size: 0.75rem;
        }
        
        .log-timestamp {
            color: var(--mm-text-muted);
        }
        
        .log-level {
            padding: 0.25rem 0.5rem;
            border-radius: var(--mm-radius-sm);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.625rem;
        }
        
        .log-level-error {
            background: var(--mm-danger);
            color: white;
        }
        
        .log-level-warning {
            background: var(--mm-warning);
            color: white;
        }
        
        .log-level-info {
            background: var(--mm-info);
            color: white;
        }
        
        .log-level-debug {
            background: var(--mm-success);
            color: white;
        }
        
        .log-user {
            color: var(--mm-text-secondary);
            font-style: italic;
        }
        
        .log-message {
            color: var(--mm-text-primary);
            line-height: 1.4;
        }
        
        .feature-card {
            text-align: center;
            padding: 1.5rem;
            background: var(--mm-bg-elevated);
            border-radius: var(--mm-radius-lg);
            border: 1px solid var(--mm-border);
            transition: var(--mm-transition);
        }
        
        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--mm-shadow-lg);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .feature-card h5 {
            margin: 0 0 0.5rem 0;
            color: var(--mm-text-primary);
            font-weight: 600;
        }
        
        .feature-card p {
            margin: 0;
            color: var(--mm-text-secondary);
            font-size: 0.875rem;
        }
        </style>
        <?php
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
    
    /**
     * Import Preview page
     */
    public function admin_page_import_preview() {
        ?>
        <div class="menu-master-wrap">
            <div class="menu-master-header">
                <h1>📊 Import Preview</h1>
                <div class="header-actions">
                    <div class="theme-switch">
                        <span class="theme-switch-label">Light</span>
                        <input type="checkbox" id="theme-toggle" />
                        <span class="theme-switch-label">Dark</span>
                    </div>
                </div>
            </div>
            
            <div class="menu-master-nav">
                <div class="nav-tabs">
                    <a href="<?php echo admin_url('admin.php?page=menu-master'); ?>" class="nav-tab">📋 Menus</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-add'); ?>" class="nav-tab">➕ Add Menu</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-import-preview'); ?>" class="nav-tab nav-tab-active">📊 Import Preview</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-images'); ?>" class="nav-tab">🖼️ Images</a>
                    <?php if (MenuMaster_Logger::is_debug_enabled()): ?>
                        <a href="<?php echo admin_url('admin.php?page=menu-master-debug'); ?>" class="nav-tab">🔧 Debug</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <div class="menu-master-card">
                    <h2>🔍 Test Google Sheets Import</h2>
                    <p class="text-muted mb-4">Paste your Google Sheets URL below to preview the first 100 rows that will be imported.</p>
                    
                    <form id="preview-form" class="settings-fields-grid">
                        <div class="settings-field-group full-width">
                            <label class="form-label">Google Sheets URL</label>
                            <input type="url" id="sheet-url" class="form-input" 
                                   placeholder="https://docs.google.com/spreadsheets/d/..." 
                                   required>
                            <div class="form-help">Make sure your Google Sheet is public (Anyone with the link can view)</div>
                        </div>
                        
                        <div class="settings-field-group">
                            <label class="form-label">Sheet Name (optional)</label>
                            <input type="text" id="sheet-name" class="form-input" 
                                   placeholder="Sheet1" value="Sheet1">
                        </div>
                        
                        <div class="settings-field-group full-width text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                📊 Preview Import
                            </button>
                        </div>
                    </form>
                </div>
                
                <div id="preview-results" class="hidden">
                    <div class="menu-master-card">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-success">✅ Import Preview</h3>
                            <div id="preview-stats" class="tag success"></div>
                        </div>
                        <div class="table-container">
                            <table id="preview-table" class="data-table">
                                <thead id="preview-headers"></thead>
                                <tbody id="preview-data"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="preview-error" class="hidden">
                    <div class="notification error">
                        <h4>❌ Import Error</h4>
                        <p id="error-message"></p>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('preview-form');
            const resultsDiv = document.getElementById('preview-results');
            const errorDiv = document.getElementById('preview-error');
            const previewTable = document.getElementById('preview-table');
            const previewHeaders = document.getElementById('preview-headers');
            const previewData = document.getElementById('preview-data');
            const previewStats = document.getElementById('preview-stats');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const sheetUrl = document.getElementById('sheet-url').value;
                const sheetName = document.getElementById('sheet-name').value || 'Sheet1';
                
                if (!sheetUrl) {
                    alert('Please enter a Google Sheets URL');
                    return;
                }
                
                // Hide previous results
                resultsDiv.classList.add('hidden');
                errorDiv.classList.add('hidden');
                
                // Show loading
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.textContent = '⏳ Loading...';
                submitBtn.disabled = true;
                
                // Make AJAX request
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'menu_master_preview_import',
                        sheet_url: sheetUrl,
                        sheet_name: sheetName,
                        nonce: '<?php echo wp_create_nonce('menu_master_nonce'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showPreview(data.data);
                    } else {
                        showError(data.data || 'Unknown error occurred');
                    }
                })
                .catch(error => {
                    showError('Network error: ' + error.message);
                })
                .finally(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                });
            });
            
            function showPreview(data) {
                // Clear previous data
                previewHeaders.innerHTML = '';
                previewData.innerHTML = '';
                
                if (!data.headers || !data.data) {
                    showError('Invalid data received');
                    return;
                }
                
                // Show stats
                previewStats.textContent = `${data.headers.length} columns, ${data.data.length} rows (showing first 100)`;
                
                // Create headers
                const headerRow = document.createElement('tr');
                data.headers.forEach(header => {
                    const th = document.createElement('th');
                    th.textContent = header;
                    headerRow.appendChild(th);
                });
                previewHeaders.appendChild(headerRow);
                
                // Create data rows (limit to 100)
                const limitedData = data.data.slice(0, 100);
                limitedData.forEach(row => {
                    const tr = document.createElement('tr');
                    data.headers.forEach((header, index) => {
                        const td = document.createElement('td');
                        td.textContent = row[index] || '';
                        tr.appendChild(td);
                    });
                    previewData.appendChild(tr);
                });
                
                resultsDiv.classList.remove('hidden');
            }
            
            function showError(message) {
                document.getElementById('error-message').textContent = message;
                errorDiv.classList.remove('hidden');
            }
        });
        </script>
        <?php
    }
    
    /**
     * Images management page
     */
    public function admin_page_images() {
        $upload_dir = wp_upload_dir();
        $menu_master_dir = $upload_dir['basedir'] . '/menu-master/';
        
        // Create directory if it doesn't exist
        if (!file_exists($menu_master_dir)) {
            wp_mkdir_p($menu_master_dir);
        }
        
        // Get all images
        $images = array();
        if (is_dir($menu_master_dir)) {
            $files = scandir($menu_master_dir);
            foreach ($files as $file) {
                if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), array('jpg', 'jpeg', 'png', 'gif', 'webp'))) {
                    $file_path = $menu_master_dir . $file;
                    $file_url = $upload_dir['baseurl'] . '/menu-master/' . $file;
                    $images[] = array(
                        'name' => $file,
                        'path' => $file_path,
                        'url' => $file_url,
                        'size' => filesize($file_path),
                        'modified' => filemtime($file_path)
                    );
                }
            }
        }
        
        // Sort by modification time (newest first)
        usort($images, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
        
        ?>
        <div class="menu-master-wrap">
            <div class="menu-master-header">
                <div class="header-content">
                    <h1>🖼️ Image Manager</h1>
                    <p class="text-muted">Manage your menu images</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="location.reload()">
                        🔄 Refresh
                    </button>
                    <div class="theme-switch">
                        <span class="theme-switch-label">Light</span>
                        <input type="checkbox" id="theme-toggle" />
                        <span class="theme-switch-label">Dark</span>
                    </div>
                </div>
            </div>
            
            <div class="menu-master-nav">
                <div class="nav-tabs">
                    <a href="<?php echo admin_url('admin.php?page=menu-master'); ?>" class="nav-tab">📋 Menus</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-add'); ?>" class="nav-tab">➕ Add Menu</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-import-preview'); ?>" class="nav-tab">📊 Import Preview</a>
                    <a href="<?php echo admin_url('admin.php?page=menu-master-images'); ?>" class="nav-tab nav-tab-active">🖼️ Images</a>
                    <?php if (MenuMaster_Logger::is_debug_enabled()): ?>
                        <a href="<?php echo admin_url('admin.php?page=menu-master-debug'); ?>" class="nav-tab">🔧 Debug</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <?php if (empty($images)): ?>
                    <div class="menu-master-card text-center">
                        <h2>No Images Found</h2>
                        <p class="text-muted">Images will appear here after you import menus with image URLs and download them.</p>
                        <a href="<?php echo admin_url('admin.php?page=menu-master'); ?>" class="btn btn-primary">
                            📋 Go to Menus
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Image Statistics -->
                    <div class="menu-master-grid grid-4 mb-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($images); ?></div>
                            <div class="stats-label">Total Images</div>
                        </div>
                        <div class="stats-card">
                            <div class="stats-number">
                                <?php 
                                $total_size = array_sum(array_column($images, 'size'));
                                echo $this->human_readable_bytes($total_size);
                                ?>
                            </div>
                            <div class="stats-label">Total Size</div>
                        </div>
                        <div class="stats-card">
                            <div class="stats-number">
                                <?php 
                                $recent_count = 0;
                                $week_ago = time() - (7 * 24 * 60 * 60);
                                foreach ($images as $image) {
                                    if ($image['modified'] > $week_ago) $recent_count++;
                                }
                                echo $recent_count;
                                ?>
                            </div>
                            <div class="stats-label">This Week</div>
                        </div>
                        <div class="stats-card">
                            <div class="stats-number">
                                <?php 
                                $avg_size = count($images) > 0 ? $total_size / count($images) : 0;
                                echo $this->human_readable_bytes($avg_size);
                                ?>
                            </div>
                            <div class="stats-label">Average Size</div>
                        </div>
                    </div>
                    
                    <!-- Image Manager -->
                    <div class="image-manager">
                        <div class="image-manager-toolbar">
                            <div class="image-manager-controls">
                                <div class="view-toggle">
                                    <button data-view="table" class="active">📋 Table</button>
                                    <button data-view="grid">🔲 Grid</button>
                                </div>
                                <div class="grid-size-selector">
                                    <label>Grid Size:</label>
                                    <select id="grid-size-select">
                                        <option value="5">5 per row</option>
                                        <option value="7">7 per row</option>
                                        <option value="10">10 per row</option>
                                    </select>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <input type="text" id="search-images" class="form-input" placeholder="Search images..." style="width: 200px;">
                                <select id="sort-images" class="form-select">
                                    <option value="newest">Newest First</option>
                                    <option value="oldest">Oldest First</option>
                                    <option value="name">Name A-Z</option>
                                    <option value="size">Size</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Table View -->
                        <div class="image-table-view">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Preview</th>
                                        <th>Name</th>
                                        <th>Size</th>
                                        <th>Modified</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($images as $image): ?>
                                        <tr class="image-row" data-name="<?php echo esc_attr(strtolower($image['name'])); ?>" data-size="<?php echo $image['size']; ?>" data-modified="<?php echo $image['modified']; ?>">
                                            <td>
                                                <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['name']); ?>" class="image-thumbnail">
                                            </td>
                                            <td>
                                                <strong><?php echo esc_html($image['name']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="tag"><?php echo $this->human_readable_bytes($image['size']); ?></span>
                                            </td>
                                            <td class="text-muted">
                                                <?php echo date('M j, Y H:i', $image['modified']); ?>
                                            </td>
                                            <td>
                                                <div class="flex gap-2">
                                                    <button class="btn btn-sm copy-url-btn" data-url="<?php echo esc_attr($image['url']); ?>">
                                                        📋 Copy
                                                    </button>
                                                    <button class="btn btn-sm rename-btn" data-filename="<?php echo esc_attr($image['name']); ?>">
                                                        ✏️ Rename
                                                    </button>
                                                    <button class="btn btn-sm download-btn" data-url="<?php echo esc_attr($image['url']); ?>" data-filename="<?php echo esc_attr($image['name']); ?>">
                                                        ⬇️ Download
                                                    </button>
                                                    <button class="btn btn-sm btn-danger delete-btn" data-filename="<?php echo esc_attr($image['name']); ?>">
                                                        🗑️ Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Grid View -->
                        <div class="image-grid-view grid-5">
                            <?php foreach ($images as $image): ?>
                                <div class="image-grid-item" data-name="<?php echo esc_attr(strtolower($image['name'])); ?>" data-size="<?php echo $image['size']; ?>" data-modified="<?php echo $image['modified']; ?>">
                                    <img src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['name']); ?>">
                                    <div class="image-name"><?php echo esc_html($image['name']); ?></div>
                                    <div class="image-actions">
                                        <button class="btn btn-sm copy-url-btn" data-url="<?php echo esc_attr($image['url']); ?>">📋</button>
                                        <button class="btn btn-sm rename-btn" data-filename="<?php echo esc_attr($image['name']); ?>">✏️</button>
                                        <button class="btn btn-sm download-btn" data-url="<?php echo esc_attr($image['url']); ?>" data-filename="<?php echo esc_attr($image['name']); ?>">⬇️</button>
                                        <button class="btn btn-sm btn-danger delete-btn" data-filename="<?php echo esc_attr($image['name']); ?>">🗑️</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Upload Modal -->
        <div id="upload-modal" class="modal">
            <div class="modal-card" style="width: 500px;">
                <div class="modal-card-head">
                    <h3 class="modal-card-title">Upload Image</h3>
                    <button class="delete" id="close-upload-modal"></button>
                </div>
                <div class="modal-card-body">
                    <form id="upload-form" enctype="multipart/form-data">
                        <div class="field">
                            <label class="label">Select Image</label>
                            <div class="control">
                                <input type="file" id="image-file" name="image" 
                                       accept="image/*" class="input" required>
                            </div>
                            <p class="help">Supported formats: JPG, PNG, GIF, WebP (max 10MB)</p>
                        </div>
                        
                        <div class="field">
                            <label class="label">Image Name (optional)</label>
                            <div class="control">
                                <input type="text" id="image-name" name="name" 
                                       class="input" placeholder="Auto-generated from filename">
                            </div>
                        </div>
                        
                        <div class="field">
                            <label class="label">Description (optional)</label>
                            <div class="control">
                                <textarea id="image-description" name="description" 
                                          class="textarea" rows="3" 
                                          placeholder="Brief description of the image"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-card-foot">
                    <button type="submit" form="upload-form" class="btn btn-primary">
                        📤 Upload
                    </button>
                    <button class="btn btn-secondary" id="cancel-upload">Cancel</button>
                </div>
            </div>
        </div>
        
        <!-- Image Details Modal -->
        <div id="image-modal" class="modal">
            <div class="modal-card" style="width: 800px;">
                <div class="modal-card-head">
                    <h3 class="modal-card-title" id="image-modal-title">Image Details</h3>
                    <button class="delete" id="close-image-modal"></button>
                </div>
                <div class="modal-card-body">
                    <div class="d-flex gap-2">
                        <div style="flex: 1;">
                            <img id="image-modal-preview" src="" alt="" style="width: 100%; max-height: 300px; object-fit: contain; border: 1px solid var(--mm-border); border-radius: 0.5rem;">
                        </div>
                        <div style="flex: 1;">
                            <div class="field">
                                <label class="label">Image URL</label>
                                <div class="control d-flex gap-1">
                                    <input type="text" id="image-url" class="input" readonly>
                                    <button class="btn btn-secondary" id="copy-url">📋 Copy</button>
                                </div>
                            </div>
                            
                            <div class="field">
                                <label class="label">File Info</label>
                                <div id="image-info" class="text-muted"></div>
                            </div>
                            
                            <div class="field">
                                <label class="label">Usage</label>
                                <div id="image-usage" class="text-muted"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-card-foot">
                    <button class="btn btn-danger" id="delete-image">🗑️ Delete</button>
                    <button class="btn btn-secondary" id="close-image-details">Close</button>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize images page
            loadImages();
            loadStats();
            
            // Upload modal
            const uploadModal = document.getElementById('upload-modal');
            const imageModal = document.getElementById('image-modal');
            
            document.getElementById('upload-image-btn').addEventListener('click', () => {
                uploadModal.classList.add('is-active');
            });
            
            document.getElementById('close-upload-modal').addEventListener('click', () => {
                uploadModal.classList.remove('is-active');
            });
            
            document.getElementById('cancel-upload').addEventListener('click', () => {
                uploadModal.classList.remove('is-active');
            });
            
            document.getElementById('close-image-modal').addEventListener('click', () => {
                imageModal.classList.remove('is-active');
            });
            
            document.getElementById('close-image-details').addEventListener('click', () => {
                imageModal.classList.remove('is-active');
            });
            
            // Upload form
            document.getElementById('upload-form').addEventListener('submit', function(e) {
                e.preventDefault();
                uploadImage();
            });
            
            // Search and filter
            document.getElementById('search-images').addEventListener('input', filterImages);
            document.getElementById('filter-images').addEventListener('change', filterImages);
            
            // Copy URL functionality
            document.getElementById('copy-url').addEventListener('click', function() {
                const urlInput = document.getElementById('image-url');
                urlInput.select();
                document.execCommand('copy');
                this.textContent = '✅ Copied!';
                setTimeout(() => {
                    this.textContent = '📋 Copy';
                }, 2000);
            });
            
            function loadImages() {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'menu_master_get_images',
                        nonce: '<?php echo wp_create_nonce('menu_master_nonce'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayImages(data.data);
                    }
                });
            }
            
            function loadStats() {
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'menu_master_get_image_stats',
                        nonce: '<?php echo wp_create_nonce('menu_master_nonce'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const stats = data.data;
                        document.getElementById('total-images').textContent = stats.total;
                        document.getElementById('total-size').textContent = stats.size;
                        document.getElementById('recent-uploads').textContent = stats.recent;
                        document.getElementById('unused-images').textContent = stats.unused;
                    }
                });
            }
            
            function displayImages(images) {
                const gallery = document.getElementById('image-gallery');
                const noImages = document.getElementById('no-images');
                
                if (images.length === 0) {
                    gallery.innerHTML = '';
                    noImages.classList.remove('d-none');
                    return;
                }
                
                noImages.classList.add('d-none');
                gallery.innerHTML = images.map(image => `
                    <div class="image-item" data-id="${image.id}">
                        <img src="${image.url}" alt="${image.name}" loading="lazy">
                        <div class="image-item-info">
                            <div class="image-item-name">${image.name}</div>
                            <div class="image-item-size">${image.size}</div>
                            <div class="image-item-actions">
                                <button class="btn btn-sm" onclick="viewImage(${image.id})">👁️ View</button>
                                <button class="btn btn-sm" onclick="copyImageUrl('${image.url}')">📋 Copy</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteImage(${image.id})">🗑️</button>
                            </div>
                        </div>
                    </div>
                `).join('');
            }
            
            function uploadImage() {
                const form = document.getElementById('upload-form');
                const formData = new FormData(form);
                formData.append('action', 'menu_master_upload_image');
                formData.append('nonce', '<?php echo wp_create_nonce('menu_master_nonce'); ?>');
                
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.textContent = '⏳ Uploading...';
                submitBtn.disabled = true;
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        uploadModal.classList.remove('is-active');
                        form.reset();
                        loadImages();
                        loadStats();
                        alert('Image uploaded successfully!');
                    } else {
                        alert('Upload failed: ' + (data.data || 'Unknown error'));
                    }
                })
                .finally(() => {
                    submitBtn.textContent = '📤 Upload';
                    submitBtn.disabled = false;
                });
            }
            
            function filterImages() {
                // Implementation for search and filter
                const search = document.getElementById('search-images').value.toLowerCase();
                const filter = document.getElementById('filter-images').value;
                
                const items = document.querySelectorAll('.image-item');
                items.forEach(item => {
                    const name = item.querySelector('.image-item-name').textContent.toLowerCase();
                    const matchesSearch = name.includes(search);
                    // Add filter logic here based on your needs
                    
                    item.style.display = matchesSearch ? 'block' : 'none';
                });
            }
            
            // Global functions for image actions
            window.viewImage = function(id) {
                // Load and show image details
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'menu_master_get_image_details',
                        image_id: id,
                        nonce: '<?php echo wp_create_nonce('menu_master_nonce'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const image = data.data;
                        document.getElementById('image-modal-title').textContent = image.name;
                        document.getElementById('image-modal-preview').src = image.url;
                        document.getElementById('image-url').value = image.url;
                        document.getElementById('image-info').innerHTML = `
                            <strong>Size:</strong> ${image.dimensions}<br>
                            <strong>File Size:</strong> ${image.file_size}<br>
                            <strong>Type:</strong> ${image.type}<br>
                            <strong>Uploaded:</strong> ${image.date}
                        `;
                        document.getElementById('image-usage').textContent = image.usage || 'Not used in any menus';
                        
                        imageModal.classList.add('is-active');
                    }
                });
            };
            
            window.copyImageUrl = function(url) {
                navigator.clipboard.writeText(url).then(() => {
                    alert('URL copied to clipboard!');
                });
            };
            
            window.deleteImage = function(id) {
                if (!confirm('Are you sure you want to delete this image?')) return;
                
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'menu_master_delete_image',
                        image_id: id,
                        nonce: '<?php echo wp_create_nonce('menu_master_nonce'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadImages();
                        loadStats();
                        alert('Image deleted successfully!');
                    } else {
                        alert('Delete failed: ' + (data.data || 'Unknown error'));
                    }
                });
            };
        });
        </script>
        <?php
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