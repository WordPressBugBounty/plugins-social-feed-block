<?php
/**
 * Plugin Name: Social Feed Block
 * Description: Apply animation on any text.
 * Version: 1.0.7
 * Author: bPlugins
 * Author URI: https://bplugins.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: instagram-feed
 */

// ABS PATH
if ( !defined( 'ABSPATH' ) ) { exit; }

// Constant
define( 'IFB_PLUGIN_VERSION', isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '1.0.7' );
define( 'IFB_ASSETS_DIR', plugin_dir_url( __FILE__ ) . 'assets/' );

// Instagram Feed
class IFBInstagramFeed{
	function __construct(){
		add_action( 'init', [$this, 'onInit'] );
	}

	function onInit() {
		wp_register_style( 'ifb-instagram-editor-style', plugins_url( 'dist/editor.css', __FILE__ ), [], IFB_PLUGIN_VERSION ); // Backend Style
		wp_register_style( 'ifb-instagram-style', plugins_url( 'dist/style.css', __FILE__ ), [], IFB_PLUGIN_VERSION ); // Both Style

		register_block_type( __DIR__, [
			'editor_style'		=> 'ifb-instagram-editor-style',
			'style'				=> 'ifb-instagram-style',
			'render_callback'	=> [$this, 'render']
		] ); // Register Block

		wp_set_script_translations( 'ifb-instagram-editor-script', 'instagram-feed', plugin_dir_path( __FILE__ ) . 'languages' ); // Translate
	}

	function render( $attributes ){
		extract( $attributes );

		$className = $className ?? '';
		$blockClassName = 'wp-block-ifb-instagram ' . $className . ' align' . $align;

		ob_start(); ?>
		<div class='<?php echo esc_attr( $blockClassName ); ?>' id='ifbInstagramFeed-<?php echo esc_attr( $cId ) ?>' data-attributes='<?php echo esc_attr( wp_json_encode( $attributes ) ); ?>'></div>

		<?php return ob_get_clean();
	} // Render
}
new IFBInstagramFeed;