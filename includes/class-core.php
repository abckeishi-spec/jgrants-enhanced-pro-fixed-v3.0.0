<?php
/**
 * コアエンジンクラス
 * 
 * プラグインの中枢となるクラスで、各モジュールの初期化と連携を管理
 * 
 * @package JGrants_Auto_Poster_Enhanced_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class JGrants_Enhanced_Pro_Core {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;
    
    /**
     * API連携クラス
     */
    private $api;
    
    /**
     * キーワード戦略クラス
     */
    private $keyword_strategy;
    
    /**
     * コンテンツ処理クラス
     */
    private $content_processor;
    
    /**
     * バッチスケジューラークラス
     */
    private $batch_scheduler;
    
    /**
     * パフォーマンスモニタークラス
     */
    private $performance_monitor;
    
    /**
     * プラグインバージョン
     */
    private $version;
    
    /**
     * 処理ステータス
     */
    private $processing_status = [
        'is_running' => false,
        'current_batch' => 0,
        'total_processed' => 0,
        'errors' => []
    ];
    
    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->version = JGAP_VERSION;
        $this->init();
        $this->setup_hooks();
    }
    
    /**
     * シングルトンインスタンス取得
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 初期化処理
     */
    private function init() {
        // 各クラスのインスタンス化
        $this->load_dependencies();
        
        // プロセスロックの初期化
        $this->init_process_lock();
        
        // キャッシュシステムの初期化
        $this->init_cache_system();
    }
    
    /**
     * 依存クラスの読み込み
     */
    private function load_dependencies() {
        // API連携クラス
        if (class_exists('JGrants_Enhanced_Pro_JGrantsAPI')) {
            $this->api = new JGrants_Enhanced_Pro_JGrantsAPI();
        }
        
        // キーワード戦略クラス
        if (class_exists('JGrants_Enhanced_Pro_Keyword_Strategy')) {
            $this->keyword_strategy = new JGrants_Enhanced_Pro_Keyword_Strategy();
        }
        
        // コンテンツ処理クラス
        if (class_exists('JGrants_Enhanced_Pro_Content_Processor')) {
            $this->content_processor = new JGrants_Enhanced_Pro_Content_Processor();
        }
        
        // バッチスケジューラークラス
        if (class_exists('JGrants_Enhanced_Pro_Batch_Scheduler')) {
            $this->batch_scheduler = new JGrants_Enhanced_Pro_Batch_Scheduler();
        }
        
        // パフォーマンスモニタークラス
        if (class_exists('JGrants_Enhanced_Pro_Performance_Monitor')) {
            $this->performance_monitor = new JGrants_Enhanced_Pro_Performance_Monitor();
        }
    }
    
    /**
     * フックのセットアップ
     */
    private function setup_hooks() {
        // Cronフック
        add_action('jgap_cron_fetch', [$this, 'execute_cron_fetch']);
        add_action('jgap_cron_process', [$this, 'execute_cron_process']);
        
        // AJAX処理フック
        add_action('wp_ajax_jgap_manual_fetch', [$this, 'ajax_manual_fetch']);
        add_action('wp_ajax_jgap_get_status', [$this, 'ajax_get_status']);
        add_action('wp_ajax_jgap_stop_process', [$this, 'ajax_stop_process']);
        
        // 投稿保存時のフック
        add_action('save_post_grant', [$this, 'on_grant_save'], 10, 3);
        
        // 投稿削除時のフック
        add_action('before_delete_post', [$this, 'on_grant_delete'], 10, 1);
        
        // REST APIエンドポイント
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }
    
    /**
     * Cron取得処理の実行
     */
    public function execute_cron_fetch() {
        // 処理中チェック
        if ($this->is_processing()) {
            jgap_log('既に処理中のため、Cron取得をスキップしました');
            return;
        }
        
        // 処理開始
        $this->start_processing();
        
        try {
            // パフォーマンス計測開始
            $start_time = microtime(true);
            $start_memory = memory_get_usage(true);
            
            // キーワード取得
            $keywords = $this->keyword_strategy->get_active_keywords();
            
            if (empty($keywords)) {
                jgap_log('有効なキーワードが設定されていません');
                $this->stop_processing();
                return;
            }
            
            // 検索オプション取得
            $search_options = $this->keyword_strategy->get_search_options();
            
            // API実行
            $results = $this->api->search_by_keywords($keywords, $search_options);
            
            if (empty($results)) {
                jgap_log('検索結果が0件でした', ['keywords' => $keywords]);
                $this->stop_processing();
                return;
            }
            
            // 結果の処理
            $processed_count = 0;
            $error_count = 0;
            
            foreach ($results as $grant_data) {
                $result = $this->api->import_or_update_post($grant_data, $grant_data['_source_keyword'] ?? '');
                
                if ($result['action'] === 'error') {
                    $error_count++;
                    jgap_log('投稿処理エラー', [
                        'grant_id' => $grant_data['id'] ?? 'unknown',
                        'error' => $result['message']
                    ]);
                } else {
                    $processed_count++;
                    
                    // 処理キューに追加
                    if (isset($result['post_id'])) {
                        $this->batch_scheduler->add_to_queue($result['post_id'], $grant_data['id']);
                    }
                }
                
                // レート制限
                if ($processed_count % 5 === 0) {
                    sleep(get_option('jgap_rate_limit', 2));
                }
            }
            
            // パフォーマンスログ記録
            $execution_time = microtime(true) - $start_time;
            $memory_used = memory_get_usage(true) - $start_memory;
            
            $this->performance_monitor->log_operation([
                'operation' => 'cron_fetch',
                'execution_time' => $execution_time,
                'memory_used' => $memory_used,
                'processed_count' => $processed_count,
                'error_count' => $error_count,
                'keyword_count' => count($keywords)
            ]);
            
            jgap_log('Cron取得処理完了', [
                'processed' => $processed_count,
                'errors' => $error_count,
                'execution_time' => round($execution_time, 2) . 's'
            ]);
            
        } catch (Exception $e) {
            jgap_log('Cron取得処理中に例外が発生', ['error' => $e->getMessage()]);
        }
        
        // 処理終了
        $this->stop_processing();
    }
    
    /**
     * Cron処理実行
     */
    public function execute_cron_process() {
        // 処理中チェック
        if ($this->is_processing()) {
            return;
        }
        
        $this->start_processing();
        
        try {
            // バッチサイズ取得
            $batch_size = get_option('jgap_batch_size', 10);
            
            // キューから取得
            $queue_items = $this->batch_scheduler->get_pending_items($batch_size);
            
            if (empty($queue_items)) {
                $this->stop_processing();
                return;
            }
            
            foreach ($queue_items as $item) {
                // AI処理とコンテンツ強化
                if ($this->content_processor) {
                    $this->content_processor->process_post($item['post_id']);
                }
                
                // キューステータス更新
                $this->batch_scheduler->update_status($item['id'], 'completed');
                
                // レート制限
                sleep(get_option('jgap_rate_limit', 2));
            }
            
        } catch (Exception $e) {
            jgap_log('Cron処理中に例外が発生', ['error' => $e->getMessage()]);
        }
        
        $this->stop_processing();
    }
    
    /**
     * AJAX: 手動取得処理
     */
    public function ajax_manual_fetch() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        // nonce検証
        check_ajax_referer('jgap_ajax_nonce', 'nonce');
        
        // 既に処理中の場合
        if ($this->is_processing()) {
            wp_send_json_error(['message' => '現在処理中です']);
        }
        
        // キーワード取得
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        
        if (empty($keyword)) {
            wp_send_json_error(['message' => 'キーワードが指定されていません']);
        }
        
        $this->start_processing();
        
        try {
            // API実行
            $search_options = $this->keyword_strategy->optimize_search_parameters($keyword);
            $results = $this->api->search_by_keywords([$keyword], $search_options);
            
            if (empty($results)) {
                wp_send_json_error(['message' => '検索結果が0件でした']);
            }
            
            $imported = 0;
            $updated = 0;
            $errors = 0;
            
            foreach ($results as $grant_data) {
                $result = $this->api->import_or_update_post($grant_data, $keyword);
                
                switch ($result['action']) {
                    case 'imported':
                        $imported++;
                        break;
                    case 'updated':
                        $updated++;
                        break;
                    case 'error':
                        $errors++;
                        break;
                }
            }
            
            wp_send_json_success([
                'message' => '処理が完了しました',
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors,
                'total' => count($results)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        } finally {
            $this->stop_processing();
        }
    }
    
    /**
     * AJAX: ステータス取得
     */
    public function ajax_get_status() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        check_ajax_referer('jgap_ajax_nonce', 'nonce');
        
        // ステータス情報を収集
        $status = [
            'is_processing' => $this->is_processing(),
            'last_fetch' => get_option('jgap_last_fetch_time', ''),
            'last_process' => get_option('jgap_last_process_time', ''),
            'queue_count' => $this->batch_scheduler->get_queue_count(),
            'pending_count' => $this->batch_scheduler->get_pending_count(),
            'today_processed' => $this->get_today_processed_count(),
            'api_health' => $this->check_api_health()
        ];
        
        wp_send_json_success($status);
    }
    
    /**
     * AJAX: 処理停止
     */
    public function ajax_stop_process() {
        // 権限チェック
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        check_ajax_referer('jgap_ajax_nonce', 'nonce');
        
        $this->stop_processing(true);
        
        wp_send_json_success(['message' => '処理を停止しました']);
    }
    
    /**
     * 補助金投稿保存時の処理
     */
    public function on_grant_save($post_id, $post, $update) {
        // 自動保存時は処理しない
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // リビジョンは処理しない
        if (wp_is_post_revision($post_id)) {
            return;
        }
        
        // SEO最適化処理
        if (class_exists('JGrants_Enhanced_Pro_SEO_Integrator')) {
            $seo = new JGrants_Enhanced_Pro_SEO_Integrator();
            $seo->optimize_post($post_id);
        }
        
        // キャッシュクリア
        $this->clear_post_cache($post_id);
    }
    
    /**
     * 補助金投稿削除時の処理
     */
    public function on_grant_delete($post_id) {
        if (get_post_type($post_id) !== 'grant') {
            return;
        }
        
        // 関連データの削除
        $jgrants_id = get_post_meta($post_id, '_jgap_jgrants_id', true);
        
        if ($jgrants_id) {
            // キューから削除
            $this->batch_scheduler->remove_from_queue($jgrants_id);
            
            // キャッシュクリア
            $this->clear_api_cache($jgrants_id);
        }
    }
    
    /**
     * REST APIルートの登録
     */
    public function register_rest_routes() {
        register_rest_route('jgap/v1', '/grants/search', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_search_grants'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
            'args' => [
                'keyword' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        register_rest_route('jgap/v1', '/grants/import', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_import_grant'],
            'permission_callback' => function() {
                return current_user_can('publish_posts');
            }
        ]);
    }
    
    /**
     * REST API: 補助金検索
     */
    public function rest_search_grants($request) {
        $keyword = $request->get_param('keyword');
        
        try {
            $search_options = $this->keyword_strategy->optimize_search_parameters($keyword);
            $results = $this->api->search_by_keywords([$keyword], $search_options);
            
            return rest_ensure_response([
                'success' => true,
                'data' => $results
            ]);
            
        } catch (Exception $e) {
            return new WP_Error('search_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * REST API: 補助金インポート
     */
    public function rest_import_grant($request) {
        $grant_id = $request->get_param('grant_id');
        
        if (empty($grant_id)) {
            return new WP_Error('invalid_params', '補助金IDが指定されていません', ['status' => 400]);
        }
        
        try {
            // 詳細取得
            $detail = $this->api->fetch_grant_detail($grant_id);
            
            if (!$detail) {
                return new WP_Error('not_found', '補助金情報が見つかりません', ['status' => 404]);
            }
            
            // インポート処理
            $result = $this->api->import_or_update_post($detail);
            
            return rest_ensure_response([
                'success' => true,
                'data' => $result
            ]);
            
        } catch (Exception $e) {
            return new WP_Error('import_error', $e->getMessage(), ['status' => 500]);
        }
    }
    
    /**
     * 処理中チェック
     */
    private function is_processing() {
        $lock = get_transient('jgap_process_lock');
        return !empty($lock);
    }
    
    /**
     * 処理開始
     */
    private function start_processing() {
        set_transient('jgap_process_lock', time(), 3600);
        update_option('jgap_last_process_start', current_time('mysql'));
        $this->processing_status['is_running'] = true;
    }
    
    /**
     * 処理停止
     */
    private function stop_processing($force = false) {
        delete_transient('jgap_process_lock');
        update_option('jgap_last_process_end', current_time('mysql'));
        $this->processing_status['is_running'] = false;
        
        if ($force) {
            // 強制停止時はキューもクリア
            $this->batch_scheduler->clear_processing_status();
        }
    }
    
    /**
     * プロセスロックの初期化
     */
    private function init_process_lock() {
        // 古いロックをクリア（1時間以上前のもの）
        $lock = get_transient('jgap_process_lock');
        if ($lock && (time() - $lock) > 3600) {
            delete_transient('jgap_process_lock');
        }
    }
    
    /**
     * キャッシュシステムの初期化
     */
    private function init_cache_system() {
        // 期限切れキャッシュのクリア
        if (!wp_next_scheduled('jgap_clear_expired_cache')) {
            wp_schedule_event(time(), 'daily', 'jgap_clear_expired_cache');
        }
        
        add_action('jgap_clear_expired_cache', [$this, 'clear_expired_cache']);
    }
    
    /**
     * 期限切れキャッシュのクリア
     */
    public function clear_expired_cache() {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_api_cache';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE expires_at < %s",
            current_time('mysql')
        ));
    }
    
    /**
     * 投稿キャッシュクリア
     */
    private function clear_post_cache($post_id) {
        // WordPressキャッシュクリア
        clean_post_cache($post_id);
        
        // カスタムキャッシュクリア
        delete_transient('jgap_post_' . $post_id);
    }
    
    /**
     * APIキャッシュクリア
     */
    private function clear_api_cache($jgrants_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_api_cache';
        
        $cache_key = md5('grant_detail_' . $jgrants_id);
        $wpdb->delete($table, ['cache_key' => $cache_key]);
    }
    
    /**
     * 今日の処理件数取得
     */
    private function get_today_processed_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'jgap_processing_queue';
        
        $today = current_time('Y-m-d');
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE DATE(processed_at) = %s 
             AND status = 'completed'",
            $today
        ));
        
        return intval($count);
    }
    
    /**
     * APIヘルスチェック
     */
    private function check_api_health() {
        $cache_key = 'jgap_api_health_check';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // テストリクエスト
        $url = JGRANTS_API_BASE_URL . '/subsidies';
        $args = [
            'timeout' => 10,
            'method' => 'HEAD'
        ];
        
        $response = wp_remote_request($url, $args);
        $is_healthy = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
        
        set_transient($cache_key, $is_healthy, 300); // 5分キャッシュ
        
        return $is_healthy;
    }
    
    /**
     * 公開メソッド: API取得
     */
    public function get_api() {
        return $this->api;
    }
    
    /**
     * 公開メソッド: キーワード戦略取得
     */
    public function get_keyword_strategy() {
        return $this->keyword_strategy;
    }
    
    /**
     * 公開メソッド: コンテンツプロセッサー取得
     */
    public function get_content_processor() {
        return $this->content_processor;
    }
    
    /**
     * 公開メソッド: バッチスケジューラー取得
     */
    public function get_batch_scheduler() {
        return $this->batch_scheduler;
    }
    
    /**
     * 公開メソッド: パフォーマンスモニター取得
     */
    public function get_performance_monitor() {
        return $this->performance_monitor;
    }
}