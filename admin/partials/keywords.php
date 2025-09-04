<?php
if (!defined('ABSPATH')) exit;

if (isset($_POST['save_keywords']) && wp_verify_nonce($_POST['jgap_keywords_nonce'], 'save_keywords')) {
    update_option('jgap_keywords_main', sanitize_textarea_field($_POST['keywords_main']));
    update_option('jgap_keywords_exclude', sanitize_textarea_field($_POST['keywords_exclude']));
    echo '<div class="notice notice-success"><p>キーワードを保存しました。</p></div>';
}

$keywords_main = get_option('jgap_keywords_main', '');
$keywords_exclude = get_option('jgap_keywords_exclude', '');
?>

<div class="wrap">
    <h1>キーワード設定</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('save_keywords', 'jgap_keywords_nonce'); ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="keywords_main">検索キーワード</label>
                </th>
                <td>
                    <textarea name="keywords_main" id="keywords_main" rows="10" cols="50" class="large-text"><?php echo esc_textarea($keywords_main); ?></textarea>
                    <p class="description">1行に1つのキーワードを入力してください。</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="keywords_exclude">除外キーワード</label>
                </th>
                <td>
                    <textarea name="keywords_exclude" id="keywords_exclude" rows="5" cols="50" class="large-text"><?php echo esc_textarea($keywords_exclude); ?></textarea>
                    <p class="description">このキーワードを含む補助金は取得しません。</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" name="save_keywords" class="button button-primary">変更を保存</button>
        </p>
    </form>
</div>
