/**
 * Create revision fixed JavaScript
 * This improves error handling and debugging for the create revision functionality
 */
jQuery(document).ready(function($) {
	// Handle create revision links
	$(document).on('click', '.create-revision-link, .create-revision', function(e) {
		e.preventDefault();
		
		var postId = $(this).data('post-id');
		var nonce = $(this).data('nonce');
		
		// If nonce is undefined, try to get it from the form
		if (!nonce && $('input[name="test_method_revision_nonce"]').length) {
			nonce = $('input[name="test_method_revision_nonce"]').val();
		}
		
		if (!nonce) {
			console.error('Revision nonce is missing');
			alert('Security validation failed. Please refresh the page and try again.');
			return;
		}
		
		if (confirm('Are you sure you want to create a new revision of this test method?')) {
			// Show loading indicator if possible
			if ($(this).is('button')) {
				var originalText = $(this).text();
				$(this).prop('disabled', true).text('Creating revision...');
			}
			
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'create_test_method_revision',
					post_id: postId,
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						window.location.href = response.data.edit_url;
					} else {
						console.error('Revision creation error:', response.data);
						alert(response.data || 'An error occurred while creating the revision. Please try again.');
						
						// Reset button if it exists
						if ($(this).is('button')) {
							$(this).prop('disabled', false).text(originalText);
						}
					}
				},
				error: function(xhr, status, error) {
					console.error('AJAX error:', { xhr: xhr, status: status, error: error });
					alert('An error occurred while communicating with the server. Please try again.');
					
					// Reset button if it exists
					if ($(this).is('button')) {
						$(this).prop('disabled', false).text(originalText);
					}
				}
			});
		}
	});
});