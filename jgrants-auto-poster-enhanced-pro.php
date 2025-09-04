<?php
/**
 * Plugin Name: Jグランツ自動投稿システム Enhanced Pro
 * Plugin URI: https://jgrants-auto-poster.pro
 * Description: JグランツAPIから補助金情報を自動取得し、AI処理を経てWordPressに高品質な記事として投稿するプロフェッショナルプラグイン
 * Version: 1.0.0
 * Author: Enhanced Pro Development Team
 * Author URI: https://jgrants-auto-poster.pro
 * License: GPL v2 or later
 * Text Domain: jgrants-auto-poster-enhanced-pro
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// プラグイン定数定義
define('JGAP_VERSION', '1.0.0');
define('JGAP_PLUGIN_FILE', __FILE__);
define('JGAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JGAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('JGAP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// API定数定義（JグランツAPI仕様準拠）
define('JGRANTS_API_BASE_URL', 'https://api.jgrants-portal.go.jp/exp/v1/public');
define('JGRANTS_API_SUBSIDIES', '/subsidies');
define('JGRANTS_API_SUBSIDY_DETAIL', '/subsidies/id/');

// フォールバック用（管理画面オプション）
define('JGRANTS_API_BASE_URL_HTTP', 'http://api.jgrants-portal.go.jp/exp/v1/public');

// クラスファイルの自動読み込み
spl_autoload_register(function ($class) {
    if (strpos($class, 'JGrants_Enhanced_Pro_') === 0) {
        $class_name = str_replace('JGrants_Enhanced_Pro_', '', $class);
        $class_name = str_replace('_', '-', strtolower($class_name));
        $file = JGAP_PLUGIN_DIR . 'includes/class-' . $class_name . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * プラグインアクティベーションフック
 */
function jgap_activate() {
    // 必要な権限チェック
    if (!current_user_can('activate_plugins')) {
        return;
    }
    
    // PHP・WordPressバージョンチェック
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(JGAP_PLUGIN_BASENAME);
        wp_die('このプラグインはPHP 7.4以上が必要です。');
    }
    
    global $wp_version;
    if (version_compare($wp_version, '5.8', '<')) {
        deactivate_plugins(JGAP_PLUGIN_BASENAME);
        wp_die('このプラグインはWordPress 5.8以上が必要です。');
    }
    
    // カスタム投稿タイプとタクソノミーの登録
    jgap_register_post_types();
    jgap_register_taxonomies();
    
    // リライトルールをフラッシュ
    flush_rewrite_rules();
    
    // データベーステーブルの作成
    jgap_create_database_tables();
    
    // デフォルトオプションの設定
    jgap_set_default_options();
    
    // Cronジョブのセットアップ
    if (!wp_next_scheduled('jgap_cron_fetch')) {
        wp_schedule_event(time(), 'hourly', 'jgap_cron_fetch');
    }
    
    if (!wp_next_scheduled('jgap_cron_process')) {
        wp_schedule_event(time() + 300, 'jgap_custom_interval', 'jgap_cron_process');
    }
    
    // アクティベーションログ記録
    update_option('jgap_activated_at', current_time('mysql'));
    update_option('jgap_version', JGAP_VERSION);
}
register_activation_hook(__FILE__, 'jgap_activate');

/**
 * プラグインディアクティベーションフック
 */
function jgap_deactivate() {
    // Cronジョブの削除
    wp_clear_scheduled_hook('jgap_cron_fetch');
    wp_clear_scheduled_hook('jgap_cron_process');
    
    // リライトルールをフラッシュ
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'jgap_deactivate');

/**
 * プラグインアンインストールフック
 */
function jgap_uninstall() {
    // オプション削除の確認
    if (get_option('jgap_delete_data_on_uninstall', false)) {
        // オプションの削除
        $options = [
            'jgap_activated_at',
            'jgap_version',
            'jgap_use_https',
            'jgap_keywords_main',
            'jgap_keywords_exclude',
            'jgap_gemini_api_key',
            'jgap_auto_publish',
            'jgap_seo_integration',
            'jgap_acf_enabled',
            'jgap_batch_size',
            'jgap_rate_limit',
            'jgap_delete_data_on_uninstall'
        ];
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // カスタムデータベーステーブルの削除
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'jgap_fetch_logs',
            $wpdb->prefix . 'jgap_processing_queue',
            $wpdb->prefix . 'jgap_api_cache',
            $wpdb->prefix . 'jgap_performance_logs'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        // 投稿タイプ'grant'の全投稿を削除
        $grants = get_posts([
            'post_type' => 'grant',
            'numberposts' => -1,
            'post_status' => 'any'
        ]);
        
        foreach ($grants as $grant) {
            wp_delete_post($grant->ID, true);
        }
    }
}
register_uninstall_hook(__FILE__, 'jgap_uninstall');

/**
 * カスタム投稿タイプの登録
 */
