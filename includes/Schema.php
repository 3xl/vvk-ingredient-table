<?php
declare( strict_types=1 );

namespace VVKit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database schema.
 *
 * The four v1 tables keep their original columns untouched (full data
 * compatibility with the legacy plugin); v2.1 only ADDS nullable columns
 * (vvkit_tables.servings, vvkit_units.dimension / unit_system) and two
 * satellite tables for nutrition facts and ingredient tags. dbDelta
 * applies the additions without touching existing data.
 */
final class Schema {

	public const TABLES = [
		'vvkit_ingredient_table',
		'vvkit_ingredients',
		'vvkit_tables',
		'vvkit_units',
		'vvkit_ingredient_nutrition',
		'vvkit_ingredient_tags',
	];

	/**
	 * @return string[] One CREATE TABLE statement per table, dbDelta-ready.
	 */
	public static function get_schema(): array {
		global $wpdb;

		$collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		return [
			"CREATE TABLE {$wpdb->prefix}vvkit_ingredient_table (
				id int(11) unsigned NOT NULL AUTO_INCREMENT,
				ingredient_id int(11) unsigned NOT NULL,
				table_id int(11) NOT NULL,
				quantity float DEFAULT NULL,
				unit_id int(11) DEFAULT NULL,
				note text,
				position tinyint(11) unsigned NOT NULL DEFAULT '0',
				referral text,
				product_id int(11) unsigned DEFAULT '0',
				PRIMARY KEY  (id)
			) $collate;",

			"CREATE TABLE {$wpdb->prefix}vvkit_ingredients (
				id int(11) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(100) NOT NULL DEFAULT '',
				PRIMARY KEY  (id)
			) $collate;",

			"CREATE TABLE {$wpdb->prefix}vvkit_tables (
				id int(11) unsigned NOT NULL AUTO_INCREMENT,
				post_id bigint(20) unsigned NOT NULL,
				title varchar(100) DEFAULT NULL,
				title_tagname varchar(10) DEFAULT NULL,
				positions varchar(10) DEFAULT NULL,
				servings int(11) unsigned DEFAULT NULL,
				display_options text,
				PRIMARY KEY  (id)
			) $collate;",

			"CREATE TABLE {$wpdb->prefix}vvkit_units (
				id int(11) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(10) DEFAULT NULL,
				value float DEFAULT NULL,
				dimension varchar(10) DEFAULT NULL,
				unit_system varchar(10) DEFAULT NULL,
				PRIMARY KEY  (id)
			) $collate;",

			"CREATE TABLE {$wpdb->prefix}vvkit_ingredient_nutrition (
				ingredient_id int(11) unsigned NOT NULL,
				kcal float DEFAULT NULL,
				fat float DEFAULT NULL,
				saturated_fat float DEFAULT NULL,
				carbs float DEFAULT NULL,
				sugars float DEFAULT NULL,
				protein float DEFAULT NULL,
				fiber float DEFAULT NULL,
				salt float DEFAULT NULL,
				PRIMARY KEY  (ingredient_id)
			) $collate;",

			"CREATE TABLE {$wpdb->prefix}vvkit_ingredient_tags (
				id int(11) unsigned NOT NULL AUTO_INCREMENT,
				ingredient_id int(11) unsigned NOT NULL,
				tag varchar(32) NOT NULL,
				type varchar(16) NOT NULL,
				PRIMARY KEY  (id),
				KEY ingredient_id (ingredient_id)
			) $collate;",
		];
	}
}
