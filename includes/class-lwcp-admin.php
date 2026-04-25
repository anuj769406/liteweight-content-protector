<?php
/**
 * Admin settings page.
 *
 * @package LightweightContentProtection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin admin settings UI and related AJAX.
 *
 * @package LightweightContentProtection
 */
class LWCP_Admin {

	/**
	 * Core plugin instance.
	 *
	 * @var LWCP_Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param LWCP_Plugin $plugin Plugin instance.
	 */
	public function __construct( LWCP_Plugin $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_lwcp_search_pages', array( $this, 'ajax_search_pages' ) );
	}

	/**
	 * Enqueue admin assets only on the plugin settings screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'settings_page_lwcp-settings' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'lwcp-admin-settings',
			LWCP_PLUGIN_URL . 'assets/css/admin-settings.css',
			array(),
			LWCP_VERSION
		);

		wp_enqueue_script(
			'lwcp-admin-settings',
			LWCP_PLUGIN_URL . 'assets/js/admin-settings.js',
			array(),
			LWCP_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'lwcp-admin-settings',
			'LWCP_ADMIN',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'lwcp_page_search' ),
				'removeLabel' => __( 'Remove', 'liteweight-content-protector' ),
			)
		);
	}

	/**
	 * Register plugin settings and sanitization callback.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'lwcp_settings_group',
			LWCP_Plugin::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => LWCP_Plugin::default_settings(),
			)
		);
	}

	/**
	 * Add settings page under Settings menu.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Content Protection', 'liteweight-content-protector' ),
			__( 'Content Protection', 'liteweight-content-protector' ),
			'manage_options',
			'lwcp-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->plugin->get_settings();
		}

		$defaults = LWCP_Plugin::default_settings();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		$output['apply_mode'] = ( isset( $input['apply_mode'] ) && 'specific' === sanitize_key( $input['apply_mode'] ) ) ? 'specific' : 'global';

		$boolean_keys = array(
			'disable_copy',
			'disable_right_click',
			'allow_right_click_links',
			'disable_text_selection',
			'disable_image_drag',
			'disable_ctrl_u',
			'disable_ctrl_s',
			'disable_ctrl_p',
			'disable_f12',
			'enable_alerts',
			'bypass_logged_in',
		);

		foreach ( $boolean_keys as $key ) {
			$output[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		if ( ! empty( $input['alert_message'] ) ) {
			$output['alert_message'] = sanitize_text_field( wp_unslash( $input['alert_message'] ) );
		}

		$editable_roles = array_keys( get_editable_roles() );
		$roles_input    = isset( $input['bypass_roles'] ) && is_array( $input['bypass_roles'] ) ? array_map( 'sanitize_key', $input['bypass_roles'] ) : array();

		$output['bypass_roles'] = array_values(
			array_intersect( $roles_input, $editable_roles )
		);

		$page_ids = array();
		if ( isset( $input['excluded_page_ids'] ) ) {
			$raw_ids = $input['excluded_page_ids'];

			if ( is_array( $raw_ids ) ) {
				$page_ids = array_map( 'absint', $raw_ids );
			} else {
				$raw_ids  = sanitize_text_field( wp_unslash( $raw_ids ) );
				$parts    = preg_split( '/[,\s]+/', $raw_ids );
				$page_ids = array_map( 'absint', array_filter( (array) $parts ) );
			}
		}

		$page_ids = array_values( array_unique( array_filter( $page_ids ) ) );

		$output['excluded_page_ids'] = array();
		if ( ! empty( $page_ids ) ) {
			$valid_page_ids = get_posts(
				array(
					'post_type'              => 'page',
					'post_status'            => 'any',
					'post__in'               => $page_ids,
					'numberposts'            => count( $page_ids ),
					'fields'                 => 'ids',
					'orderby'                => 'post__in',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			$output['excluded_page_ids'] = array_map( 'absint', (array) $valid_page_ids );
		}

		$specific_page_ids = array();
		if ( isset( $input['specific_page_ids'] ) ) {
			$raw_ids = $input['specific_page_ids'];

			if ( is_array( $raw_ids ) ) {
				$specific_page_ids = array_map( 'absint', $raw_ids );
			} else {
				$raw_ids           = sanitize_text_field( wp_unslash( $raw_ids ) );
				$parts             = preg_split( '/[,\s]+/', $raw_ids );
				$specific_page_ids = array_map( 'absint', array_filter( (array) $parts ) );
			}
		}

		$specific_page_ids = array_values( array_unique( array_filter( $specific_page_ids ) ) );

		$output['specific_page_ids'] = array();
		if ( ! empty( $specific_page_ids ) ) {
			$valid_specific_ids = get_posts(
				array(
					'post_type'              => 'page',
					'post_status'            => 'any',
					'post__in'               => $specific_page_ids,
					'numberposts'            => count( $specific_page_ids ),
					'fields'                 => 'ids',
					'orderby'                => 'post__in',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			$output['specific_page_ids'] = array_map( 'absint', (array) $valid_specific_ids );
		}

		return $output;
	}

	/**
	 * Render settings page markup.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings                = $this->plugin->get_settings();
		$editable_roles          = get_editable_roles();
		$selected_excluded       = $this->get_selected_pages_data( (array) $settings['excluded_page_ids'] );
		$excluded_ids            = implode( ', ', array_map( 'absint', (array) $settings['excluded_page_ids'] ) );
		$selected_specific       = $this->get_selected_pages_data( (array) $settings['specific_page_ids'] );
		$specific_ids            = implode( ', ', array_map( 'absint', (array) $settings['specific_page_ids'] ) );
		$apply_mode              = isset( $settings['apply_mode'] ) && 'specific' === $settings['apply_mode'] ? 'specific' : 'global';
		$show_strict_mode_notice = ! empty( get_option( LWCP_Plugin::STRICT_LINKS_NOTICE_OPTION ) );
		$show_bypass_roles_notice = ! empty( get_option( LWCP_Plugin::BYPASS_ROLES_NOTICE_OPTION ) );

		if ( $show_strict_mode_notice ) {
			delete_option( LWCP_Plugin::STRICT_LINKS_NOTICE_OPTION );
		}

		if ( $show_bypass_roles_notice ) {
			delete_option( LWCP_Plugin::BYPASS_ROLES_NOTICE_OPTION );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Liteweight Content Protector', 'liteweight-content-protector' ); ?></h1>
			<p><?php esc_html_e( 'Apply lightweight restrictions only where needed to avoid performance or UX penalties.', 'liteweight-content-protector' ); ?></p>
			<?php if ( $show_strict_mode_notice ) : ?>
				<div class="notice notice-info is-dismissible">
					<p><?php esc_html_e( 'Update notice: strict right-click mode was enabled by default in version 1.0.3. You can re-enable right-click on standard navigation links from Content Restrictions if needed.', 'liteweight-content-protector' ); ?></p>
				</div>
			<?php endif; ?>
			<?php if ( $show_bypass_roles_notice ) : ?>
				<div class="notice notice-info is-dismissible">
					<p><?php esc_html_e( 'Update notice: default bypass roles were cleared in version 1.0.4 so restrictions also apply while logged in. Configure bypass roles again only if you intentionally want admin/editor to bypass protection.', 'liteweight-content-protector' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'lwcp_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Apply Mode', 'liteweight-content-protector' ); ?></th>
							<td>
								<label>
									<input type="radio" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[apply_mode]" value="global" <?php checked( 'global', $apply_mode ); ?> />
									<?php esc_html_e( 'Global: apply protection to all posts/pages (except exclusions below).', 'liteweight-content-protector' ); ?>
								</label><br />
								<label>
									<input type="radio" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[apply_mode]" value="specific" <?php checked( 'specific', $apply_mode ); ?> />
									<?php esc_html_e( 'Specific: apply protection only to selected pages.', 'liteweight-content-protector' ); ?>
								</label><br />
								<p class="description"><?php esc_html_e( 'Per-page meta box override still works for individual posts/pages.', 'liteweight-content-protector' ); ?></p>
								<p class="description"><strong><?php esc_html_e( 'How this works:', 'liteweight-content-protector' ); ?></strong></p>
								<p class="description"><?php esc_html_e( 'Global mode protects all posts/pages, then skips any page listed in Exclude Specific Pages.', 'liteweight-content-protector' ); ?></p>
								<p class="description"><?php esc_html_e( 'Specific mode protects only pages listed in Specific Protected Pages.', 'liteweight-content-protector' ); ?></p>
								<p class="description"><?php esc_html_e( 'Per-page override has highest priority: Force enable always protects, and Disable always turns protection off.', 'liteweight-content-protector' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Content Restrictions', 'liteweight-content-protector' ); ?></th>
							<td>
								<fieldset>
									<label><input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[disable_copy]" value="1" <?php checked( ! empty( $settings['disable_copy'] ) ); ?> /> <?php esc_html_e( 'Disable Ctrl/Cmd + C and copy event', 'liteweight-content-protector' ); ?></label><br />
									<label><input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[disable_right_click]" value="1" <?php checked( ! empty( $settings['disable_right_click'] ) ); ?> /> <?php esc_html_e( 'Disable right-click context menu', 'liteweight-content-protector' ); ?></label><br />
									<label><input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[allow_right_click_links]" value="1" <?php checked( ! empty( $settings['allow_right_click_links'] ) ); ?> /> <?php esc_html_e( 'Allow right-click on standard navigation links only', 'liteweight-content-protector' ); ?></label><br />
									<label><input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[disable_text_selection]" value="1" <?php checked( ! empty( $settings['disable_text_selection'] ) ); ?> /> <?php esc_html_e( 'Disable text selection (CSS-based)', 'liteweight-content-protector' ); ?></label><br />
									<label><input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[disable_image_drag]" value="1" <?php checked( ! empty( $settings['disable_image_drag'] ) ); ?> /> <?php esc_html_e( 'Disable image drag & drop saving', 'liteweight-content-protector' ); ?></label>
								</fieldset>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Keyboard Shortcuts', 'liteweight-content-protector' ); ?></th>
							<td>
								<fieldset>
									<label><input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[disable_ctrl_u]" value="1" <?php checked( ! empty( $settings['disable_ctrl_u'] ) ); ?> /> <?php esc_html_e( 'Disable Ctrl/Cmd + U (view source)', 'liteweight-content-protector' ); ?></label><br />
									<label><input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[disable_ctrl_s]" value="1" <?php checked( ! empty( $settings['disable_ctrl_s'] ) ); ?> /> <?php esc_html_e( 'Disable Ctrl/Cmd + S (save page)', 'liteweight-content-protector' ); ?></label><br />
									<label><input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[disable_ctrl_p]" value="1" <?php checked( ! empty( $settings['disable_ctrl_p'] ) ); ?> /> <?php esc_html_e( 'Disable Ctrl/Cmd + P (print)', 'liteweight-content-protector' ); ?></label><br />
									<label><input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[disable_f12]" value="1" <?php checked( ! empty( $settings['disable_f12'] ) ); ?> /> <?php esc_html_e( 'Disable F12 (best effort)', 'liteweight-content-protector' ); ?></label>
								</fieldset>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Alert Popup', 'liteweight-content-protector' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[enable_alerts]" value="1" <?php checked( ! empty( $settings['enable_alerts'] ) ); ?> />
									<?php esc_html_e( 'Show a small alert when an action is blocked', 'liteweight-content-protector' ); ?>
								</label>
								<p>
									<label class="screen-reader-text" for="lwcp-alert-message"><?php esc_html_e( 'Alert message text', 'liteweight-content-protector' ); ?></label>
									<input type="text" id="lwcp-alert-message" class="regular-text" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[alert_message]" value="<?php echo esc_attr( $settings['alert_message'] ); ?>" maxlength="160" />
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Debug / Bypass', 'liteweight-content-protector' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[bypass_logged_in]" value="1" <?php checked( ! empty( $settings['bypass_logged_in'] ) ); ?> />
									<?php esc_html_e( 'Bypass all restrictions for all logged-in users', 'liteweight-content-protector' ); ?>
								</label>
								<p><?php esc_html_e( 'Or keep this off and bypass only selected roles:', 'liteweight-content-protector' ); ?></p>
								<fieldset>
									<?php foreach ( $editable_roles as $role_key => $role_data ) : ?>
										<label>
											<input type="checkbox" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[bypass_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, (array) $settings['bypass_roles'], true ) ); ?> />
											<?php echo esc_html( $role_data['name'] ); ?>
										</label><br />
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>

						<tr id="lwcp-specific-pages-row">
							<th scope="row"><?php esc_html_e( 'Specific Protected Pages', 'liteweight-content-protector' ); ?></th>
							<td>
								<p><?php esc_html_e( 'Used when Apply Mode is set to Specific.', 'liteweight-content-protector' ); ?></p>
								<div
									id="lwcp-specific-pages-picker"
									class="lwcp-page-picker"
									data-initial-pages="<?php echo esc_attr( wp_json_encode( $selected_specific ) ); ?>"
								>
									<label class="screen-reader-text" for="lwcp-specific-page-search"><?php esc_html_e( 'Search specific protected pages', 'liteweight-content-protector' ); ?></label>
									<input type="search" id="lwcp-specific-page-search" class="regular-text" placeholder="<?php esc_attr_e( 'Type to search pages...', 'liteweight-content-protector' ); ?>" autocomplete="off" />
									<ul id="lwcp-specific-page-search-results" class="lwcp-page-search-results" aria-live="polite"></ul>
									<ul id="lwcp-specific-selected-pages" class="lwcp-selected-pages"></ul>
								</div>
								<label class="screen-reader-text" for="lwcp-specific-page-ids"><?php esc_html_e( 'Specific protected page IDs', 'liteweight-content-protector' ); ?></label>
								<input type="text" id="lwcp-specific-page-ids" class="regular-text" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[specific_page_ids]" value="<?php echo esc_attr( $specific_ids ); ?>" />
								<p class="description"><?php esc_html_e( 'Manual fallback: enter IDs separated by commas. Example: 5, 8, 21', 'liteweight-content-protector' ); ?></p>
							</td>
						</tr>

						<tr id="lwcp-excluded-pages-row">
							<th scope="row"><?php esc_html_e( 'Exclude Specific Pages', 'liteweight-content-protector' ); ?></th>
							<td>
								<p><?php esc_html_e( 'Search pages and add them to exclusion. Mainly useful in Global mode.', 'liteweight-content-protector' ); ?></p>
								<div
									id="lwcp-excluded-pages-picker"
									class="lwcp-page-picker"
									data-initial-pages="<?php echo esc_attr( wp_json_encode( $selected_excluded ) ); ?>"
								>
									<label class="screen-reader-text" for="lwcp-page-search"><?php esc_html_e( 'Search pages', 'liteweight-content-protector' ); ?></label>
									<input type="search" id="lwcp-page-search" class="regular-text" placeholder="<?php esc_attr_e( 'Type to search pages...', 'liteweight-content-protector' ); ?>" autocomplete="off" />
									<ul id="lwcp-page-search-results" class="lwcp-page-search-results" aria-live="polite"></ul>
									<ul id="lwcp-selected-pages" class="lwcp-selected-pages"></ul>
								</div>
								<label class="screen-reader-text" for="lwcp-excluded-page-ids"><?php esc_html_e( 'Excluded page IDs', 'liteweight-content-protector' ); ?></label>
								<input type="text" id="lwcp-excluded-page-ids" class="regular-text" name="<?php echo esc_attr( LWCP_Plugin::OPTION_KEY ); ?>[excluded_page_ids]" value="<?php echo esc_attr( $excluded_ids ); ?>" />
								<p class="description"><?php esc_html_e( 'Manual fallback: enter IDs separated by commas. Example: 12, 25, 300', 'liteweight-content-protector' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Search pages for admin exclusion picker.
	 *
	 * @return void
	 */
	public function ajax_search_pages() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'liteweight-content-protector' ) ), 403 );
		}

		check_ajax_referer( 'lwcp_page_search', 'nonce' );

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$term = trim( $term );

		if ( '' === $term ) {
			wp_send_json_success( array() );
		}

		$page_ids = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				's'                      => $term,
				'posts_per_page'         => 15,
				'fields'                 => 'ids',
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$results = array();
		foreach ( $page_ids as $page_id ) {
			$title = get_the_title( $page_id );
			$title = wp_strip_all_tags( (string) $title );

			$results[] = array(
				'id'    => (int) $page_id,
				'title' => $title ? $title : sprintf(
					/* translators: %d: page ID. */
					__( '(no title) #%d', 'liteweight-content-protector' ),
					$page_id
				),
			);
		}

		wp_send_json_success( $results );
	}

	/**
	 * Resolve saved IDs to small page payload for the picker.
	 *
	 * @param array $page_ids Saved page IDs.
	 * @return array
	 */
	private function get_selected_pages_data( $page_ids ) {
		$page_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $page_ids ) ) ) );

		if ( empty( $page_ids ) ) {
			return array();
		}

		$pages = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'any',
				'post__in'               => $page_ids,
				'posts_per_page'         => count( $page_ids ),
				'orderby'                => 'post__in',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$data = array();
		foreach ( $pages as $page ) {
			$page_title = wp_strip_all_tags( (string) $page->post_title );

			$data[] = array(
				'id'    => (int) $page->ID,
				'title' => $page_title ? $page_title : sprintf(
					/* translators: %d: page ID. */
					__( '(no title) #%d', 'liteweight-content-protector' ),
					$page->ID
				),
			);
		}

		return $data;
	}
}