function jgap_register_post_types() {
    $labels = [
        'name'                  => '補助金情報',
        'singular_name'         => '補助金',
        'menu_name'             => '補助金情報',
        'name_admin_bar'        => '補助金',
        'add_new'               => '新規追加',
        'add_new_item'          => '新規補助金を追加',
        'new_item'              => '新規補助金',
        'edit_item'             => '補助金を編集',
        'view_item'             => '補助金を表示',
        'all_items'             => 'すべての補助金',
        'search_items'          => '補助金を検索',
        'parent_item_colon'     => '親補助金:',
        'not_found'             => '補助金が見つかりません。',
        'not_found_in_trash'    => 'ゴミ箱に補助金が見つかりません。',
        'featured_image'        => 'アイキャッチ画像',
        'set_featured_image'    => 'アイキャッチ画像を設定',
        'remove_featured_image' => 'アイキャッチ画像を削除',
        'use_featured_image'    => 'アイキャッチ画像として使用',
        'archives'              => '補助金アーカイブ',
        'insert_into_item'      => '補助金に挿入',
        'uploaded_to_this_item' => 'この補助金にアップロード',
        'filter_items_list'     => '補助金リストをフィルタリング',
        'items_list_navigation' => '補助金リストナビゲーション',
        'items_list'            => '補助金リスト',
    ];

    $args = [
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => ['slug' => 'grant', 'with_front' => false],
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-money-alt',
        'supports'            => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields'],
        'show_in_rest'        => true,
        'rest_base'           => 'grants',
        'rest_controller_class' => 'WP_REST_Posts_Controller',
    ];

    register_post_type('grant', $args);
}
add_action('init', 'jgap_register_post_types');

/**
 * カスタムタクソノミーの登録
 */
function jgap_register_taxonomies() {
    // 都道府県タクソノミー
    $prefecture_labels = [
        'name'              => '都道府県',
        'singular_name'     => '都道府県',
        'search_items'      => '都道府県を検索',
        'all_items'         => 'すべての都道府県',
        'parent_item'       => '親都道府県',
        'parent_item_colon' => '親都道府県:',
        'edit_item'         => '都道府県を編集',
        'update_item'       => '都道府県を更新',
        'add_new_item'      => '新規都道府県を追加',
        'new_item_name'     => '新規都道府県名',
        'menu_name'         => '都道府県',
    ];

    $prefecture_args = [
        'hierarchical'      => true,
        'labels'            => $prefecture_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'prefecture'],
        'show_in_rest'      => true,
    ];

    register_taxonomy('prefecture', ['grant'], $prefecture_args);

    // 補助金カテゴリタクソノミー
    $category_labels = [
        'name'              => '補助金カテゴリ',
        'singular_name'     => '補助金カテゴリ',
        'search_items'      => '補助金カテゴリを検索',
        'all_items'         => 'すべての補助金カテゴリ',
        'parent_item'       => '親カテゴリ',
        'parent_item_colon' => '親カテゴリ:',
        'edit_item'         => 'カテゴリを編集',
        'update_item'       => 'カテゴリを更新',
        'add_new_item'      => '新規カテゴリを追加',
        'new_item_name'     => '新規カテゴリ名',
        'menu_name'         => '補助金カテゴリ',
    ];

    $category_args = [
        'hierarchical'      => true,
        'labels'            => $category_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => 'grant-category'],
        'show_in_rest'      => true,
    ];

    register_taxonomy('grant_category', ['grant'], $category_args);
}
add_action('init', 'jgap_register_taxonomies');

/**
 * データベーステーブルの作成
 */
function jgap_create_database_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // API取得ログテーブル
    $table_name = $wpdb->prefix . 'jgap_fetch_logs';
    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        keyword varchar(255) NOT NULL,
        results_count int(11) NOT NULL DEFAULT 0,
        status enum('success','error','partial') NOT NULL DEFAULT 'success',
        error_message text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY keyword (keyword),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta($sql);
    
    // 処理キューテーブル
    $table_name = $wpdb->prefix . 'jgap_processing_queue';
    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        jgrants_id varchar(18) NOT NULL,
        post_id bigint(20) unsigned DEFAULT NULL,
        status enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
        priority int(11) NOT NULL DEFAULT 0,
        retry_count int(11) NOT NULL DEFAULT 0,
        error_message text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        processed_at datetime DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY jgrants_id (jgrants_id),
        KEY status (status),
        KEY priority (priority)
    ) $charset_collate;";
    dbDelta($sql);
    
    // APIレスポンスキャッシュテーブル
    $table_name = $wpdb->prefix . 'jgap_api_cache';
    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        cache_key varchar(32) NOT NULL,
        cache_value longtext NOT NULL,
        expires_at datetime NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY cache_key (cache_key),
        KEY expires_at (expires_at)
    ) $charset_collate;";
    dbDelta($sql);
    
    // パフォーマンスログテーブル
    $table_name = $wpdb->prefix . 'jgap_performance_logs';
    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        operation varchar(100) NOT NULL,
        execution_time float NOT NULL,
        memory_used int(11) NOT NULL,
        success boolean NOT NULL DEFAULT TRUE,
        details text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY operation (operation),
        KEY created_at (created_at)
    ) $charset_collate;";
    dbDelta($sql);
}

