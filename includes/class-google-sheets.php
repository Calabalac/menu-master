<?php
/**
 * Google Sheets integration class (CSV-only, public sheets)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MenuMaster_GoogleSheets {
    /**
     * Import data from public Google Sheets via CSV
     */
    public static function import_from_url($sheet_url, $sheet_name = 'Sheet1') {
        MenuMaster_Logger::info('Starting Google Sheets import (CSV)', [
            'url' => $sheet_url,
            'sheet_name' => $sheet_name
        ]);
        
        try {
            $csv_url = self::convert_to_csv_url($sheet_url);
            if (!$csv_url) {
                return ['error' => 'Invalid Google Sheets URL.'];
            }
            
            MenuMaster_Logger::info('📊 Downloading CSV from Google Sheets', [
                'original_url' => $sheet_url,
                'csv_url' => $csv_url,
                'sheet_name' => $sheet_name
            ]);
            
            $response = wp_remote_get($csv_url, [
                'timeout' => 30,
                'user-agent' => 'WordPress/MenuMaster'
            ]);
            
            if (is_wp_error($response)) {
                MenuMaster_Logger::error('❌ Failed to download CSV', [
                    'error' => $response->get_error_message()
                ]);
                return ['error' => 'Download error: ' . $response->get_error_message()];
            }
            
            $csv = wp_remote_retrieve_body($response);
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code !== 200) {
                MenuMaster_Logger::error('❌ HTTP error downloading CSV', [
                    'response_code' => $response_code
                ]);
                return ['error' => 'Error accessing sheet. Code: ' . $response_code];
            }
            
            if (empty($csv)) {
                return ['error' => 'Sheet is empty or inaccessible.'];
            }
            
            $rows = self::parse_csv($csv);
            if (empty($rows)) {
                return ['error' => 'No data found in sheet.'];
            }
            
            $headers = $rows[0];
            $data_rows = array_slice($rows, 1);
            $filtered_data = self::filter_empty_rows($data_rows);
            
            MenuMaster_Logger::info('✅ CSV parsed and filtered successfully', [
                'headers_count' => count($headers),
                'total_rows_parsed' => count($data_rows),
                'non_empty_rows' => count($filtered_data),
                'empty_rows_filtered' => count($data_rows) - count($filtered_data),
                'headers' => $headers
            ]);
            
            return [
                'success' => true,
                'headers' => $headers,
                'data' => $filtered_data
            ];
            
        } catch (Exception $e) {
            MenuMaster_Logger::error('Google Sheets import failed', [
                'error' => $e->getMessage(),
                'url' => $sheet_url
            ]);
            return ['error' => 'Exception: ' . $e->getMessage()];
        }
    }
    
    /**
     * Convert Google Sheets URL to CSV export URL
     */
    private static function convert_to_csv_url($url) {
        // Extract spreadsheet ID from various URL formats
        if (!preg_match('~/d/([a-zA-Z0-9-_]+)~', $url, $m)) {
            return false;
        }
        
        $spreadsheet_id = $m[1];
        $gid = 0; // Default to first sheet
        
        // Extract GID if present
        if (preg_match('/gid=([0-9]+)/', $url, $gm)) {
            $gid = $gm[1];
        }
        
        return "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}/export?format=csv&gid={$gid}";
    }
    
    /**
     * Parse CSV data
     */
    private static function parse_csv($csv_data) {
        $rows = [];
        $f = fopen('php://temp', 'r+');
        fwrite($f, $csv_data);
        rewind($f);
        
        while (($row = fgetcsv($f)) !== false) {
            $rows[] = $row;
        }
        
        fclose($f);
        return $rows;
    }
    
    /**
     * Filter empty rows
     */
    private static function filter_empty_rows($rows) {
        return array_filter($rows, function($row) {
            foreach ($row as $cell) {
                if (trim((string)$cell) !== '') {
                    return true;
                }
            }
            return false;
        });
    }
    
    /**
     * Get available sheets (stub for future implementation)
     */
    public static function get_available_sheets($sheet_url) {
        return ['Sheet1'];
    }
}