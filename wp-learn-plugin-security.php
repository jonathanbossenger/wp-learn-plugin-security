<?php
/**
 * Plugin Name:     WP Learn Plugin Security
 * Description:     Badly coded Plugin
 * Author:          Jonathan Bossenger
 * Author URI:      https://jonthanbossenger.com
 * Text Domain:     wp-learn-plugin-security
 * Domain Path:     /languages
 * Version:         1.0.0
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
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

/**
 * Set up the required form submissions table
 */
register_activation_hook( __FILE__, 'wp_learn_setup_table' );
function wp_learn_setup_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'form_submissions';

	// Using $wpdb->prepare for table creation is not necessary as the table name
	// is already prefixed and controlled by WordPress
	$charset_collate = $wpdb->get_charset_collate();
	
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		name varchar(100) NOT NULL,
		email varchar(100) NOT NULL,
		PRIMARY KEY  (id)
	) $charset_collate";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * Enqueue Admin assets
 */
add_action( 'admin_enqueue_scripts', 'wp_learn_enqueue_script' );
function wp_learn_enqueue_script() {
	wp_register_script(
		'wp-learn-admin',
		WPLEARN_PLUGIN_URL . 'assets/admin.js',
		array( 'jquery' ),
		'1.0.0',
		true
	);
	wp_enqueue_script( 'wp-learn-admin' );
	wp_localize_script(
		'wp-learn-admin',
		'wp_learn_ajax',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wp_learn_delete_submission' ),
			'confirm'  => esc_html__( 'Are you sure you want to delete this submission?', 'wp-learn-plugin-security' ),
		)
	);
}

/**
 * Enqueue Frontend assets
 */
add_action( 'wp_enqueue_scripts', 'wp_learn_enqueue_script_frontend' );
function wp_learn_enqueue_script_frontend() {
	wp_register_style(
		'wp-learn-style',
		WPLEARN_PLUGIN_URL . 'assets/style.css',
		array(),
		'1.0.0'
	);
	wp_enqueue_style( 'wp-learn-style' );
}

/**
 * Submission Form
 * https://developer.wordpress.org/reference/functions/add_shortcode/
 */
add_shortcode( 'wp_learn_form_shortcode', 'wp_learn_form_shortcode' );
function wp_learn_form_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'class' => 'red',
		),
		$atts
	);
	ob_start();
	?>
	<div id="wp_learn_form" class="<?php echo esc_attr( $atts['class'] ); ?>">
		<form method="post">
			<?php wp_nonce_field( 'wp_learn_form_submit', 'wp_learn_nonce' ); ?>
			<input type="hidden" name="wp_learn_form" value="submit">
			<div>
				<label for="name">Name</label>
				<input type="text" id="name" name="name" placeholder="Name" required>
			</div>
			<div>
				<label for="email">Email address</label>
				<input type="email" id="email" name="email" placeholder="Email address" required>
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
/**
 * Process the form submission.
 *
 * @since 1.0.0
 * @return void
 */