/**
 * デフォルトオプションの設定
 */
function jgap_set_default_options() {
    // API設定
    add_option('jgap_use_https', true);
    
    // デフォルトキーワード設定
    $default_keywords = [
        'IT導入補助金',
        '事業再構築補助金',
        'ものづくり補助金',
        '小規模事業者持続化補助金',
        '創業支援',
        '設備整備',
        'IT導入',
        '販路拡大',
        '人材育成',
        '研究開発',
        'DX推進',
        '事業承継'
    ];
    add_option('jgap_keywords_main', implode("\n", $default_keywords));
    add_option('jgap_keywords_exclude', '');
    
    // AI設定
    add_option('jgap_gemini_api_key', '');
    
    // 投稿設定
    add_option('jgap_auto_publish', false);
    
    // SEO設定
    add_option('jgap_seo_integration', 'yoast');
    
    // ACF設定
    add_option('jgap_acf_enabled', true);
    
    // バッチ処理設定
    add_option('jgap_batch_size', 10);
    add_option('jgap_rate_limit', 2); // 秒単位
    
    // アンインストール設定
    add_option('jgap_delete_data_on_uninstall', false);
}

/**
 * カスタムCron間隔の追加
 */
function jgap_add_cron_intervals($schedules) {
    $schedules['jgap_custom_interval'] = [
        'interval' => 300, // 5分
        'display'  => '5分ごと'
    ];
    return $schedules;
}
add_filter('cron_schedules', 'jgap_add_cron_intervals');

/**
 * プラグイン初期化
 */
function jgap_init() {
    // 言語ファイルの読み込み
    load_plugin_textdomain(
        'jgrants-auto-poster-enhanced-pro',
        false,
        dirname(JGAP_PLUGIN_BASENAME) . '/languages'
    );
    
    // コアクラスのインスタンス化
    if (class_exists('JGrants_Enhanced_Pro_Core')) {
        JGrants_Enhanced_Pro_Core::get_instance();
    }
    
    // 管理画面の場合のみ管理インターフェースを読み込む
    if (is_admin() && class_exists('JGrants_Enhanced_Pro_Admin_Interface')) {
        JGrants_Enhanced_Pro_Admin_Interface::get_instance();
    }
}
add_action('plugins_loaded', 'jgap_init');

/**
 * プラグインアクションリンクの追加
 */
function jgap_add_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=jgap-settings') . '">設定</a>';
    $dashboard_link = '<a href="' . admin_url('admin.php?page=jgap-dashboard') . '">ダッシュボード</a>';
    
    array_unshift($links, $settings_link, $dashboard_link);
    
    return $links;
}
add_filter('plugin_action_links_' . JGAP_PLUGIN_BASENAME, 'jgap_add_action_links');

/**
 * プラグインメタリンクの追加
 */
function jgap_add_meta_links($links, $file) {
    if ($file === JGAP_PLUGIN_BASENAME) {
        $links[] = '<a href="https://jgrants-auto-poster.pro/docs" target="_blank">ドキュメント</a>';
        $links[] = '<a href="https://jgrants-auto-poster.pro/support" target="_blank">サポート</a>';
    }
    
    return $links;
}
add_filter('plugin_row_meta', 'jgap_add_meta_links', 10, 2);

/**
 * 管理者通知の表示
 */
function jgap_admin_notices() {
    // Gemini APIキーが設定されていない場合
    if (empty(get_option('jgap_gemini_api_key')) && current_user_can('manage_options')) {
        $settings_url = admin_url('admin.php?page=jgap-settings');
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>Jグランツ自動投稿システム Enhanced Pro:</strong> 
                AI機能を利用するためには、Gemini APIキーの設定が必要です。
                <a href="<?php echo esc_url($settings_url); ?>">設定ページ</a>から設定してください。
            </p>
        </div>
        <?php
    }
    
    // ACF Proがインストールされていない場合
    if (!class_exists('ACF') && current_user_can('manage_options')) {
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong>Jグランツ自動投稿システム Enhanced Pro:</strong> 
                高度な機能を利用するには、Advanced Custom Fields PROのインストールを推奨します。
            </p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'jgap_admin_notices');

/**
 * デバッグログ関数
 */
if (!function_exists('jgap_log')) {
    function jgap_log($message, $context = []) {
        if (WP_DEBUG && WP_DEBUG_LOG) {
            $log_message = '[JGAP] ' . $message;
            if (!empty($context)) {
                $log_message .= ' | Context: ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE);
            }
            error_log($log_message);
        }
    }
}