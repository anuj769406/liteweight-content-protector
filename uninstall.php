<?php
/**
 * Uninstall cleanup for Liteweight Content Protector.
 *
 * @package LiteweightContentProtection
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove plugin data for the current site.
 *
 * @return void
 */
function lwcp_cleanup_site_data() {
	delete_option( 'lwcp_settings' );
	delete_option( 'lwcp_migrated_strict_links_103' );
	delete_option( 'lwcp_show_strict_mode_notice_103' );
	delete_option( 'lwcp_migrated_bypass_roles_104' );
	delete_option( 'lwcp_show_bypass_roles_notice_104' );

	// Remove all per-post/page protection overrides saved by this plugin.
	delete_metadata( 'post', 0, '_lwcp_protection_mode', '', true );
}

/**
 * Remove plugin data across all sites in multisite.
 *
 * @return void
 */
function lwcp_cleanup_multisite_data() {
	$lwcp_batch_size = 100;
	$lwcp_offset     = 0;

	do {
		$lwcp_site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => $lwcp_batch_size,
				'offset' => $lwcp_offset,
			)
		);

		foreach ( $lwcp_site_ids as $lwcp_site_id ) {
			switch_to_blog( (int) $lwcp_site_id );
			lwcp_cleanup_site_data();
			restore_current_blog();
		}

		$lwcp_offset += $lwcp_batch_size;
	} while ( count( $lwcp_site_ids ) === $lwcp_batch_size );
}

if ( is_multisite() ) {
	lwcp_cleanup_multisite_data();
	return;
}

lwcp_cleanup_site_data();