function wp_learn_maybe_process_form() {
	// Verify if form was submitted
	if ( ! isset( $_POST['wp_learn_form'] ) ) {
		return;
	}

	// Verify nonce
	if ( ! isset( $_POST['wp_learn_nonce'] ) || ! wp_verify_nonce( $_POST['wp_learn_nonce'], 'wp_learn_form_submit' ) ) {
		wp_redirect( WPLEARN_ERROR_PAGE_SLUG );
		exit;
	}

	// Validate required fields
	if ( ! isset( $_POST['name'] ) || ! isset( $_POST['email'] ) ) {
		wp_redirect( WPLEARN_ERROR_PAGE_SLUG );
		exit;
	}

	// Sanitize and validate input data
	$name = sanitize_text_field( wp_unslash( $_POST['name'] ) );
	$email = sanitize_email( wp_unslash( $_POST['email'] ) );

	// Validate email format
	if ( ! is_email( $email ) ) {
		wp_redirect( WPLEARN_ERROR_PAGE_SLUG );
		exit;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'form_submissions';

	// Prepare and execute the query safely
	$result = $wpdb->insert(
		$table_name,
		array(
			'name'  => $name,
			'email' => $email,
		),
		array(
			'%s',
			'%s',
		)
	);

	if ( false !== $result ) {
		wp_redirect( WPLEARN_SUCCESS_PAGE_SLUG );
		exit;
	}

	wp_redirect( WPLEARN_ERROR_PAGE_SLUG );
	exit;
}

add_action( 'admin_menu', 'wp_learn_add_admin_menu_page', 11 );
/**
 * Add the admin menu page under Tools.
 *
 * @since 1.0.0
 * @return void
 */
function wp_learn_add_admin_menu_page() {
	add_submenu_page(
		'tools.php',
		esc_html__( 'WP Learn Admin Page', 'wp-learn-plugin-security' ),
		esc_html__( 'WP Learn Admin Page', 'wp-learn-plugin-security' ),
		'manage_options',
		'wp_learn_admin',
		'wp_learn_render_admin_page'
	);
}

/**
 * Render the form submissions admin page
 */
function wp_learn_render_admin_page() {
	// Check if user has permission to access the admin page
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 
			esc_html__( 'You do not have sufficient permissions to access this page.', 'wp-learn-plugin-security' ),
			403 
		);
	}

	$submissions = wp_learn_get_form_submissions();
	?>
	<div class="wrap" id="wp_learn_admin">
		<h1><?php echo esc_html__( 'Form Submissions', 'wp-learn-plugin-security' ); ?></h1>
		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php echo esc_html__( 'Name', 'wp-learn-plugin-security' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Email', 'wp-learn-plugin-security' ); ?></th>
					<th scope="col"><?php echo esc_html__( 'Actions', 'wp-learn-plugin-security' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $submissions ) ) : ?>
					<?php foreach ( $submissions as $submission ) : ?>
						<tr>
							<td><?php echo esc_html( $submission->name ); ?></td>
							<td><?php echo esc_html( $submission->email ); ?></td>
							<td>
								<a 
									class="delete-submission" 
									data-id="<?php echo esc_attr( $submission->id ); ?>" 
									href="#"
									role="button"
								>
									<?php echo esc_html__( 'Delete', 'wp-learn-plugin-security' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="3">
							<?php echo esc_html__( 'No submissions found.', 'wp-learn-plugin-security' ); ?>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
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
	// Check if user has permission to view submissions
	if ( ! current_user_can( 'manage_options' ) ) {
		return array();
	}

	global $wpdb;

	// Using prepare even though there are no variables, for consistency
	return $wpdb->get_results( 
		$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}form_submissions" )
	 );
}

/**
 * Ajax Hook to delete the form submissions
 *
 * @return void
 */
add_action( 'wp_ajax_delete_form_submission', 'wp_learn_delete_form_submission' );
function wp_learn_delete_form_submission() {
	// Check if user has permission to delete submissions
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 
			array(
				'message' => esc_html__( 'You do not have permission to perform this action.', 'wp-learn-plugin-security' ),
			)
		);
	}

	// Verify nonce
	if ( 
		! isset( $_POST['nonce'] ) || 
		! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'wp_learn_delete_submission' ) 
	) {
		wp_send_json_error( 
			array(
				'message' => esc_html__( 'Security check failed.', 'wp-learn-plugin-security' ),
			)
		);
	}

	// Validate and sanitize the ID
	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	
	if ( ! $id ) {
		wp_send_json_error( 
			array(
				'message' => esc_html__( 'Invalid submission ID.', 'wp-learn-plugin-security' ),
			)
		);
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'form_submissions';

	// Use delete method instead of direct query
	$result = $wpdb->delete(
		$table_name,
		array( 'id' => $id ),
		array( '%d' )
	);

	if ( false === $result ) {
		wp_send_json_error( 
			array(
				'message' => esc_html__( 'Failed to delete submission.', 'wp-learn-plugin-security' ),
			)
		);
	}

	wp_send_json_success( 
		array(
			'message' => esc_html__( 'Submission deleted successfully.', 'wp-learn-plugin-security' ),
			'deleted' => $result,
		)
	);
}
