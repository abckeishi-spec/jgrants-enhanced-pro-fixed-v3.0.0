<?php
/**
 * コンテンツ処理クラス
 * @package JGrants_Auto_Poster_Enhanced_Pro
 */

if (!defined('ABSPATH')) exit;

class JGrants_Enhanced_Pro_Content_Processor {
    
    private $gemini_ai = null;
    
    public function __construct() {
        if (class_exists('JGrants_Enhanced_Pro_Gemini_AI')) {
            $this->gemini_ai = new JGrants_Enhanced_Pro_Gemini_AI();
        }
    }
    
    /**
     * 投稿処理
     */
    public function process_post($post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'grant') {
            return false;
        }
        
        // 処理ステータス更新
        update_post_meta($post_id, '_jgap_processing_status', 'processing');
        
        // AIでコンテンツ強化
        if ($this->gemini_ai && $this->gemini_ai->is_available()) {
            $enhanced_content = $this->enhance_with_ai($post);
            if ($enhanced_content) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $enhanced_content['content'],
                    'post_excerpt' => $enhanced_content['excerpt']
                ]);
                
                // AI要約をACFに保存
                if (function_exists('update_field') && !empty($enhanced_content['summary'])) {
                    update_field('ai_summary', $enhanced_content['summary'], $post_id);
                }
            }
        }
        
        // SEO最適化
        $this->optimize_for_seo($post_id);
        
        // アイキャッチ画像生成
        $this->generate_featured_image($post_id);
        
        // 処理完了
        update_post_meta($post_id, '_jgap_processing_status', 'completed');
        update_post_meta($post_id, '_jgap_processed_at', current_time('mysql'));
        
        return true;
    }
    
    /**
     * AIでコンテンツ強化
     */
    private function enhance_with_ai($post) {
        if (!$this->gemini_ai) {
            return false;
        }
        
        $raw_data = get_post_meta($post->ID, '_jgap_jgrants_raw', true);
        $grant_data = json_decode($raw_data, true);
        
        if (!$grant_data) {
            return false;
        }
        
        // AIプロンプト構築
        $prompt = $this->build_ai_prompt($grant_data);
        
        // Gemini API呼び出し
        $ai_response = $this->gemini_ai->generate_content($prompt);
        
        if (!$ai_response) {
            return false;
        }
        
        return $this->parse_ai_response($ai_response);
    }
    
    /**
     * AIプロンプト構築
     */
    private function build_ai_prompt($grant_data) {
        $prompt = "以下の補助金情報を基に、魅力的なSEO最適化記事を生成してください。\n\n";
        $prompt .= "タイトル: " . ($grant_data['title'] ?? '') . "\n";
        $prompt .= "概要: " . ($grant_data['detail'] ?? '') . "\n";
        $prompt .= "補助金額: " . ($grant_data['subsidy_max_limit'] ?? '') . "\n";
        $prompt .= "対象地域: " . ($grant_data['target_area_search'] ?? '') . "\n";
        $prompt .= "対象業種: " . ($grant_data['industry'] ?? '') . "\n\n";
        $prompt .= "次の形式で出力してください:\n";
        $prompt .= "[CONTENT]\n詳細な記事内容\n[/CONTENT]\n";
        $prompt .= "[EXCERPT]\n短い説明文（50文字以内）\n[/EXCERPT]\n";
        $prompt .= "[SUMMARY]\n箇条書きポイント\n[/SUMMARY]";
        
        return $prompt;
    }
    
    /**
     * AIレスポンス解析
     */
    private function parse_ai_response($response) {
        $result = [
            'content' => '',
            'excerpt' => '',
            'summary' => ''
        ];
        
        // コンテンツ抽出
        if (preg_match('/\[CONTENT\](.+?)\[\/CONTENT\]/s', $response, $matches)) {
            $result['content'] = trim($matches[1]);
        }
        
        // 抄録抽出
        if (preg_match('/\[EXCERPT\](.+?)\[\/EXCERPT\]/s', $response, $matches)) {
            $result['excerpt'] = trim($matches[1]);
        }
        
        // 要約抽出
        if (preg_match('/\[SUMMARY\](.+?)\[\/SUMMARY\]/s', $response, $matches)) {
            $result['summary'] = trim($matches[1]);
        }
        
        return $result;
    }
    
    /**
     * SEO最適化
     */
    private function optimize_for_seo($post_id) {
        if (class_exists('JGrants_Enhanced_Pro_SEO_Integrator')) {
            $seo = new JGrants_Enhanced_Pro_SEO_Integrator();
            $seo->optimize_post($post_id);
        }
    }
    
    /**
     * アイキャッチ画像生成
     */
    private function generate_featured_image($post_id) {
        // 既にアイキャッチ画像がある場合はスキップ
        if (has_post_thumbnail($post_id)) {
            return;
        }
        
        // プレースホルダー画像を設定またはデフォルト画像を生成
        // TODO: 実装
    }
}