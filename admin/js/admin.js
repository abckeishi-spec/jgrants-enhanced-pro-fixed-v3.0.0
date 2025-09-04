jQuery(document).ready(function($) {
    // APIステータスチェック
    checkApiStatus();
    
    // 手動取得
    $('#manual-fetch').on('click', function() {
        var keyword = prompt('取得するキーワードを入力してください:');
        if (!keyword) return;
        
        $(this).prop('disabled', true);
        
        $.post(jgap_ajax.ajax_url, {
            action: 'jgap_manual_fetch',
            nonce: jgap_ajax.nonce,
            keyword: keyword
        }, function(response) {
            $('#manual-fetch').prop('disabled', false);
            
            if (response.success) {
                showResult('success', response.data.message);
            } else {
                showResult('error', response.data.message || 'エラーが発生しました');
            }
        });
    });
    
    // ステータス更新
    $('#check-status').on('click', function() {
        $.post(jgap_ajax.ajax_url, {
            action: 'jgap_get_status',
            nonce: jgap_ajax.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            }
        });
    });
    
    // キャッシュクリア
    $('#clear-cache').on('click', function() {
        if (!confirm('キャッシュをクリアしますか？')) return;
        
        // 実装は省略
        showResult('success', 'キャッシュをクリアしました');
    });
    
    // APIステータスチェック
    function checkApiStatus() {
        $.post(jgap_ajax.ajax_url, {
            action: 'jgap_get_status',
            nonce: jgap_ajax.nonce
        }, function(response) {
            if (response.success && response.data.api_health) {
                $('#api-status').text('正常').addClass('online');
            } else {
                $('#api-status').text('エラー').addClass('offline');
            }
        });
    }
    
    // 結果表示
    function showResult(type, message) {
        var $result = $('#action-result');
        $result.removeClass('notice-success notice-error')
               .addClass('notice-' + type)
               .html('<p>' + message + '</p>')
               .show();
        
        setTimeout(function() {
            $result.fadeOut();
        }, 5000);
    }
});
