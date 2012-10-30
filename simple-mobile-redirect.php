<?php
/*
Plugin Name: Simple Mobile Redirect
Plugin URI: 
Description: Redirect mobile and desktop users and crawlers to the correct URL
Version: 0.1.7
Author: Kaspars Dambis
Author URI: http://konstruktors.com
*/

add_action( 'init', 'mobile_redirect_init' );

function mobile_redirect_init() {
	new mobile_redirect();
}

class mobile_redirect {
	var $settings = array();

	function mobile_redirect() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'save_post', array( $this, 'save_mobile_redirect' ) );
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ) );
		add_action( 'wp_head', array( $this, 'maybe_add_alternative_link' ) );
	}

	function admin_init() {
		if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
			// Use WordPress SEO UI, if possible
			add_action( 'wpseo_tab_header', array( $this, 'wpseo_tab_header' ) );
			add_action( 'wpseo_tab_content', array( $this, 'wpseo_tab_content' ) );
		} else {
			// Add our own UX, if WordPress SEO is not active
			add_action( 'add_meta_boxes', array( $this, 'mobile_redirect_metabox_setup' ) );
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
		printf( '<li id="mobile-redirects" class="mobile_redirects"><a href="#wpseo_mobile_redirects" class="wpseo_tablink">%s</a></li>', __( 'Redirects' ) );
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

		$this->settings = wp_parse_args( 
			get_post_meta( $post->ID, 'mobile_redirect', true ), 
			array(
				'enable' => false,
				'type' => null,
				'url' => ''
			) 
		);
		
		$redirect_type = array(
				'handheld' => __('mobile users'),
				'screen' => __('desktop users'),
				'all' => __('everyone')
			);

		// TODO: add filter for available redirect destinations

		$redirect_type_dropdown = array();

		foreach ( $redirect_type as $name => $value )
			$redirect_type_dropdown[] = sprintf( '<option value="%s" %s>%s</option>', esc_attr( $name ), selected( $this->settings['type'], esc_attr( $name ), false ), esc_html( $value ) );

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
				checked( $this->settings['enable'], true, false ),
				__( 'Redirect' ),
				implode( '', $redirect_type_dropdown ),
				__( 'to' ),
				esc_attr( 'example.com' ),
				esc_url( $this->settings['url'] ),
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
		$queried_object = get_queried_object();
		
		// Run only on single posts/pages, blog index page and front page
		if ( empty( $queried_object ) || ! isset( $queried_object->post_date ) )
			return;

		$this->settings = apply_filters( 'mobile_redirect_settings', get_post_meta( get_queried_object_id(), 'mobile_redirect', true ) );

		if ( empty( $this->settings ) || ! isset( $this->settings['enable'] ) )
			return;

		// Notify Google that we tend to redirect visitors based on their User-Agent string
		// https://developers.google.com/webmasters/smartphone-sites/redirects
		header( 'Vary: User-Agent' );

		if ( empty( $this->settings['enable'] ) || empty( $this->settings['url'] ) || strpos( $this->settings['url'], 'http' ) === false )
			return;
		
		if ( $this->settings['type'] == 'all' ) {
			wp_redirect( $this->settings['url'] );
			exit;
		}

		$is_mobile = apply_filters( 'simple_mobile_is_mobile', wp_is_mobile() );

		// Redirect mobile users
		if ( $is_mobile && $this->settings['type'] == 'handheld' ) {
			wp_redirect( $this->settings['url'], 302 );
			exit;
		}

		// Redirect desktop users
		if ( ! $is_mobile && $settings['type'] == 'desktop' ) {
			wp_redirect( $this->settings['url'], 302 );
			exit;
		}
	}

	function maybe_add_alternative_link() {
		if ( empty( $this->settings ) )
			return;

		if ( isset( $this->settings['enable'] ) && isset( $this->settings['url'] ) )
			printf( 
					'<link rel="alternate" type="text/html" media="%s" href="%s" />', 
					esc_attr( $this->settings['type'] ), 
					esc_attr( $this->settings['url'] ) 
				);
	}

}


