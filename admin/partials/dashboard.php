<?php
if (!defined('ABSPATH')) exit;

$core = JGrants_Enhanced_Pro_Core::get_instance();
$batch_scheduler = $core->get_batch_scheduler();
$performance_monitor = $core->get_performance_monitor();

$queue_count = $batch_scheduler->get_queue_count();
$pending_count = $batch_scheduler->get_pending_count();
$stats = $performance_monitor->get_stats('7days');
?>

<div class="wrap jgap-dashboard">
    <h1><span class="dashicons dashicons-money-alt"></span> Jグランツ自動投稿システム</h1>
    
    <div class="jgap-stats-grid">
        <div class="jgap-stat-card">
            <h3>処理待ち</h3>
            <div class="stat-number"><?php echo number_format($pending_count); ?></div>
            <div class="stat-label">件</div>
        </div>
        
        <div class="jgap-stat-card">
            <h3>全キュー数</h3>
            <div class="stat-number"><?php echo number_format($queue_count); ?></div>
            <div class="stat-label">件</div>
        </div>
        
        <div class="jgap-stat-card">
            <h3>最終取得</h3>
            <div class="stat-text"><?php echo get_option('jgap_last_fetch_time', '未実行'); ?></div>
        </div>
        
        <div class="jgap-stat-card">
            <h3>APIステータス</h3>
            <div class="stat-status" id="api-status">確認中...</div>
        </div>
    </div>
    
    <div class="jgap-actions">
        <h2>アクション</h2>
        <button class="button button-primary" id="manual-fetch">手動取得</button>
        <button class="button" id="check-status">ステータス更新</button>
        <button class="button button-secondary" id="clear-cache">キャッシュクリア</button>
        
        <div id="action-result" class="notice" style="display:none;"></div>
    </div>
    
    <?php if ($stats): ?>
    <div class="jgap-performance">
        <h2>パフォーマンス（過去7日間）</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>オペレーション</th>
                    <th>実行回数</th>
                    <th>平均時間</th>
                    <th>最大時間</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats as $stat): ?>
                <tr>
                    <td><?php echo esc_html($stat->operation); ?></td>
                    <td><?php echo number_format($stat->count); ?></td>
                    <td><?php echo number_format($stat->avg_time, 2); ?>秒</td>
                    <td><?php echo number_format($stat->max_time, 2); ?>秒</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
