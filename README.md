# Jグランツ自動投稿システム Enhanced Pro

## 概要
JグランツAPIから補助金情報を自動取得し、AI処理を経てWordPressに高品質な記事として投稿するプロフェッショナルプラグインです。

## 主な機能
- **JグランツAPI完全準拠**: Swagger仕様書に100%準拠した実装
- **戦略的キーワード管理**: 効果的なキーワードによる自動取得
- **AI統合**: Gemini AIによるコンテンツ強化
- **SEO最適化**: Yoast SEO/RankMath完全対応
- **ACF Pro統合**: 高度なカスタムフィールド管理
- **バッチ処理**: 効率的な大量データ処理
- **パフォーマンス監視**: リアルタイム処理状況確認

## 必要環境
- WordPress 5.8以上
- PHP 7.4以上
- MySQL 5.7以上

## インストール
1. プラグインファイルを `/wp-content/plugins/` にアップロード
2. WordPressの管理画面でプラグインを有効化
3. 「Jグランツ投稿」メニューから設定

## 初期設定
1. キーワード設定
2. Gemini APIキー設定（オプション）
3. SEO統合設定
4. バッチ処理設定

## 使用方法
### 自動取得
- Cronジョブにより自動的に実行されます
- デフォルトは1時間ごと

### 手動取得
- ダッシュボードから「手動取得」ボタンをクリック
- キーワードを入力して実行

## API仕様準拠
- 必須パラメータ：keyword, sort, order, acceptance
- オプションパラメータ：use_purpose, industry, target_number_of_employees, target_area_search
- レート制限：2秒間隔（設定可能）

## サポート
https://jgrants-auto-poster.pro/support

## ライセンス
GPL v2 or later
