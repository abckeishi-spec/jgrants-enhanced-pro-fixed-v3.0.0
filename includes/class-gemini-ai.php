<?php
/**
 * Gemini AI統合クラス
 * @package JGrants_Auto_Poster_Enhanced_Pro
 */

if (!defined('ABSPATH')) exit;

class JGrants_Enhanced_Pro_Gemini_AI {
    
    private $api_key;
    private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
    
    public function __construct() {
        $this->api_key = get_option('jgap_gemini_api_key', '');
    }
    
    /**
     * API利用可能チェック
     */
    public function is_available() {
        return !empty($this->api_key);
    }
    
    /**
     * コンテンツ生成
     */
    public function generate_content($prompt) {
        if (!$this->is_available()) {
            return false;
        }
        
        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048,
            ]
        ];
        
        $response = wp_remote_post($this->api_url . '?key=' . $this->api_key, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
            'timeout' => 30
        ]);
        
        if (is_wp_error($response)) {
            jgap_log('Gemini API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }
        
        return false;
    }
    
    /**
     * 要約生成
     */
    public function generate_summary($text, $max_length = 200) {
        $prompt = "以下のテキストを{$max_length}文字以内で要約してください:\n\n" . $text;
        return $this->generate_content($prompt);
    }
    
    /**
     * キーワード抽出
     */
    public function extract_keywords($text, $count = 5) {
        $prompt = "以下のテキストから重要なキーワードを{$count}個抽出してください:\n\n" . $text;
        return $this->generate_content($prompt);
    }
}
