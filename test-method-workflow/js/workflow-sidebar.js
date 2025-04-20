/**
 * Workflow Sidebar for Gutenberg
 */
(function(wp) {
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginSidebar = wp.editPost.PluginSidebar;
	var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	
	// Components
	var PanelBody = wp.components.PanelBody;
	var Button = wp.components.Button;
	var TextareaControl = wp.components.TextareaControl;
	var SelectControl = wp.components.SelectControl;
	var Spinner = wp.components.Spinner;
	
	function WorkflowSidebar() {
		// Get post ID and type
		var postId = wp.data.select('core/editor').getCurrentPostId();
		var postType = wp.data.select('core/editor').getCurrentPostType();
		
		// Skip for test_method post type
		if (postType === 'test_method') {
			return null;
		}
		
		// Simple content for now
		return el(
			Fragment,
			{},
			el(
				PluginSidebarMoreMenuItem,
				{
					target: 'workflow-sidebar'
				},
				'Workflow'
			),
			el(
				PluginSidebar,
				{
					name: 'workflow-sidebar',
					title: 'Workflow'
				},
				el(
					PanelBody,
					{
						title: 'Workflow Status',
						initialOpen: true
					},
					el(
						'div',
						{ className: 'workflow-status-container' },
						el(
							'p',
							{},
							'Workflow sidebar is loaded successfully.'
						),
						el(
							Button,
							{
								isPrimary: true,
								onClick: function() {
									alert('Workflow action button clicked');
								}
							},
							'Test Button'
						)
					)
				)
			)
		);
	}
	
	// Register the plugin
	if (registerPlugin) {
		registerPlugin('test-method-workflow-sidebar', {
			render: WorkflowSidebar
		});
	}
})(window.wp);

// When the DOM is ready
jQuery(document).ready(function($) {
	
	// Reset related test method button functionality
	$('.reset-related-test-method').on('click', function(e) {
		e.preventDefault();
		
		var postId = $(this).data('post-id');
		
		if (confirm('Are you sure you want to reset the related test method? This should only be done if the wrong test method was selected.')) {
			$.ajax({
				url: testMethodWorkflow.ajaxurl,
				type: 'POST',
				data: {
					action: 'reset_related_test_method',
					post_id: postId,
					nonce: testMethodWorkflow.nonce
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data || 'An error occurred');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				}
			});
		}
	});
	
	// Validate related test method field on form submission
	$('#post').on('submit', function(e) {
		// Check if the field exists and is visible (not the hidden input version)
		var $relatedMethod = $('#related_test_method:visible');
		
		if ($relatedMethod.length && $relatedMethod.is('select') && $relatedMethod.prop('required')) {
			if ($relatedMethod.val() === '') {
				e.preventDefault();
				alert('Please select a Related Test Method. This is required.');
				$relatedMethod.focus();
				return false;
			}
		}
	});
	
	// Also validate on clicking submit for review
	$(document).on('click', '.submit-for-review', function(e) {
		// Only validate if the selection is visible and required
		var $relatedMethod = $('#related_test_method:visible');
		
		if ($relatedMethod.length && $relatedMethod.is('select') && $relatedMethod.prop('required')) {
			if ($relatedMethod.val() === '') {
				e.preventDefault();
				alert('Please select a Related Test Method before submitting for review.');
				$relatedMethod.focus();
				return false;
			}
		}
	});
});