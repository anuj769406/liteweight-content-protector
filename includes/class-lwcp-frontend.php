<?php
/**
 * Frontend conditional loader.
 *
 * @package LightweightContentProtection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles frontend asset loading and body classes.
 *
 * @package LightweightContentProtection
 */
class LWCP_Frontend {

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

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_filter( 'body_class', array( $this, 'add_body_classes' ) );
	}

	/**
	 * Add optional CSS classes for CSS-driven protections.
	 *
	 * @param array $classes Existing body classes.
	 * @return array
	 */
	public function add_body_classes( $classes ) {
		if ( ! $this->plugin->is_page_protected() ) {
			return $classes;
		}

		$settings = $this->plugin->get_settings();

		if ( ! empty( $settings['disable_text_selection'] ) ) {
			$classes[] = 'lwcp-no-select';
		}

		if ( ! empty( $settings['disable_image_drag'] ) ) {
			$classes[] = 'lwcp-no-image-drag';
		}

		return $classes;
	}

	/**
	 * Enqueue only required assets on protected pages.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->plugin->is_page_protected() ) {
			return;
		}

		$settings = $this->plugin->get_settings();
		$style_needed = ! empty( $settings['disable_text_selection'] ) || ! empty( $settings['disable_image_drag'] ) || ! empty( $settings['enable_alerts'] );

		if ( $style_needed ) {
			wp_enqueue_style(
				'lwcp-content-protection',
				LWCP_PLUGIN_URL . 'assets/css/content-protection.css',
				array(),
				LWCP_VERSION
			);
		}

		$script_config = array(
			'disableCopy'             => ! empty( $settings['disable_copy'] ),
			'disableRightClick'       => ! empty( $settings['disable_right_click'] ),
			'allowRightClickLinks'    => ! empty( $settings['allow_right_click_links'] ),
			'disableTextSelection'    => ! empty( $settings['disable_text_selection'] ),
			'disableImageDrag'        => ! empty( $settings['disable_image_drag'] ),
			'disableCtrlU'            => ! empty( $settings['disable_ctrl_u'] ),
			'disableCtrlS'            => ! empty( $settings['disable_ctrl_s'] ),
			'disableCtrlP'            => ! empty( $settings['disable_ctrl_p'] ),
			'disableF12'              => ! empty( $settings['disable_f12'] ),
			'enableAlerts'            => ! empty( $settings['enable_alerts'] ),
			'alertMessage'            => ! empty( $settings['alert_message'] ) ? $settings['alert_message'] : __( 'This action is disabled on this page.', 'liteweight-content-protector' ),
		);

		$needs_script = false;
		foreach ( array(
			'disableCopy',
			'disableRightClick',
			'disableTextSelection',
			'disableImageDrag',
			'disableCtrlU',
			'disableCtrlS',
			'disableCtrlP',
			'disableF12',
		) as $key ) {
			if ( ! empty( $script_config[ $key ] ) ) {
				$needs_script = true;
				break;
			}
		}

		if ( ! $needs_script ) {
			return;
		}

		wp_enqueue_script(
			'lwcp-content-protection',
			LWCP_PLUGIN_URL . 'assets/js/content-protection.js',
			array(),
			LWCP_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'lwcp-content-protection',
			'LWCP_CONFIG',
			$script_config
		);
	}
}
