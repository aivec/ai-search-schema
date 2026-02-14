#!/usr/bin/env bash

set -euo pipefail

usage() {
    echo "Usage: $0 root root root [db-host] [wp-version]" >&2
    exit 1
}

DB_NAME=${1:-root}
DB_USER=${2:-root}
DB_PASS=${3:-root}
DB_HOST=${4:-localhost}
REQUESTED_WP_VERSION=${5:-latest}
WP_VERSION=${WP_VERSION:-$REQUESTED_WP_VERSION}

if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
    usage
fi

WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}

download() {
    local url="$1"

    if command -v curl >/dev/null 2>&1; then
        curl -sSL "$url"
    elif command -v wget >/dev/null 2>&1; then
        wget -q -O - "$url"
    else
        echo "Error: curl or wget is required to download files." >&2
        exit 1
    fi
}

php_escape() {
    php -r "echo addcslashes(\$argv[1], \"'\\\\\");" "$1"
}

install_wp() {
    if [ -f "${WP_CORE_DIR}/wp-load.php" ]; then
        echo "WordPress already exists at ${WP_CORE_DIR}; skipping download."
        return
    fi

    mkdir -p "$WP_CORE_DIR"

    local archive_url="https://wordpress.org/latest.tar.gz"
    if [ "$WP_VERSION" != "latest" ]; then
        archive_url="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
    fi

    echo "Downloading WordPress (${WP_VERSION})..."
    download "$archive_url" | tar --strip-components=1 -xz -C "$WP_CORE_DIR"
    echo "WordPress extracted to ${WP_CORE_DIR}"
}

install_test_suite() {
    if [ -f "${WP_TESTS_DIR}/includes/functions.php" ]; then
        echo "WordPress test suite already exists at ${WP_TESTS_DIR}; skipping download."
        return
    fi

    rm -rf "$WP_TESTS_DIR"
    mkdir -p "$WP_TESTS_DIR"

    local tmpdir
    tmpdir="$(mktemp -d)"
    local checkout_dir="${tmpdir}/wp-tests"
    local tests_tag="trunk"
    if [ "$WP_VERSION" != "latest" ]; then
        tests_tag="tags/${WP_VERSION}"
    fi

    local use_github=false
    if command -v svn >/dev/null 2>&1; then
        echo "Fetching WordPress tests from develop.svn.wordpress.org/${tests_tag}"
        if ! svn export --force --quiet --ignore-externals "https://develop.svn.wordpress.org/${tests_tag}/tests/phpunit" "$checkout_dir"; then
            echo "svn export failed; falling back to GitHub archive." >&2
            use_github=true
        fi
    else
        echo "svn not found; downloading tests from GitHub archive."
        use_github=true
    fi

    if [ "$use_github" = true ]; then
        local ref="heads/trunk"
        if [ "$tests_tag" != "trunk" ]; then
            ref="tags/${WP_VERSION}"
        fi

        local archive_url="https://github.com/WordPress/wordpress-develop/archive/refs/${ref}.tar.gz"

        download "$archive_url" | tar -xz -C "$tmpdir"

        local extracted
        extracted="$(find "$tmpdir" -maxdepth 1 -type d -name 'wordpress-develop-*' -print -quit)"
        if [ -z "$extracted" ] || [ ! -d "$extracted/tests/phpunit" ]; then
            echo "Could not locate tests/phpunit in the downloaded archive." >&2
            exit 1
        fi

        checkout_dir="$extracted/tests/phpunit"
    fi

    if [ ! -d "$checkout_dir" ]; then
        echo "Temporary checkout of WordPress tests not found at ${checkout_dir}." >&2
        exit 1
    fi

    cp -R "${checkout_dir}/." "$WP_TESTS_DIR"
    rm -rf "$tmpdir"
}

create_db() {
    if ! command -v mysql >/dev/null 2>&1; then
        echo "mysql client not found; please create database '${DB_NAME}' manually." >&2
        return
    fi

    local host="$DB_HOST"
    local port=""

    if [[ "$DB_HOST" == *:* ]]; then
        host="${DB_HOST%%:*}"
        port="${DB_HOST##*:}"
    fi

    local mysql_cmd=(mysql -u"$DB_USER" -h "$host")
    if [ -n "$port" ]; then
        mysql_cmd+=(-P "$port")
    fi
    if [ -n "$DB_PASS" ]; then
        mysql_cmd+=(-p"$DB_PASS")
    fi

    "${mysql_cmd[@]}" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
        || {
            echo "Could not create database '${DB_NAME}'. Please check credentials." >&2
            exit 1
        }
}

create_config() {
    local config_path="${WP_TESTS_DIR}/wp-tests-config.php"

    local db_name_esc db_user_esc db_pass_esc db_host_esc core_dir
    db_name_esc="$(php_escape "$DB_NAME")"
    db_user_esc="$(php_escape "$DB_USER")"
    db_pass_esc="$(php_escape "$DB_PASS")"
    db_host_esc="$(php_escape "$DB_HOST")"
    core_dir="$(php_escape "${WP_CORE_DIR%/}")"
    local php_binary
    php_binary="$(php_escape "${PHP_BINARY:-php}")"

    local needs_write=true
    if [ -f "$config_path" ]; then
        if grep -q "WP_TESTS_DOMAIN" "$config_path" && grep -q "WP_PHP_BINARY" "$config_path"; then
            needs_write=false
        fi
    fi

    if [ "$needs_write" = false ]; then
        echo "Config already present at ${config_path}; leaving untouched."
        return
    fi

    cat > "$config_path" <<PHP
<?php
define( 'DB_NAME', '${db_name_esc}' );
define( 'DB_USER', '${db_user_esc}' );
define( 'DB_PASSWORD', '${db_pass_esc}' );
define( 'DB_HOST', '${db_host_esc}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

\$table_prefix = 'wptests_';

define( 'WP_DEBUG', true );
define( 'ABSPATH', '${core_dir}/' );
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', '${php_binary}' );
PHP

    echo "Created wp-tests-config.php at ${config_path}"
}

install_wp
install_test_suite
create_db
create_config

echo "WordPress test environment ready."
