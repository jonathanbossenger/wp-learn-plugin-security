jQuery( document ).ready(
	function ($) {
		console.log( 'WP Learn Plugin Security admin.js loaded' );
		$( '.delete-submission' ).on(
			'click',
			function (event) {
				event.preventDefault();
				
				const thisButton = $( this );
				const id = thisButton.data( 'id' );
				
				// Show confirmation dialog
				if ( ! confirm( wp_learn_ajax.confirm ) ) {
					return;
				}
				
				// Send AJAX request with nonce
				$.post(
					wp_learn_ajax.ajax_url,
					{
						'action': 'delete_form_submission',
						'id': id,
						'nonce': wp_learn_ajax.nonce
					},
					function (response) {
						if ( response.success ) {
							// Show success message and remove the row
							alert( response.data.message );
							thisButton.closest( 'tr' ).fadeOut( 400, function() {
								$( this ).remove();
								
								// If no more rows exist (except header), show the "no submissions" message
								if ( $( 'table tbody tr' ).length === 0 ) {
									$( 'table tbody' ).append(
										'<tr><td colspan="3">' + 
										wp_learn_ajax.no_submissions + 
										'</td></tr>'
									);
								}
							} );
						} else {
							// Show error message
							alert( response.data.message );
						}
					}
				).fail( function() {
					alert( wp_learn_ajax.error_message );
				} );
			}
		);
	}
);
