<?php
/**
 * SEO統合クラス
 * @package JGrants_Auto_Poster_Enhanced_Pro
 */

if (!defined('ABSPATH')) exit;

class JGrants_Enhanced_Pro_SEO_Integrator {
    
    /**
     * 投稿のSEO最適化
     */
    public function optimize_post($post_id) {
        $post = get_post($post_id);
        if (!$post) return;
        
        // Yoast SEO対応
        if ($this->is_yoast_active()) {
            $this->optimize_for_yoast($post_id, $post);
        }
        
        // RankMath対応
        if ($this->is_rankmath_active()) {
            $this->optimize_for_rankmath($post_id, $post);
        }
        
        // 構造化データ追加
        $this->add_structured_data($post_id);
    }
    
    /**
     * Yoast SEO有効チェック
     */
    private function is_yoast_active() {
        return defined('WPSEO_VERSION');
    }
    
    /**
     * RankMath有効チェック
     */
    private function is_rankmath_active() {
        return class_exists('RankMath');
    }
    
    /**
     * Yoast SEO最適化
     */
    private function optimize_for_yoast($post_id, $post) {
        if (!function_exists('WPSEO_Meta')) return;
        
        $raw_data = get_post_meta($post_id, '_jgap_jgrants_raw', true);
        $grant_data = json_decode($raw_data, true);
        
        // メタタイトル
        $title = $post->post_title . ' | 最大' . $this->format_amount($grant_data['subsidy_max_limit'] ?? 0);
        update_post_meta($post_id, '_yoast_wpseo_title', $title);
        
        // メタディスクリプション
        $desc = mb_substr(strip_tags($grant_data['detail'] ?? $post->post_excerpt), 0, 150) . '...';
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $desc);
        
        // フォーカスキーワード
        $keyword = get_post_meta($post_id, '_jgap_keyword_source', true) ?: $post->post_title;
        update_post_meta($post_id, '_yoast_wpseo_focuskw', $keyword);
    }
    
    /**
     * RankMath最適化
     */
    private function optimize_for_rankmath($post_id, $post) {
        if (!class_exists('RankMath\Post\Post')) return;
        
        $raw_data = get_post_meta($post_id, '_jgap_jgrants_raw', true);
        $grant_data = json_decode($raw_data, true);
        
        // メタタイトル
        $title = $post->post_title . ' | 最大' . $this->format_amount($grant_data['subsidy_max_limit'] ?? 0);
        update_post_meta($post_id, 'rank_math_title', $title);
        
        // メタディスクリプション
        $desc = mb_substr(strip_tags($grant_data['detail'] ?? $post->post_excerpt), 0, 150) . '...';
        update_post_meta($post_id, 'rank_math_description', $desc);
        
        // フォーカスキーワード
        $keyword = get_post_meta($post_id, '_jgap_keyword_source', true) ?: $post->post_title;
        update_post_meta($post_id, 'rank_math_focus_keyword', $keyword);
    }
    
    /**
     * 構造化データ追加
     */
    private function add_structured_data($post_id) {
        $raw_data = get_post_meta($post_id, '_jgap_jgrants_raw', true);
        $grant_data = json_decode($raw_data, true);
        if (!$grant_data) return;
        
        $structured_data = [
            '@context' => 'https://schema.org',
            '@type' => 'GovernmentService',
            'name' => get_the_title($post_id),
            'description' => $grant_data['detail'] ?? '',
            'provider' => [
                '@type' => 'GovernmentOrganization',
                'name' => '日本政府'
            ],
            'audience' => [
                '@type' => 'Audience',
                'audienceType' => $grant_data['target_number_of_employees'] ?? '事業者'
            ],
            'areaServed' => $grant_data['target_area_search'] ?? '日本'
        ];
        
        update_post_meta($post_id, '_jgap_structured_data', wp_json_encode($structured_data));
    }
    
    /**
     * 金額フォーマット
     */
    private function format_amount($amount) {
        $amount = intval($amount);
        if ($amount >= 100000000) {
            return floor($amount / 100000000) . '億円';
        } elseif ($amount >= 10000) {
            return number_format($amount / 10000) . '万円';
        } else {
            return number_format($amount) . '円';
        }
    }
}
