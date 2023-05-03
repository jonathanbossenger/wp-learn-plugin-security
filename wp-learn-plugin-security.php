<?php
/**
 * Plugin Name:     WP Learn Plugin Security
 * Description:     More secure Plugin
 * Author:          Jonathan Bossenger
 * Author URI:      https://jonthanbossenger.com
 * Text Domain:     wp-learn-plugin-security
 * Domain Path:     /languages
 * Version:         1.0.2
 *
 * @package         WP_Learn_Plugin_Security
 */

/**
 * Update these with the page slugs of your success and error pages
 */
define( 'WPLEARN_SUCCESS_PAGE_SLUG', 'form-success-page' );
define( 'WPLEARN_ERROR_PAGE_SLUG', 'form-error-page' );

/**
 * Setting up some URL constants
 */
define( 'WPLEARN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPLEARN_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Set up the required form submissions table
 */
register_activation_hook( __FILE__, 'wp_learn_setup_table' );
function wp_learn_setup_table() {
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
add_action( 'admin_enqueue_scripts', 'wp_learn_enqueue_script' );
function wp_learn_enqueue_script() {
	wp_register_script(
		'wp_learn-admin',
		WPLEARN_PLUGIN_URL . 'assets/admin.js',
		array( 'jquery' ),
		'2.0.0',
		true
	);
	wp_enqueue_script( 'wp_learn-admin' );
	/**
	 * 04 (a). Add an ajax nonce to the script
	 * https://developer.wordpress.org/apis/security/nonces/
	 */
	$ajax_nonce = wp_create_nonce( 'wp_learn_ajax_nonce' );
	wp_localize_script(
		'wp_learn-admin',
		'wp_learn_ajax',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => $ajax_nonce,
		)
	);
}

/**
 * Submission Form
 * https://developer.wordpress.org/reference/functions/add_shortcode/
 */
add_shortcode( 'wp_learn_form_shortcode', 'wp_learn_form_shortcode' );
function wp_learn_form_shortcode( $atts ) {
	$atts = shortcode_atts (
		array(
			'class' => 'red',
		),
		$atts
	);
	ob_start();
	?>
	<div id="wp_learn_form" class="<?php echo $atts['class'] ?>">
		<form method="post">
			<?php
			/**
			 * 04 (b). Add a nonce to the form
			 * https://developer.wordpress.org/apis/security/nonces/
			 */
			wp_nonce_field( 'wp_learn_form_nonce_action', 'wp_learn_form_nonce_field' );
			?>
			<input type="hidden" name="wp_learn_form" value="submit">
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
	</div>
	<?php
	$form = ob_get_clean();
	return $form;
}

/**
 * Process the form data and redirect
 * https://developer.wordpress.org/reference/hooks/wp/
 */
add_action( 'wp', 'wp_learn_maybe_process_form' );
function wp_learn_maybe_process_form() {
	if (!isset($_POST['wp_learn_form'])){
		return;
	}

	/**
	 * 04 (b). Verify the nonce
	 * https://developer.wordpress.org/apis/security/nonces/
	 */
	if ( ! isset( $_POST['wp_learn_form_nonce_field'] ) || ! wp_verify_nonce( $_POST['wp_learn_form_nonce_field'], 'wp_learn_form_nonce_action' ) ) {
		wp_redirect( WPLEARN_ERROR_PAGE_SLUG );
		die();
	}

	/**
	 * 01. Sanitize the data
	 * https://developer.wordpress.org/apis/security/sanitizing/
	 */
	$name = sanitize_text_field( $_POST['name'] );
	$email = sanitize_text_field( $_POST['email'] );

	global $wpdb;
	$table_name = $wpdb->prefix . 'form_submissions';

	$sql = "INSERT INTO $table_name (name, email) VALUES ('$name', '$email')";
	$result = $wpdb->query($sql);
	if ( 0 < $result ) {
		wp_redirect( WPLEARN_SUCCESS_PAGE_SLUG );
		die();
	}

	wp_redirect( WPLEARN_ERROR_PAGE_SLUG );
	die();
}

/**
 * Create an admin page to show the form submissions
 */
add_action( 'admin_menu', 'wp_learn_submenu', 11 );
function wp_learn_submenu() {
	add_menu_page(
		esc_html__( 'WP Learn Admin Page', 'wp_learn' ),
		esc_html__( 'WP Learn Admin Page', 'wp_learn' ),
		'manage_options',
		'wp_learn_admin',
		'wp_learn_render_admin_page',
		'dashicons-admin-tools'
	);
}

/**
 * Render the form submissions admin page
 */
function wp_learn_render_admin_page(){
	$submissions = wp_learn_get_form_submissions();
	/**
	 * 03. Escape the data
	 * https://developer.wordpress.org/apis/security/escaping/
	 * Escape the name and email values
	 * Cast the ID to an integer
	 */
	?>
	<div class="wrap" id="wp_learn_admin">
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
					<td><?php echo esc_html( $submission->name ); ?></td>
					<td><?php echo esc_html( $submission->email ); ?></td>
					<td><a class="delete-submission" data-id="<?php echo (int) $submission->id?>" style="cursor:pointer;">Delete</a></td>
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
function wp_learn_get_form_submissions() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'form_submissions';

	$sql     = "SELECT * FROM $table_name";
	$results = $wpdb->get_results( $sql );

	return $results;
}

/**
 * Ajax Hook to delete the form submissions
 */
add_action( 'wp_ajax_delete_form_submission', 'wp_learn_delete_form_submission' );
function wp_learn_delete_form_submission() {
	/**
	 * 05. Check user capabilities
	 * https://developer.wordpress.org/apis/security/user-roles-and-capabilities/
	 */
	if ( ! current_user_can( 'manage_options' ) ) {
		return wp_send_json( array( 'result' => 'Authentication error' ) );
	}

	/**
	 * 04 (a). Verify the ajax nonce
	 * https://developer.wordpress.org/apis/security/nonces/
	 */
	check_ajax_referer( 'wp_learn_ajax_nonce' );

	/**
	 * 02. Validate the incoming data
	 * https://developer.wordpress.org/apis/security/data-validation/
	 * Make sure that the id has been POSTed
	 * Make sure that the id is an integer
	 */
	if ( ! isset( $_POST['id'] ) ) {
		wp_send_json_error( 'Invalid ID' );
	}
	$id = (int) $_POST['id'];

	global $wpdb;
	$table_name = $wpdb->prefix . 'form_submissions';

	$sql    = "DELETE FROM $table_name WHERE id = $id";
	$result = $wpdb->get_results( $sql );

	return wp_send_json( array( 'result' => $result ) );
}




