<?php
/**
 * Plugin Name: Default Gravatar Sans
 * Plugin URI: http://raohmaru.com/blog/wordpress/default-gravatar-sans/
 * Description: Disables default Gravatar.com avatars and redirections to gravatar.com servers, and allows one local default avatar image for users without avatar in his profile. To be used alongside <a href="http://www.get10up.com/plugins/simple-local-avatars-wordpress/">Simple Local Avatars</a>. <br>Based on the plugin <a href="http://wordpress.stackexchange.com/questions/17413/removing-gravatar-com-support-for-wordpress-and-simple-local-avatars">Disable Default Avatars</a> by <a href="http://wordpress.stackexchange.com/users/1685/thedeadmedic">TheDeadMedic</a>
 * Version: 1.0
 * Author: Raohmaru
 * Author URI: http://raohmaru.com
 * License: GPLv3 or later
 */
/*
Copyright (C) 2012  Raohmaru

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

if ( !function_exists( 'get_avatar' ) )
{
	/**
	 * Retrieve the avatar for a user who provided a user ID or email address.
	 * Removed all gravatar.com references.
	 * (Edit of the WP function get_avatar() found at wp-includes/pluggable.php.)
	 *
	 * @since 2.5
	 * @param int|string|object $id_or_email A user ID,  email address, or comment object
	 * @param int $size Size of the avatar image
	 * @param string $default URL to a default image to use if no avatar is available
	 * @param string $alt Alternate text to use in image tag. Defaults to blank
	 * @return string <img> tag for the user's avatar
	*/
	function get_avatar( $id_or_email, $size = '96', $default = '', $alt = false )
	{
		if ( ! get_option('show_avatars') )
			return false;

		if ( empty($default) ) {
			$avatar_default = get_option('avatar_default');
			if ( empty($avatar_default) )
				$default = 'blank';
			else
				$default = $avatar_default;
		}
		
		if ( 'blank' == $default )
			$default = includes_url('images/blank.gif') . '?';
		else
			// Path to theme-path/images/default_avatar.png
			$default = apply_filters( 'local_default_avatar', get_template_directory_uri() . '/images/default_avatar.png' );

		if ( false === $alt)
			$safe_alt = '';
		else
			$safe_alt = esc_attr( $alt );

		if ( !is_numeric( $size ) )
			$size = '96';

		$avatar = "<img alt='{$safe_alt}' src='{$default}' class='avatar avatar-{$size} photo avatar-default' height='{$size}' width='{$size}' />";
		return apply_filters( 'get_avatar', $avatar, $id_or_email, $size, $default, $alt );
	}
}

// Init the plugin
new raoh_CustomDefaultAvatar( );

/**
 * Default Gravatar Sans class. It does all the magic.
 * 
 * @version 1.0
 */
class raoh_CustomDefaultAvatar
{
	/**
	 * Class constructor. Adds hooks, actions and filters.
	 * 
	 * @since 1.0
	 */
	function raoh_CustomDefaultAvatar()
	{
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_filter( 'local_default_avatar', array( &$this , 'local_default_avatar' ) );
		if ( is_admin() )
			add_filter( 'avatar_defaults', array( &$this , 'avatar_defaults' ) );
			
		register_deactivation_hook( __FILE__, array( &$this , 'deactivate' ) );
	}
	
	/**
	 * Loads the language file, register the plugin options and adds a new settings field in the Discussion >> Avatars admin page.
	 * 
	 * @since 1.0
	 */
	function admin_init()
	{
		load_plugin_textdomain( 'default-gravatar-sans', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		
		register_setting( 'discussion', 'raoh_CustomDefaultAvatar', array( $this, 'sanitize' ) );
		
		add_settings_field('raoh-gds', __('Local Avatar URL' , 'default-gravatar-sans' ), array(&$this, 'settings_fields') , 'discussion', 'avatars'); 
	}
	
	/**
	 * Sanitizes the array values to store in the plugin options.
	 * 
	 * @since 1.0
	 * @param array $input Associative array with the values to sanitize
	 * @return array Array with their vaules sanitized
	 */
	function sanitize( $input )
	{
		$input['url'] = esc_url( $input['url'] );			
		return $input;
	}
	
	/**
	 * Creates the HTML settings fields.
	 * 
	 * @since 1.0
	 * @param array $args Additional arguments 
	 */
	function settings_fields( $args )
	{
		$option = get_option( 'raoh_CustomDefaultAvatar', array( 'url' => get_template_directory_uri() . '/images/default_avatar.png' ) );
		echo '<input type="text" name="raoh_CustomDefaultAvatar[url]" value="'. $option['url'] .'" size="50" />';
		echo '<p>' . __( 'It\'s recommended to use an image that fits the avatar size of your theme.', 'default-gravatar-sans' );
	}
	
	/**
	 * Returns the default avatar image URL.
	 * 
	 * @since 1.0
	 * @param string $url Path to the avatar image file
	 * @return string The URL to the user defined avatar
	 */
	function local_default_avatar( $url )
	{	
		if( $option = get_option( 'raoh_CustomDefaultAvatar') )
			$url = $option['url'];
		
		return $url . '?';
	}

	/**
	 * Filters the list of default avatars.
	 * 
	 * @since 1.0
	 * @param array $defaults An array containing the WP list of default avatars
	 * @return array A list with two default avatars: 'blank' and 'Local Avatar'
	 */
	function avatar_defaults( $defaults )
	{
		return array(
			'blank'			=> __('Blank'),
			'local_default' => __('Local Avatar' , 'default-gravatar-sans' )
		);
	}
	
	/**
	 * Deactivation hook. Sets the option 'avatar_default' to 'mystery' and deletes plugin settings.
	 * 
	 * @since 1.0
	 */
	function deactivate()
	{
		update_option( 'avatar_default', 'mystery' );
		delete_option( 'raoh_CustomDefaultAvatar' );
	}
}
?>