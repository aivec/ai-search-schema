<?php
/**
 * AI Search Schema - テスト管理ツール
 *
 * WordPress管理画面に統合されたテスト実行・スクリーンショット管理ツール
 *
 * @package AI_Search_Schema
 * @subpackage Tools
 */

// WordPress環境チェック
if ( ! defined( 'ABSPATH' ) ) {
	// 直接アクセス時はWordPressをロード
	$wp_load_paths = array(
		dirname( __DIR__, 4 ) . '/wp-load.php',
		dirname( __DIR__, 5 ) . '/wp-load.php',
	);

	if ( isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
		// WordPress未ロード時は基本的なサニタイズを実施（後続でWordPressをロード）
		$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : '';
		$document_root = function_exists( 'wp_unslash' ) ? wp_unslash( $document_root ) : stripslashes( $document_root );
		$document_root = function_exists( 'sanitize_text_field' )
			? sanitize_text_field( $document_root )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: filter_var( $document_root, FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		$wp_load_paths[] = rtrim( $document_root, '/\\' ) . '/wp-load.php';
	}

	$wp_loaded = false;
	foreach ( $wp_load_paths as $wp_load_path ) {
		if ( file_exists( $wp_load_path ) ) {
			require_once $wp_load_path;
			$wp_loaded = true;
			break;
		}
	}

	if ( ! $wp_loaded ) {
		die( 'WordPress not found. Please access this tool from the WordPress admin menu.' );
	}
}

// 権限チェック
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die(
		esc_html__( 'You do not have sufficient permissions to access this page.', 'ai-search-schema' ),
		esc_html__( 'Access Denied', 'ai-search-schema' ),
		array( 'response' => 403 )
	);
}

// Nonceチェック（POST時）
if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	$nonce = isset( $_POST['ai_search_schema_test_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['ai_search_schema_test_nonce'] ) )
		: '';
	if ( ! wp_verify_nonce( $nonce, 'ai_search_schema_test_manager' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'ai-search-schema' ) );
	}
}

// テストデータの保存/読み込み
$test_data_option = 'ai_search_schema_test_data';
$test_data        = get_option( $test_data_option, array() );

// AJAXハンドラー：テストデータ保存
$post_action = isset( $_POST['action'] )
	? sanitize_text_field( wp_unslash( $_POST['action'] ) )
	: '';

if ( 'save_test_data' === $post_action ) {
	$json_data = isset( $_POST['test_data'] ) ? wp_unslash( $_POST['test_data'] ) : '';
	$decoded   = json_decode( $json_data, true );

	if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
		update_option( $test_data_option, $decoded );
		wp_send_json_success(
			array( 'message' => __( 'Data saved successfully.', 'ai-search-schema' ) )
		);
	} else {
		wp_send_json_error(
			array( 'message' => __( 'Invalid JSON data.', 'ai-search-schema' ) )
		);
	}

	wp_die();
}

