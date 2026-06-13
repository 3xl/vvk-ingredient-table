<?php
declare( strict_types=1 );

namespace VVKit;

use VVKit\Repository\TableRepository;
use VVKit\Support\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gutenberg block 'vvkit/ingredients-table': dynamic block rendering an
 * ingredient table inline in the content, with live preview in the
 * editor (ServerSideRender). tableId = 0 means "first table of the post".
 */
class Blocks {

	public function register(): void {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	public function register_block(): void {
		$asset_file = VVKIT_PLUGIN_DIR . 'build/editor.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;

			wp_register_script(
				'vvkit-editor',
				VVKIT_PLUGIN_URL . 'build/editor.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_set_script_translations( 'vvkit-editor', 'vvkit' );
		}

		register_block_type( 'vvkit/ingredients-table', [
			'attributes'      => [
				'tableId' => [
					'type'    => 'number',
					'default' => 0,
				],
			],
			'render_callback' => [ $this, 'render' ],
			'editor_script'   => 'vvkit-editor',
		] );
	}

	public function render( array $attributes ): string {
		$tables   = new TableRepository();
		$table_id = absint( $attributes['tableId'] ?? 0 );
		$table    = $table_id ? $tables->find( $table_id ) : null;

		if ( ! $table ) {
			$post = get_post();

			if ( $post ) {
				$all   = $tables->for_post( (int) $post->ID );
				$table = $all[0] ?? null;
			}
		}

		return $table ? Renderer::table( $table ) : '';
	}
}
