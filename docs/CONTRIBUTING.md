# Contributing Guide / 開発貢献ガイド

このドキュメントは、AVC AEO Schema の開発プロセス（CI手順、リリース手順、テスト実行手順）を説明します。

---

## Table of Contents

1. [開発環境セットアップ](#開発環境セットアップ)
2. [テスト実行手順](#テスト実行手順)
3. [CI/CD パイプライン](#cicd-パイプライン)
4. [リリース手順](#リリース手順)
5. [コーディング規約](#コーディング規約)
6. [トラブルシューティング](#トラブルシューティング)

---

## 開発環境セットアップ

### 前提条件

| ツール | バージョン | 用途 |
|--------|-----------|------|
| PHP | 8.0 以上 | 必須 |
| Node.js | 20.x | アセットビルド |
| Composer | 2.x | PHP依存管理 |
| MySQL | 8.0 | テスト用DB |

### 初期セットアップ

```bash
# リポジトリをクローン
git clone https://github.com/your-org/avc-aeo-schema.git
cd avc-aeo-schema

# PHP依存をインストール
composer install

# Node依存をインストール
npm install

# アセットをビルド
npm run build
```

### MySQL セットアップ（macOS）

```bash
# Homebrew でインストール
brew install mysql mysql-client

# PATH に追加（~/.zshrc または ~/.bashrc）
export PATH="/usr/local/opt/mysql/bin:/usr/local/opt/mysql-client/bin:$PATH"

# MySQL を起動
brew services start mysql

# （オプション）root パスワードを設定
mysql -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED BY 'root';"
```

---

## テスト実行手順

### クイックスタート（推奨）

一括実行スクリプトを使用：

```bash
# MySQL 起動 → WP テスト環境準備 → PHPUnit 実行
bin/test-with-env.sh

# カバレッジ付きで実行
COVERAGE=1 bin/test-with-env.sh
```

### 手動実行

#### 1. WordPress テスト環境をセットアップ

```bash
# MySQL が起動していることを確認
brew services list | grep mysql

# WP テストライブラリとコアをインストール
bin/install-wp-tests.sh wordpress root root 127.0.0.1:3306 latest
```

**引数の説明:**
| 引数 | デフォルト | 説明 |
|------|-----------|------|
| DB_NAME | wordpress | テスト用データベース名 |
| DB_USER | root | MySQL ユーザー名 |
| DB_PASS | root | MySQL パスワード |
| DB_HOST | 127.0.0.1:3306 | MySQL ホスト:ポート |
| WP_VERSION | latest | WordPress バージョン |

**インストール先:**
- `WP_TESTS_DIR=/tmp/wordpress-tests-lib` - テストライブラリ
- `WP_CORE_DIR=/tmp/wordpress` - WordPress コア

#### 2. テストを実行

```bash
# 全テストを実行
composer test

# 特定のテストファイルを実行
./vendor/bin/phpunit tests/phpunit/SettingsTest.php

# 特定のテストメソッドを実行
./vendor/bin/phpunit --filter test_get_options_returns_defaults
```

#### 3. カバレッジレポート生成

```bash
# Xdebug が必要
XDEBUG_MODE=coverage composer run test:coverage
```

**出力先:** `build/coverage/`
- `index.html` - HTML レポート
- `clover.xml` - Clover 形式
- `junit.xml` - JUnit 形式

### テストファイル構成

```
tests/phpunit/
├── bootstrap.php           # テストブートストラップ
├── mocks.php              # モック関数定義
├── PluginTest.php         # Plugin クラステスト
├── SettingsTest.php       # Settings クラステスト
├── BreadcrumbsTest.php    # パンくずテスト
├── WizardAjaxTest.php     # Wizard AJAX テスト
├── WizardImporterTest.php # インポーター テスト
├── SchemaGraphBuilderTest.php  # スキーマ生成テスト
└── ...
```

### 環境変数オプション

| 変数 | デフォルト | 説明 |
|------|-----------|------|
| `DB_NAME` | wordpress | テストDB名 |
| `DB_USER` | root | DBユーザー |
| `DB_PASS` | root | DBパスワード |
| `DB_HOST` | 127.0.0.1 | DBホスト |
| `WP_TESTS_DIR` | /tmp/wordpress-tests-lib | テストライブラリパス |
| `WP_CORE_DIR` | /tmp/wordpress | WordPress コアパス |
| `COVERAGE` | 0 | 1でカバレッジ生成 |
| `AUTO_CLEAN` | 0 | 1で終了時に/tmp/wordpress*を削除 |

---

## CI/CD パイプライン

### ワークフロー概要

```
┌─────────────────────────────────────────────────────────────┐
│                    GitHub Actions CI/CD                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  push/PR (main, dev, v1.0.0)                                │
│       │                                                      │
│       ▼                                                      │
│  ┌─────────────┐                                            │
│  │ ci.yml      │                                            │
│  ├─────────────┤                                            │
│  │ 1. readme-sync   - README/readme.txt 同期確認            │
│  │ 2. lint          - PHPCS コード検査                      │
│  │ 3. test          - PHPUnit (PHP 8.0-8.4)                │
│  │ 4. coverage      - カバレッジレポート生成                 │
│  └──────┬──────┘                                            │
│         │                                                    │
│         │ CI 成功 (main ブランチのみ)                        │
│         ▼                                                    │
│  ┌──────────────────┐                                       │
│  │ build-release.yml │                                       │
│  ├──────────────────┤                                       │
│  │ 1. Build assets                                          │
│  │ 2. Create ZIP                                            │
│  │ 3. Upload to S3                                          │
│  │ 4. Create GitHub Release (タグ時)                        │
│  └──────────────────┘                                       │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### ci.yml - 継続的インテグレーション

**トリガー:**
- `push` → main, dev, v1.0.0 ブランチ
- `pull_request` → main, dev, v1.0.0 ブランチ

**ジョブフロー:**

```yaml
jobs:
  readme-sync:     # README 同期確認
    ↓
  lint:            # PHPCS コード検査 (readme-sync に依存)
    ↓
  test:            # PHPUnit テスト (lint に依存)
    - PHP 8.0, 8.1, 8.2, 8.3, 8.4 でマトリクス実行
    - MySQL 8.0 サービスコンテナ使用
    ↓
  coverage:        # カバレッジレポート (test に依存)
    - Xdebug 有効
    - build/coverage をアーティファクトとして保存
```

**各ジョブの詳細:**

| ジョブ | 処理内容 | 失敗時の対処 |
|--------|---------|-------------|
| `readme-sync` | `composer readme:sync` でREADME生成、`git diff --exit-code` で差分確認 | `composer readme:sync` を実行してコミット |
| `lint` | `composer run lint` でPHPCS実行 | `./vendor/bin/phpcbf` で自動修正、または手動修正 |
| `test` | `composer test` でPHPUnit実行 | テスト失敗箇所を修正 |
| `coverage` | `composer test:coverage` でカバレッジ生成 | coverage ジョブは test 成功後のみ実行 |

### build-release.yml - リリースビルド

**トリガー:**
- `workflow_run` → CI 成功後 (main ブランチのみ)
- `push` → `v*` タグ
- `workflow_dispatch` → 手動実行

**処理内容:**
1. PHP/Node セットアップ
2. Composer 本番依存インストール (`--no-dev`)
3. npm アセットビルド
4. リリース ZIP 作成（開発ファイル除外）
5. AWS S3 アップロード
6. GitHub Release 作成（タグ時のみ）

**必要な GitHub Secrets:**
| Secret | 説明 |
|--------|------|
| `AWS_ACCESS_KEY_ID` | AWS アクセスキー |
| `AWS_SECRET_ACCESS_KEY` | AWS シークレットキー |
| `AWS_S3_BUCKET` | S3 バケット名 |
| `AWS_REGION` | AWS リージョン |

### CI 失敗時のトラブルシューティング

#### readme-sync 失敗

```bash
# ローカルで同期を実行
composer readme:sync

# 差分を確認してコミット
git diff README.md readme.txt
git add README.md readme.txt
git commit -m "docs: README/readme.txt を同期"
```

#### lint 失敗

```bash
# エラー詳細を確認
composer run lint

# 自動修正を試行
./vendor/bin/phpcbf

# 残りは手動修正
```

#### test 失敗

```bash
# ローカルで再現
bin/test-with-env.sh

# 特定のテストを実行
./vendor/bin/phpunit --filter TestName
```

---

## リリース手順

### バージョニング規則

[セマンティックバージョニング](https://semver.org/lang/ja/) に従います：

- **MAJOR** (1.0.0): 互換性のない変更
- **MINOR** (0.1.0): 後方互換性のある機能追加
- **PATCH** (0.0.1): 後方互換性のあるバグ修正

### リリースフロー

```
┌─────────────────────────────────────────────────────────────┐
│                     リリースフロー                           │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. 準備                                                     │
│     ├── バージョン番号を決定                                 │
│     ├── リリースノート作成 (docs/release-notes/vX.Y.Z.md)   │
│     └── CHANGELOG 更新                                      │
│                                                              │
│  2. バージョン更新                                           │
│     ├── avc-aeo-schema.php の Version を更新                │
│     ├── readme.txt の Stable tag を更新                     │
│     └── package.json の version を更新                      │
│                                                              │
│  3. ビルド＆テスト                                           │
│     ├── npm run release                                     │
│     │   (build → lint → test → i18n → zip)                 │
│     └── ZIP ファイルを検証                                   │
│                                                              │
│  4. コミット＆タグ                                           │
│     ├── git add -A && git commit                            │
│     ├── git tag vX.Y.Z                                      │
│     └── git push origin main --tags                         │
│                                                              │
│  5. 自動リリース                                             │
│     ├── CI が成功後 build-release.yml が実行                │
│     ├── S3 にアップロード                                   │
│     └── GitHub Release 作成                                 │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### 詳細手順

#### 1. 準備

```bash
# 最新の main を取得
git checkout main
git pull origin main

# リリースノートを作成
touch docs/release-notes/v0.19.0.md
# エディタでリリースノートを記入
```

**リリースノート テンプレート:**

```markdown
# v0.19.0

リリース日: 2025-12-15

## 新機能
- 機能A を追加

## 改善
- 機能B のパフォーマンス向上

## バグ修正
- 問題C を修正

## 破壊的変更
- なし
```

#### 2. バージョン更新

以下の3ファイルのバージョンを更新：

**avc-aeo-schema.php:**
```php
* Version:           0.19.0
```

**readme.txt:**
```
Stable tag: 0.19.0
```

**package.json:**
```json
"version": "0.19.0"
```

#### 3. CHANGELOG 更新

```bash
# リリースノートから readme.txt の Changelog を自動生成
composer changelog:generate

# README も同期
composer readme:sync
```

#### 4. ビルド＆テスト

**ローカル環境（推奨）:**

```bash
# MySQL 起動 + WP テスト環境 + npm run release を一括実行
composer release:env
```

**または手動:**

```bash
# ビルド → lint → test → i18n → zip
npm run release

# 出力を確認
ls -la bundled/AVC-AEO-Schema-*.zip
ls -la dist/
```

#### 5. コミット＆タグ

```bash
# 全変更をステージング
git add -A

# コミット
git commit -m "release: v0.19.0"

# タグ作成
git tag v0.19.0

# プッシュ
git push origin main --tags
```

#### 6. 検証

1. [GitHub Actions](https://github.com/your-org/avc-aeo-schema/actions) で CI が成功することを確認
2. build-release ワークフローが実行されることを確認
3. [GitHub Releases](https://github.com/your-org/avc-aeo-schema/releases) にリリースが作成されることを確認
4. S3 にファイルがアップロードされることを確認

### ホットフィックスリリース

緊急バグ修正の場合：

```bash
# main から hotfix ブランチを作成
git checkout main
git checkout -b hotfix/v0.18.3

# 修正を実施
# ...

# main にマージ
git checkout main
git merge hotfix/v0.18.3

# 通常のリリース手順を実行
```

---

## コーディング規約

### PHP

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) に準拠
- PHPCS 設定: `phpcs.xml`

```bash
# コード検査
composer run lint

# 自動修正
./vendor/bin/phpcbf
```

### SCSS/CSS

- BEM 命名規則を推奨
- Sass 変数を活用

```bash
# SCSS ビルド
npm run build

# 開発時の監視モード
npm run dev
```

### JavaScript

- ES6+ 構文を使用
- WordPress の依存管理に従う

### コミットメッセージ

[Conventional Commits](https://www.conventionalcommits.org/ja/) に従います：

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

**Type:**
- `feat`: 新機能
- `fix`: バグ修正
- `docs`: ドキュメント
- `style`: コードスタイル（機能変更なし）
- `refactor`: リファクタリング
- `test`: テスト追加・修正
- `chore`: ビルド・ツール変更
- `ci`: CI設定変更
- `release`: リリース

**例:**
```
feat(wizard): ステップ間のデータ引き継ぎを追加

Wizard の各ステップで入力したデータを
次のステップに引き継げるようになりました。

Closes #123
```

---

## トラブルシューティング

### テスト環境

#### MySQL 接続エラー

```
Error: SQLSTATE[HY000] [2002] No such file or directory
```

**解決策:**
```bash
# MySQL が起動しているか確認
brew services list | grep mysql

# 起動
brew services start mysql

# ソケットパスを確認
mysql_config --socket
```

#### WP テストライブラリが見つからない

```
Error: WP_TESTS_DIR not set
```

**解決策:**
```bash
# テスト環境を再インストール
rm -rf /tmp/wordpress /tmp/wordpress-tests-lib
bin/install-wp-tests.sh wordpress root root 127.0.0.1:3306
```

### CI/CD

#### Artifact not found エラー

```
Error: Artifact not found for name: avc-aeo-schema-vX.Y.Z
```

**原因:** `create-release` ジョブがバージョン取得に失敗

**解決策:**
1. `avc-aeo-schema.php` の Version ヘッダーを確認
2. 手動で GitHub Release を作成

#### README 同期失敗

**解決策:**
```bash
composer readme:sync
git add README.md readme.txt
git commit -m "docs: README/readme.txt を同期"
git push
```

### ビルド

#### npm run build 失敗

**解決策:**
```bash
# node_modules を再インストール
rm -rf node_modules
npm install

# キャッシュクリア
npm cache clean --force
```

#### Composer 依存エラー

**解決策:**
```bash
# vendor を再インストール
rm -rf vendor
composer install
```

---

## 関連ドキュメント

- [README.md](../README.md) - プロジェクト概要
- [ARCHITECTURE.md](../.ai/ARCHITECTURE.md) - 設計・アーキテクチャ
- [developer-hooks.md](./developer-hooks.md) - フック/フィルター リファレンス
- [quick-start.md](./quick-start.md) - ユーザー向けセットアップ
- [FAQ.md](./FAQ.md) - よくある質問
