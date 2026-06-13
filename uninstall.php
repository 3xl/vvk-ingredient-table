<?php
/**
 * Uninstall routine.
 *
 * Data (custom tables + options) is removed only when the user opted in
 * via the "Delete data on uninstall" setting; otherwise everything is
 * left in place so the plugin can be reinstalled without data loss.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( '1' !== (string) get_option( 'vvkit_delete_data_on_uninstall', '0' ) ) {
	return;
}

global $wpdb;

foreach ( [
	'vvkit_ingredient_table',
	'vvkit_ingredients',
	'vvkit_tables',
	'vvkit_units',
	'vvkit_ingredient_nutrition',
	'vvkit_ingredient_tags',
] as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed table names, uninstall context.
}

foreach ( [
	'vvkit_default_title',
	'vvkit_default_title_tagname',
	'vvkit_css_classes',
	'vvkit_post_type',
	'vvkit_fractions',
	'vvkit_jsonld',
	'vvkit_display_defaults',
	'vvkit_delete_data_on_uninstall',
	'vvkit_i18n_signature',
	'vvkit_version',
] as $option ) {
	delete_option( $option );
}

wp_cache_flush();
