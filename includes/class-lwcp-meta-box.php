<?php
/**
 * Per-post/page protection controls.
 *
 * @package LightweightContentProtection
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and saves per-post protection override controls.
 *
 * @package LightweightContentProtection
 */
class LWCP_Meta_Box {

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

		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
	}

	/**
	 * Register meta box.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		add_meta_box(
			'lwcp-protection-meta',
			__( 'Content Protection', 'liteweight-content-protector' ),
			array( $this, 'render_meta_box' ),
			array( 'post', 'page' ),
			'side',
			'default'
		);
	}

	/**
	 * Meta box markup.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		wp_nonce_field( 'lwcp_meta_box_save', 'lwcp_meta_box_nonce' );

		$mode = get_post_meta( $post->ID, LWCP_Plugin::META_KEY, true );
		$mode = in_array( $mode, array( 'inherit', 'enable', 'disable' ), true ) ? $mode : 'inherit';
		?>
		<p><?php esc_html_e( 'Control restrictions for this item only.', 'liteweight-content-protector' ); ?></p>
		<label>
			<input type="radio" name="lwcp_protection_mode" value="inherit" <?php checked( 'inherit', $mode ); ?> />
			<?php esc_html_e( 'Inherit global settings', 'liteweight-content-protector' ); ?>
		</label><br />
		<label>
			<input type="radio" name="lwcp_protection_mode" value="enable" <?php checked( 'enable', $mode ); ?> />
			<?php esc_html_e( 'Force enable protection', 'liteweight-content-protector' ); ?>
		</label><br />
		<label>
			<input type="radio" name="lwcp_protection_mode" value="disable" <?php checked( 'disable', $mode ); ?> />
			<?php esc_html_e( 'Disable protection', 'liteweight-content-protector' ); ?>
		</label>
		<?php
	}

	/**
	 * Save meta box values.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['lwcp_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lwcp_meta_box_nonce'] ) ), 'lwcp_meta_box_save' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['lwcp_protection_mode'] ) ) {
			return;
		}

		$mode = sanitize_key( wp_unslash( $_POST['lwcp_protection_mode'] ) );
		if ( ! in_array( $mode, array( 'inherit', 'enable', 'disable' ), true ) ) {
			$mode = 'inherit';
		}

		if ( 'inherit' === $mode ) {
			delete_post_meta( $post_id, LWCP_Plugin::META_KEY );
		} else {
			update_post_meta( $post_id, LWCP_Plugin::META_KEY, $mode );
		}
	}
}
