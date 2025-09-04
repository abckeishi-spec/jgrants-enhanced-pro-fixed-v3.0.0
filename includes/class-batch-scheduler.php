<?php
/**
 * バッチスケジューラークラス
 * @package JGrants_Auto_Poster_Enhanced_Pro
 */

if (!defined('ABSPATH')) exit;

class JGrants_Enhanced_Pro_Batch_Scheduler {
    
    /**
     * キューに追加
     */
    public function add_to_queue($post_id, $jgrants_id, $priority = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_processing_queue';
        
        return $wpdb->replace($table, [
            'jgrants_id' => $jgrants_id,
            'post_id' => $post_id,
            'status' => 'pending',
            'priority' => $priority,
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * 保留中アイテム取得
     */
    public function get_pending_items($limit = 10) {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_processing_queue';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE status = 'pending' 
             ORDER BY priority DESC, created_at ASC 
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }
    
    /**
     * ステータス更新
     */
    public function update_status($id, $status) {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_processing_queue';
        
        $update_data = ['status' => $status];
        
        if ($status === 'completed' || $status === 'failed') {
            $update_data['processed_at'] = current_time('mysql');
        }
        
        return $wpdb->update(
            $table,
            $update_data,
            ['id' => $id]
        );
    }
    
    /**
     * キューから削除
     */
    public function remove_from_queue($jgrants_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_processing_queue';
        
        return $wpdb->delete($table, ['jgrants_id' => $jgrants_id]);
    }
    
    /**
     * キュー数取得
     */
    public function get_queue_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_processing_queue';
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }
    
    /**
     * 保留中数取得
     */
    public function get_pending_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_processing_queue';
        
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table WHERE status = 'pending'"
        );
    }
    
    /**
     * 処理ステータスクリア
     */
    public function clear_processing_status() {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_processing_queue';
        
        return $wpdb->update(
            $table,
            ['status' => 'pending'],
            ['status' => 'processing']
        );
    }
}