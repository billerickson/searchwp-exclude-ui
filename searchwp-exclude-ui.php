<?php
/*
Plugin Name: SearchWP Exclude UI
Plugin URI: https://searchwp.com/
Description: Add a checkbox to edit screens to add an "Exclude from search" checkbox
Version: 1.0.1
Author: Jonathan Christopher
Author URI: https://searchwp.com/

Copyright 2015-2016 Jonathan Christopher

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, see <http://www.gnu.org/licenses/>.
*/

// exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SEARCHWP_EXCLUDEUI_VERSION' ) ) {
	define( 'SEARCHWP_EXCLUDEUI_VERSION', '1.0.1' );
}

/**
 * instantiate the updater
 */
if ( ! class_exists( 'SWP_Exclude_UI_Updater' ) ) {
	// load our custom updater
	include_once( dirname( __FILE__ ) . '/vendor/updater.php' );
}

// set up the updater
function searchwp_excludeui_update_check(){

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return false;
	}

	// environment check
	if ( ! defined( 'SEARCHWP_PREFIX' ) ) {
		return false;
	}

	if ( ! defined( 'SEARCHWP_EDD_STORE_URL' ) ) {
		return false;
	}

	if ( ! defined( 'SEARCHWP_EXCLUDEUI_VERSION' ) ) {
		return false;
	}

	// retrieve stored license key
	$license_key = trim( get_option( SEARCHWP_PREFIX . 'license_key' ) );
	$license_key = sanitize_text_field( $license_key );

	// instantiate the updater to prep the environment
	$searchwp_excludeui_updater = new SWP_Exclude_UI_Updater( SEARCHWP_EDD_STORE_URL, __FILE__, array(
			'item_id' 	=> 36614,
			'version'   => SEARCHWP_EXCLUDEUI_VERSION,
			'license'   => $license_key,
			'item_name' => 'Exclude UI',
			'author'    => 'Jonathan Christopher',
			'url'       => site_url(),
		)
	);

	return $searchwp_excludeui_updater;
}

add_action( 'admin_init', 'searchwp_excludeui_update_check' );

class SearchWPExcludeUI {

	private $meta_key = '_searchwp_excluded';

	function __construct() {
		add_action( 'post_submitbox_misc_actions', array( $this, 'output_exclude_checkbox' ) );
		add_action( 'attachment_submitbox_misc_actions', array( $this, 'output_exclude_checkbox' ) );
		add_action( 'save_post', array( $this, 'save_post' ) );
		add_action( 'edit_attachment', array( $this, 'save_post' ) );

		add_filter( 'searchwp_exclude', array( $this, 'searchwp_exclude' ), 10, 3 );
		add_filter( 'searchwp_prevent_indexing', array( $this, 'searchwp_prevent_indexing' ), 10, 3 );
	}

	function output_exclude_checkbox() {
		global $post;

		$excluded_post_types = apply_filters( 'searchwp_exclude_ui_excluded_post_types', array() );
		$post_type_name = get_post_type( $post );
		$post_type = get_post_type_object( $post_type_name );

		if ( ! $post_type->exclude_from_search && ! in_array( $post_type_name, $excluded_post_types ) ) : ?>
			<?php
				wp_nonce_field( plugin_basename( __FILE__ ), 'searchwp_exclude_ui' );
				$val = get_post_meta( $post->ID, $this->meta_key, true );
			?>
			<div class="misc-pub-section">
				<input type="checkbox" name="searchwp_exclude" id="searchwp_exclude"
				       value="1"<?php echo checked( $val, '1', false ); ?> />
				<label for="searchwp_exclude"><?php _e( 'Exclude from search', 'searchwp' ); ?></label>
			</div>
		<?php endif;
	}

	function save_post( $post_id ) {

		if ( ! isset( $_POST['post_type'] ) ) {
			return $post_id;
		}

		if ( ! isset( $_POST['searchwp_exclude_ui'] ) || ! wp_verify_nonce( $_POST['searchwp_exclude_ui'], plugin_basename( __FILE__ ) ) ) {
			return $post_id;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		if ( 'post' == $_POST['post_type'] && ! current_user_can( 'edit_post', $post_id ) ) {
			return $post_id;
		}

		if ( ! isset( $_POST['searchwp_exclude'] ) ) {
			// editing the post causes it to be purged anyway, so just remove the meta
			delete_post_meta( $post_id, $this->meta_key );
		} else {
			if ( 1 === absint( $_POST['searchwp_exclude'] ) ) {
				update_post_meta( $post_id, $this->meta_key, 1 );
			}
		}

		return $post_id;
	}

	function get_excluded_ids() {
		$args = array(
			'post_type'         => 'any',
			'post_status'       => 'any',
			'fields'            => 'ids',
			'posts_per_page'    => -1,
			'meta_query' => array(
				array(
					'key'     => $this->meta_key,
					'compare' => 'EXISTS',
				),
			),
		);

		$ids = get_posts( $args );
		$ids = array_map( 'absint', $ids );

		return $ids;
	}

	function searchwp_exclude( $ids, $engine, $terms ) {

		if ( ! empty( $engine ) ) {
			$engine = null;
		}

		if ( ! empty( $terms ) ) {
			$terms = null;
		}

		$excluded_by_ui = $this->get_excluded_ids();

		if ( ! empty( $excluded_by_ui ) ) {
			$ids = array_merge( $ids, $excluded_by_ui );
			$ids = array_unique( $ids );
			$ids = array_map( 'absint', $ids );
		}

		return $ids;
	}

	function searchwp_prevent_indexing() {
		return $this->get_excluded_ids();
	}

}

new SearchWPExcludeUI();
