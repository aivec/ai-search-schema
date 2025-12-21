#!/usr/bin/env bash

# PHPUnit 実行ヘルパー: MySQL を起動（必要に応じて）し、WP テスト環境を準備してから
# PHPUnit またはカバレッジ付きテストを実行します。macOS/Linux 開発者向け。

set -euo pipefail

DB_NAME=${DB_NAME:-root}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-root}
DB_HOST=${DB_HOST:-127.0.0.1}
WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}
AUTO_CLEAN=${AUTO_CLEAN:-0} # 1 で /tmp/wordpress* を削除
COVERAGE=${COVERAGE:-0}     # 1 で composer run test:coverage を実行

# Homebrew の MySQL クライアント/サーバーを優先して PATH に追加
for p in /usr/local/opt/mysql/bin /opt/homebrew/opt/mysql/bin /usr/local/opt/mysql-client/bin /opt/homebrew/opt/mysql-client/bin; do
    if [ -d "$p" ]; then
        PATH="$p:$PATH"
    fi
done
export PATH

if ! command -v mysql >/dev/null 2>&1; then
    echo "MySQL client not found. Install mysql/mysql-client (e.g. brew install mysql mysql-client)." >&2
    exit 1
fi

mysql_running_before=0
if command -v brew >/dev/null 2>&1; then
    if brew services list 2>/dev/null | grep -q '^mysql\s\+started'; then
        mysql_running_before=1
        echo "MySQL already running (brew services)."
    else
        echo "Starting MySQL via brew services..."
        brew services start mysql >/dev/null
    fi
else
    echo "Homebrew not found; please ensure MySQL server is running." >&2
fi

cleanup() {
    if [ "$mysql_running_before" -eq 0 ] && command -v brew >/dev/null 2>&1; then
        echo "Stopping MySQL (started by this script)..."
        brew services stop mysql >/dev/null || true
    fi
    if [ "$AUTO_CLEAN" != "0" ]; then
        echo "Cleaning up ${WP_CORE_DIR} and ${WP_TESTS_DIR}"
        rm -rf "$WP_CORE_DIR" "$WP_TESTS_DIR"
    fi
}
trap cleanup EXIT

host="$DB_HOST"
port=""
if [[ "$DB_HOST" == *:* ]]; then
    host="${DB_HOST%%:*}"
    port="${DB_HOST##*:}"
fi

echo "Waiting for MySQL at ${host}${port:+:$port}..."
retries=15
until MYSQL_PWD="$DB_PASS" mysqladmin --protocol=TCP -h "$host" ${port:+-P "$port"} -u "$DB_USER" ping >/dev/null 2>&1; do
    retries=$((retries - 1))
    if [ "$retries" -le 0 ]; then
        echo "MySQL is not reachable at ${host}${port:+:$port}. Is the server running and credentials correct?" >&2
        exit 1
    fi
    sleep 2
done
echo "MySQL is up."

WP_TESTS_DIR="$WP_TESTS_DIR" WP_CORE_DIR="$WP_CORE_DIR" bin/install-wp-tests.sh "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST"

if [ "$COVERAGE" != "0" ]; then
    echo "Running PHPUnit with coverage..."
    XDEBUG_MODE=coverage composer run test:coverage
else
    echo "Running PHPUnit..."
    composer test
fi

echo "Test flow finished."
