<?php
/**
 * Plugin Name: Default Gravatar Sans
 * Plugin URI: http://raohmaru.com/blog/wordpress/default-gravatar-sans/
 * Description: Disables default Gravatar.com avatar and redirection to gravatar.com servers, and allows to define a local default avatar image for users without avatar in his profile. To be used alongside Simple Local Avatars.
 * Version: 1.1.1
 * Author: Raohmaru
 * Author URI: http://raohmaru.com
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: default-gravatar-sans
 * Domain Path: /languages
 */
/*
Copyright (C) 2017  Raohmaru

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! function_exists( 'get_avatar' ) ) {
	/**
	 * Removed all gravatar.com references.
	 * (Edit of the WP function get_avatar() found at wp-includes/pluggable.php.)
	 * 
	 * @since 1.0
	 */
	function get_avatar( $id_or_email, $size = 96, $default = '', $alt = '', $args = null )	{
		$defaults = array(
			// get_avatar_data() args.
			'size'          => 96,
			'height'        => null,
			'width'         => null,
			'default'       => get_option( 'avatar_default', 'blank' ),
			'force_default' => false,
			'rating'        => get_option( 'avatar_rating' ),
			'scheme'        => null,
			'alt'           => '',
			'class'         => null,
			'force_display' => false,
			'extra_attr'    => '',
		);

		if ( empty( $args ) ) {
			$args = array();
		}

		$args['size']    = (int) $size;
		$args['default'] = $default;
		$args['alt']     = $alt;

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['height'] ) ) {
			$args['height'] = $args['size'];
		}
		if ( empty( $args['width'] ) ) {
			$args['width'] = $args['size'];
		}

		if ( is_object( $id_or_email ) && isset( $id_or_email->comment_ID ) ) {
			$id_or_email = get_comment( $id_or_email );
		}

		if ( ! $args['force_display'] && ! get_option( 'show_avatars' ) ) {
			return false;
		}
		
		if ( $default == 'blank' ) {
			return false;
		}
		else {
			$url   = apply_filters( 'local_default_avatar',   plugin_dir_url( __FILE__ ) . 'images/default_avatar.jpg' );
			$url2x = apply_filters( 'local_default_avatar2x', plugin_dir_url( __FILE__ ) . 'images/default_avatar2x.jpg' );
		}
		
		if ( ! $url || is_wp_error( $url ) ) {
			return false;
		}

		$class = array( 'avatar', 'avatar-' . (int) $args['size'], 'photo' );

		if ( ! $args['found_avatar'] || $args['force_default'] ) {
			$class[] = 'avatar-default';
		}

		if ( $args['class'] ) {
			if ( is_array( $args['class'] ) ) {
				$class = array_merge( $class, $args['class'] );
			} else {
				$class[] = $args['class'];
			}
		}

		$avatar = sprintf(
			"<img alt='%s' src='%s' srcset='%s' class='%s' height='%d' width='%d' %s/>",
			esc_attr( $args['alt'] ),
			esc_url( $url ),
			esc_attr( "$url2x 2x" ),
			esc_attr( join( ' ', $class ) ),
			(int) $args['height'],
			(int) $args['width'],
			$args['extra_attr']
		);
		
		return apply_filters( 'get_avatar', $avatar, $id_or_email, $args['size'], $args['default'], $args['alt'], $args );
	}
}

// Init the plugin
new raoh_CustomDefaultAvatar( );

/**
 * Default Gravatar Sans class. It does all the magic.
 * 
 * @version 1.1.1
 */
