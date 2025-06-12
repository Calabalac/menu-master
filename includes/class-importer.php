<?php
/**
 * Data import class for Menu Master
 */

if (!defined('ABSPATH')) {
    exit;
}

class MenuMaster_Importer {
    
    /**
     * Fetch CSV data from Google Sheets URL
     */
    public function fetch_csv_data($google_sheet_url) {
        MenuMaster_Logger::info('Fetching CSV data from Google Sheets', [
            'url' => $google_sheet_url
        ]);
        
        $result = MenuMaster_GoogleSheets::import_from_url($google_sheet_url);
        
        if (isset($result['error'])) {
            throw new Exception($result['error']);
        }
        
        if (!isset($result['data']) || !isset($result['headers'])) {
            throw new Exception('Invalid response from Google Sheets');
        }
        
        // Combine headers and data for return
        $csv_data = array_merge([$result['headers']], $result['data']);
        
        MenuMaster_Logger::info('CSV data fetched successfully', [
            'headers_count' => count($result['headers']),
            'rows_count' => count($result['data'])
        ]);
        
        return $csv_data;
    }
    
    /**
     * Import data from Google Sheets with column mapping
     */
    public function import_from_google_sheets($catalog_id, $google_sheet_url, $mapping, $download_images = false) {
        MenuMaster_Logger::info('Starting import from Google Sheets', [
            'catalog_id' => $catalog_id,
            'url' => $google_sheet_url,
            'mapping' => $mapping,
            'download_images' => $download_images
        ]);
        
        try {
            // Get data from Google Sheets
            $result = MenuMaster_GoogleSheets::import_from_url($google_sheet_url);
            
            if (isset($result['error'])) {
                return [
                    'success' => false,
                    'message' => $result['error']
                ];
            }
            
            $headers = $result['headers'];
            $data_rows = $result['data'];
            
            if (empty($data_rows)) {
                return [
                    'success' => false,
                    'message' => 'No data rows found in the sheet'
                ];
            }
            
            // Clear existing items for this catalog
            global $wpdb;
            $items_table = $wpdb->prefix . 'menu_master_items';
            $wpdb->delete($items_table, ['catalog_id' => $catalog_id]);
            
            $imported_count = 0;
            $errors = [];
            
            foreach ($data_rows as $row_index => $row) {
                try {
                    $item_data = $this->map_row_data($row, $headers, $mapping);
                    $item_data['catalog_id'] = $catalog_id;
                    
                    $result = $wpdb->insert($items_table, $item_data);
                    
                    if ($result === false) {
                        $errors[] = "Row " . ($row_index + 1) . ": Database error - " . $wpdb->last_error;
                    } else {
                        $imported_count++;
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Row " . ($row_index + 1) . ": " . $e->getMessage();
                }
            }
            
            MenuMaster_Logger::info('Import completed', [
                'imported_count' => $imported_count,
                'total_rows' => count($data_rows),
                'errors_count' => count($errors)
            ]);
            
            if (!empty($errors)) {
                MenuMaster_Logger::warning('Import had errors', ['errors' => $errors]);
            }
            
            return [
                'success' => true,
                'imported_count' => $imported_count,
                'total_rows' => count($data_rows),
                'errors' => $errors,
                'message' => "Imported {$imported_count} items successfully"
            ];
            
        } catch (Exception $e) {
            MenuMaster_Logger::error('Import failed with exception', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Map row data according to column mapping
     */
    private function map_row_data($row, $headers, $mapping) {
        $item_data = [];
        
        // Default values for all possible fields
        $default_fields = [
            'product_id' => '',
            'product_name' => '',
            'product_price' => 0.00,
            'product_qty' => 0,
            'product_image_url' => '',
            'product_sort_order' => 0,
            'product_description' => '',
            'category_id_1' => '',
            'category_id_2' => '',
            'category_id_3' => '',
            'category_name_1' => '',
            'category_name_2' => '',
            'category_name_3' => '',
            'category_image_1' => '',
            'category_image_2' => '',
            'category_image_3' => '',
            'category_sort_order_1' => 0,
            'category_sort_order_2' => 0,
            'category_sort_order_3' => 0
        ];
        
        // Apply mapping
        foreach ($mapping as $sheet_column => $db_column) {
            if (empty($db_column) || $db_column === 'ignore') {
                continue;
            }
            
            $column_index = array_search($sheet_column, $headers);
            if ($column_index !== false && isset($row[$column_index])) {
                $value = trim($row[$column_index]);
                
                // Type conversion based on field
                if (in_array($db_column, ['product_price'])) {
                    $value = floatval(str_replace(',', '.', $value));
                } elseif (in_array($db_column, ['product_qty', 'product_sort_order', 'category_sort_order_1', 'category_sort_order_2', 'category_sort_order_3'])) {
                    $value = intval($value);
                }
                
                $item_data[$db_column] = $value;
            }
        }
        
        // Merge with defaults to ensure all fields are present
        return array_merge($default_fields, $item_data);
    }
} 