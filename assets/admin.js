jQuery(document).ready(function ($) {

	$('#delete-submission').on('clilck', function (event){
		var this_button = $(this);
		event.preventDefault();
		var id = this_button.data('id');
		jQuery.post(
			wcjhb_ajax.ajax_url,
			{
				'action': 'delete_form_submission',
				'id': id,
			},
			function( response ){
				document.location.reload();
			}
		);
	});
});
