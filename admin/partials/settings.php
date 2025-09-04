<?php
if (!defined('ABSPATH')) exit;

if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['jgap_settings_nonce'], 'save_settings')) {
    update_option('jgap_use_https', isset($_POST['use_https']));
    update_option('jgap_gemini_api_key', sanitize_text_field($_POST['gemini_api_key']));
    update_option('jgap_auto_publish', isset($_POST['auto_publish']));
    update_option('jgap_seo_integration', sanitize_text_field($_POST['seo_integration']));
    update_option('jgap_batch_size', intval($_POST['batch_size']));
    update_option('jgap_rate_limit', intval($_POST['rate_limit']));
    
    echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
}

$use_https = get_option('jgap_use_https', true);
$gemini_api_key = get_option('jgap_gemini_api_key', '');
$auto_publish = get_option('jgap_auto_publish', false);
$seo_integration = get_option('jgap_seo_integration', 'yoast');
$batch_size = get_option('jgap_batch_size', 10);
$rate_limit = get_option('jgap_rate_limit', 2);
?>

<div class="wrap">
    <h1>設定</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('save_settings', 'jgap_settings_nonce'); ?>
        
        <h2>API設定</h2>
        <table class="form-table">
            <tr>
                <th scope="row">HTTPS使用</th>
                <td>
                    <label>
                        <input type="checkbox" name="use_https" <?php checked($use_https); ?>>
                        APIアクセスにHTTPSを使用する
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="gemini_api_key">Gemini APIキー</label>
                </th>
                <td>
                    <input type="text" name="gemini_api_key" id="gemini_api_key" value="<?php echo esc_attr($gemini_api_key); ?>" class="regular-text">
                    <p class="description">AI機能を使用する場合は入力してください。</p>
                </td>
            </tr>
        </table>
        
        <h2>投稿設定</h2>
        <table class="form-table">
            <tr>
                <th scope="row">自動公開</th>
                <td>
                    <label>
                        <input type="checkbox" name="auto_publish" <?php checked($auto_publish); ?>>
                        取得した補助金を自動的に公開する
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">SEO統合</th>
                <td>
                    <select name="seo_integration">
                        <option value="yoast" <?php selected($seo_integration, 'yoast'); ?>>Yoast SEO</option>
                        <option value="rankmath" <?php selected($seo_integration, 'rankmath'); ?>>RankMath</option>
                        <option value="none" <?php selected($seo_integration, 'none'); ?>>使用しない</option>
                    </select>
                </td>
            </tr>
        </table>
        
        <h2>処理設定</h2>
        <table class="form-table">
            <tr>
                <th scope="row">バッチサイズ</th>
                <td>
                    <input type="number" name="batch_size" value="<?php echo esc_attr($batch_size); ?>" min="1" max="50">
                    <p class="description">一度に処理する件数</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">レート制限</th>
                <td>
                    <input type="number" name="rate_limit" value="<?php echo esc_attr($rate_limit); ?>" min="1" max="10"> 秒
                    <p class="description">API呼び出し間隔</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" name="save_settings" class="button button-primary">変更を保存</button>
        </p>
    </form>
</div>
