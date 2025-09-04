<?php
/**
 * ACF管理クラス
 * @package JGrants_Auto_Poster_Enhanced_Pro
 */

if (!defined('ABSPATH')) exit;

class JGrants_Enhanced_Pro_ACF_Manager {
    
    public function __construct() {
        add_action('acf/init', [$this, 'register_fields']);
    }
    
    /**
     * ACFフィールド登録
     */
    public function register_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }
        
        acf_add_local_field_group([
            'key' => 'group_jgap_grant_fields',
            'title' => '補助金情報',
            'fields' => [
                [
                    'key' => 'field_max_amount_numeric',
                    'label' => '補助金額（数値）',
                    'name' => 'max_amount_numeric',
                    'type' => 'number',
                ],
                [
                    'key' => 'field_max_amount',
                    'label' => '補助金額（表示用）',
                    'name' => 'max_amount',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_deadline_date',
                    'label' => '申請期限（日付）',
                    'name' => 'deadline_date',
                    'type' => 'date_picker',
                    'display_format' => 'Y年m月d日',
                    'return_format' => 'Ymd',
                ],
                [
                    'key' => 'field_deadline_text',
                    'label' => '申請期限（テキスト）',
                    'name' => 'deadline_text',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_application_status',
                    'label' => '申請ステータス',
                    'name' => 'application_status',
                    'type' => 'select',
                    'choices' => [
                        'open' => '募集中',
                        'closing_soon' => '締切間近',
                        'closed' => '募集終了',
                    ],
                ],
                [
                    'key' => 'field_difficulty_level',
                    'label' => '申請難易度',
                    'name' => 'difficulty_level',
                    'type' => 'select',
                    'choices' => [
                        'easy' => '簡単',
                        'medium' => '普通',
                        'hard' => '難しい',
                    ],
                ],
                [
                    'key' => 'field_official_url',
                    'label' => '公式URL',
                    'name' => 'official_url',
                    'type' => 'url',
                ],
                [
                    'key' => 'field_ai_summary',
                    'label' => 'AI要約',
                    'name' => 'ai_summary',
                    'type' => 'wysiwyg',
                    'tabs' => 'visual',
                    'toolbar' => 'basic',
                    'media_upload' => false,
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'grant',
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'left',
            'instruction_placement' => 'label',
        ]);
    }
}

// ACF Proがインストールされている場合のみ有効化
if (class_exists('ACF')) {
    new JGrants_Enhanced_Pro_ACF_Manager();
}
