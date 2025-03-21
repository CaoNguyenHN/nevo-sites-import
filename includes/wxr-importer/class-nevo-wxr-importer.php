<?php
/**
 * Class that connects the plugin with WXR Importer library.
 *
 * @package Nevo Sites Import
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WXR Importer Class
 */
class Nevo_WXR_Importer {

	/**
	 * Singleton instance
	 *
	 * @var Nevo_Sites_Import
	 */
	private static $instance;

	/**
	 * ====================================================
	 * Singleton & constructor functions
	 * ====================================================
	 */

	/**
	 * Get singleton instance.
	 *
	 * @return Nevo_Sites_Import
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class constructor
	 */
	protected function __construct() {

		/**
		 * Import content and media files using WXR Importer.
		 */

		if ( ! class_exists( 'WP_Importer' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-importer.php';
		}
		if ( ! class_exists( 'WP_Importer_Logger' ) ) {
			require_once trailingslashit( NEVO_SITES_IMPORT_INCLUDES_DIR ) . 'wxr-importer/class-wp-importer-logger.php';
		}
		if ( ! class_exists( 'WP_Importer_Logger_ServerSentEvents' ) ) {
			require_once trailingslashit( NEVO_SITES_IMPORT_INCLUDES_DIR ) . 'wxr-importer/class-wp-importer-logger-serversentevents.php';
		}
		if ( ! class_exists( 'WXR_Importer' ) ) {
			require_once trailingslashit( NEVO_SITES_IMPORT_INCLUDES_DIR ) . 'wxr-importer/class-wxr-importer.php';
		}
		if ( ! class_exists( 'WXR_Import_Info' ) ) {
			require_once trailingslashit( NEVO_SITES_IMPORT_INCLUDES_DIR ) . 'wxr-importer/class-wxr-import-info.php';
		}

		/**
		 * Filters
		 */

		// Are we allowed to create users?
		add_filter( 'wxr_importer.pre_process.user', '__return_null' );

		// Modify post data.
		add_filter( 'wp_import_post_data_processed', array( $this, 'replace_guid' ), 10, 2 );

		add_action( 'wxr_importer.processed.post', array( $this, 'imported_post' ), 10, 2 );
		add_action( 'wxr_importer.process_failed.post', array( $this, 'imported_post' ), 10, 2 );
		add_action( 'wxr_importer.process_already_imported.post', array( $this, 'already_imported_post' ), 10, 2 );
		add_action( 'wxr_importer.process_skipped.post', array( $this, 'already_imported_post' ), 10, 2 );
		add_action( 'wxr_importer.processed.comment', array( $this, 'imported_comment' ) );
		add_action( 'wxr_importer.process_already_imported.comment', array( $this, 'imported_comment' ) );
		add_action( 'wxr_importer.processed.term', array( $this, 'imported_term' ) );
		add_action( 'wxr_importer.process_failed.term', array( $this, 'imported_term' ) );
		add_action( 'wxr_importer.process_already_imported.term', array( $this, 'imported_term' ) );
		add_action( 'wxr_importer.processed.user', array( $this, 'imported_user' ) );
		add_action( 'wxr_importer.process_failed.user', array( $this, 'imported_user' ) );
	}

	/**
	 * Main function to run the import process.
	 *
	 * @param string $xml_url The XML file URL.
	 * @since 1.1.0
	 */
	/* public function sse_import( $xml_url ) {
		// Prepare response's header.
		// Start the event stream.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );

		// Turn off PHP output compression.
		$previous = error_reporting( error_reporting() ^ E_WARNING );
		ini_set( 'output_buffering', 'off' );
		ini_set( 'zlib.output_compression', false );
		error_reporting( $previous );

		if ( $GLOBALS['is_nginx'] ) {
			// Setting this header instructs Nginx to disable fastcgi_buffering
			// and disable gzip for this request.
			header( 'X-Accel-Buffering: no' );
			header( 'Content-Encoding: none' );
		}

		// 2KB padding for IE.
		echo esc_html( ':' . str_repeat( ' ', 2048 ) . "\n\n" );

		// Check XML file.

		if ( empty( $xml_url ) ) {
			// Send error message.
			$this->emit_sse_message(
				array(
					'action' => 'complete',
					'error'  => esc_html__( 'Invalid uploaded XML file URL used.', 'nevo-sites-import' ),
				)
			);

			exit;
		}

		// Prepare the importer

		$importer = new WXR_Importer(
			array(
				'update_attachment_guids' => true,
				'fetch_attachments'       => true,
				'default_author'          => get_current_user_id(),
			)
		);

		$logger = new WP_Importer_Logger_ServerSentEvents();
		$importer->set_logger( $logger );

		// Set unlimited time limit.
		set_time_limit( 0 );

		// Ensure we're not buffered.
		wp_ob_end_flush_all();
		flush();

		// Send XML data for tracking progress.

		$info = $importer->get_preliminary_information( $xml_url );
		$this->emit_sse_message(
			array(
				'action' => 'setCounts',
				'counts' => array(
					'posts'    => $info->post_count,
					'media'    => $info->media_count,
					'comments' => $info->comment_count,
					'terms'    => $info->term_count,
				),
			)
		);

		// Begin import.

		// Flush once more.
		flush();

		// Run the import function.
		$response = $importer->import( $xml_url );

		// Return value.

		// Send return value via SSE message.
		$this->emit_sse_message(
			array(
				'action' => 'complete',
				'error'  => is_wp_error( $response ) ? $response->get_error_message() : false,
			)
		);
	} */
	public function sse_import( $xml_url ) {
		// Gửi header cho SSE.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );

		// Kiểm tra và tắt buffering.
		if ( function_exists( 'ob_get_level' ) && ob_get_level() > 0 ) {
			while ( ob_get_level() > 0 ) {
				ob_end_flush();
			}
		}

		// Tắt buffering và nén nếu dùng NGINX.
		if ( isset( $GLOBALS['is_nginx'] ) && $GLOBALS['is_nginx'] ) {
			header( 'X-Accel-Buffering: no' );
			header( 'Content-Encoding: none' );
		}

		// Thêm padding 2KB cho IE.
		echo esc_html( ':' . str_repeat( ' ', 2048 ) . "\n\n" );

		// Kiểm tra URL XML.
		if ( empty( $xml_url ) ) {
			$this->emit_sse_message(
				array(
					'action' => 'complete',
					'error'  => esc_html__( 'Invalid uploaded XML file URL used.', 'nevo-sites-import' ),
				)
			);
			exit;
		}

		// Cấu hình importer.
		$importer = new WXR_Importer(
			array(
				'update_attachment_guids' => true,
				'fetch_attachments'       => true,
				'default_author'          => get_current_user_id(),
			)
		);

		$logger = new WP_Importer_Logger_ServerSentEvents();
		$importer->set_logger( $logger );

		// Kiểm tra và thiết lập thời gian chạy không giới hạn.
		if ( function_exists( 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) {
			@set_time_limit( 0 ); // Chạy với cảnh báo, tránh lỗi trên server không hỗ trợ.
		}

		// Đảm bảo không bị giới hạn bởi bộ đệm.
		wp_ob_end_flush_all();
		flush();

		// Lấy thông tin sơ bộ từ XML.
		$info = $importer->get_preliminary_information( $xml_url );

		// Gửi thông báo về số lượng mục cần nhập.
		$this->emit_sse_message(
			array(
				'action' => 'setCounts',
				'counts' => array(
					'posts'    => $info->post_count,
					'media'    => $info->media_count,
					'comments' => $info->comment_count,
					'terms'    => $info->term_count,
				),
			)
		);

		flush();

		// Tiến hành nhập dữ liệu.
		$response = $importer->import( $xml_url );

		// Gửi thông báo hoàn thành.
		$this->emit_sse_message(
			array(
				'action' => 'complete',
				'error'  => is_wp_error( $response ) ? $response->get_error_message() : false,
			)
		);
	}

	/**
	 * Skip GUID field.
	 *
	 * @param array $postdata Post data.
	 * @param array $data     Data.
	 * @return array
	 */
	public function replace_guid( $postdata, $data ) {
		// Skip GUID field which point to the https://demo.nevothemes.com.
		$postdata['guid'] = '';

		return $postdata;
	}

	/**
	 * Emit a Server-Sent Events message.
	 *
	 * @since 1.1.0
	 * @param mixed $data Data to be JSON-encoded and sent in the message.
	 */
	public function emit_sse_message( $data ) {
		echo 'event: message' . "\n";
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";

		// Extra padding.
		echo esc_html( ':' . str_repeat( ' ', 2048 ) . "\n\n" );

		ob_flush();
		flush();
	}

	/**
	 * Send message when a post has been imported.
	 *
	 * @since 1.1.0
	 * @param int   $id   Post ID.
	 * @param array $data Post data saved to the DB.
	 */
	public function imported_post( $id, $data ) {
		$this->emit_sse_message(
			array(
				'action' => 'updateDelta',
				'type'   => 'attachment' === $data['post_type'] ? 'media' : 'posts',
				'delta'  => 1,
			)
		);
	}

	/**
	 * Send message when a post is marked as already imported.
	 *
	 * @since 1.1.0
	 * @param array $data Post data saved to the DB.
	 */
	public function already_imported_post( $data ) {
		$this->emit_sse_message(
			array(
				'action' => 'updateDelta',
				'type'   => 'attachment' === $data['post_type'] ? 'media' : 'posts',
				'delta'  => 1,
			)
		);
	}

	/**
	 * Send message when a comment has been imported.
	 *
	 * @since 1.1.0
	 */
	public function imported_comment() {
		$this->emit_sse_message(
			array(
				'action' => 'updateDelta',
				'type'   => 'comments',
				'delta'  => 1,
			)
		);
	}

	/**
	 * Send message when a term has been imported.
	 *
	 * @since 1.1.0
	 */
	public function imported_term() {
		$this->emit_sse_message(
			array(
				'action' => 'updateDelta',
				'type'   => 'terms',
				'delta'  => 1,
			)
		);
	}

	/**
	 * Send message when a user has been imported.
	 *
	 * @since 1.1.0
	 */
	public function imported_user() {
		$this->emit_sse_message(
			array(
				'action' => 'updateDelta',
				'type'   => 'users',
				'delta'  => 1,
			)
		);
	}
}

// Initialize class.
Nevo_WXR_Importer::instance();
