<?php
/**
 * パフォーマンスモニタークラス
 * @package JGrants_Auto_Poster_Enhanced_Pro
 */

if (!defined('ABSPATH')) exit;

class JGrants_Enhanced_Pro_Performance_Monitor {
    
    /**
     * オペレーションログ記録
     */
    public function log_operation($details) {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_performance_logs';
        
        return $wpdb->insert($table, [
            'operation' => $details['operation'] ?? 'unknown',
            'execution_time' => $details['execution_time'] ?? 0,
            'memory_used' => $details['memory_used'] ?? memory_get_usage(true),
            'success' => $details['success'] ?? true,
            'details' => wp_json_encode($details, JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * パフォーマンス統計取得
     */
    public function get_stats($period = '7days') {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_performance_logs';
        
        $date_condition = $this->get_date_condition($period);
        
        return $wpdb->get_results(
            "SELECT operation,
                    COUNT(*) as count,
                    AVG(execution_time) as avg_time,
                    MAX(execution_time) as max_time,
                    AVG(memory_used) as avg_memory
             FROM $table
             WHERE created_at >= '$date_condition'
             GROUP BY operation"
        );
    }
    
    /**
     * 日付条件取得
     */
    private function get_date_condition($period) {
        switch ($period) {
            case '24hours':
                return date('Y-m-d H:i:s', strtotime('-24 hours'));
            case '7days':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case '30days':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            default:
                return date('Y-m-d H:i:s', strtotime('-7 days'));
        }
    }
    
    /**
     * クリーンアップ
     */
    public function cleanup_old_logs($days = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_performance_logs';
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE created_at < %s",
                $cutoff_date
            )
        );
    }
}
