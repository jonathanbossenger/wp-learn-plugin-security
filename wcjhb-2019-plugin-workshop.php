<?php
/**
 * Plugin Name:     Wcjhb 2019 Plugin Workshop
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          YOUR NAME HERE
 * Author URI:      YOUR SITE HERE
 * Text Domain:     wcjhb-2019-plugin-workshop
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Wcjhb_2019_Plugin_Workshop
 */

// Your code starts here.

register_activation_hook( __FILE__, 'wcjhb_setup_table' );
function wcjhb_setup_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'form_submissions';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  name varchar (100) NOT NULL,
	  email varchar (100) NOT NULL,
	  PRIMARY KEY  (id)
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

/**
 * Step 1: Let's build a simple form
 * https://developer.wordpress.org/reference/functions/add_shortcode/
 */
add_shortcode( 'wcjhb_form_shortcode', 'wcjhb_form_shortcode' );
function wcjhb_form_shortcode() {
	ob_start();
	?>
	<form method="post">
		<input type="hidden" name="wcjhb_form" value="submit">
		<div>
			<label for="email">Name</label>
			<input type="text" id="name" name="name" placeholder="Name">
		</div>
		<div>
			<label for="email">Email address</label>
			<input type="text" id="email" name="email" placeholder="Email address">
		</div>
		<div>
			<input type="submit" id="submit" name="submit" value="Submit">
		</div>
	</form>
	<?php
	$form = ob_get_clean();
	return $form;
}

/**
 * Step 2: Let's process the form data
 * https://developer.wordpress.org/reference/hooks/wp/
 */
add_action( 'wp', 'wcjhb_maybe_process_form' );
function wcjhb_maybe_process_form() {
	$name = $_POST['name'];
	$email = $_POST['email'];
	// insert form data into form_submissions table insecurely
	// echo insecure front end content
	?>
	<div>Form submitted successfully</div>
	<?php
}

// admin page that shows form submissions without any user cap checks

add_action('wp_ajax_update_form_submission');
function wcjhb_ajax_get_form_submissions(){
	// retrieve form submissions via ajax
}