// スクリーンショットアップロード処理
if ( 'upload_screenshot' === $post_action ) {
	if ( ! empty( $_FILES['screenshot'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload_overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'gif'      => 'image/gif',
				'webp'     => 'image/webp',
			),
		);

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$uploaded = wp_handle_upload( $_FILES['screenshot'], $upload_overrides );

		if ( isset( $uploaded['error'] ) ) {
			wp_send_json_error( array( 'message' => $uploaded['error'] ) );
		} else {
			$file_name = isset( $_FILES['screenshot']['name'] )
				? sanitize_file_name( wp_unslash( $_FILES['screenshot']['name'] ) )
				: 'screenshot';

			$attachment = array(
				'post_mime_type' => $uploaded['type'],
				'post_title'     => $file_name,
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			$attach_id = wp_insert_attachment( $attachment, $uploaded['file'] );

			if ( ! is_wp_error( $attach_id ) ) {
				$attach_data = wp_generate_attachment_metadata( $attach_id, $uploaded['file'] );
				wp_update_attachment_metadata( $attach_id, $attach_data );

				wp_send_json_success(
					array(
						'message'       => __( 'Screenshot uploaded.', 'ai-search-schema' ),
						'attachment_id' => $attach_id,
						'url'           => $uploaded['url'],
					)
				);
			} else {
				wp_send_json_error(
					array( 'message' => __( 'Failed to create attachment.', 'ai-search-schema' ) )
				);
			}
		}
	} else {
		wp_send_json_error(
			array( 'message' => __( 'No file uploaded.', 'ai-search-schema' ) )
		);
	}

	wp_die();
}

// AJAXハンドラー：自動判定
if ( 'auto_judge' === $post_action ) {
	// Diagnostic Evaluator をロード
	$evaluator_file = dirname( __DIR__ ) . '/includes/class-ai-search-schema-diagnostic-evaluator.php';
	if ( file_exists( $evaluator_file ) ) {
		require_once $evaluator_file;
	}

	$test_id     = isset( $_POST['test_id'] ) ? sanitize_text_field( wp_unslash( $_POST['test_id'] ) ) : '';
	$json_ld_raw = isset( $_POST['json_ld'] ) ? wp_unslash( $_POST['json_ld'] ) : '';

	// テスト仕様をロード
	$spec_file    = __DIR__ . '/test-spec.json';
	$spec_content = file_exists( $spec_file )
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		? file_get_contents( $spec_file )
		: '{}';
	$spec_data = json_decode( $spec_content, true );
	$spec_data = is_array( $spec_data ) ? $spec_data : array();
	$judgment  = $spec_data['schemaJudgment'][ $test_id ] ?? array();

	if ( empty( $judgment ) || 'manual' === ( $judgment['checkType'] ?? '' ) ) {
		wp_send_json_error(
			array(
				'message' => __( 'This test requires manual verification.', 'ai-search-schema' ),
				'status'  => 'manual',
			)
		);
		wp_die();
	}

	// Evaluatorが利用可能か確認
	if ( ! class_exists( 'AI_Search_Schema_Diagnostic_Evaluator' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Diagnostic evaluator not available.', 'ai-search-schema' ) )
		);
		wp_die();
	}

	// スペック構築
	$eval_spec = array(
		'targetType'    => $judgment['targetType'] ?? null,
		'required'      => $judgment['required'] ?? array(),
		'recommended'   => $judgment['recommended'] ?? array(),
		'minCount'      => $judgment['minCount'] ?? 0,
		'minCountField' => $judgment['minCountField'] ?? '',
		'minLen'        => $judgment['minLen'] ?? 0,
		'minLenField'   => $judgment['minLenField'] ?? '',
		'errorOnFail'   => $judgment['errorOnFail'] ?? false,
	);

	// 判定実行
	$result = AI_Search_Schema_Diagnostic_Evaluator::evaluate( $eval_spec, $json_ld_raw );

	// ステータスをレガシー形式にマップ（UI互換性）
	$status_map = array(
		'info'    => 'pass',
		'warning' => 'pending',
		'error'   => 'fail',
	);

	wp_send_json_success(
		array(
			'status'       => $result['status'],
			'legacyStatus' => $status_map[ $result['status'] ] ?? 'pending',
			'message'      => $result['message'],
			'phase'        => $result['phase'] ?? '',
			'details'      => $result['details'] ?? array(),
			'checkType'    => $judgment['checkType'] ?? 'unknown',
		)
	);
	wp_die();
}

// プラグインのルートディレクトリを取得
$plugin_dir = dirname( __DIR__ );
$plugin_url = plugin_dir_url( $plugin_dir . '/ai-search-schema.php' );

// テスト仕様データ（JSONからロード）
$test_spec_file = __DIR__ . '/test-spec.json';
$test_spec      = array();
if ( file_exists( $test_spec_file ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$test_spec_content = file_get_contents( $test_spec_file );
	$test_spec         = json_decode( $test_spec_content, true );
}

// プラグインバージョン取得
$plugin_version = defined( 'AI_SEARCH_SCHEMA_VERSION' ) ? AI_SEARCH_SCHEMA_VERSION : '0.1.0';

// 設定ページURL
$settings_url     = admin_url( 'options-general.php?page=ai-search-schema' );
$rich_results_url = 'https://search.google.com/test/rich-results?url=' . rawurlencode( home_url() );
$nonce_value      = wp_create_nonce( 'ai_search_schema_test_manager' );
$test_spec_json   = wp_json_encode( $test_spec );
// 空配列の場合はオブジェクトとして出力（JSで文字列キーが使えるようにするため）
$test_data_json = wp_json_encode( empty( $test_data ) ? new stdClass() : $test_data );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'AI Search Schema Test Manager', 'ai-search-schema' ); ?></title>
	<?php wp_head(); ?>
	<style>
		:root {
			--color-primary: #2563eb;
			--color-success: #16a34a;
			--color-warning: #d97706;
			--color-error: #dc2626;
			--color-gray-50: #f9fafb;
			--color-gray-100: #f3f4f6;
			--color-gray-200: #e5e7eb;
			--color-gray-300: #d1d5db;
			--color-gray-500: #6b7280;
			--color-gray-700: #374151;
			--color-gray-900: #111827;
		}

		* { box-sizing: border-box; }

		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			background: var(--color-gray-100);
			margin: 0;
			padding: 20px;
			color: var(--color-gray-900);
		}

		.test-manager { max-width: 1400px; margin: 0 auto; }

		.test-manager__header {
			background-color: white!important;
			padding: 24px;
			border-radius: 12px;
			margin-bottom: 24px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
		}

		.test-manager__title { margin: 0 0 8px; font-size: 24px; font-weight: 700; }
		.test-manager__subtitle { margin: 0; color: var(--color-gray-500); }
		.test-manager__stats { display: flex; gap: 24px; margin-top: 20px; }

		.stat-card {
			background: var(--color-gray-50);
			padding: 16px 24px;
			border-radius: 8px;
			text-align: center;
			min-width: 120px;
		}

		.stat-card__value { font-size: 28px; font-weight: 700; display: block; }
		.stat-card__label {
			font-size: 12px;
			color: var(--color-gray-500);
			text-transform: uppercase;
		}
		.stat-card--pass .stat-card__value { color: var(--color-success); }
		.stat-card--fail .stat-card__value { color: var(--color-error); }
		.stat-card--pending .stat-card__value { color: var(--color-warning); }

		.test-manager__controls { display: flex; gap: 12px; margin-top: 20px; flex-wrap: wrap; }

		.btn {
			padding: 10px 20px;
			border: none;
			border-radius: 6px;
			font-size: 14px;
			font-weight: 500;
			cursor: pointer;
			display: inline-flex;
			align-items: center;
			gap: 8px;
			transition: all 0.2s;
			text-decoration: none;
		}

		.btn--primary { background: var(--color-primary); color: white; }
		.btn--primary:hover { background: #1d4ed8; }
		.btn--secondary {
			background: white;
			color: var(--color-gray-700);
			border: 1px solid var(--color-gray-300);
		}
		.btn--secondary:hover { background: var(--color-gray-50); }

		.test-manager__tabs {
			display: flex;
			gap: 4px;
			background: white;
			padding: 8px;
			border-radius: 12px;
			margin-bottom: 24px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
			overflow-x: auto;
		}

		.tab-btn {
			padding: 12px 20px;
			border: none;
			background: transparent;
			border-radius: 8px;
			font-size: 14px;
			font-weight: 500;
			cursor: pointer;
			white-space: nowrap;
			color: var(--color-gray-500);
			transition: all 0.2s;
		}

		.tab-btn:hover { background: var(--color-gray-100); color: var(--color-gray-700); }
		.tab-btn.active { background: var(--color-primary); color: white; }

		.tab-btn__count {
			background: var(--color-gray-200);
			padding: 2px 8px;
			border-radius: 10px;
			font-size: 12px;
			margin-left: 8px;
		}

		.tab-btn.active .tab-btn__count { background: rgba(255,255,255,0.2); }

		.test-panel {
			display: none;
			background: white;
			border-radius: 12px;
			box-shadow: 0 1px 3px rgba(0,0,0,0.1);
			overflow: hidden;
		}

		.test-panel.active { display: block; }

				.test-table { width: 100%; border-collapse: collapse; }

		.test-table th,
		.test-table td {
			padding: 12px 16px;
			text-align: left;
			border-bottom: 1px solid var(--color-gray-200);
		}

		.test-table th {
			background: var(--color-gray-50);
			font-weight: 600;
			font-size: 12px;
			text-transform: uppercase;
			color: var(--color-gray-500);
			position: sticky;
			top: 0;
		}

				.test-table tr:hover { background: var(--color-gray-50); }
				.test-id { font-family: monospace; font-size: 12px; color: var(--color-gray-500); }
				.test-number { font-weight: 700; color: var(--color-gray-700); }
				.steps-list {
					margin: 0;
					padding-left: 20px;
					color: var(--color-gray-700);
					line-height: 1.5;
				}
				.steps-list li { margin-bottom: 6px; }
				.placeholder-text { color: var(--color-gray-500); font-size: 12px; }

		.priority-badge {
			padding: 4px 8px;
			border-radius: 4px;
			font-size: 11px;
			font-weight: 600;
			text-transform: uppercase;
		}

		.priority-badge--high { background: #fef2f2; color: var(--color-error); }
		.priority-badge--medium { background: #fffbeb; color: var(--color-warning); }
		.priority-badge--low { background: var(--color-gray-100); color: var(--color-gray-500); }

		.result-select {
			padding: 8px 12px;
			border: 1px solid var(--color-gray-300);
			border-radius: 6px;
			font-size: 14px;
			min-width: 120px;
		}

		.result-select--pass { background: #dcfce7; border-color: var(--color-success); }
		.result-select--fail { background: #fef2f2; border-color: var(--color-error); }
		.result-select--pending { background: #fffbeb; border-color: var(--color-warning); }

		.screenshot-cell { min-width: 200px; }

		.screenshot-dropzone {
			border: 2px dashed var(--color-gray-300);
			border-radius: 8px;
			padding: 16px;
			text-align: center;
			cursor: pointer;
			transition: all 0.2s;
			background: var(--color-gray-50);
		}

		.screenshot-dropzone:hover,
		.screenshot-dropzone.dragover {
			border-color: var(--color-primary);
			background: #eff6ff;
		}

		.screenshot-dropzone__text { font-size: 12px; color: var(--color-gray-500); }
		.screenshot-dropzone__icon { font-size: 24px; margin-bottom: 8px; }

		.screenshot-preview { position: relative; display: inline-block; }

		.screenshot-preview img {
			max-width: 150px;
			max-height: 100px;
			border-radius: 6px;
			cursor: pointer;
			transition: transform 0.2s;
		}

		.screenshot-preview img:hover { transform: scale(1.05); }

		.screenshot-preview__remove {
			position: absolute;
			top: -8px;
			right: -8px;
			width: 24px;
			height: 24px;
			background: var(--color-error);
			color: white;
			border: none;
			border-radius: 50%;
			cursor: pointer;
			font-size: 14px;
			line-height: 1;
		}

		.notes-input {
			width: 100%;
			min-width: 200px;
			padding: 8px;
			border: 1px solid var(--color-gray-300);
			border-radius: 6px;
			font-size: 13px;
			resize: vertical;
		}

		.modal-overlay {
			display: none;
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0,0,0,0.8);
			z-index: 10000;
			justify-content: center;
			align-items: center;
		}

		.modal-overlay.active { display: flex; }
		.modal-overlay img { max-width: 90vw; max-height: 90vh; border-radius: 8px; }

		.modal-close {
			position: absolute;
			top: 20px;
			right: 20px;
			width: 40px;
			height: 40px;
			background: white;
			border: none;
			border-radius: 50%;
			font-size: 24px;
			cursor: pointer;
		}

		.toast {
			position: fixed;
			bottom: 20px;
			right: 20px;
			padding: 16px 24px;
			background: var(--color-gray-900);
			color: white;
			border-radius: 8px;
			box-shadow: 0 4px 12px rgba(0,0,0,0.2);
			transform: translateY(100px);
			opacity: 0;
			transition: all 0.3s;
			z-index: 10001;
		}

		.toast.show { transform: translateY(0); opacity: 1; }
		.toast--success { background: var(--color-success); }
		.toast--error { background: var(--color-error); }

		/* Auto-Judge Button */
		.auto-judge-btn {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 28px;
			height: 28px;
			margin-left: 4px;
			background: var(--color-gray-100);
			border: 1px solid var(--color-gray-300);
			border-radius: 4px;
			cursor: pointer;
			font-size: 14px;
			vertical-align: middle;
			transition: background 0.2s;
		}
		.auto-judge-btn:hover {
			background: var(--color-primary);
			color: white;
			border-color: var(--color-primary);
		}

		/* Auto-Judge Dialog */
		.auto-judge-dialog {
			background: white;
			border-radius: 12px;
			max-width: 600px;
			width: 90%;
			max-height: 90vh;
			overflow: auto;
			box-shadow: 0 20px 40px rgba(0,0,0,0.3);
		}
		.auto-judge-dialog__header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 16px 20px;
			border-bottom: 1px solid var(--color-gray-200);
		}
		.auto-judge-dialog__header h3 {
			margin: 0;
			font-size: 18px;
			color: var(--color-gray-900);
		}
		.auto-judge-dialog__header .modal-close {
			position: static;
			width: 32px;
			height: 32px;
			font-size: 20px;
		}
		.auto-judge-dialog__body {
			padding: 20px;
		}
		.auto-judge-info {
			background: var(--color-gray-50);
			padding: 12px 16px;
			border-radius: 8px;
			margin-bottom: 16px;
		}
		.auto-judge-info p {
			margin: 4px 0;
			font-size: 14px;
			color: var(--color-gray-700);
		}
		.auto-judge-input {
			margin-bottom: 16px;
		}
		.auto-judge-input label {
			display: block;
			margin-bottom: 8px;
			font-weight: 500;
			color: var(--color-gray-700);
		}
		.auto-judge-input textarea {
			width: 100%;
			padding: 12px;
			border: 1px solid var(--color-gray-300);
			border-radius: 6px;
			font-family: monospace;
			font-size: 13px;
			resize: vertical;
			box-sizing: border-box;
		}
		.auto-judge-input textarea:focus {
			outline: none;
			border-color: var(--color-primary);
			box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
		}
		.auto-judge-actions {
			display: flex;
			gap: 8px;
			margin-bottom: 16px;
		}
		.btn {
			padding: 10px 20px;
			border: none;
			border-radius: 6px;
			font-size: 14px;
			font-weight: 500;
			cursor: pointer;
			transition: background 0.2s;
		}
		.btn--primary {
			background: var(--color-primary);
			color: white;
		}
		.btn--primary:hover {
			background: #1d4ed8;
		}
		.btn--secondary {
			background: var(--color-gray-200);
			color: var(--color-gray-700);
		}
		.btn--secondary:hover {
			background: var(--color-gray-300);
		}

		@media (max-width: 1200px) {
			.test-table { display: block; overflow-x: auto; }
		}
	</style>
</head>
<body>
	<div class="test-manager">
		<header class="test-manager__header">
			<h1 class="test-manager__title">🧪 AI Search Schema Test Manager</h1>
			<p class="test-manager__subtitle">
				v<?php echo esc_html( $plugin_version ); ?> - テスト実行・スクリーンショット管理
			</p>

			<div class="test-manager__stats">
				<div class="stat-card stat-card--total">
					<span class="stat-card__value" id="stat-total">0</span>
					<span class="stat-card__label">Total</span>
				</div>
				<div class="stat-card stat-card--pass">
					<span class="stat-card__value" id="stat-pass">0</span>
					<span class="stat-card__label">Pass</span>
				</div>
				<div class="stat-card stat-card--fail">
					<span class="stat-card__value" id="stat-fail">0</span>
					<span class="stat-card__label">Fail</span>
				</div>
				<div class="stat-card stat-card--pending">
					<span class="stat-card__value" id="stat-pending">0</span>
					<span class="stat-card__label">Pending</span>
				</div>
			</div>

			<div class="test-manager__controls">
				<button class="btn btn--primary" onclick="saveAllData()">💾 保存</button>
				<button class="btn btn--secondary" onclick="exportData()">
					📤 エクスポート (JSON)
				</button>
				<button class="btn btn--secondary" onclick="exportCSV()">
					📊 エクスポート (CSV)
				</button>
				<label class="btn btn--secondary">
					📥 インポート
					<input type="file" accept=".json" onchange="importData(event)" style="display:none">
				</label>
				<a class="btn btn--secondary"
					href="<?php echo esc_url( $settings_url ); ?>"
					target="_blank">
					⚙️ プラグイン設定
				</a>
				<a class="btn btn--secondary"
					href="<?php echo esc_url( $rich_results_url ); ?>"
					target="_blank">
					🔍 Rich Results Test
				</a>
			</div>
		</header>

		<nav class="test-manager__tabs" id="tabs"></nav>
		<main id="panels"></main>
	</div>

	<div class="modal-overlay" id="imageModal" onclick="closeModal()">
		<button class="modal-close" onclick="closeModal()">×</button>
		<img id="modalImage" src="" alt="Screenshot preview">
	</div>

	<!-- Auto-Judge Modal -->
	<div class="modal-overlay" id="autoJudgeModal" onclick="if(event.target===this)closeAutoJudgeModal()">
		<div class="auto-judge-dialog">
			<div class="auto-judge-dialog__header">
				<h3>🔍 自動判定</h3>
				<button class="modal-close" onclick="closeAutoJudgeModal()">×</button>
			</div>
			<div class="auto-judge-dialog__body">
				<div class="auto-judge-info">
					<p><strong>テストID:</strong> <span id="autoJudgeTestId"></span></p>
					<p><strong>項目:</strong> <span id="autoJudgeTestItem"></span></p>
					<p><strong>チェック種別:</strong> <span id="autoJudgeCheckType"></span></p>
					<p><strong>対象タイプ:</strong> <span id="autoJudgeTargetType"></span></p>
				</div>
				<div class="auto-judge-input">
					<label for="jsonLdInput">JSON-LDを貼り付け:</label>
					<textarea id="jsonLdInput" rows="10" placeholder='&lt;script type="application/ld+json"&gt;...&lt;/script&gt;&#10;または&#10;{"@context":"https://schema.org",...}'></textarea>
				</div>
				<div class="auto-judge-actions">
					<button type="button" class="btn btn--primary" onclick="runAutoJudge()">判定実行</button>
					<button type="button" class="btn btn--secondary" onclick="closeAutoJudgeModal()">キャンセル</button>
				</div>
				<div id="autoJudgeResult"></div>
			</div>
		</div>
	</div>

	<div class="toast" id="toast"></div>

	<script>
		// Test spec and data are safely encoded via wp_json_encode().
		const testSpec = <?php echo $test_spec_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() is safe for JS ?>;
		// 空配列の場合はオブジェクトに変換（配列に文字列キーを設定するとJSON.stringifyで失われるため）
		let testData = <?php echo $test_data_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() is safe for JS ?>;
		if (Array.isArray(testData)) testData = {};
		const nonce = <?php echo wp_json_encode( $nonce_value ); ?>;
		const pluginVersion = <?php echo wp_json_encode( $plugin_version ); ?>;

		function escapeHtml(text) {
			if (!text) return '';
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}

		document.addEventListener('DOMContentLoaded', function() {
			if (!testSpec || !testSpec.categories) {
				document.getElementById('panels').innerHTML =
					'<p style="padding:20px;">テスト仕様ファイルが見つかりません。</p>';
				return;
			}
			renderTabs();
			renderPanels();
			updateStats();
		});

		function renderTabs() {
			const tabsContainer = document.getElementById('tabs');
			tabsContainer.innerHTML = testSpec.categories.map((cat, i) =>
				'<button class="tab-btn ' + (i === 0 ? 'active' : '') +
				'" onclick="switchTab(' + i + ')">' +
				escapeHtml(cat.name) +
				'<span class="tab-btn__count">' + cat.tests.length + '</span>' +
				'</button>'
			).join('');
		}

				function renderPanels() {
						const panelsContainer = document.getElementById('panels');
						panelsContainer.innerHTML = testSpec.categories.map((cat, i) =>
								'<section class="test-panel ' + (i === 0 ? 'active' : '') +
								'" id="panel-' + i + '">' +
								'<table class="test-table">' +
								'<thead><tr>' +
								'<th style="width:60px">No.</th>' +
								'<th style="width:90px">ID</th>' +
								'<th style="width:120px">カテゴリ</th>' +
								'<th>テスト項目</th>' +
								'<th style="width:320px">手順</th>' +
								'<th style="width:80px">優先度</th>' +
								'<th style="width:130px">結果</th>' +
								'<th style="width:220px">スクリーンショット</th>' +
								'<th style="width:200px">備考</th>' +
								'</tr></thead>' +
								'<tbody>' +
								cat.tests.map((test, index) => renderTestRow(test, index + 1)).join('') +
								'</tbody>' +
								'</table>' +
								'</section>'
						).join('');
				}

				function renderTestRow(test, rowNumber = '') {
						const data = testData[test.id] || {};
						const result = data.result || '';
						const screenshot = data.screenshot || '';
						const notes = data.notes || '';

			const priorityClasses = {
				'高': 'priority-badge--high',
				'中': 'priority-badge--medium',
				'低': 'priority-badge--low'
			};
			const resultClasses = {
				'pass': 'result-select--pass',
				'fail': 'result-select--fail',
				'pending': 'result-select--pending'
			};
			const priorityClass = priorityClasses[test.priority] || '';
			const resultClass = resultClasses[result] || '';
						const testIdEsc = escapeHtml(test.id);
						const rowNumberEsc = escapeHtml(String(rowNumber || ''));
						const steps = Array.isArray(test.steps) ? test.steps : [];
						const stepsHtml = steps.length
								? '<ol class="steps-list">' + steps.map(step => '<li>' + escapeHtml(step) + '</li>').join('') + '</ol>'
								: '<span class="placeholder-text">手順未登録</span>';

			let screenshotHtml = '';
			if (screenshot) {
				const ssEsc = escapeHtml(screenshot);
				screenshotHtml =
					'<div class="screenshot-preview">' +
					'<img src="' + ssEsc + '" ' +
					'onclick="openModal(\'' + ssEsc + '\')" alt="Screenshot">' +
					'<button class="screenshot-preview__remove" ' +
					'onclick="removeScreenshot(\'' + testIdEsc + '\')">&times;</button>' +
					'</div>';
			} else {
				screenshotHtml =
					'<div class="screenshot-dropzone" ' +
					'onclick="triggerUpload(\'' + testIdEsc + '\')" ' +
					'ondragover="handleDragOver(event)" ' +
					'ondragleave="handleDragLeave(event)" ' +
					'ondrop="handleDrop(event, \'' + testIdEsc + '\')">' +
					'<div class="screenshot-dropzone__icon">📷</div>' +
					'<div class="screenshot-dropzone__text">ドロップまたはクリック</div>' +
					'</div>' +
					'<input type="file" id="file-' + testIdEsc + '" ' +
					'accept="image/*" style="display:none" ' +
					'onchange="uploadScreenshot(\'' + testIdEsc + '\', this.files[0])">';
			}

						return '<tr data-test-id="' + testIdEsc + '" data-row-number="' + rowNumberEsc + '">' +
								'<td class="test-number">' + rowNumberEsc + '</td>' +
								'<td><span class="test-id">' + testIdEsc + '</span></td>' +
								'<td>' + escapeHtml(test.category) + '</td>' +
								'<td><strong>' + escapeHtml(test.item) + '</strong><br>' +
								'<small style="color:#6b7280">' + escapeHtml(test.expected) + '</small></td>' +
								'<td>' + stepsHtml + '</td>' +
								'<td><span class="priority-badge ' + priorityClass + '">' +
								escapeHtml(test.priority) + '</span></td>' +
								'<td>' +
				'<select class="result-select ' + resultClass + '" ' +
				'onchange="updateResult(\'' + testIdEsc + '\', this.value, this)">' +
				'<option value="">未実施</option>' +
				'<option value="pass"' + (result === 'pass' ? ' selected' : '') + '>✅ 合格</option>' +
				'<option value="fail"' + (result === 'fail' ? ' selected' : '') + '>❌ 不合格</option>' +
				'<option value="pending"' + (result === 'pending' ? ' selected' : '') +
				'>⏸️ 保留</option>' +
				'</select>' +
				'</td>' +
				'<td class="screenshot-cell">' + screenshotHtml + '</td>' +
				'<td>' +
				'<textarea class="notes-input" rows="2" placeholder="備考..." ' +
				'onchange="updateNotes(\'' + testIdEsc + '\', this.value)">' +
				escapeHtml(notes) +
				'</textarea>' +
				'</td>' +
				'</tr>';
		}

		function switchTab(index) {
			document.querySelectorAll('.tab-btn').forEach((btn, i) => {
				btn.classList.toggle('active', i === index);
			});
			document.querySelectorAll('.test-panel').forEach((panel, i) => {
				panel.classList.toggle('active', i === index);
			});
		}

		function updateResult(testId, value, selectEl) {
			if (!testData[testId]) testData[testId] = {};
			testData[testId].result = value;
			selectEl.className = 'result-select';
			if (value) selectEl.classList.add('result-select--' + value);
			updateStats();
		}

		function updateNotes(testId, value) {
			if (!testData[testId]) testData[testId] = {};
			testData[testId].notes = value;
		}

		function triggerUpload(testId) {
			document.getElementById('file-' + testId).click();
		}

		function handleDragOver(e) {
			e.preventDefault();
			e.currentTarget.classList.add('dragover');
		}

		function handleDragLeave(e) {
			e.currentTarget.classList.remove('dragover');
		}

		function handleDrop(e, testId) {
			e.preventDefault();
			e.currentTarget.classList.remove('dragover');
			const file = e.dataTransfer.files[0];
			if (file && file.type.startsWith('image/')) {
				uploadScreenshot(testId, file);
			}
		}

		async function uploadScreenshot(testId, file) {
			if (!file) return;
			const formData = new FormData();
			formData.append('action', 'upload_screenshot');
			formData.append('ai_search_schema_test_nonce', nonce);
			formData.append('screenshot', file);

			try {
				const response = await fetch(window.location.href, {
					method: 'POST',
					body: formData
				});
				const result = await response.json();
				if (result.success) {
					if (!testData[testId]) testData[testId] = {};
					testData[testId].screenshot = result.data.url;
					testData[testId].attachment_id = result.data.attachment_id;
					const row = document.querySelector('tr[data-test-id="' + testId + '"]');
					const rowNumber = row ? row.dataset.rowNumber : '';
					const test = findTestById(testId);
					if (row && test) row.outerHTML = renderTestRow(test, rowNumber);
					showToast('スクリーンショットをアップロードしました', 'success');
				} else {
					showToast(result.data.message || 'アップロードに失敗しました', 'error');
				}
			} catch (error) {
				showToast('アップロードに失敗しました', 'error');
			}
		}

		function removeScreenshot(testId) {
			if (!testData[testId]) return;
			delete testData[testId].screenshot;
			delete testData[testId].attachment_id;
					const row = document.querySelector('tr[data-test-id="' + testId + '"]');
					const rowNumber = row ? row.dataset.rowNumber : '';
					const test = findTestById(testId);
					if (row && test) row.outerHTML = renderTestRow(test, rowNumber);
		}

		function findTestById(testId) {
			for (const cat of testSpec.categories) {
				const test = cat.tests.find(t => t.id === testId);
				if (test) return test;
			}
			return null;
		}

		function updateStats() {
			let total = 0, pass = 0, fail = 0, pending = 0;
			for (const cat of testSpec.categories) {
				total += cat.tests.length;
				for (const test of cat.tests) {
					const result = testData[test.id]?.result || '';
					if (result === 'pass') pass++;
					else if (result === 'fail') fail++;
					else if (result === 'pending') pending++;
				}
			}
			document.getElementById('stat-total').textContent = total;
			document.getElementById('stat-pass').textContent = pass;
			document.getElementById('stat-fail').textContent = fail;
			document.getElementById('stat-pending').textContent = pending;
		}

		async function saveAllData() {
			const formData = new FormData();
			formData.append('action', 'save_test_data');
			formData.append('ai_search_schema_test_nonce', nonce);
			formData.append('test_data', JSON.stringify(testData));
			try {
				const response = await fetch(window.location.href, {
					method: 'POST',
					body: formData
				});
				const result = await response.json();
				if (result.success) showToast('データを保存しました', 'success');
				else showToast('保存に失敗しました', 'error');
			} catch (error) {
				showToast('保存に失敗しました', 'error');
			}
		}

		function exportData() {
			const exportObj = {
				version: pluginVersion,
				exportedAt: new Date().toISOString(),
				testSpec: testSpec,
				testData: testData
			};
			const blob = new Blob(
				[JSON.stringify(exportObj, null, 2)],
				{ type: 'application/json' }
			);
			const url = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = 'ai-search-schema-test-' +
				new Date().toISOString().slice(0,10) + '.json';
			a.click();
			URL.revokeObjectURL(url);
		}

		function exportCSV() {
			const rows = [[
				'ID', 'カテゴリ', 'テスト項目', '期待結果',
				'優先度', '結果', 'スクリーンショット', '備考'
			]];
			for (const cat of testSpec.categories) {
				for (const test of cat.tests) {
					const data = testData[test.id] || {};
					rows.push([
						test.id,
						test.category,
						test.item,
						test.expected,
						test.priority,
						data.result || '未実施',
						data.screenshot || '',
						data.notes || ''
					]);
				}
			}
			const csv = rows.map(row =>
				row.map(cell => '"' + String(cell).replace(/"/g, '""') + '"').join(',')
			).join('\n');
			const blob = new Blob(
				['\uFEFF' + csv],
				{ type: 'text/csv;charset=utf-8' }
			);
			const url = URL.createObjectURL(blob);
			const a = document.createElement('a');
			a.href = url;
			a.download = 'ai-search-schema-test-' +
				new Date().toISOString().slice(0,10) + '.csv';
			a.click();
			URL.revokeObjectURL(url);
		}

		function importData(event) {
			const file = event.target.files[0];
			if (!file) return;
			const reader = new FileReader();
			reader.onload = function(e) {
				try {
					const imported = JSON.parse(e.target.result);
					if (imported.testData !== undefined) {
						// 配列の場合はオブジェクトに変換（空配列対策）
						testData = Array.isArray(imported.testData) ? {} : imported.testData;
						renderPanels();
						updateStats();
						showToast('データをインポートしました', 'success');
					} else {
						showToast('testDataが見つかりません', 'error');
					}
				} catch (error) {
					showToast('インポートに失敗しました: ' + error.message, 'error');
				}
			};
			reader.readAsText(file);
		}

		function openModal(src) {
			document.getElementById('modalImage').src = src;
			document.getElementById('imageModal').classList.add('active');
		}

		function closeModal() {
			document.getElementById('imageModal').classList.remove('active');
		}

		function showToast(message, type) {
			const toast = document.getElementById('toast');
			toast.textContent = message;
			toast.className = 'toast toast--' + (type || 'info') + ' show';
			setTimeout(() => toast.classList.remove('show'), 3000);
		}

		// Auto-Judgment機能
		let currentAutoJudgeTestId = null;
		const schemaJudgment = testSpec.schemaJudgment || {};

		function isSchemaTest(testId) {
			return testId && testId.startsWith('SC-');
		}

		function hasAutoJudge(testId) {
			const judgment = schemaJudgment[testId];
			return judgment && judgment.checkType !== 'manual';
		}

		function openAutoJudgeModal(testId) {
			currentAutoJudgeTestId = testId;
			const modal = document.getElementById('autoJudgeModal');
			const testInfo = findTestById(testId);
			const judgment = schemaJudgment[testId] || {};

			document.getElementById('autoJudgeTestId').textContent = testId;
			document.getElementById('autoJudgeTestItem').textContent = testInfo ? testInfo.item : '';
			document.getElementById('autoJudgeCheckType').textContent = judgment.checkType || 'unknown';
			document.getElementById('autoJudgeTargetType').textContent = judgment.targetType || '-';
			document.getElementById('jsonLdInput').value = '';
			document.getElementById('autoJudgeResult').innerHTML = '';

			modal.classList.add('active');
		}

		function closeAutoJudgeModal() {
			document.getElementById('autoJudgeModal').classList.remove('active');
			currentAutoJudgeTestId = null;
		}

		async function runAutoJudge() {
			if (!currentAutoJudgeTestId) return;

			const jsonLd = document.getElementById('jsonLdInput').value.trim();
			if (!jsonLd) {
				showToast('JSON-LDを入力してください', 'error');
				return;
			}

			const resultDiv = document.getElementById('autoJudgeResult');
			resultDiv.innerHTML = '<p style="color:#6b7280">判定中...</p>';

			const formData = new FormData();
			formData.append('action', 'auto_judge');
			formData.append('ai_search_schema_test_nonce', nonce);
			formData.append('test_id', currentAutoJudgeTestId);
			formData.append('json_ld', jsonLd);

			try {
				const response = await fetch(window.location.href, {
					method: 'POST',
					body: formData
				});
				const result = await response.json();

				if (result.success) {
					const data = result.data;
					const statusColors = {
						'info': '#16a34a',
						'warning': '#d97706',
						'error': '#dc2626'
					};
					const statusLabels = {
						'info': '✅ OK (info)',
						'warning': '⚠️ 警告 (warning)',
						'error': '❌ エラー (error)'
					};

					let html = '<div style="padding:12px;background:#f9fafb;border-radius:6px;margin-top:8px">';
					html += '<p style="margin:0 0 8px;font-weight:600;color:' + (statusColors[data.status] || '#374151') + '">';
					html += (statusLabels[data.status] || data.status) + '</p>';
					html += '<p style="margin:0 0 8px;color:#374151">' + escapeHtml(data.message) + '</p>';
					html += '<p style="margin:0;font-size:12px;color:#6b7280">Phase: ' + escapeHtml(data.phase || '-') + '</p>';
					html += '</div>';

					resultDiv.innerHTML = html;

					// テストデータを自動更新するかどうか確認
					if (confirm('判定結果をテスト結果に反映しますか？')) {
						if (!testData[currentAutoJudgeTestId]) {
							testData[currentAutoJudgeTestId] = {};
						}
						testData[currentAutoJudgeTestId].result = data.legacyStatus;
						testData[currentAutoJudgeTestId].autoJudgeStatus = data.status;
						testData[currentAutoJudgeTestId].autoJudgeMessage = data.message;

						// UIを更新
						const row = document.querySelector('tr[data-test-id="' + currentAutoJudgeTestId + '"]');
						const rowNumber = row ? row.dataset.rowNumber : '';
						const test = findTestById(currentAutoJudgeTestId);
						if (row && test) row.outerHTML = renderTestRow(test, rowNumber);
						updateStats();
						showToast('判定結果を反映しました', 'success');
					}
				} else {
					resultDiv.innerHTML = '<p style="color:#dc2626">' +
						escapeHtml(result.data?.message || '判定に失敗しました') + '</p>';
				}
			} catch (error) {
				resultDiv.innerHTML = '<p style="color:#dc2626">エラー: ' + escapeHtml(error.message) + '</p>';
			}
		}

		// Auto-Judgeボタンをスキーマテストの行に追加するための拡張
		const originalRenderTestRow = renderTestRow;
		renderTestRow = function(test, rowNumber = '') {
			const html = originalRenderTestRow(test, rowNumber);

			// スキーマテスト（SC-*）で自動判定可能な場合、ボタンを追加
			if (isSchemaTest(test.id) && hasAutoJudge(test.id)) {
				const testIdEsc = escapeHtml(test.id);
				const buttonHtml = '<button class="auto-judge-btn" ' +
					'onclick="openAutoJudgeModal(\'' + testIdEsc + '\')" ' +
					'title="JSON-LDから自動判定">🔍</button>';

				// result-selectの後ろにボタンを挿入
				return html.replace(
					'</select>\n\t\t\t\t</td>',
					'</select> ' + buttonHtml + '</td>'
				);
			}
			return html;
		};

		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape') {
				closeModal();
				closeAutoJudgeModal();
			}
		});
	</script>
	<?php wp_footer(); ?>
</body>
</html>
