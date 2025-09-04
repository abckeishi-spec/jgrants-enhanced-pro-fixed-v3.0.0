<?php
/**
 * キーワード戦略クラス
 * @package JGrants_Auto_Poster_Enhanced_Pro
 */

if (!defined('ABSPATH')) exit;

class JGrants_Enhanced_Pro_Keyword_Strategy {
    
    /**
     * API仕様に基づく効果的デフォルトキーワード取得
     */
    public function get_optimized_default_keywords() {
        return [
            // 基本補助金（高効果実績）
            'IT導入補助金', '事業再構築補助金', 'ものづくり補助金',
            '小規模事業者持続化補助金', '創業支援',
            
            // 利用目的ベース（use_purpose API対応）
            '設備整備', 'IT導入', '販路拡大', '人材育成',
            '研究開発', 'DX推進', '事業承継',
            
            // 業種ベース（industry API対応）
            '製造業', '情報通信業', '建設業', '小売業',
            '医療', '福祉', '教育',
            
            // 規模ベース（target_number_of_employees API対応）
            '小規模事業者', '中小企業', 'スタートアップ'
        ];
    }
    
    /**
     * アクティブキーワード取得
     */
    public function get_active_keywords() {
        $keywords_string = get_option('jgap_keywords_main', '');
        
        if (empty($keywords_string)) {
            return $this->get_optimized_default_keywords();
        }
        
        $keywords = array_filter(array_map('trim', explode("\n", $keywords_string)));
        return array_unique($keywords);
    }
    
    /**
     * 検索オプション取得
     */
    public function get_search_options() {
        return [
            'sort' => get_option('jgap_sort_order', 'created_date'),
            'order' => get_option('jgap_order_direction', 'DESC'),
            'acceptance' => get_option('jgap_acceptance_filter', '0')
        ];
    }
    
    /**
     * キーワードに応じたAPI検索パラメータ最適化
     */
    public function optimize_search_parameters($keyword) {
        $base_params = [
            'sort' => 'created_date',
            'order' => 'DESC',
            'acceptance' => '0'
        ];
        
        $keyword_lower = mb_strtolower($keyword);
        
        // IT・DX関連
        if (strpos($keyword_lower, 'it') !== false || 
            strpos($keyword_lower, 'dx') !== false ||
            strpos($keyword_lower, 'デジタル') !== false) {
            $base_params['use_purpose'] = '設備整備・IT導入をしたい';
            $base_params['industry'] = '情報通信業';
        }
        
        // 製造業関連
        if (strpos($keyword_lower, '製造') !== false || 
            strpos($keyword_lower, 'ものづくり') !== false) {
            $base_params['industry'] = '製造業';
            $base_params['use_purpose'] = '設備整備・IT導入をしたい / 研究開発・実証事業を行いたい';
        }
        
        // 小規模事業者関連
        if (strpos($keyword_lower, '小規模') !== false) {
            $base_params['target_number_of_employees'] = '20名以下';
        }
        
        // 創業・スタートアップ関連
        if (strpos($keyword_lower, '創業') !== false || 
            strpos($keyword_lower, 'スタートアップ') !== false) {
            $base_params['use_purpose'] = '新たな事業を行いたい';
        }
        
        // 販路拡大関連
        if (strpos($keyword_lower, '販路') !== false || 
            strpos($keyword_lower, '海外') !== false) {
            $base_params['use_purpose'] = '販路拡大・海外展開をしたい';
        }
        
        return $base_params;
    }
    
    /**
     * キーワード分析
     */
    public function analyze_keyword_performance() {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_fetch_logs';
        
        $results = $wpdb->get_results(
            "SELECT keyword, COUNT(*) as fetch_count, 
                    AVG(results_count) as avg_results,
                    MAX(created_at) as last_used
             FROM $table
             WHERE status = 'success'
             GROUP BY keyword
             ORDER BY fetch_count DESC
             LIMIT 20"
        );
        
        return $results;
    }
}