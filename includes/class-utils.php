<?php
/**
 * Utility functions for Catalog Master
 */
class CatalogMaster_Utils {
    
    /**
     * Calculate similarity between two strings (using Levenshtein distance)
     */
    public static function string_similarity($str1, $str2) {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if ($str1 === $str2) return 100;
        if (strlen($str1) === 0 || strlen($str2) === 0) return 0;
        
        $distance = levenshtein($str1, $str2);
        $maxLength = max(strlen($str1), strlen($str2));
        
        return (1 - $distance / $maxLength) * 100;
    }
    
    /**
     * Suggest column mapping based on similarity
     */
    public static function suggest_mapping($sheet_columns, $catalog_columns, $similarity_threshold = 70) {
        $suggestions = array();
        $used_catalog_columns = array();
        
        foreach ($sheet_columns as $sheet_column) {
            $best_match = null;
            $best_similarity = 0;
            
            foreach ($catalog_columns as $catalog_column) {
                if (in_array($catalog_column, $used_catalog_columns)) continue;
                
                $similarity = self::string_similarity($sheet_column, $catalog_column);
                if ($similarity > $best_similarity && $similarity >= $similarity_threshold) {
                    $best_similarity = $similarity;
                    $best_match = $catalog_column;
                }
            }
            
            if ($best_match) {
                $suggestions[$sheet_column] = $best_match;
                $used_catalog_columns[] = $best_match;
            }
        }
        
        return $suggestions;
    }
}
