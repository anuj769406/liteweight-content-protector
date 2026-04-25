<?php
/**
 * Core plugin bootstrap and shared helpers.
 *
 * @package LightweightContentProtection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 *
 * @package LightweightContentProtection
 */
class LWCP_Plugin {

	const OPTION_KEY = 'lwcp_settings';
	const META_KEY   = '_lwcp_protection_mode';
	const STRICT_LINKS_MIGRATION_OPTION = 'lwcp_migrated_strict_links_103';
	const STRICT_LINKS_NOTICE_OPTION    = 'lwcp_show_strict_mode_notice_103';
	const BYPASS_ROLES_MIGRATION_OPTION = 'lwcp_migrated_bypass_roles_104';
	const BYPASS_ROLES_NOTICE_OPTION    = 'lwcp_show_bypass_roles_notice_104';

	/**
	 * Singleton instance.
	 *
	 * @var LWCP_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Cached settings array.
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Per-request cache for page protection checks.
	 *
	 * @var array<string,bool>
	 */
	private $page_protection_cache = array();

	/**
	 * Get singleton instance.
	 *
	 * @return LWCP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Set plugin defaults during activation.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION_KEY ) ) {
			add_option( self::OPTION_KEY, self::default_settings() );
		}
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		require_once LWCP_PLUGIN_DIR . 'includes/class-lwcp-admin.php';
		require_once LWCP_PLUGIN_DIR . 'includes/class-lwcp-meta-box.php';
		require_once LWCP_PLUGIN_DIR . 'includes/class-lwcp-frontend.php';

		add_action( 'init', array( $this, 'maybe_migrate_strict_right_click_default' ), 20 );
		add_action( 'init', array( $this, 'maybe_migrate_bypass_roles_default' ), 21 );

		if ( is_admin() ) {
			new LWCP_Admin( $this );
			new LWCP_Meta_Box( $this );
		}

		new LWCP_Frontend( $this );

		add_action( 'update_option_' . self::OPTION_KEY, array( $this, 'refresh_settings_cache' ), 10, 0 );
		add_action( 'added_option', array( $this, 'refresh_settings_cache_on_add' ), 10, 2 );
	}

	/**
	 * One-time migration to strict right-click default for existing installs.
	 *
	 * @return void
	 */
	public function maybe_migrate_strict_right_click_default() {
		if ( get_option( self::STRICT_LINKS_MIGRATION_OPTION ) ) {
			return;
		}

		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			update_option( self::STRICT_LINKS_MIGRATION_OPTION, 1 );

			return;
		}

		if ( ! empty( $stored['allow_right_click_links'] ) ) {
			$stored['allow_right_click_links'] = 0;
			update_option( self::OPTION_KEY, $stored );
			update_option( self::STRICT_LINKS_NOTICE_OPTION, 1 );
		}

