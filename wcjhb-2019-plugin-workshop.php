<?php
/**
 * Plugin Name:     Wcjhb 2019 Plugin Workshop
 * Plugin URI:      https://johannesburg.wordcamp.org
 * Description:     Badly coded Workshop Plugin
 * Author:          Jonathan Bossenger
 * Author URI:      https://jonthanbossenger.com
 * Text Domain:     wcjhb
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         Wcjhb_2019_Plugin_Workshop
 */

/**
 * Update these with the page slugs of your success and error pages
 */
define( 'WCJHB_SUCCESS_PAGE_SLUG', 'form-success-page' );
define( 'WCJHB_ERROR_PAGE_SLUG', 'form-error-page' );

define( 'WCJHB_VERSION', '1.0.0' );
define( 'WCJHB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCJHB_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Set up the required form submissions table
 */
register_activation_hook( __FILE__, 'wcjhb_setup_table' );
function wcjhb_setup_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'form_submissions';

	$sql = "CREATE TABLE $table_name (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  name varchar (100) NOT NULL,
	  email varchar (100) NOT NULL,
	  PRIMARY KEY  (id)
	)";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );
}

/**
 * Enqueue JavaScript assets
 */
add_action( 'admin_enqueue_scripts', 'wcjhb_enqueue_script' );
function wcjhb_enqueue_script() {
	wp_register_script(
		'wcjhb-admin',
		WCJHB_PLUGIN_URL . 'assets/admin.js',
		array( 'jquery' ),
		'1.0.0',
		true
	);
	wp_enqueue_script( 'wcjhb-admin' );
	wp_localize_script(
		'wcjhb-admin',
		'wcjhb_ajax',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		)
	);
}

/**
 * Submission Form
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
 * Process the form data and redirect
 * https://developer.wordpress.org/reference/hooks/wp/
 */
add_action( 'wp', 'wcjhb_maybe_process_form' );
function wcjhb_maybe_process_form() {
	if (!isset($_POST['wcjhb_form'])){
		return;
	}
	$name = $_POST['name'];
	$email = $_POST['email'];

	global $wpdb;
	$table_name = $wpdb->prefix . 'form_submissions';

	$sql = "INSERT INTO $table_name (name, email) VALUES ('$name', '$email')";
	$result = $wpdb->query($sql);
	if ( 0 < $result ) {
		wp_redirect( WCJHB_SUCCESS_PAGE_SLUG );
		die();
	}

	wp_redirect( WCJHB_ERROR_PAGE_SLUG );
	die();
}

/**
 * Create an admin page to show the form submissions
 */
add_action( 'admin_menu', 'wcjhb_submenu', 11 );
function wcjhb_submenu() {
	add_menu_page(
		esc_html__( 'WCJHB Admin Page', 'wcjhb' ),
		esc_html__( 'WCJHB Admin Page', 'wcjhb' ),
		'manage_options',
		'wcjhb_admin',
		'wcjhb_render_admin_page',
		'dashicons-admin-tools'
	);
}

/**
 * Render the form submissions admin page
 */
function wcjhb_render_admin_page(){
	$submissions = wcjhb_get_form_submissions();
	?>
	<div class="wrap" id="wcjhb_admin">
		<h1>Admin</h1>
		<table>
			<thead>
				<tr>
					<th>Name</th>
					<th>Email</th>
				</tr>
			</thead>
			<?php foreach ($submissions as $submission){ ?>
				<tr>
					<td><?php echo $submission->name?></td>
					<td><?php echo $submission->email?></td>
					<td><a class="delete-submission" data-id="<?php echo $submission->id?>" style="cursor:pointer;">Delete</a></td>
				</tr>
			<?php } ?>
		</table>
	</div>
	<?php
}

/**
 * Get all the form submissions
 *
 * @return array|object|null
 */
function wcjhb_get_form_submissions() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'form_submissions';

	$sql     = "SELECT * FROM $table_name";
	$results = $wpdb->get_results( $sql );

	return $results;
}

/**
 * Ajax Hook to delete the form submissions
 */
add_action( 'wp_ajax_delete_form_submission', 'wcjhb_delete_form_submission' );
function wcjhb_delete_form_submission() {
	$id = $_POST['id'];
	global $wpdb;
	$table_name = $wpdb->prefix . 'form_submissions';

	$sql     = "DELETE FROM $table_name WHERE id = $id";
	$result = $wpdb->get_results( $sql );

	return wp_send_json( array( 'result' => $result ) );
}




