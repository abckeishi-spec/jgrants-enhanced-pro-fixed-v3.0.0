<?php
/**
 * JグランツAPI連携クラス（API仕様完全準拠版）
 * 
 * JグランツAPIのSwagger仕様に100%準拠した実装
 * 必須パラメータ、オプションパラメータ、レスポンス構造を正確に処理
 * 
 * @package JGrants_Auto_Poster_Enhanced_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class JGrants_Enhanced_Pro_JGrantsAPI {
    
    /**
     * API基本URL（HTTPS）
     */
    private $api_base_url = 'https://api.jgrants-portal.go.jp/exp/v1/public';
    
    /**
     * HTTPS使用フラグ
     */
    private $use_https = true;
    
    /**
     * API仕様定義：必須パラメータ
     */
    private $required_params = [
        'keyword' => [
            'type' => 'string',
            'minLength' => 2,
            'maxLength' => 255,
            'validation' => 'no_spaces' // スペース入力不可
        ],
        'sort' => [
            'type' => 'enum',
            'values' => ['created_date', 'acceptance_start_datetime', 'acceptance_end_datetime'],
            'default' => 'created_date'
        ],
        'order' => [
            'type' => 'enum',
            'values' => ['ASC', 'DESC'],
            'default' => 'DESC'
        ],
        'acceptance' => [
            'type' => 'enum',
            'values' => ['0', '1'], // 0:期間外含む、1:募集期間内のみ
            'default' => '0'
        ]
    ];
    
    /**
     * API仕様定義：オプションパラメータ
     */
    private $optional_params = [
        'use_purpose' => [
            'type' => 'string',
            'maxLength' => 255,
            'separator' => ' / ',
            'values' => [
                '新たな事業を行いたい',
                '販路拡大・海外展開をしたい',
                '設備整備・IT導入をしたい',
                '人材育成を行いたい',
                '研究開発・実証事業を行いたい',
                '地域活性化・まちづくりをしたい',
                '環境・エネルギー対策をしたい',
                'その他'
            ]
        ],
        'industry' => [
            'type' => 'string',
            'maxLength' => 255,
            'separator' => ' / ',
            'values' => [
                '製造業',
                '情報通信業',
                '建設業',
                '卸売業、小売業',
                '医療、福祉',
                '宿泊業、飲食サービス業',
                '教育、学習支援業',
                '農業、林業',
                'その他'
            ]
        ],
        'target_number_of_employees' => [
            'type' => 'enum',
            'values' => [
                '従業員数の制約なし',
                '5名以下',
                '20名以下',
                '50名以下',
                '100名以下',
                '300名以下',
                '900名以下',
                '901名以上'
            ]
        ],
        'target_area_search' => [
            'type' => 'string',
            'maxLength' => 1000,
            'separator' => ' / '
        ]
    ];
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->use_https = get_option('jgap_use_https', true);
        if (!$this->use_https) {
            $this->api_base_url = str_replace('https://', 'http://', $this->api_base_url);
        }
    }
    
    /**
     * 戦略的キーワード検索（API仕様完全準拠）
     */
    public function search_by_keywords($keywords_array, $search_options = []) {
        $all_results = [];
        $seen_ids = [];
        
        // パフォーマンスモニタリング開始
        $start_time = microtime(true);
        $total_requests = 0;
        
        foreach ($keywords_array as $keyword) {
            // キーワードバリデーション（API仕様準拠）
            $validation_result = $this->validate_keyword($keyword);
            if (!$validation_result['valid']) {
                $this->log_warning('キーワードバリデーション失敗', [
                    'keyword' => $keyword,
                    'reason' => $validation_result['reason']
                ]);
                continue;
            }
            
            // API必須パラメータ構築
            $params = $this->build_required_params($keyword, $search_options);
            
            // オプションパラメータ追加
            $params = $this->add_optional_params($params, $search_options);
            
            // API呼び出し実行
            $url = add_query_arg($params, $this->api_base_url . '/subsidies');
            
            // リトライロジック付きリクエスト
            $response = $this->execute_request_with_retry($url, 3);
            
            if ($response === false) {
                continue;
            }
            
            // レスポンス解析
            $data = json_decode($response, true);
            if (!$this->validate_list_response($data)) {
                $this->log_error('無効なAPIレスポンス構造', [
                    'keyword' => $keyword,
                    'response_keys' => array_keys($data ?? [])
                ]);
                continue;
            }
            
            // 結果処理（重複除去）
            foreach ($data['result'] as $item) {
                $grant_id = $item['id'] ?? null;
                if ($grant_id && !isset($seen_ids[$grant_id])) {
                    $seen_ids[$grant_id] = true;
                    $item['_source_keyword'] = $keyword;
                    $item['_fetched_at'] = current_time('mysql');
                    $all_results[] = $item;
                }
            }
            
            $total_requests++;
            
            // APIレート制限対策（推奨間隔）
            if ($total_requests < count($keywords_array)) {
                sleep(get_option('jgap_rate_limit', 2));
            }
        }
        
        // パフォーマンスログ
        $execution_time = microtime(true) - $start_time;
        $this->log_performance('search_by_keywords', [
            'keyword_count' => count($keywords_array),
            'total_requests' => $total_requests,
            'results_count' => count($all_results),
            'execution_time' => round($execution_time, 2)
        ]);
        
        return $all_results;
    }
    
    /**
     * 補助金詳細情報取得（API仕様完全準拠）
     */
    public function fetch_grant_detail($grant_id) {
        // IDバリデーション（API仕様：最大18文字）
        if (empty($grant_id) || mb_strlen($grant_id) > 18) {
            $this->log_error('無効な補助金ID', ['grant_id' => $grant_id]);
            return null;
        }
        
        // キャッシュチェック
        $cached = $this->get_cached_detail($grant_id);
        if ($cached !== false) {
            return $cached;
        }
        
        $url = $this->api_base_url . '/subsidies/id/' . urlencode($grant_id);
        
        // リトライロジック付きリクエスト
        $response = $this->execute_request_with_retry($url, 3);
        
        if ($response === false) {
            return null;
        }
        
        $data = json_decode($response, true);
        if (!$this->validate_detail_response($data)) {
            $this->log_error('無効な詳細APIレスポンス', ['grant_id' => $grant_id]);
            return null;
        }
        
        $detail = $data['result'][0] ?? null;
        
        if ($detail) {
            // キャッシュに保存
            $this->cache_detail($grant_id, $detail);
        }
        
        return $detail;
    }
    
    /**
     * WordPress投稿作成・更新（API仕様データマッピング）
     */
    public function import_or_update_post($grant_summary, $source_keyword = '') {
        $grant_id = $grant_summary['id'] ?? null;
        if (empty($grant_id)) {
            return ['action' => 'error', 'message' => '無効な補助金ID'];
        }
        
        // 詳細情報取得
        $detail_data = $this->fetch_grant_detail($grant_id);
        if (!$detail_data) {
            return ['action' => 'error', 'message' => '詳細情報取得失敗'];
        }
        
        // 除外キーワードチェック
        if ($this->should_exclude_grant($detail_data)) {
            return ['action' => 'excluded', 'message' => '除外キーワードに該当'];
        }
        
        // 既存投稿チェック
        $existing_posts = get_posts([
            'post_type' => 'grant',
            'meta_key' => '_jgap_jgrants_id',
            'meta_value' => $grant_id,
            'posts_per_page' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private']
        ]);
        
        // 投稿データ構築
        $post_data = $this->build_post_data($detail_data, $source_keyword);
        
        if ($existing_posts) {
            // 更新処理
            $post_data['ID'] = $existing_posts[0]->ID;
            $post_id = wp_update_post($post_data);
            $action = 'updated';
        } else {
            // 新規作成
            $post_id = wp_insert_post($post_data);
            $action = 'imported';
        }
        
        if (is_wp_error($post_id)) {
            return ['action' => 'error', 'message' => $post_id->get_error_message()];
        }
        
        // メタデータとACFフィールドの設定
        $this->set_post_metadata($post_id, $detail_data);
        $this->map_detail_to_acf($post_id, $detail_data);
        $this->set_taxonomies($post_id, $detail_data);
        
        return ['action' => $action, 'post_id' => $post_id];
    }
    
    /**
     * キーワードバリデーション（API仕様準拠）
     */
    private function validate_keyword($keyword) {
        $keyword = trim($keyword);
        
        // 空チェック
        if (empty($keyword)) {
            return ['valid' => false, 'reason' => 'empty'];
        }
        
        // 最小文字数チェック（2文字以上）
        if (mb_strlen($keyword) < 2) {
            return ['valid' => false, 'reason' => 'too_short'];
        }
        
        // 最大文字数チェック（255文字以下）
        if (mb_strlen($keyword) > 255) {
            return ['valid' => false, 'reason' => 'too_long'];
        }
        
        // スペース文字チェック（API仕様：スペース入力不可）
        if (strpos($keyword, ' ') !== false || strpos($keyword, '　') !== false) {
            return ['valid' => false, 'reason' => 'contains_space'];
        }
        
        return ['valid' => true];
    }
    
    /**
     * 必須パラメータ構築
     */
    private function build_required_params($keyword, $options) {
        return [
            'keyword' => trim($keyword),
            'sort' => $options['sort'] ?? $this->required_params['sort']['default'],
            'order' => $options['order'] ?? $this->required_params['order']['default'],
            'acceptance' => $options['acceptance'] ?? $this->required_params['acceptance']['default']
        ];
    }
    
    /**
     * オプションパラメータ追加
     */
    private function add_optional_params($params, $options) {
        // use_purpose
        if (!empty($options['use_purpose'])) {
            $params['use_purpose'] = is_array($options['use_purpose'])
                ? implode(' / ', $options['use_purpose'])
                : $options['use_purpose'];
        }
        
        // industry
        if (!empty($options['industry'])) {
            $params['industry'] = is_array($options['industry'])
                ? implode(' / ', $options['industry'])
                : $options['industry'];
        }
        
        // target_number_of_employees
        if (!empty($options['target_number_of_employees'])) {
            $params['target_number_of_employees'] = $options['target_number_of_employees'];
        }
        
        // target_area_search
        if (!empty($options['target_area_search'])) {
            $params['target_area_search'] = is_array($options['target_area_search'])
                ? implode(' / ', $options['target_area_search'])
                : $options['target_area_search'];
        }
        
        return $params;
    }
    
    /**
     * リトライロジック付きHTTPリクエスト実行
     */
    private function execute_request_with_retry($url, $max_retries = 3) {
        $retry_count = 0;
        $backoff = 1;
        
        while ($retry_count < $max_retries) {
            $response = wp_remote_get($url, [
                'timeout' => 45,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'JGrants-Enhanced-Pro/1.0'
                ],
                'sslverify' => $this->use_https
            ]);
            
            if (is_wp_error($response)) {
                $this->log_error('API通信エラー', [
                    'url' => $url,
                    'error' => $response->get_error_message(),
                    'retry' => $retry_count
                ]);
                
                $retry_count++;
                if ($retry_count < $max_retries) {
                    sleep($backoff);
                    $backoff *= 2; // 指数バックオフ
                }
                continue;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            
            if ($status_code === 200) {
                return $body;
            }
            
            // エラーハンドリング
            $this->handle_api_error($status_code, $body, $url);
            
            // 5xx系エラーの場合はリトライ
            if ($status_code >= 500 && $retry_count < $max_retries - 1) {
                $retry_count++;
                sleep($backoff);
                $backoff *= 2;
                continue;
            }
            
            break;
        }
        
        return false;
    }
    
    /**
     * APIレスポンス検証（一覧）
     */
    private function validate_list_response($data) {
        if (!is_array($data)) {
            return false;
        }
        
        // 必須フィールドチェック
        if (!isset($data['metadata']['resultset']['count'])) {
            return false;
        }
        
        if (!isset($data['result']) || !is_array($data['result'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * APIレスポンス検証（詳細）
     */
    private function validate_detail_response($data) {
        if (!is_array($data)) {
            return false;
        }
        
        // 必須フィールドチェック
        if (!isset($data['metadata']['resultset']['count'])) {
            return false;
        }
        
        if (!isset($data['result']) || !is_array($data['result']) || empty($data['result'])) {
            return false;
        }
        
        // 最初の結果に必須フィールドがあるか
        $first_result = $data['result'][0];
        if (!isset($first_result['id']) || !isset($first_result['name'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 投稿データ構築
     */
    private function build_post_data($detail_data, $source_keyword) {
        // タイトル生成（優先順位：title > subsidy_catch_phrase > name）
        $title = $detail_data['title'] ?? $detail_data['subsidy_catch_phrase'] ?? $detail_data['name'] ?? '無題の補助金';
        
        // コンテンツ生成
        $content = $this->format_grant_content($detail_data);
        
        // 抜粋生成
        $excerpt = $detail_data['subsidy_catch_phrase'] ?? '';
        if (empty($excerpt) && !empty($detail_data['detail'])) {
            $excerpt = wp_trim_words($detail_data['detail'], 50);
        }
        
        return [
            'post_title' => wp_strip_all_tags($title),
            'post_content' => wp_kses_post($content),
            'post_excerpt' => wp_strip_all_tags($excerpt),
            'post_status' => get_option('jgap_auto_publish', false) ? 'publish' : 'draft',
            'post_type' => 'grant',
            'post_author' => get_current_user_id() ?: 1
        ];
    }
    
    /**
     * 補助金コンテンツフォーマット
     */
    private function format_grant_content($detail_data) {
        $content = '';
        
        // 概要セクション
        if (!empty($detail_data['detail'])) {
            $content .= '<h2>概要</h2>' . "\n";
            $content .= '<div class="grant-detail">' . nl2br(esc_html($detail_data['detail'])) . '</div>' . "\n\n";
        }
        
        // 基本情報セクション
        $content .= '<h2>基本情報</h2>' . "\n";
        $content .= '<table class="grant-info-table">' . "\n";
        
        // 補助金額
        if (!empty($detail_data['subsidy_max_limit'])) {
            $amount = $this->format_amount_display($detail_data['subsidy_max_limit']);
            $content .= '<tr><th>補助金額上限</th><td>' . esc_html($amount) . '</td></tr>' . "\n";
        }
        
        // 補助率
        if (!empty($detail_data['subsidy_rate'])) {
            $content .= '<tr><th>補助率</th><td>' . esc_html($detail_data['subsidy_rate']) . '</td></tr>' . "\n";
        }
        
        // 申請期限
        if (!empty($detail_data['acceptance_end_datetime'])) {
            $end_date = $this->format_datetime($detail_data['acceptance_end_datetime']);
            $content .= '<tr><th>申請期限</th><td>' . esc_html($end_date) . '</td></tr>' . "\n";
        }
        
        // 対象地域
        if (!empty($detail_data['target_area_search'])) {
            $content .= '<tr><th>対象地域</th><td>' . esc_html($detail_data['target_area_search']) . '</td></tr>' . "\n";
        }
        
        // 対象従業員数
        if (!empty($detail_data['target_number_of_employees'])) {
            $content .= '<tr><th>対象従業員数</th><td>' . esc_html($detail_data['target_number_of_employees']) . '</td></tr>' . "\n";
        }
        
        $content .= '</table>' . "\n\n";
        
        // 詳細リンク
        if (!empty($detail_data['front_subsidy_detail_page_url'])) {
            $content .= '<h2>詳細情報</h2>' . "\n";
            $content .= '<p><a href="' . esc_url($detail_data['front_subsidy_detail_page_url']) . '" target="_blank" rel="noopener noreferrer">公式サイトで詳細を確認</a></p>' . "\n";
        }
        
        return $content;
    }
    
    /**
     * メタデータ設定
     */
    private function set_post_metadata($post_id, $detail_data) {
        // JグランツID
        update_post_meta($post_id, '_jgap_jgrants_id', $detail_data['id']);
        
        // 生データ保存
        update_post_meta($post_id, '_jgap_jgrants_raw', wp_json_encode($detail_data, JSON_UNESCAPED_UNICODE));
        
        // 処理ステータス
        update_post_meta($post_id, '_jgap_processing_status', 'pending');
        
        // インポート日時
        update_post_meta($post_id, '_jgap_imported_at', current_time('mysql'));
        
        // ファイル情報カウント
        $file_counts = $this->count_attached_files($detail_data);
        update_post_meta($post_id, '_jgap_file_counts', wp_json_encode($file_counts));
        
        // 申請ステータス
        $status = $this->determine_application_status($detail_data);
        update_post_meta($post_id, '_jgap_application_status', $status);
    }
    
    /**
     * API詳細データをACFフィールドにマッピング
     */
    private function map_detail_to_acf($post_id, $detail_data) {
        if (!function_exists('update_field')) {
            return;
        }
        
        // 金額情報
        $max_amount_numeric = intval($detail_data['subsidy_max_limit'] ?? 0);
        if ($max_amount_numeric > 0) {
            update_field('max_amount_numeric', $max_amount_numeric, $post_id);
            update_field('max_amount', $this->format_amount_display($max_amount_numeric), $post_id);
        }
        
        // 期限情報（ISO 8601 → ACF形式変換）
        $end_datetime = $detail_data['acceptance_end_datetime'] ?? null;
        if ($end_datetime) {
            try {
                $date = new DateTime($end_datetime);
                update_field('deadline_date', $date->format('Ymd'), $post_id);
                update_field('deadline_text', $date->format('Y年n月j日'), $post_id);
            } catch (Exception $e) {
                update_field('deadline_text', '通年', $post_id);
            }
        } else {
            update_field('deadline_text', '通年', $post_id);
        }
        
        // 申請ステータス
        $status = $this->determine_application_status($detail_data);
        update_field('application_status', $status, $post_id);
        
        // 難易度（デフォルト値）
        update_field('difficulty_level', 'medium', $post_id);
        
        // 公式URL
        $official_url = $detail_data['front_subsidy_detail_page_url'] ?? '';
        if ($official_url) {
            update_field('official_url', $official_url, $post_id);
        }
        
        // AI要約用データ準備
        $summary_parts = [];
        
        if ($max_amount_numeric > 0) {
            $summary_parts[] = '最大' . $this->format_amount_display($max_amount_numeric) . 'の支援';
        }
        
        if (!empty($detail_data['target_number_of_employees'])) {
            $summary_parts[] = $detail_data['target_number_of_employees'] . '対象';
        }
        
        if (!empty($detail_data['subsidy_rate'])) {
            $summary_parts[] = '補助率: ' . $detail_data['subsidy_rate'];
        }
        
        if ($end_datetime) {
            $deadline = get_field('deadline_text', $post_id) ?: '通年';
            $summary_parts[] = '申請期限: ' . $deadline;
        }
        
        if (!empty($summary_parts)) {
            $summary_html = '<ul class="grant-summary-points">';
            foreach ($summary_parts as $part) {
                $summary_html .= '<li>' . esc_html($part) . '</li>';
            }
            $summary_html .= '</ul>';
            update_field('ai_summary', $summary_html, $post_id);
        }
    }
    
    /**
     * タクソノミー設定
     */
    private function set_taxonomies($post_id, $detail_data) {
        // 都道府県タクソノミー
        $this->set_prefecture_terms($post_id, $detail_data);
        
        // 補助金カテゴリ
        $this->set_grant_category_terms($post_id, $detail_data);
    }
    
    /**
     * 都道府県タクソノミー設定
     */
    private function set_prefecture_terms($post_id, $detail_data) {
        $target_areas = $detail_data['target_area_search'] ?? '';
        
        if (empty($target_areas)) {
            return;
        }
        
        $areas = explode(' / ', $target_areas);
        $prefecture_terms = [];
        
        // 都道府県名パターン
        $prefecture_pattern = '/(北海道|青森県|岩手県|宮城県|秋田県|山形県|福島県|' .
                              '茨城県|栃木県|群馬県|埼玉県|千葉県|東京都|神奈川県|' .
                              '新潟県|富山県|石川県|福井県|山梨県|長野県|岐阜県|' .
                              '静岡県|愛知県|三重県|滋賀県|京都府|大阪府|兵庫県|' .
                              '奈良県|和歌山県|鳥取県|島根県|岡山県|広島県|山口県|' .
                              '徳島県|香川県|愛媛県|高知県|福岡県|佐賀県|長崎県|' .
                              '熊本県|大分県|宮崎県|鹿児島県|沖縄県)/u';
        
        foreach ($areas as $area) {
            $area = trim($area);
            
            // 全国の場合
            if ($area === '全国') {
                $prefecture_terms[] = '全国';
                break;
            }
            
            // 都道府県名を抽出
            if (preg_match_all($prefecture_pattern, $area, $matches)) {
                $prefecture_terms = array_merge($prefecture_terms, $matches[0]);
            }
        }
        
        if (!empty($prefecture_terms)) {
            wp_set_object_terms($post_id, array_unique($prefecture_terms), 'prefecture');
        }
    }
    
    /**
     * 補助金カテゴリタクソノミー設定
     */
    private function set_grant_category_terms($post_id, $detail_data) {
        $use_purposes = $detail_data['use_purpose'] ?? '';
        $industry = $detail_data['industry'] ?? '';
        
        $category_terms = [];
        
        // 利用目的からカテゴリ生成
        if (!empty($use_purposes)) {
            $purposes = explode(' / ', $use_purposes);
            foreach ($purposes as $purpose) {
                $purpose = trim($purpose);
                // 「〜したい」を削除してカテゴリ名に
                $category = str_replace(['をしたい', 'したい'], '', $purpose);
                if (!empty($category)) {
                    $category_terms[] = $category;
                }
            }
        }
        
        // 業種からカテゴリ追加
        if (!empty($industry)) {
            $industries = explode(' / ', $industry);
            foreach ($industries as $ind) {
                $ind = trim($ind);
                if (!empty($ind) && $ind !== 'その他') {
                    $category_terms[] = $ind . '向け';
                }
            }
        }
        
        if (!empty($category_terms)) {
            wp_set_object_terms($post_id, array_unique($category_terms), 'grant_category');
        }
    }
    
    /**
     * 金額表示フォーマット
     */
    private function format_amount_display($amount) {
        $amount = intval($amount);
        
        if ($amount >= 100000000) { // 1億以上
            $oku = floor($amount / 100000000);
            $man = floor(($amount % 100000000) / 10000);
            if ($man > 0) {
                return $oku . '億' . number_format($man) . '万円';
            } else {
                return $oku . '億円';
            }
        } elseif ($amount >= 10000) { // 1万以上
            return number_format($amount / 10000) . '万円';
        } else {
            return number_format($amount) . '円';
        }
    }
    
    /**
     * 日時フォーマット
     */
    private function format_datetime($datetime) {
        try {
            $date = new DateTime($datetime);
            return $date->format('Y年n月j日');
        } catch (Exception $e) {
            return $datetime;
        }
    }
    
    /**
     * 申請ステータス判定
     */
    private function determine_application_status($detail_data) {
        $reception = $detail_data['request_reception_presence'] ?? '';
        $end_datetime = $detail_data['acceptance_end_datetime'] ?? '';
        
        if ($reception === '無') {
            return 'closed';
        }
        
        if ($end_datetime) {
            try {
                $end_date = new DateTime($end_datetime);
                $now = new DateTime();
                
                if ($end_date < $now) {
                    return 'closed';
                }
                
                // 1週間以内の場合は「締切間近」
                $diff = $now->diff($end_date);
                if ($diff->days <= 7) {
                    return 'closing_soon';
                }
                
            } catch (Exception $e) {
                // 日付解析エラー時は募集中として扱う
            }
        }
        
        return 'open';
    }
    
    /**
     * 添付ファイル数カウント
     */
    private function count_attached_files($detail_data) {
        return [
            'application_guidelines' => count($detail_data['application_guidelines'] ?? []),
            'outline_of_grant' => count($detail_data['outline_of_grant'] ?? []),
            'application_form' => count($detail_data['application_form'] ?? [])
        ];
    }
    
    /**
     * 除外キーワードチェック
     */
    private function should_exclude_grant($detail_data) {
        $exclude_keywords = get_option('jgap_keywords_exclude', '');
        
        if (empty($exclude_keywords)) {
            return false;
        }
        
        $exclude_list = array_filter(array_map('trim', explode("\n", $exclude_keywords)));
        
        if (empty($exclude_list)) {
            return false;
        }
        
        // チェック対象テキストを結合
        $check_text = '';
        $check_text .= ($detail_data['title'] ?? '') . ' ';
        $check_text .= ($detail_data['subsidy_catch_phrase'] ?? '') . ' ';
        $check_text .= ($detail_data['detail'] ?? '') . ' ';
        $check_text .= ($detail_data['use_purpose'] ?? '') . ' ';
        $check_text .= ($detail_data['industry'] ?? '');
        
        $check_text = mb_strtolower($check_text);
        
        foreach ($exclude_list as $exclude_keyword) {
            $exclude_keyword = mb_strtolower(trim($exclude_keyword));
            if (!empty($exclude_keyword) && strpos($check_text, $exclude_keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * キャッシュ取得
     */
    private function get_cached_detail($grant_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_api_cache';
        $cache_key = md5('grant_detail_' . $grant_id);
        
        $cached = $wpdb->get_var($wpdb->prepare(
            "SELECT cache_value FROM $table 
             WHERE cache_key = %s 
             AND expires_at > %s",
            $cache_key,
            current_time('mysql')
        ));
        
        if ($cached) {
            return json_decode($cached, true);
        }
        
        return false;
    }
    
    /**
     * キャッシュ保存
     */
    private function cache_detail($grant_id, $detail) {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_api_cache';
        $cache_key = md5('grant_detail_' . $grant_id);
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $wpdb->replace($table, [
            'cache_key' => $cache_key,
            'cache_value' => wp_json_encode($detail, JSON_UNESCAPED_UNICODE),
            'expires_at' => $expires_at,
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * APIエラーハンドリング（仕様準拠）
     */
    private function handle_api_error($status_code, $response_body, $context = '') {
        $error_data = json_decode($response_body, true);
        $error_message = $error_data['message'] ?? $error_data['error'] ?? 'Unknown error';
        
        $log_context = [
            'status_code' => $status_code,
            'context' => $context,
            'response' => $error_message
        ];
        
        switch ($status_code) {
            case 400:
                $this->log_error('Bad Request - パラメータエラー', $log_context);
                break;
            case 401:
                $this->log_error('Unauthorized - 認証エラー', $log_context);
                break;
            case 404:
                $this->log_warning('Resource Not Found - リソースが見つかりません', $log_context);
                break;
            case 405:
                $this->log_error('Method Not Allowed - メソッドエラー', $log_context);
                break;
            case 429:
                $this->log_warning('Too Many Requests - レート制限', $log_context);
                break;
            case 500:
                $this->log_error('Internal Server Error - サーバーエラー', $log_context);
                break;
            case 502:
                $this->log_error('Bad Gateway - ゲートウェイエラー', $log_context);
                break;
            case 503:
                $this->log_error('Service Unavailable - サービス利用不可', $log_context);
                break;
            default:
                $this->log_error('Unknown API Error', $log_context);
        }
    }
    
    /**
     * パフォーマンスログ記録
     */
    private function log_performance($operation, $details) {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_performance_logs';
        
        $wpdb->insert($table, [
            'operation' => $operation,
            'execution_time' => $details['execution_time'] ?? 0,
            'memory_used' => memory_get_usage(true),
            'success' => true,
            'details' => wp_json_encode($details, JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * エラーログ記録
     */
    private function log_error($message, $context = []) {
        jgap_log('[API ERROR] ' . $message, $context);
        
        // データベースにも記録
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_fetch_logs';
        
        $wpdb->insert($table, [
            'keyword' => $context['keyword'] ?? '',
            'results_count' => 0,
            'status' => 'error',
            'error_message' => $message . ' | ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at' => current_time('mysql')
        ]);
    }
    
    /**
     * 警告ログ記録
     */
    private function log_warning($message, $context = []) {
        jgap_log('[API WARNING] ' . $message, $context);
    }
}