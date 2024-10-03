<?php
/**
 * Plugin Name: Social Feed Block
 * Description: Apply animation on any text.
 * Version: 1.0.8
 * Author: bPlugins
 * Author URI: https://bplugins.com
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: instagram-feed
 */

// ABS PATH
if ( !defined( 'ABSPATH' ) ) { exit; }

register_activation_hook(__FILE__, function () {
	if ( is_plugin_active('my-social-feeds/my-social-feeds.php')) {
		deactivate_plugins('my-social-feeds/my-social-feeds.php');
	}  
});

// Constant
define( 'IFB_PLUGIN_VERSION', isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === $_SERVER['HTTP_HOST'] ? time() : '1.0.8' );
define( 'IFB_ASSETS_DIR', plugin_dir_url( __FILE__ ) . 'assets/' );


// Instagram Feed
if(!class_exists('IFBInstagramFeed')) {

	class IFBInstagramFeed{
		private $graphFBUrl = 'https://graph.facebook.com';
		private $graphIGUrl = 'https://graph.instagram.com';

		private $businessUserFields = 'biography,id,ig_id,followers_count,follows_count,name,profile_picture_url,username,website';
		private $personalUserFields = 'account_type,id,media_count,username';

		private $businessMediaFields = 'caption,comments_count,id,ig_id,is_comment_enabled,is_shared_to_feed,like_count,media_product_type,media_type,media_url,permalink,shortcode,thumbnail_url,timestamp,username,children{id,ig_id,media_type,media_url,permalink,shortcode,thumbnail_url,timestamp},comments{from,hidden,id,like_count,media,text,timestamp,replies{from,hidden,id,like_count,media,parent_id,text,timestamp}}';
		private $personalMediaFields = 'caption,id,is_shared_to_feed,media_type,media_url,permalink,thumbnail_url,timestamp,username,children{id,media_type,media_url,permalink,thumbnail_url,timestamp}';

		private function getUserURL( $accessToken, $user_id, $connectType ){
			return 'business' === $connectType ?
			"$this->graphFBUrl/$user_id?fields=$this->businessUserFields&access_token=$accessToken" :
			"$this->graphIGUrl/me?fields=$this->personalUserFields&access_token=$accessToken";
		}

		private function getMediaURL( $accessToken, $user_id, $connectType, $limit = 200 ){
			return 'business' === $connectType ?
			"$this->graphFBUrl/$user_id/media?fields=$this->businessMediaFields&limit=$limit&access_token=$accessToken" :
			"$this->graphIGUrl/me/media?fields=$this->personalMediaFields&limit=$limit&access_token=$accessToken";
		}

		function __construct(){
			add_action( 'init', [$this, 'onInit'] );
			add_action( 'admin_init', [$this, 'registerIFBSetting'] );
			add_action( 'rest_api_init', [$this, 'registerIFBSetting'] );
			add_action( 'wp_ajax_ifbAjaxRequest', [$this, 'ajaxRequest'] );
			add_action( 'wp_ajax_nopriv_ifbAjaxRequest', [$this, 'ajaxRequest'] );
			add_action( 'wp_ajax_ifbDeleteTransient', [$this, 'deleteTransient'] );
			add_action( 'wp_ajax_nopriv_ifbDeleteTransient', [$this, 'deleteTransient'] );
		}

		function registerIFBSetting() {
			register_setting( 'ifbData', 'ifbData', array(
				'show_in_rest' => array(
					'name' => 'ifbData',
					'schema' => array(
						'type'  => 'string'
					)
				),
				'type' => 'string',
				'default' => '',
				'sanitize_callback' => 'sanitize_text_field'
			) ); 
		}

		function ajaxRequest() {
			$ifbData = json_decode( get_option( 'ifbData' ), true );
			$accounts = $ifbData['iAccounts'] ?? [];
			$cacheTime = $ifbData['iCacheTime'] ?? 1800;
		
			$key = 'ifbInstagramData';
		
			// Check if transient data exists
			$transientData = get_transient( $key );
		
			if ( !empty( $transientData ) && empty( $transientData['error'] ) ) {
				$response = [
					'success' => true,
					'data'    => $transientData,
				];
			} else {
				$users = [];
				$usersData = [];
				$error = '';

				foreach ( $accounts as $account ) {
					extract( $account );
					
					// Retrieve user data
					$userURL = $this->getUserURL( $access_token, $user_id, $connectType );
					$userRes = wp_remote_get( $userURL );
					$user = json_decode( wp_remote_retrieve_body( $userRes ), true );
					$user['connectType'] = $connectType;
					
					// Retrieve media data
					$userDataURL = $this->getMediaURL( $access_token, $user_id, $connectType );
					$userDataRes = wp_remote_get( $userDataURL );
					$userData = json_decode( wp_remote_retrieve_body( $userDataRes ), true );

					// Fetch comment count and like count for each media item
					// foreach ($userData['data'] as $mediaItem) {
					// 	$mediaID = $mediaItem['id'];
					// 	$commentsURL = "https://graph.instagram.com/$mediaID/comments?summary=true&access_token=$access_token";
					// 	$commentsRes = wp_remote_get($commentsURL);
					// 	$commentsData = json_decode(wp_remote_retrieve_body($commentsRes), true);
					// 	$mediaItem['comment_count'] = $commentsData['summary']['total_count'];
		
					// 	$likesURL = "https://graph.instagram.com/$mediaID/likes?summary=true&access_token=$access_token";
					// 	$likesRes = wp_remote_get($likesURL);
					// 	$likesData = json_decode(wp_remote_retrieve_body($likesRes), true);
					// 	$mediaItem['like_count'] = $likesData['summary']['total_count'];
					// }

					// Set to array
					if( $user['username'] ){
						$users[] = $user;

						$username = $user['username'];
						$usersData[$username] = [
							'media' => $userData['data'],
							'page' => $userData['paging']['cursors']
						];
					}else if( $user['error'] ){
						$error = $user['error']['message'];
					}
				}

				$instagramData = array_filter([ 'users' => $users, 'usersData' => $usersData, 'error' => $error ], function ( $value ) {
					return !empty($value);
				});
		
				// Store the Instagram Data in the transient
				set_transient( $key, $instagramData, $cacheTime );
		
				$response = [
					'success' => $error ? false : true,
					'data'    => $instagramData
				];
			}
		
			wp_send_json( $response );
		}

		function deleteTransient() {
			delete_transient('ifbInstagramData');

			die();
		}

		function onInit() {
			wp_register_script( 'fancyapps', IFB_ASSETS_DIR . 'js/fancyapps.min.js', [], '3.5.7', true );
			wp_register_style( 'fancyapps', IFB_ASSETS_DIR . 'css/fancyapps.min.css', [], '3.5.7' );

			wp_register_script( 'swiper', IFB_ASSETS_DIR . 'js/swiper.min.js', [], '9.3.2', true );
			wp_register_style( 'swiper', IFB_ASSETS_DIR . 'css/swiper.min.css', [], '9.3.2' );

			wp_register_style( 'ifb-instagram-style', plugins_url( 'dist/style.css', __FILE__ ), [ 'swiper', 'fancyapps' ], IFB_PLUGIN_VERSION ); // Style
			wp_register_style( 'ifb-instagram-editor-style', plugins_url( 'dist/editor.css', __FILE__ ), [ 'ifb-instagram-style' ], IFB_PLUGIN_VERSION ); // Backend Style

			register_block_type( __DIR__, [
				'editor_style'		=> 'ifb-instagram-editor-style',
				'style'				=> 'ifb-instagram-style',
				'render_callback'	=> [$this, 'render']
			] ); // Register Block

			wp_localize_script( 'ifb-instagram-script', 'ifbLocal', [ 'ajaxURL' => admin_url( 'admin-ajax.php' ) ] );

			wp_set_script_translations( 'ifb-instagram-editor-script', 'instagram-feed', plugin_dir_path( __FILE__ ) . 'languages' ); // Translate
		}

		function render( $attributes ){
			extract( $attributes );

			$className = $className ?? '';
			$blockClassName = 'wp-block-ifb-instagram ' . $className . ' align' . $align;

			$ifbData = json_decode( get_option( 'ifbData' ), true );
			$accounts = $ifbData['iAccounts'] ?? [];

			ob_start(); ?>
			<div class='<?php echo esc_attr( $blockClassName ); ?>' id='ifbInstagramFeed-<?php echo esc_attr( $cId ) ?>' data-attributes='<?php echo esc_attr( wp_json_encode( $attributes ) ); ?>' data-accounts='<?php echo esc_attr( wp_json_encode( $accounts ) ); ?>'></div>

			<?php return ob_get_clean();
		} // Render
	}
	new IFBInstagramFeed;
}