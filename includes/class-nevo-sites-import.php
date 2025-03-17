<?php
/**
 * Main class of Nevo Sites Import plugin.
 *
 * @package Nevo Sites Import
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Class
 */
class Nevo_Sites_Import {

	/**
	 * Singleton instance
	 *
	 * @var Nevo_Sites_Import
	 */
	private static $instance;

	/**
	 * Sites Server API URL
	 *
	 * @var string
	 */
	public static $api_url;

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
		self::$api_url = apply_filters( 'nevo/sites_import/api_url', 'https://demo.nevothemes.com/demoimport/wp-json/nevo/v1/' );

		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'after_setup_theme', array( $this, 'init' ) );
	}

	/**
	 * ====================================================
	 * Hook functions
	 * ====================================================
	 */

	/**
	 * Load plugin textdomain.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'nevo-sites-import', false, 'nevo-sites-import/languages' );
	}

	/**
	 * Initialize plugin (required Nevo theme to be active).
	 */
	public function init() {
		// Check if Nevo theme is installed.
		// If not, don't run the plugin and show an warning message on admin page.
		if ( Nevo::$version || Nevo::$theme-name ) {
			remove_action('nevo/dashboard/sidebar', array(Nevo_Dashboard::$_instance, 'box_plugins'), 8);
			add_action('nevo/dashboard/sidebar', array( $this, 'sbox_plugins'), 8);
			add_action('nevo/dashboard/sidebar', array( $this, 'box_child_themes'), 12);
			add_filter( 'nevo/sites_import/scripts_data', array( $this, 'check_dev_mode' ) );

			add_action( 'upload_mimes', array( $this, 'add_custom_mimes' ) );
			add_filter( 'wp_check_filetype_and_ext', array( $this, 'real_mime_type_for_xml' ), 10, 4 );

			add_action( 'nevo/admin/menu', array( $this, 'register_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			add_action( 'wp_ajax_nevo_sites_import__select_builder', array( $this, 'ajax_select_builder' ) );

			add_action( 'wp_ajax_nevo_sites_import__get_plugins_status', array( $this, 'ajax_get_plugins_status' ) );
			add_action( 'wp_ajax_nevo_sites_import__install_plugin', array( $this, 'ajax_install_plugin' ) );
			add_action( 'wp_ajax_nevo_sites_import__activate_plugin', array( $this, 'ajax_activate_plugin' ) );

			add_action( 'wp_ajax_nevo_sites_import__prepare_import', array( $this, 'ajax_prepare_import' ) );
			add_action( 'wp_ajax_nevo_sites_import__prepare_contents', array( $this, 'ajax_prepare_contents' ) );
			add_action( 'wp_ajax_nevo_sites_import__import_contents', array( $this, 'ajax_import_contents' ) );
			add_action( 'wp_ajax_nevo_sites_import__import_customizer', array( $this, 'ajax_import_customizer' ) );
			add_action( 'wp_ajax_nevo_sites_import__import_widgets', array( $this, 'ajax_import_widgets' ) );
			add_action( 'wp_ajax_nevo_sites_import__import_options', array( $this, 'ajax_import_options' ) );
			add_action( 'wp_ajax_nevo_sites_import__finalize_import', array( $this, 'ajax_finalize_import' ) );

			if ( class_exists( 'WooCommerce' ) ) {
				require_once trailingslashit( NEVO_SITES_IMPORT_INCLUDES_DIR ) . 'compatibilities/class-nevo-sites-import-compatibility-woocommerce.php';
			}

			if ( class_exists( '\Elementor\Plugin' ) ) {
				require_once trailingslashit( NEVO_SITES_IMPORT_INCLUDES_DIR ) . 'compatibilities/class-nevo-sites-import-compatibility-elementor.php';
			}
		} else {
			add_action( 'admin_notices', array( $this, 'render_theme_not_installed_motice' ) );
		}
	}
	
	/**
	 * Display recommend plugins
	 */
	function sbox_plugins() {
		?>
		<div class="cd-box box-plugins">
			<div class="cd-box-top"><?php _e('Nevo ready to import Plugins', 'nevo-sites-import'); ?></div>
			<div class="cd-sites-thumb">
				<img src="<?php echo esc_url(get_template_directory_uri()) . '/assets/images/admin/sites_thumbnail.png'; ?>">
			</div>
			<div class="cd-box-content">
				<p><?php _e('<strong>Nevo Sites</strong> is a free add-on for the Nevo theme which help you browse and import ready made websites with few clicks.', 'nevo-sites-import'); ?></p>
				<?php
				
				// Define multiple plugins
				$plugins = array(
					'nevo-sites-import' => array(
						'name'            => 'nevo-sites-import',
						'active_filename' => 'nevo-sites-import/nevo-sites-import.php',
						'github_repo'     => 'CaoNguyenHN/nevo-sites-import',
						'github_branch'   => 'main',
						'title'           => __('Nevo Sites Import', 'nevo-sites-import'),
						'description'     => __('Import ready-made websites with a few clicks.', 'nevo-sites-import'),
						'view_url'        => add_query_arg(
							array(
								'page' => 'nevo-sites-import',
							),
							admin_url('themes.php')
						),
						'view_text'       => __('View Site Library', 'nevo-sites-import'),
					),
					'custom-image-sizes' => array(
						'name'            => 'custom-image-sizes',
						'active_filename' => 'custom-image-sizes/custom-image-sizes.php',
						'github_repo'     => 'CaoNguyenHN/custom-image-sizes',
						'github_branch'   => 'main',
						'title'           => __('Custom Image Sizes Manager', 'nevo-sites-import'),
						'description'     => __('Add or remove custom image sizes in WordPress with enable/disable option in Media settings.', 'nevo-sites-import'),
						'view_url'        => admin_url('options-media.php'),
						'view_text'       => __('Media settings', 'nevo-sites-import'),
					),
					'nevo-user-social' => array(
						'name'            => 'nevo-user-social',
						'active_filename' => 'nevo-user-social/nevo-user-social.php',
						'github_repo'     => 'CaoNguyenHN/nevo-user-social',
						'github_branch'   => 'main',
						'title'           => __('Nevo User Social', 'nevo-sites-import'),
						'description'     => __('Add social fields to author/user profile.', 'nevo-sites-import'),
						'view_url'        => admin_url('profile.php'),
						'view_text'       => __('Profile settings', 'nevo-sites-import'),
					),
				);
				
				// Output each plugin
				foreach ($plugins as $plugin_slug => $plugin_info) {
					echo '<div class="plugin-container cd-box-shadow" id="plugin-' . esc_attr($plugin_slug) . '">';
					echo '<h3>' . esc_html($plugin_info['title']) . '</h3>';
					echo '<p>' . esc_html($plugin_info['description']) . '</p>';
					
					$plugin_info  = wp_parse_args(
						$plugin_info,
						array(
							'name'            => '',
							'active_filename' => '',
							'github_repo'     => '',
							'github_branch'   => 'main',
							'view_url'        => '',
							'view_text'       => __('View Plugin', 'nevo-sites-import'),
						)
					);
					
					$status       = is_dir(WP_PLUGIN_DIR . '/' . $plugin_slug);
					$button_class = 'install-now button';
					
					if ($plugin_info['active_filename']) {
						$active_file_name = $plugin_info['active_filename'];
					} else {
						$active_file_name = $plugin_slug . '/' . $plugin_slug . '.php';
					}

					if (!is_plugin_active($active_file_name)) {
						$button_txt = esc_html__('Install Now', 'nevo-sites-import');
						if (!$status) {
							// For GitHub install, we'll use a custom AJAX action
							$install_url = add_query_arg(
								array(
									'action' => 'install_github_plugin',
									'plugin' => $plugin_slug,
									'repo'   => $plugin_info['github_repo'],
									'branch' => $plugin_info['github_branch'],
									'_wpnonce' => wp_create_nonce('install-github-plugin_' . $plugin_slug),
								),
								admin_url('admin-ajax.php')
							);
						} else {
							$install_url = add_query_arg(
								array(
									'action'        => 'activate',
									'plugin'        => rawurlencode($active_file_name),
									'plugin_status' => 'all',
									'paged'         => '1',
									'_wpnonce'      => wp_create_nonce('activate-plugin_' . $active_file_name),
								),
								network_admin_url('plugins.php')
							);
							$button_class = 'activate-now button-primary';
							$button_txt   = esc_html__('Active Now', 'nevo-sites-import');
						}

						// No plugin-information for GitHub plugins, so we'll link to the GitHub repo
						$detail_link = 'https://github.com/' . esc_attr($plugin_info['github_repo']);

						echo '<div class="rcp">';
						echo '<p class="action-btn plugin-card-' . esc_attr($plugin_slug) . '"><a href="' . esc_url($install_url) . '" data-slug="' . esc_attr($plugin_slug) . '" data-plugin-id="' . esc_attr($plugin_slug) . '" class="' . esc_attr($button_class) . '">' . $button_txt . '</a></p>'; // WPCS: XSS OK.
						echo '<a class="plugin-detail" href="' . esc_url($detail_link) . '" target="_blank">' . esc_html__('Details', 'nevo-sites-import') . '</a>';
						echo '</div>';
					} else {
						echo '<div class="rcp">';
						echo '<p><a href="' . esc_url($plugin_info['view_url']) . '" data-slug="' . esc_attr($plugin_slug) . '" class="view-plugin">' . esc_html($plugin_info['view_text']) . '</a></p>'; // WPCS: XSS OK.
						echo '</div>';
					}
					
					echo '</div>'; // Close plugin-container
				}
				?>
				<script type="text/javascript">
					jQuery(document).ready(function($) {
						// Store plugin data for reference
						var pluginData = <?php echo json_encode($plugins); // phpcs:ignore ?>;
						
						$('#plugin-filter .box-plugins').on('click', '.install-now', function(e) {
							e.preventDefault();
							var button = $(this);
							var url = button.attr('href');
							var pluginId = button.data('plugin-id');
							button.addClass('button installing updating-message');
							
							$.ajax({
								url: url,
								type: 'GET',
								success: function(response) {
									if (response.success) {
										var activateUrl = response.data.activate_url;
										button.attr('href', activateUrl);
										button.attr('class', 'activate-now button-primary');
										button.text('<?php echo esc_js(__('Active Now', 'nevo-sites-import')); ?>');
									} else {
										button.removeClass('button installing updating-message');
										alert(response.data.message || 'Installation failed');
									}
								},
								error: function() {
									button.removeClass('button installing updating-message');
									alert('<?php echo esc_js(__('Installation failed', 'nevo-sites-import')); ?>');
								}
							});
						});
						
						$('#plugin-filter .box-plugins').on('click', '.activate-now', function(e) {
							e.preventDefault();
							var button = $(this);
							var url = button.attr('href');
							var pluginId = button.data('plugin-id');
							var pluginInfo = pluginData[pluginId];
							button.addClass('button installing updating-message');
							
							$.get(url, function() {
								var container = button.closest('.plugin-container');
								container.find('.plugin-detail').hide();
								button.attr('href', pluginInfo.view_url);
								button.attr('class', 'view-plugin');
								button.text(pluginInfo.view_text);
							});
						});
					});
				</script>
			</div>
		</div>
		<?php
	}

	function box_child_themes() {
		?>
		<div class="cd-box box-child-themes">
			<div class="cd-box-top"><?php _e('Nevo ready to import child themes', 'nevo-sites-import'); ?></div>
			<div class="cd-box-body">
				<?php
				// Define multiple child themes
				$child_themes = array(
					'nevo-basic-child' => array(
						'name'          => 'nevo-basic-child',
						'github_repo'   => 'CaoNguyenHN/nevo-basic-child',
						'github_branch' => 'main',
						'title'         => __('Nevo Basic Child Theme', 'nevo-sites-import'),
						'description'   => __('A basic child theme for Nevo.', 'nevo-sites-import'),
						'preview_url'   => 'https://github.com/CaoNguyenHN/nevo-basic-child',
						'screenshot_url' => 'https://raw.githubusercontent.com/caonguyenhn/nevo-basic-child/master/screenshot.png',
					),
					'nevo-child' => array(
						'name'          => 'nevo-child',
						'github_repo'   => 'CaoNguyenHN/nevo-child',
						'github_branch' => 'main',
						'title'         => __('Nevo Child Theme', 'nevo-sites-import'),
						'description'   => __('Default Nevo child theme For Classic EDITOR. This version includes optimization functions - removing some unnecessary functions for Wordpres Website. You can find and customize in the nevo-child/functions.php file.', 'nevo-sites-import'),
						'preview_url'   => 'https://github.com/CaoNguyenHN/nevo-child',
						'screenshot_url' => 'https://raw.githubusercontent.com/caonguyenhn/nevo-child/master/screenshot.png',
					),
					'nevo-child-block' => array(
						'name'          => 'nevo-child-block',
						'github_repo'   => 'CaoNguyenHN/nevo-child-block',
						'github_branch' => 'main',
						'title'         => __('Nevo Child Block Theme', 'nevo-sites-import'),
						'description'   => __('The default child theme for the Nevo parent theme uses blocks (Gutenberg), which is a theme that contains pre-built block templates. This version includes optimization functions - removing some unnecessary functions for Wordpres Website. You can find and customize in the nevo-child/functions.php file.', 'nevo-sites-import'),
						'preview_url'   => 'https://github.com/CaoNguyenHN/nevo-child-block',
						'screenshot_url' => 'https://raw.githubusercontent.com/caonguyenhn/nevo-child-block/master/screenshot.png',
					),
				);
				
				// Get current active theme
				$current_theme = wp_get_theme();
				$current_theme_slug = $current_theme->get_stylesheet();
				
				// Output each child theme
				foreach ($child_themes as $theme_slug => $theme_info) {
					echo '<div class="cd-box-content cd-box-shadow" id="theme-' . esc_attr($theme_slug) . '">';
					echo '<div class="cd-sites-thumb"><img src="' . esc_html($theme_info['screenshot_url']) . '"></div>';
					echo '<h3>' . esc_html($theme_info['title']) . '</h3>';
					echo '<p>' . esc_html($theme_info['description']) . '</p>';
					
					$theme_info = wp_parse_args(
						$theme_info,
						array(
							'name'          => '',
							'github_repo'   => '',
							'github_branch' => 'main',
							'preview_url'   => '',
						)
					);
					
					$theme_exists = wp_get_theme($theme_slug)->exists();
					$is_active = ($current_theme_slug === $theme_slug);
					
					$button_class = 'install-now button';
					$button_txt = esc_html__('Install Now', 'nevo-sites-import');
					
					// Child theme is not installed
					if (!$theme_exists) {
						// For GitHub install, use a custom AJAX action
						$install_url = add_query_arg(
							array(
								'action'   => 'install_github_child_theme',
								'theme'    => $theme_slug,
								'repo'     => $theme_info['github_repo'],
								'branch'   => $theme_info['github_branch'],
								'_wpnonce' => wp_create_nonce('install-github-child-theme_' . $theme_slug),
							),
							admin_url('admin-ajax.php')
						);
					} 
					// Child theme is installed but not active
					elseif ($theme_exists && !$is_active) {
						$install_url = add_query_arg(
							array(
								'action'     => 'activate',
								'stylesheet' => rawurlencode($theme_slug),
								'_wpnonce'   => wp_create_nonce('switch-theme_' . $theme_slug),
							),
							admin_url('themes.php')
						);
						$button_class = 'activate-now button-primary';
						$button_txt   = esc_html__('Activate Now', 'nevo-sites-import');
					} 
					// Child theme is installed and active
					else {
						$install_url = '#';
						$button_class = 'button disabled';
						$button_txt   = esc_html__('Active Theme', 'nevo-sites-import');
					}

					// Preview link
					$detail_link = $theme_info['preview_url'];

					echo '<div class="rcp">';
					
					// Don't add data attributes to the disabled button
					if ($is_active) {
						echo '<p class="action-btn theme-card-' . esc_attr($theme_slug) . '"><a href="' . esc_url($install_url) . '" class="' . esc_attr($button_class) . '">' . $button_txt . '</a></p>';
					} else {
						echo '<p class="action-btn theme-card-' . esc_attr($theme_slug) . '"><a href="' . esc_url($install_url) . '" data-slug="' . esc_attr($theme_slug) . '" data-theme-id="' . esc_attr($theme_slug) . '" class="' . esc_attr($button_class) . '">' . $button_txt . '</a></p>';
					}
					
					echo '<a class="theme-detail plugin-detail" href="' . esc_url($detail_link) . '" target="_blank">' . esc_html__('Preview', 'nevo-sites-import') . '</a>';
					echo '</div>';
					
					echo '</div>'; // Close theme-container
				}
				?>
				<script type="text/javascript">
					jQuery(document).ready(function($) {
						// Store theme data for reference
						var themeData = <?php echo json_encode($child_themes); // phpcs:ignore ?>;
						
						// Install button click handler
						$('.box-child-themes .install-now').on('click', function(e) {
							e.preventDefault();
							var button = $(this);
							var url = button.attr('href');
							var themeId = button.data('theme-id');
							
							console.log('Install button clicked');
							console.log('URL:', url);
							console.log('Theme ID:', themeId);
							
							button.addClass('installing updating-message');
							button.text('<?php echo esc_js(__('Installing...', 'nevo-sites-import')); ?>');
							
							$.ajax({
								url: url,
								type: 'GET',
								dataType: 'json',
								success: function(response) {
									console.log('Response:', response);
									if (response.success) {
										var activateUrl = response.data.activate_url;
										button.attr('href', activateUrl);
										button.removeClass('install-now installing updating-message');
										button.addClass('activate-now button-primary');
										button.text('<?php echo esc_js(__('Activate Now', 'nevo-sites-import')); ?>');
										
										// Cập nhật event handler cho nút mới
										button.off('click');
										button.on('click', activateClickHandler);
									} else {
										button.removeClass('installing updating-message');
										button.text('<?php echo esc_js(__('Install Now', 'nevo-sites-import')); ?>');
										alert(response.data.message || 'Installation failed');
									}
								},
								error: function(xhr, status, error) {
									console.log('Error:', xhr.responseText);
									button.removeClass('installing updating-message');
									button.text('<?php echo esc_js(__('Install Now', 'nevo-sites-import')); ?>');
									alert('<?php echo esc_js(__('Installation failed', 'nevo-sites-import')); ?>: ' + error);
								}
							});
						});
						
						// Định nghĩa hàm xử lý sự kiện click cho nút Activate
						function activateClickHandler(e) {
							e.preventDefault();
							var button = $(this);
							var url = button.attr('href');
							
							console.log('Activate button clicked');
							console.log('URL:', url);
							
							button.addClass('updating-message');
							button.text('<?php echo esc_js(__('Activating...', 'nevo-sites-import')); ?>');
							
							// Chuyển hướng đến trang kích hoạt
							window.location.href = url;
							return false;
						}
						
						// Đăng ký sự kiện cho các nút Activate đã tồn tại
						$('.box-child-themes .activate-now').on('click', activateClickHandler);
						
						// Disable "Active Theme" buttons
						$('.box-child-themes .disabled').on('click', function(e) {
							e.preventDefault();
							return false;
						});
					});
				</script>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Check if we are in development mode, pass a flag status to the javascript via `localize_script` variable.
	 *
	 * @param array $data The scripts data array.
	 * @return boolean
	 */
	public function check_dev_mode( $data ) {
		if ( defined( 'NEVO_DEVELOPMENT_MODE' ) && NEVO_DEVELOPMENT_MODE ) {
			$data['dev_mode'] = true;
		}

		return $data;
	}

	/**
	 * Add custom mimes for the uploader.
	 *
	 * @param array $mimes MIME types.
	 * @return array
	 */
	public function add_custom_mimes( $mimes ) {
		// Allow SVG files.
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';

		// Allow XML files.
		$mimes['xml'] = 'text/xml';

		// Allow JSON files.
		$mimes['json'] = 'application/json';

		return $mimes;
	}

	/**
	 * Filters the "real" file type of the given file.
	 *
	 * @param array  $wp_check_filetype_and_ext File info.
	 * @param string $file                      File.
	 * @param string $filename                  File name.
	 * @param array  $mimes                     MIME types.
	 * @return array
	 */
	public function real_mime_type_for_xml( $wp_check_filetype_and_ext, $file, $filename, $mimes ) {
		if ( '.xml' === substr( $filename, -4 ) ) {
			$wp_check_filetype_and_ext['ext']  = 'xml';
			$wp_check_filetype_and_ext['type'] = 'text/xml';
		}

		return $wp_check_filetype_and_ext;
	}

	/**
	 * Add admin submenu page: Appearance > Sites Import.
	 */
	public function register_admin_menu() {
		add_theme_page(
			esc_html__( 'Nevo Sites Import', 'nevo-sites-import' ),
			esc_html__( 'Sites Import', 'nevo-sites-import' ),
			'edit_theme_options',
			'nevo-sites-import',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue custom scripts on site import page.
	 *
	 * @param string $hook_suffix Current admin page's slug.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		if ( 'appearance_page_nevo-sites-import' === $hook_suffix ) {
			$suffix = SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_style( 'nevo-sites-import', NEVO_SITES_IMPORT_URI . 'assets/css/sites-import' . $suffix . '.css', array(), NEVO_SITES_IMPORT_VERSION );
			wp_style_add_data( 'nevo-sites-import', 'rtl', 'replace' );
			wp_style_add_data( 'nevo-sites-import', 'suffix', $suffix );

			wp_enqueue_script( 'nevo-sites-import', NEVO_SITES_IMPORT_URI . 'assets/js/sites-import' . $suffix . '.js', array( 'jquery', 'wp-util', 'updates' ), NEVO_SITES_IMPORT_VERSION, true );
			wp_localize_script(
				'nevo-sites-import',
				'NevoSitesImportScriptsData',
				apply_filters(
					'nevo/sites_import/scripts_data',
					array(
						'home_url'         => home_url(),
						'api_url'          => self::$api_url,
						'nonce'            => wp_create_nonce( 'nevo-sites-import' ),
						'license_key'      => get_option( 'nevo_pro_license_key', null ),
						'selected_builder' => intval( get_option( 'nevo_sites_import_selected_builder' ) ),
						'strings'          => array(
							'plugin_not_installed'        => esc_html__( 'Install & Activate', 'nevo-sites-import' ),
							'plugin_installing'           => esc_html__( 'Installing', 'nevo-sites-import' ),
							'plugin_inactive'             => esc_html__( 'Activate', 'nevo-sites-import' ),
							'plugin_activating'           => esc_html__( 'Activating', 'nevo-sites-import' ),
							'plugin_active'               => esc_html__( 'Active', 'nevo-sites-import' ),

							'action_upgrade_required'     => esc_html__( 'Upgrade Your License', 'nevo-sites-import' ),
							'action_plugins_not_active'   => esc_html__( 'Please Activate Required Plugins', 'nevo-sites-import' ),
							'action_ready_to_import'      => esc_html__( 'Import This Site', 'nevo-sites-import' ),
							'action_validating_data'      => esc_html__( 'Validating data...', 'nevo-sites-import' ),
							'action_preparing_import'     => esc_html__( 'Preparing import', 'nevo-sites-import' ),
							'action_importing_contents'   => esc_html__( 'Importing contents...', 'nevo-sites-import' ),
							'action_importing_customizer' => esc_html__( 'Importing theme options...', 'nevo-sites-import' ),
							'action_importing_widgets'    => esc_html__( 'Importing widgets...', 'nevo-sites-import' ),
							'action_importing_options'    => esc_html__( 'Importing other options...', 'nevo-sites-import' ),
							'action_finalizing_import'    => esc_html__( 'Finalizing import...', 'nevo-sites-import' ),
							'action_finished'             => esc_html__( 'Finished! Visit your site', 'nevo-sites-import' ),

							'confirm_import'              => esc_html__( "Before importing this site site, please note:\n\n1. It is recommended to run import on a fresh WordPress installation (no data has been added). You can reset to fresh installation using any \"WordPress reset\" plugin.\n\n2. Importing site site data into a non-fresh installation might overwrite your existing content.\n\n3. Copyrighted media will not be imported and will be replaced with placeholders.\n\n", 'nevo-sites-import' ),

							'confirm_close_importing'     => esc_html__( 'Warning! The import process is not finished yet. Do not close the window until import process complete, otherwise the imported data might be corrupted. Do you still want to leave the window?', 'nevo-sites-import' ),

							'site_error_invalid'          => esc_html__( 'Failed to fetch site info', 'nevo-sites-import' ),
							'plugin_error_invalid'        => esc_html__( 'Invalid plugin status, please refresh this page.', 'nevo-sites-import' ),
							'action_error_invalid'        => esc_html__( 'Invalid action, please refresh this page.', 'nevo-sites-import' ),
							'import_error_invalid'        => esc_html__( 'Invalid requirements for importing, please refresh this page.', 'nevo-sites-import' ),
						),
					)
				)
			);
		}
	}

	/**
	 * ====================================================
	 * AJAX functions
	 * ====================================================
	 */

	/**
	 * AJAX callback when selecting builder.
	 */
	public function ajax_select_builder() {
		check_ajax_referer( 'nevo-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'manage_options' ) || ! isset( $_REQUEST['builder'] ) ) {
			wp_send_json_error();
		}

		update_option( 'nevo_sites_import_selected_builder', sanitize_text_field( wp_unslash( $_REQUEST['builder'] ) ) );

		wp_send_json_success();
	}

	/**
	 * AJAX callback to get status of plugins.
	 */
	public function ajax_get_plugins_status() {
		check_ajax_referer( 'nevo-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'install_plugins' ) || ! isset( $_REQUEST['plugins'] ) ) {
			wp_send_json_error();
		}

		$response = array();

		foreach ( wp_unslash( $_REQUEST['plugins'] ) as $i => $plugin ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin['path'] ) ) {
				$response[ $i ] = 'not_installed';
			} elseif ( is_plugin_active( $plugin['path'] ) ) {
				$response[ $i ] = 'active';
			} else {
				$response[ $i ] = 'inactive';
			}
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX callback to install a plugin.
	 */
	public function ajax_install_plugin() {
		check_ajax_referer( 'nevo-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'install_plugins' ) || ! isset( $_REQUEST['plugin_slug'] ) ) {
			wp_send_json_error();
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}
		if ( ! class_exists( 'WP_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		$api = plugins_api(
			'plugin_information',
			array(
				'slug'   => sanitize_text_field( wp_unslash( $_REQUEST['plugin_slug'] ) ),
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			)
		);

		// Use AJAX upgrader skin instead of plugin installer skin.
		// ref: function wp_ajax_install_plugin().
		$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );

		$install = $upgrader->install( $api->download_link );

		if ( false === $install ) {
			wp_send_json_error();
		} else {
			wp_send_json_success();
		}
	}

	/**
	 * AJAX callback to activate a plugin.
	 */
	public function ajax_activate_plugin() {
		check_ajax_referer( 'nevo-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'install_plugins' ) || ! isset( $_REQUEST['plugin_path'] ) ) {
			wp_send_json_error();
		}

		wp_clean_plugins_cache();

		$activate = activate_plugin( sanitize_text_field( wp_unslash( $_REQUEST['plugin_path'] ) ), '', false, true );

		if ( is_wp_error( $activate ) ) {
			wp_send_json_error();
		} else {
			wp_send_json_success();
		}
	}

	/**
	 * AJAX callback to prepare anything before the import run.
	 */
	public function ajax_prepare_import() {
		check_ajax_referer( 'nevo-sites-import', '_ajax_nonce' );

		if ( ! isset( $_REQUEST['info'] ) ) {
			wp_send_json_error( esc_html__( 'No import info provided.', 'nevo-sites-import' ) );
		}

		/**
		 * Save info into database.
		 */

		$data = wp_parse_args(
			wp_unslash( $_REQUEST['info'] ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			array(
				'slug'                     => '',
				'required_plugins'         => '',
				'required_pro_modules'     => '',
				'contents_xml_file_url'    => '',
				'customizer_json_file_url' => '',
				'widgets_json_file_url'    => '',
				'options_json_file_url'    => '',
			)
		);

		update_option( 'nevo_sites_import_demo_info', $data );

		/**
		 * Activate pro modules (if any)
		 */

		if ( isset( $data['required_pro_modules'] ) && is_array( $data['required_pro_modules'] ) ) {
			$slugs = array();

			foreach ( $data['required_pro_modules'] as $key => $module ) {
				$slugs[] = $module['slug'];
			}

			update_option( 'nevo_modules', $slugs );
		}

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/prepare_import', $data );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * AJAX callback to download contents XML file and prepare for importing.
	 */
	public function ajax_prepare_contents() {
		check_ajax_referer( 'nevo-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not permitted to import contents.', 'nevo-sites-import' ) );
		}

		$data = get_option( 'nevo_sites_import_demo_info', array() );

		if ( ! isset( $data['contents_xml_file_url'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid downloadable XML file URL specified.', 'nevo-sites-import' ) );
		}

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/before_prepare_contents' );

		/**
		 * Clean up default contents.
		 */

		// Remove "Hello World" post.
		$posts = get_posts(
			array(
				'name'           => 'hello-world',
				'post_type'      => 'post',
				'posts_per_page' => 1,
			)
		);
		if ( 0 < count( $posts ) ) {
			wp_delete_post( $posts[0]->ID, true );
		}

		// Remove "Sample Page" page.
		$posts = get_posts(
			array(
				'name'           => 'sample-page',
				'post_type'      => 'page',
				'posts_per_page' => 1,
			)
		);
		if ( 0 < count( $posts ) ) {
			wp_delete_post( $posts[0]->ID, true );
		}

		// Remove default comment.
		wp_delete_comment( 1, true );

		/**
		 * Download contents.xml
		 */

		// Gives us access to the download_url() and wp_handle_sideload() functions.
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Get the XML file URL.
		$url = wp_unslash( $data['contents_xml_file_url'] );

		// Set timeout.
		$timeout_seconds = 5;

		// Download file to temp dir.
		$temp_file = download_url( $url, $timeout_seconds );

		if ( is_wp_error( $temp_file ) ) {
			wp_send_json_error( $temp_file->get_error_message() );
		}

		// Array based on $_FILE as seen in PHP file uploads.
		$file_args = array(
			'name'     => basename( $url ),
			'tmp_name' => $temp_file,
			'error'    => 0,
			'size'     => filesize( $temp_file ),
		);

		$overrides = array(
			// This tells WordPress to not look for the POST form
			// fields that would normally be present. Default is true.
			// Since the file is being downloaded from a remote server,
			// there will be no form fields.
			'test_form'   => false,

			// Setting this to false lets WordPress allow empty files – not recommended.
			'test_size'   => true,

			// A properly uploaded file will pass this test.
			// There should be no reason to override this one.
			'test_upload' => true,

			'mimes'       => array(
				'xml' => 'text/xml',
			),
		);

		// Move the temporary file into the uploads directory.
		$download_response = wp_handle_sideload( $file_args, $overrides );

		// Error when downloading XML file.
		if ( isset( $download_response['error'] ) ) {
			wp_send_json_error( $download_response['error'] );
		}

		/**
		 * Successfully downloaded, now create an attachment post for the XML file.
		 */

		// Save currently processed XML file ID in wp_options.
		update_option( 'nevo_sites_import_xml_path', $download_response['file'] );

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/after_prepare_contents' );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * AJAX callback to import contents and media files from contents.xml.
	 */
	public function ajax_import_contents() {
		check_admin_referer( 'nevo-sites-import', '_ajax_nonce' );

		// Include the importer class.
		require_once trailingslashit( NEVO_SITES_IMPORT_INCLUDES_DIR ) . 'wxr-importer/class-nevo-wxr-importer.php';

		/**
		 * Prepare XML.
		 */

		$xml_path = get_option( 'nevo_sites_import_xml_path', '' );

		if ( ! file_exists( $xml_path ) ) {
			wp_send_json_error( esc_html__( 'Invalid XML file path.', 'nevo-sites-import' ) );
		}

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/before_import_contents' );

		/**
		 * Run importer.
		 */

		Nevo_WXR_Importer::instance()->sse_import( $xml_path );

		/**
		 * After completed
		 */

		// Clean the XML ID on database.
		delete_option( 'nevo_sites_import_xml_path' );

		foreach ( get_terms( array( 'taxonomy' => 'nav_menu' ) ) as $menu ) {
			foreach ( wp_get_nav_menu_items( $menu->term_id ) as $menu_item ) {
				if ( 'custom' === $menu_item->type ) {
					update_post_meta( $menu_item->ID, '_menu_item_url', esc_url_raw( str_replace( $info->home, home_url(), $menu_item->url ) ) );
				}
			}
		}

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/after_import_contents' );

		exit;
	}

	/**
	 * AJAX callback to import customizer settings from customizer.json.
	 */
	public function ajax_import_customizer() {
		check_ajax_referer( 'nevo-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not permitted to import customizer.', 'nevo-sites-import' ) );
		}

		$data = get_option( 'nevo_sites_import_demo_info', array() );

		if ( ! isset( $data['customizer_json_file_url'] ) ) {
			wp_send_json_error( esc_html__( 'No customizer JSON file specified.', 'nevo-sites-import' ) );
		}

		/**
		 * Process customizer.json.
		 */

		// Get JSON data from customizer.json.
		$raw = wp_remote_get( wp_unslash( $data['customizer_json_file_url'] ) );

		// Abort if customizer.json response code is not successful.
		if ( 200 !== wp_remote_retrieve_response_code( $raw ) ) {
			wp_send_json_error();
		}

		// Decode raw JSON string to associative array.
		$array = json_decode( wp_remote_retrieve_body( $raw ), true );

		// Parse any dynamic values on the values array.
		$array = $this->parse_dynamic_values( $array );

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/before_import_customizer', $array );

		/**
		 * Import customizer settings to DB.
		 */

		update_option( 'theme_mods_' . get_stylesheet(), $array );

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/after_import_customizer', $array );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * AJAX callback to import widgets on all sidebars from widgets.json.
	 */
	public function ajax_import_widgets() {
		check_ajax_referer( 'nevo-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not permitted to import widgets.', 'nevo-sites-import' ) );
		}

		$data = get_option( 'nevo_sites_import_demo_info', array() );

		if ( ! isset( $data['widgets_json_file_url'] ) ) {
			wp_send_json_error( esc_html__( 'No widgets JSON file specified.', 'nevo-sites-import' ) );
		}

		/**
		 * Process widgets.json.
		 */

		// Get JSON data from widgets.json.
		$raw = wp_remote_get( wp_unslash( $data['widgets_json_file_url'] ) );

		// Abort if customizer.json response code is not successful.
		if ( 200 !== wp_remote_retrieve_response_code( $raw ) ) {
			wp_send_json_error();
		}

		// Decode raw JSON string to associative array.
		$array = json_decode( wp_remote_retrieve_body( $raw ), true );

		// Parse any dynamic values on the values array.
		$array = $this->parse_dynamic_values( $array );

		/**
		 * List all registered widgets.
		 */

		$registered_widgets = array();

		global $wp_registered_widget_controls;

		foreach ( $wp_registered_widget_controls as $widget ) {
			// Add widget to available list.
			if ( ! empty( $widget['id_base'] ) && ! in_array( $widget['id_base'], $registered_widgets, true ) ) {
				$registered_widgets[] = $widget['id_base'];
			}
		}

		/**
		 * Get all instances of registered widgets.
		 */

		$widget_instances = array();

		// Add all instances of current widget type into the big "$widget_instances" array.
		foreach ( $registered_widgets as $widget_slug ) {
			$widget_instances[ $widget_slug ] = get_option( 'widget_' . $widget_slug, array() );
		}

		/**
		 * Replace widgets on sidebars.
		 */

		$sidebar_widgets = get_option( 'sidebars_widgets', array() );

		foreach ( $array as $sidebar_id => $widgets_in_sidebar ) {
			// Skip inactive widgets.
			if ( 'wp_inactive_widgets' === $sidebar_id ) {
				continue;
			}

			// Reset all widgets inside current sidebar (if already exists).
			$sidebar_widgets[ $sidebar_id ] = array();

			foreach ( $widgets_in_sidebar as $widget_instance_id => $widget_data ) {
				// Add widgets (IDs) of current sidebar to the "sidebar_widgets" array.
				$sidebar_widgets[ $sidebar_id ][] = $widget_instance_id;

				// Break down the widget instance id into widget slug and instance number.
				$widget_slug     = preg_replace( '/-[0-9]+$/', '', $widget_instance_id );
				$instance_number = str_replace( $widget_slug . '-', '', $widget_instance_id );

				// Add instance to the "widget_instances" array.
				// Automatically replace existing instance if already exists.
				$widget_instances[ $widget_slug ][ $instance_number ] = $widget_data;
			}
		}

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/before_import_widgets', $sidebar_widgets, $widget_instances );

		/**
		 * Import widgets to DB.
		 */

		// Import sidebar widgets to DB.
		update_option( 'sidebars_widgets', $sidebar_widgets );

		foreach ( $widget_instances as $widget_slug => $instances ) {
			// Sort widget instances.
			ksort( $widget_instances[ $widget_slug ], SORT_STRING );

			// Import widget instances to DB.
			update_option( 'widget_' . $widget_slug, $instances );
		}

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/after_import_widgets', $sidebar_widgets, $widget_instances );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * AJAX callback to import other options from options.json.
	 */
	public function ajax_import_options() {
		check_ajax_referer( 'nevo-sites-import', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_send_json_error( esc_html__( 'You are not permitted to import options.', 'nevo-sites-import' ) );
		}

		$data = get_option( 'nevo_sites_import_demo_info', array() );

		if ( ! isset( $data['options_json_file_url'] ) ) {
			wp_send_json_error( esc_html__( 'No options JSON file specified.', 'nevo-sites-import' ) );
		}

		/**
		 * Process options.json.
		 */

		// Get JSON data from options.json.
		$raw = wp_remote_get( wp_unslash( $data['options_json_file_url'] ) );

		// Abort if customizer.json response code is not successful.
		if ( 200 !== wp_remote_retrieve_response_code( $raw ) ) {
			wp_send_json_error();
		}

		// Decode raw JSON string to associative array.
		$array = json_decode( wp_remote_retrieve_body( $raw ), true );

		// Parse any dynamic values on the values array.
		$array = $this->parse_dynamic_values( $array );

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/before_import_options', $array );

		/**
		 * Import options to DB.
		 */

		foreach ( $array as $key => $value ) {
			// Skip option key with "__" prefix, because it will be treated specifically via the action hook.
			if ( '__' === substr( $key, 0, 2 ) ) {
				continue;
			}

			// Insert to options table.
			update_option( $key, $value );
		}

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/after_import_options', $array );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * AJAX callback to finalize anything after the import run.
	 */
	public function ajax_finalize_import() {
		check_ajax_referer( 'nevo-sites-import', '_ajax_nonce' );

		/**
		 * Reset info in database.
		 */

		update_option( 'nevo_sites_import_demo_info', array() );

		/**
		 * Action hook.
		 */

		do_action( 'nevo/sites_import/finalize_import' );

		/**
		 * Return successful AJAX.
		 */

		wp_send_json_success();
	}

	/**
	 * ====================================================
	 * Render functions
	 * ====================================================
	 */

	/**
	 * Render notice in admin page if Nevo theme is not installed.
	 */
	public function render_theme_not_installed_motice() {
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php
				esc_html_e( 'Nevo Sites Import (plugin) requires Nevo theme to be installed and activated.', 'nevo-sites-import' );

				$theme = wp_get_theme( 'nevo-sites-import' );
				if ( $theme->exists() ) {
					$url   = esc_url( add_query_arg( 'theme', 'nevo-sites-import', admin_url( 'themes.php' ) ) );
					$label = esc_html__( 'Activate Now', 'nevo-sites-import' );
				} else {
					$url   = esc_url( add_query_arg( 'search', 'nevo-sites-import', admin_url( 'theme-install.php' ) ) );
					$label = esc_html__( 'Install and Activate Now', 'nevo-sites-import' );
				}

				echo '&nbsp;&nbsp;<a class="button button-secondary" href="' . $url . '" style="margin: -0.5em 0;">' . $label . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		?>
		<div class="wrap nevo-sites-import-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<hr class="wp-header-end">

			<div class="wp-filter hide-if-no-js"><?php // Site filters (populated via JS). ?></div>

			<div class="theme-browser rendered">
				<div class="themes wp-clearfix"><?php // Queried site grid (populated via JS). ?></div>
			</div>

			<span class="spinner"></span>

			<?php // Preview popup (populated via JS). ?>
		</div>

		<!-- JS Template: filters. -->
		<script type="text/template" id="tmpl-nevo-sites-import-filters">
			<div class="nevo-sites-import-filters-left">
				<ul class="nevo-sites-import-builders-filter filter-links">
					<# for ( var i in data.builders ) { var item = data.builders[i]; #>
						<li><a href="#" data-id="{{ item.id }}">{{{ item.name }}}</a></li>
					<# } #>
				</ul>
			</div>
			<div class="nevo-sites-import-filters-right">
				<ul class="nevo-sites-import-categories-filter filter-links">
					<li><a href="#" data-id="-1" class="current"><?php esc_html_e( 'Show All', 'nevo-sites-import' ); ?></a></li>
					<# for ( var i in data.categories ) { var item = data.categories[i]; #>
						<li><a href="#" data-id="{{ item.id }}">{{{ item.name }}}</a></li>
					<# } #>
				</ul>
				<div class="search-form">
					<label class="screen-reader-text" for="wp-filter-search-input"><?php esc_html_e( 'Search site sites', 'nevo-sites-import' ); ?></label>
					<input placeholder="<?php esc_attr_e( 'or enter keywords...', 'nevo-sites-import' ); ?>" type="search" aria-describedby="live-search-desc" id="wp-filter-search-input" class="wp-filter-search">
				</div>
			</div>
		</script>

		<!-- JS Template: site grid items. -->
		<script type="text/template" id="tmpl-nevo-sites-import-grid-items">
			<# for ( var i in data ) { var item = data[i]; #>
				<div class="theme" data-info="{{ JSON.stringify( item ) }}">
					<div class="theme-screenshot">
						<img src="{{ item.screenshot_url }}" alt="">
					</div>
					<span class="more-details"><?php esc_html_e( 'Details & Preview', 'nevo-sites-import' ); ?></span>
					<div class="theme-id-container">
						<h3 class="theme-name">{{{ item.name }}}<# if ( 1 === item.license_plan.id ) { #><span class="nevo-sites-import-badge nevo-sites-import-badge-pro"><?php esc_html_e( 'Pro', 'nevo-sites-import' ); ?></span><# } #></h3>
					</div>
				</div>
			<# } #>
		</script>

		<!-- JS Template: select builder. -->
		<script type="text/template" id="tmpl-nevo-sites-import-select-builder">
			<p class="nevo-sites-import-select-builder no-themes" style="display: block;"><?php esc_html_e( 'Select your page builder.', 'nevo-sites-import' ); ?></p>
		</script>

		<!-- JS Template: no site found. -->
		<script type="text/template" id="tmpl-nevo-sites-import-no-site-found">
			<p class="no-themes" style="display: block;"><?php esc_html_e( 'No site found. Try a different search.', 'nevo-sites-import' ); ?></p>
		</script>

		<!-- JS Template: preview popup. -->
		<script type="text/template" id="tmpl-nevo-sites-import-preview">
			<div class="nevo-sites-import-preview theme-install-overlay wp-full-overlay expanded">
				<div class="wp-full-overlay-sidebar">
					<div class="wp-full-overlay-header">
						<button class="close-full-overlay"><span class="screen-reader-text"><?php esc_html_e( 'Close', 'nevo-sites-import' ); ?></span></button>
					</div>

					<div class="wp-full-overlay-sidebar-content">
						<div class="install-theme-info">
							<h3 class="theme-name">{{{ data.name }}}</h3>
							<div class="theme-by">{{{ data.categories.map( category => category.name ).join( ', ' ) }}}</div>
							<img class="theme-screenshot" src="{{ data.screenshot_url }}" alt="">
							<#
							switch ( data.status ) {
								case 'require_higher_license_plan':
									#>
									<div class="nevo-sites-import-preview-notice notice inline notice-alt notice-warning">
										<p><strong><?php esc_html_e( 'Pro Demo Site', 'nevo-sites-import' ); ?></strong></p>
										<p><?php esc_html_e( 'To import this demo site you need an active license of {{ data.license_plan.name }} plan. Please upgrade or renew your license first.', 'nevo-sites-import' ); ?></p>
										<p><?php esc_html_e( 'If you already have an active license, please install the Nevo Pro plugin and activate your license on "Appearance > Nevo" page.', 'nevo-sites-import' ); ?></p>
									</div>
									<#
									break;

								default:
									#>
									<div class="nevo-sites-import-preview-notice notice inline notice-alt notice-warning">
										<p><?php esc_html_e( 'Make sure you disable the "debug" mode because it may disrupt the import process.', 'nevo-sites-import' ); ?></p>
										<p><a href="https://codex.wordpress.org/WP_DEBUG" target="_blank" rel="noopener"><?php esc_html_e( 'Learn how to disable debug mode', 'nevo-sites-import' ); ?></a></p>
									</div>
									<#

									if ( 0 < data.required_plugins.length ) {
										#>
										<div class="nevo-sites-import-preview-required-plugins">
											<h4><?php esc_html_e( 'Required Plugins', 'nevo-sites-import' ); ?></h4>
											<ul>
												<# for ( i in data.required_plugins ) { #>
													<li>
														<span class="nevo-sites-import-preview-required-plugin-name">{{{ data.required_plugins[ i ].name }}}</span>
														<button class="nevo-sites-import-preview-required-plugin-button button button-link disabled" data-index="{{ i }}" data-slug="{{ data.required_plugins[ i ].slug }}" data-status="loading" disabled>
															<img src="<?php echo esc_url( admin_url( '/images/spinner-2x.gif' ) ); ?>">
														</button>
													</li>
												<# } #>
											</ul>
										</div>
										<#
									}
									break;
							}
							#>
						</div>
					</div>

					<div class="wp-full-overlay-footer">
						<div class="nevo-sites-import-preview-actions">
							<button class="nevo-sites-import-preview-action-button button button-hero button-link disabled" data-status="loading" disabled>
								<img src="<?php echo esc_url( admin_url( '/images/spinner-2x.gif' ) ); ?>">
							</button>
						</div>
					</div>
				</div>

				<div class="wp-full-overlay-main">
					<iframe src="{{ data.preview_url }}" title="<?php esc_attr_e( 'Preview', 'nevo-sites-import' ); ?>"></iframe>
				</div>
			</div>
		</script>

		<!-- JS Template: load more. -->
		<script type="text/template" id="tmpl-nevo-sites-import-load-more">
			<div class="nevo-sites-import-load-more">
				<button class="button button-secondary button-hero">
					<?php esc_html_e( 'Load More', 'nevo-sites-import' ); ?>
				</button>
			</div>
		</script>
		<?php
	}

	/**
	 * ====================================================
	 * Public functions
	 * ====================================================
	 */

	/**
	 * Parse dynamic values from the specified associative array.
	 *
	 * @param array $values Values array.
	 * @return array
	 */
	public function parse_dynamic_values( $values ) {
		foreach ( $values as $key => $value ) {
			// Check the value recursively on an array value.
			if ( is_array( $value ) ) {
				$values[ $key ] = $this->parse_dynamic_values( $value );
				continue;
			}

			// Process the value.
			$matches = array();

			// Try to parse dynamic value syntax.
			$is_dynamic = preg_match( '/\[\[(.*?)\?(.*?)\]\]/', $value, $matches );

			// Process dynamic value.
			if ( $is_dynamic && 3 === count( $matches ) ) {
				$query_type = $matches[1];
				$query_args = wp_parse_args( $matches[2] );

				switch ( $query_type ) {
					case 'post_id':
						if ( isset( $query_args['post_type'] ) && isset( $query_args['slug'] ) ) {
							$posts = get_posts(
								array(
									'name'           => $query_args['slug'],
									'post_type'      => $query_args['post_type'],
									'posts_per_page' => 1,
								)
							);

							if ( 0 < count( $posts ) ) {
								$values[ $key ] = (int) $posts[0]->ID;
							} else {
								$values[ $key ] = -1;
							}
						} else {
							$values[ $key ] = -1;
						}
						break;

					case 'term_id':
						if ( isset( $query_args['taxonomy'] ) && isset( $query_args['slug'] ) ) {
							$term = get_term_by( 'slug', $query_args['slug'], $query_args['taxonomy'] );

							if ( $term ) {
								$values[ $key ] = (int) $term->term_id;
							} else {
								$values[ $key ] = -1;
							}
						} else {
							$values[ $key ] = -1;
						}
						break;

					case 'attachment_url':
						if ( isset( $query_args['slug'] ) ) {
							$posts = get_posts(
								array(
									'name'           => $query_args['slug'],
									'post_type'      => 'attachment',
									'post_status'    => 'inherit',
									'posts_per_page' => 1,
								)
							);

							if ( 0 < count( $posts ) ) {
								$image_info = wp_get_attachment_image_src( $posts[0]->ID, isset( $query_args['size'] ) ? $query_args['size'] : 'full' );
								if ( false !== $image_info ) {
									$values[ $key ] = $image_info[0]; // Image URL.
								} else {
									$values[ $key ] = '';
								}
							} else {
								$values[ $key ] = '';
							}
						} else {
							$values[ $key ] = '';
						}
						break;

					case 'home_url':
						if ( isset( $query_args['uri'] ) ) {
							$values[ $key ] = untrailingslashit( home_url() ) . $query_args['uri'];
						}
						break;
				}
			}
		}

		return $values;
	}
}

// Initialize plugin.
Nevo_Sites_Import::instance();
// Add the AJAX handler for installing GitHub plugins
add_action('wp_ajax_install_github_plugin', 'nevo_install_github_plugin');
function nevo_install_github_plugin() {
	// Check for permissions
	if (!current_user_can('install_plugins')) {
		wp_send_json_error(array('message' => __('You do not have permission to install plugins.', 'nevo-sites-import')));
	}
	
	// Verify nonce
	if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'install-github-plugin_' . $_GET['plugin'])) {
		wp_send_json_error(array('message' => __('Security check failed.', 'nevo-sites-import')));
	}
	
	// Get parameters
	$plugin_slug = isset($_GET['plugin']) ? sanitize_text_field($_GET['plugin']) : '';
	$repo = isset($_GET['repo']) ? sanitize_text_field($_GET['repo']) : '';
	$branch = isset($_GET['branch']) ? sanitize_text_field($_GET['branch']) : 'main';
	
	if (empty($plugin_slug) || empty($repo)) {
		wp_send_json_error(array('message' => __('Missing required parameters.', 'nevo-sites-import')));
	}
	
	// Download the ZIP file from GitHub
	$download_url = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
	$download_file = download_url($download_url);
	
	if (is_wp_error($download_file)) {
		wp_send_json_error(array('message' => $download_file->get_error_message()));
	}
	
	// Unzip the file
	$plugins_dir = WP_PLUGIN_DIR;
	
	// Properly initialize WP_Filesystem
	global $wp_filesystem;
	if (!function_exists('WP_Filesystem')) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	
	// Initialize with proper context - using direct method when possible
	$creds = request_filesystem_credentials('', '', false, false, null);
	if (!WP_Filesystem($creds, '')) {
		@unlink($download_file); // Clean up the downloaded file
		wp_send_json_error(array('message' => __('Could not initialize filesystem.', 'nevo-sites-import')));
		return;
	}
	
	$unzip_result = unzip_file($download_file, $plugins_dir);
	
	// Clean up the downloaded zip file
	@unlink($download_file);
	
	if (is_wp_error($unzip_result)) {
		wp_send_json_error(array('message' => $unzip_result->get_error_message()));
	}
	
	// GitHub unzips to folder named {repo}-{branch}, but we need to rename it to match our plugin slug
	$extracted_folder = trailingslashit($plugins_dir) . basename($repo) . '-' . $branch;
	$target_folder = trailingslashit($plugins_dir) . $plugin_slug;
	
	// Remove target folder if it exists (for updates)
	if (is_dir($target_folder)) {
		$wp_filesystem->delete($target_folder, true);
	}
	
	// Rename the folder
	$rename_result = $wp_filesystem->move($extracted_folder, $target_folder);
	
	if (!$rename_result) {
		wp_send_json_error(array('message' => __('Failed to rename plugin folder.', 'nevo-sites-import')));
	}
	
	// Create activation URL
	$active_file_name = $plugin_slug . '/' . $plugin_slug . '.php';
	$activate_url = add_query_arg(
		array(
			'action'        => 'activate',
			'plugin'        => rawurlencode($active_file_name),
			'plugin_status' => 'all',
			'paged'         => '1',
			'_wpnonce'      => wp_create_nonce('activate-plugin_' . $active_file_name),
		),
		network_admin_url('plugins.php')
	);
	
	wp_send_json_success(array(
		'message' => __('Plugin installed successfully.', 'nevo-sites-import'),
		'activate_url' => $activate_url
	));
}

// Add the AJAX handler for installing GitHub child themes
add_action('wp_ajax_install_github_child_theme', 'nevo_install_github_child_theme');
function nevo_install_github_child_theme() {
	// Check for permissions
	if (!current_user_can('install_themes')) {
		wp_send_json_error(array('message' => __('You do not have permission to install themes.', 'nevo-sites-import')));
	}
	
	// Verify nonce
	if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'install-github-child-theme_' . $_GET['theme'])) {
		wp_send_json_error(array('message' => __('Security check failed.', 'nevo-sites-import')));
	}
	
	// Get parameters
	$theme_slug = isset($_GET['theme']) ? sanitize_text_field($_GET['theme']) : '';
	$repo = isset($_GET['repo']) ? sanitize_text_field($_GET['repo']) : '';
	$branch = isset($_GET['branch']) ? sanitize_text_field($_GET['branch']) : 'main';
	
	if (empty($theme_slug) || empty($repo)) {
		wp_send_json_error(array('message' => __('Missing required parameters.', 'nevo-sites-import')));
	}
	
	// Download the ZIP file from GitHub
	$download_url = "https://github.com/{$repo}/archive/refs/heads/{$branch}.zip";
	$download_file = download_url($download_url);
	
	if (is_wp_error($download_file)) {
		wp_send_json_error(array('message' => $download_file->get_error_message()));
	}
	
	// Unzip the file
	$themes_dir = get_theme_root();
	
	// Properly initialize WP_Filesystem
	global $wp_filesystem;
	if (!function_exists('WP_Filesystem')) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	
	// Initialize with proper context - using direct method when possible
	$creds = request_filesystem_credentials('', '', false, false, null);
	if (!WP_Filesystem($creds, '')) {
		@unlink($download_file); // Clean up the downloaded file
		wp_send_json_error(array('message' => __('Could not initialize filesystem.', 'nevo-sites-import')));
		return;
	}
	
	$unzip_result = unzip_file($download_file, $themes_dir);
	
	// Clean up the downloaded zip file
	@unlink($download_file);
	
	if (is_wp_error($unzip_result)) {
		wp_send_json_error(array('message' => $unzip_result->get_error_message()));
	}
	
	// GitHub unzips to folder named {repo}-{branch}, but we need to rename it to match our theme slug
	$extracted_folder = trailingslashit($themes_dir) . basename($repo) . '-' . $branch;
	$target_folder = trailingslashit($themes_dir) . $theme_slug;
	
	// Remove target folder if it exists (for updates)
	if (is_dir($target_folder)) {
		$wp_filesystem->delete($target_folder, true);
	}
	
	// Rename the folder
	$rename_result = $wp_filesystem->move($extracted_folder, $target_folder);
	
	if (!$rename_result) {
		wp_send_json_error(array('message' => __('Failed to rename theme folder.', 'nevo-sites-import')));
	}
	
	// Create activation URL
	$activate_url = add_query_arg(
		array(
			'action'     => 'activate',
			'stylesheet' => rawurlencode($theme_slug),
			'_wpnonce'   => wp_create_nonce('switch-theme_' . $theme_slug),
		),
		admin_url('themes.php')
	);
	
	wp_send_json_success(array(
		'message' => __('Child theme installed successfully.', 'nevo-sites-import'),
		'activate_url' => $activate_url
	));
}