		update_option( self::STRICT_LINKS_MIGRATION_OPTION, 1 );
	}

	/**
	 * One-time migration to remove default role-based bypass for existing installs.
	 *
	 * @return void
	 */
	public function maybe_migrate_bypass_roles_default() {
		if ( get_option( self::BYPASS_ROLES_MIGRATION_OPTION ) ) {
			return;
		}

		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			update_option( self::BYPASS_ROLES_MIGRATION_OPTION, 1 );

			return;
		}

		$current_roles = array();
		if ( ! empty( $stored['bypass_roles'] ) && is_array( $stored['bypass_roles'] ) ) {
			$current_roles = array_values( array_unique( array_map( 'sanitize_key', $stored['bypass_roles'] ) ) );
		}

		sort( $current_roles );

		$legacy_default_roles = array( 'administrator', 'editor' );
		sort( $legacy_default_roles );

		if ( empty( $stored['bypass_logged_in'] ) && $legacy_default_roles === $current_roles ) {
			$stored['bypass_roles'] = array();
			update_option( self::OPTION_KEY, $stored );
			update_option( self::BYPASS_ROLES_NOTICE_OPTION, 1 );
		}

		update_option( self::BYPASS_ROLES_MIGRATION_OPTION, 1 );
	}

	/**
	 * Default settings used by the plugin.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'apply_mode'                  => 'global',
			'default_protection'          => 1,
			'disable_copy'               => 1,
			'disable_right_click'        => 1,
			'allow_right_click_links'    => 0,
			'disable_text_selection'     => 1,
			'disable_image_drag'         => 1,
			'disable_ctrl_u'             => 1,
			'disable_ctrl_s'             => 1,
			'disable_ctrl_p'             => 1,
			'disable_f12'                => 1,
			'enable_alerts'              => 1,
			'alert_message'              => __( 'This action is disabled on this page.', 'liteweight-content-protector' ),
			'bypass_logged_in'           => 0,
			'bypass_roles'               => array(),
			'excluded_page_ids'          => array(),
			'specific_page_ids'          => array(),
		);
	}

	/**
	 * Get merged settings with defaults.
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$stored = get_option( self::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$this->settings = wp_parse_args( $stored, self::default_settings() );

		return $this->settings;
	}

	/**
	 * Reset settings cache after updates.
	 *
	 * @return void
	 */
	public function refresh_settings_cache() {
		$this->settings = null;
	}

	/**
	 * Reset cache when option is newly added.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 * @return void
	 */
	public function refresh_settings_cache_on_add( $option, $value ) {
		unset( $value );

		if ( self::OPTION_KEY === $option ) {
			$this->settings = null;
		}
	}

	/**
	 * Check if at least one restriction is active.
	 *
	 * @return bool
	 */
	public function has_active_restrictions() {
		$settings = $this->get_settings();

		$restriction_keys = array(
			'disable_copy',
			'disable_right_click',
			'disable_text_selection',
			'disable_image_drag',
			'disable_ctrl_u',
			'disable_ctrl_s',
			'disable_ctrl_p',
			'disable_f12',
		);

		foreach ( $restriction_keys as $key ) {
			if ( ! empty( $settings[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether current user should bypass all restrictions.
	 *
	 * @return bool
	 */
	public function is_user_bypassed() {
		$settings = $this->get_settings();

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( ! empty( $settings['bypass_logged_in'] ) ) {
			return true;
		}

		$user = wp_get_current_user();
		if ( empty( $user->roles ) || empty( $settings['bypass_roles'] ) || ! is_array( $settings['bypass_roles'] ) ) {
			return false;
		}

		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $settings['bypass_roles'], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a page is excluded from restrictions.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_excluded_page( $post_id ) {
		$settings = $this->get_settings();
		$post_id  = absint( $post_id );

		if ( 0 === $post_id || empty( $settings['excluded_page_ids'] ) || ! is_array( $settings['excluded_page_ids'] ) ) {
			return false;
		}

		$excluded = array_map( 'absint', $settings['excluded_page_ids'] );

		return in_array( $post_id, $excluded, true );
	}

	/**
	 * Check if page is in the specific protected list.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function is_specific_page( $post_id ) {
		$settings = $this->get_settings();
		$post_id  = absint( $post_id );

		if ( 0 === $post_id || empty( $settings['specific_page_ids'] ) || ! is_array( $settings['specific_page_ids'] ) ) {
			return false;
		}

		$specific = array_map( 'absint', $settings['specific_page_ids'] );

		return in_array( $post_id, $specific, true );
	}

	/**
	 * Decide whether restrictions should run for the current request.
	 *
	 * @param int $post_id Optional post ID.
	 * @return bool
	 */
	public function is_page_protected( $post_id = 0 ) {
		$cache_key = 'global';

		$post_id = absint( $post_id );
		if ( 0 !== $post_id ) {
			$cache_key = (string) $post_id;
		}

		if ( array_key_exists( $cache_key, $this->page_protection_cache ) ) {
			return (bool) $this->page_protection_cache[ $cache_key ];
		}

		if ( is_admin() || is_feed() || wp_doing_ajax() ) {
			$this->page_protection_cache[ $cache_key ] = false;

			return false;
		}

		if ( ! is_singular( array( 'post', 'page' ) ) ) {
			$this->page_protection_cache[ $cache_key ] = false;

			return false;
		}

		if ( ! $this->has_active_restrictions() ) {
			$this->page_protection_cache[ $cache_key ] = false;

			return false;
		}

		if ( $this->is_user_bypassed() ) {
			$this->page_protection_cache[ $cache_key ] = false;

			return false;
		}

		if ( 0 === $post_id ) {
			$post_id = get_queried_object_id();
			$cache_key = 0 === $post_id ? 'global' : (string) $post_id;

			if ( array_key_exists( $cache_key, $this->page_protection_cache ) ) {
				return (bool) $this->page_protection_cache[ $cache_key ];
			}
		}

		if ( 0 === $post_id || $this->is_excluded_page( $post_id ) ) {
			$this->page_protection_cache[ $cache_key ] = false;

			return false;
		}

		$mode = get_post_meta( $post_id, self::META_KEY, true );
		if ( 'enable' === $mode ) {
			$this->page_protection_cache[ $cache_key ] = true;

			return true;
		}

		if ( 'disable' === $mode ) {
			$this->page_protection_cache[ $cache_key ] = false;

			return false;
		}

		$settings = $this->get_settings();
		$apply_mode = isset( $settings['apply_mode'] ) ? $settings['apply_mode'] : 'global';

		if ( 'specific' === $apply_mode ) {
			$this->page_protection_cache[ $cache_key ] = $this->is_specific_page( $post_id );

			return (bool) $this->page_protection_cache[ $cache_key ];
		}

		$this->page_protection_cache[ $cache_key ] = true;

		return (bool) $this->page_protection_cache[ $cache_key ];
	}
}
