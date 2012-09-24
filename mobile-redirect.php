<?php
/*
Plugin Name: Mobile Redirect
Plugin URI: 
Description: Redirect mobile and desktop users and crawlers to the correct URL
Version: 0.1
Author: Kaspars Dambis
Author URI: http://konstruktors.com
*/

add_action( 'init', 'mobile_redirect_init' );

function mobile_redirect_init() {
	new mobile_redirect();
}

class mobile_redirect {

	function mobile_redirect() {
		if ( function_exists( 'wpseo_admin_init' ) ) {
			add_action( 'wpseo_tab_header', array( $this, 'wpseo_tab_header' ) );
			add_action( 'wpseo_tab_content', array( $this, 'wpseo_tab_content' ) );
		} else {
			add_action( 'add_meta_boxes', array( $this, 'mobile_redirect_metabox_setup' ) );
			add_action( 'save_post', array( $this, 'save_mobile_redirect' ) );
			add_action( 'template_redirect', array( $this, 'maybe_redirect' ) );
		}
	}

	function mobile_redirect_metabox_setup() {
		$post_types = get_post_types( array( 'public' => true ) );

		foreach ( $post_types as $post_type )
			add_meta_box( 
				'mobile_redirect',
				__( 'Mobile Redirect' ),
				array( $this, 'mobile_redirect_metabox_render' ),
				$post_type,
				'normal',
				'high'
			);
	}

	function wpseo_tab_header() {
		printf( '<li id="mobile-redirects"><a href="#wpseo_mobile_redirects" class="wpseo_tablink">%s</a></li>', __( 'Redirects' ) );
	}

	function wpseo_tab_content() {
		global $post;
		
		printf( '<div class="wpseotab mobile_redirects">%s</div>', $this->mobile_redirect_metabox( $post ) );
	}

	function mobile_redirect_metabox_render( $post ) {
		echo $this->mobile_redirect_metabox( $post );
	}

	function mobile_redirect_metabox( $post ) {
		$notices = array();

		if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) )
			$notices[] = sprintf( '<p>%s</p>', __('<strong>Note:</strong> It looks like you have a page caching plugin active. Please make sure that it doesn\'t cache the redirects.') );

		// TODO: add filter for default settings

		$settings = wp_parse_args( 
			get_post_meta( $post->ID, 'mobile_redirect', true ), 
			array(
				'enable' => false,
				'type' => null,
				'url' => ''
			) 
		);
		
		$redirect_type = array(
				'mobile' => __('mobile users'),
				'desktop' => __('desktop users'),
				'all' => __('everyone')
			);

		// TODO: add filter for available redirect destinations

		$redirect_type_dropdown = array();

		foreach ( $redirect_type as $name => $value )
			$redirect_type_dropdown[] = sprintf( '<option value="%s" %s>%s</option>', esc_attr( $name ), selected( $settings['type'], esc_attr( $name ), false ), esc_html( $value ) );

		return sprintf( 
				'<table class="form-table">
					<tr>
						<th>
							<label>%s</label>
						</th>
						<td>
							<label><input type="checkbox" name="mobile_redirect[enable]" value="1" %s /> %s</label>
							<label><select name="mobile_redirect[type]">%s</select></label>
							<label>%s <input type="text" name="mobile_redirect[url]" placeholder="%s" value="%s" />
							%s
						</td> 
					</tr>
				</table>',
				__( '301 Redirect' ),
				checked( $settings['enable'], true, false ),
				__( 'Redirect' ),
				implode( '', $redirect_type_dropdown ),
				__( 'to' ),
				esc_attr( 'example.com' ),
				esc_url( $settings['url'] ),
				implode( '', $notices )
			);
	}

	function save_mobile_redirect( $post_id ) {
		if ( ! isset( $_POST['mobile_redirect'] ) )
			return;

		// Format the destination URL
		$_POST['mobile_redirect']['url'] = esc_url_raw( $_POST['mobile_redirect']['url'] );

		update_post_meta( $post_id, 'mobile_redirect', $_POST['mobile_redirect'] );
	}

	function maybe_redirect() {
		// Run only on single posts/pages, blog index page and front page
		if ( ! is_singular() && ! is_home() && ! is_front_page() )
			return;

		$settings = apply_filters( 'mobile_redirect_settings', get_post_meta( get_queried_object_id(), 'mobile_redirect', true ) );

		if ( empty( $settings ) || ! isset( $settings['enable'] ) )
			return;

		if ( empty( $settings['enable'] ) || empty( $settings['url'] ) || strpos( $settings['url'], 'http' ) )
			return;

		do_action( 'mobile_redirect', $settings );

		if ( $settings['type'] == 'all' ) {
			wp_redirect( $settings['url'] );
			exit;
		}

		// Redirect mobile users
		if ( wp_is_mobile() && $settings['type'] == 'mobile' ) {
			wp_redirect( $settings['url'] );
			exit;
		}

		// Redirect desktop users
		if ( ! wp_is_mobile() && $settings['type'] == 'desktop' ) {
			wp_redirect( $settings['url'] );
			exit;
		}
	}

}