class raoh_CustomDefaultAvatar {
	/**
	 * Class constructor. Adds hooks, actions and filters.
	 * 
	 * @since 1.0
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_filter( 'local_default_avatar', array( &$this , 'local_default_avatar' ) );
		add_filter( 'local_default_avatar2x', array( &$this , 'local_default_avatar2x' ) );
		if ( is_admin() ) {
			add_filter( 'avatar_defaults', array( &$this , 'avatar_defaults' ) );			
			add_filter( 'plugin_row_meta', array( $this, 'set_plugin_meta' ), 10, 2 );
		}
			
		register_deactivation_hook( __FILE__, array( &$this , 'deactivate' ) );
	}
	
	/**
	 * Loads the language file, register the plugin options and adds a new settings field in the Discussion >> Avatars admin page.
	 * 
	 * @since 1.0
	 */
	function admin_init() {
		load_plugin_textdomain( 'default-gravatar-sans', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		register_setting( 'discussion', 'raoh_CustomDefaultAvatar', array( $this, 'sanitize' ) );
		add_settings_field('raoh-gds', __('Local Avatar URL' , 'default-gravatar-sans' ), array(&$this, 'settings_fields') , 'discussion', 'avatars'); 
	}

	/**
	 * Add scripts to the profile editing page
	 *
	 * @since 1.1
	 *
	 * @param string $hook_suffix Page hook
	 */
	public function admin_enqueue_scripts( $hook_suffix ) {
		if ( $hook_suffix != 'options-discussion.php' )
			return;
		
		wp_enqueue_script( 'default-gravatar-sans', plugin_dir_url( __FILE__ ) . 'default-gravatar-sans.js', array('jquery'), false, true );
	}

	/**
	 * Add custom link to plugin metada row
	 *
	 * @since 1.1.1
	 *
	 * @param array $links Links
	 * @param string $file Plugin filename
	 */
	public function set_plugin_meta( $links, $file ) {
		static $plugin;
		$plugin = plugin_basename( __FILE__ );
		if ( $file == $plugin ) {
			$links[] = '<a href="https://github.com/raohmaru/default-gravatar-sans" target="_blank">GitHub</a>';
		}
		return $links;
	}
	
	/**
	 * Sanitizes the array values to store in the plugin options.
	 * 
	 * @since 1.0
	 *
	 * @param array $input Associative array with the values to sanitize
	 * @return array Array with their values sanitized
	 */
	function sanitize( $input ) {
		$input['url'] = esc_url( $input['url'] );			
		return $input;
	}
	
	/**
	 * Creates the HTML settings fields.
	 * 
	 * @since 1.0
	 * @since 1.1 Added 2x image
	 *
	 * @param array $args Additional arguments 
	 */
	function settings_fields( $args ) {
		$option = get_option( 'raoh_CustomDefaultAvatar', array());		 
		$html  = '<p>' . __( 'It\'s recommended to use images that fits the avatar size of your theme.', 'default-gravatar-sans' ) . '</p>';
		$html .= '<input type="text" name="raoh_CustomDefaultAvatar[url]" value="'. $option['url'] .'" size="80" placeholder="URL of the image" /><br>';
		$html .= '<input type="text" name="raoh_CustomDefaultAvatar[url2x]" value="'. $option['url2x'] .'" size="80" placeholder="URL of the double resolution image" /> ' . __( 'High resolution image (2x)', 'default-gravatar-sans' );
		echo $html;
	}
	
	/**
	 * Returns the default avatar image URL.
	 * 
	 * @since 1.0
	 *
	 * @param string $url Path to the avatar image file
	 * @return string The URL to the user defined avatar
	 */
	function local_default_avatar( $url ) {	
		$option = get_option('raoh_CustomDefaultAvatar');
		if( $option && !empty($option['url']) )
			$url = $option['url'];
		return $url;
	}
	
	/**
	 * Returns the default avatar image URL of high resolution devices.
	 * 
	 * @since 1.1
	 *
	 * @param string $url Path to the avatar image file
	 * @return string The URL to the user defined avatar
	 */
	function local_default_avatar2x( $url ) {
		$option = get_option('raoh_CustomDefaultAvatar');
		if( $option && !empty($option['url2x']) )
			$url = $option['url2x'];
		return $url;
	}

	/**
	 * Filters the list of default avatars.
	 * 
	 * @since 1.0
	 *
	 * @param array $defaults An array containing the WP list of default avatars
	 * @return array A list with two default avatars: 'blank' and 'Local Avatar'
	 */
	function avatar_defaults( $defaults ) {
		return array(
			'blank'			=> __('Blank'),
			'local_default' => __('Local Avatar', 'default-gravatar-sans' )
		);
	}
	
	/**
	 * Deactivation hook. Sets the option 'avatar_default' to 'mystery' and deletes plugin settings.
	 * 
	 * @since 1.0
	 */
	function deactivate() {
		update_option( 'avatar_default', 'mystery' );
		delete_option( 'raoh_CustomDefaultAvatar' );
	}
}
?>
