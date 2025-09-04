<?php
/**
 * 管理画面インターフェースクラス
 * @package JGrants_Auto_Poster_Enhanced_Pro
 */

if (!defined('ABSPATH')) exit;

class JGrants_Enhanced_Pro_Admin_Interface {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * 管理メニュー追加
     */
    public function add_admin_menu() {
        add_menu_page(
            'Jグランツ自動投稿',
            'Jグランツ投稿',
            'manage_options',
            'jgap-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-money-alt',
            30
        );
        
        add_submenu_page(
            'jgap-dashboard',
            'ダッシュボード',
            'ダッシュボード',
            'manage_options',
            'jgap-dashboard',
            [$this, 'render_dashboard']
        );
        
        add_submenu_page(
            'jgap-dashboard',
            'キーワード設定',
            'キーワード設定',
            'manage_options',
            'jgap-keywords',
            [$this, 'render_keywords']
        );
        
        add_submenu_page(
            'jgap-dashboard',
            '設定',
            '設定',
            'manage_options',
            'jgap-settings',
            [$this, 'render_settings']
        );
    }
    
    /**
     * 管理画面アセット読み込み
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'jgap-') === false) {
            return;
        }
        
        wp_enqueue_style('jgap-admin', JGAP_PLUGIN_URL . 'admin/css/admin.css', [], JGAP_VERSION);
        wp_enqueue_script('jgap-admin', JGAP_PLUGIN_URL . 'admin/js/admin.js', ['jquery'], JGAP_VERSION, true);
        
        wp_localize_script('jgap-admin', 'jgap_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('jgap_ajax_nonce')
        ]);
    }
    
    /**
     * ダッシュボード表示
     */
    public function render_dashboard() {
        include JGAP_PLUGIN_DIR . 'admin/partials/dashboard.php';
    }
    
    /**
     * キーワード設定表示
     */
    public function render_keywords() {
        include JGAP_PLUGIN_DIR . 'admin/partials/keywords.php';
    }
    
    /**
     * 設定画面表示
     */
    public function render_settings() {
        include JGAP_PLUGIN_DIR . 'admin/partials/settings.php';
    }
}
