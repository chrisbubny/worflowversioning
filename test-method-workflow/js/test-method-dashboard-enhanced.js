/**
 * Enhanced Test Method Dashboard JavaScript
 */
(function($) {
	'use strict';
	
	// Initialize dashboard functionality when document is ready
	$(document).ready(function() {
		initDashboardTabs();
		initQuickActions();
		initHelpModal();
		initWidgetRefresh();
	});
	
	/**
	 * Initialize dashboard tabs
	 */
	function initDashboardTabs() {
		// Handle tab navigation
		$('.tabs-nav a').on('click', function(e) {
			e.preventDefault();
			
			var $this = $(this);
			var tabId = $this.attr('href');
			
			// Update active tab nav
			$this.parent().addClass('active').siblings().removeClass('active');
			
			// Show active tab content
			$(tabId).addClass('active').siblings('.tab-content').removeClass('active');
			
			// Store active tab in session storage for persistence
			if (typeof(Storage) !== "undefined") {
				sessionStorage.setItem('activeDashboardTab', tabId);
			}
		});
		
		// Restore active tab from session storage if available
		if (typeof(Storage) !== "undefined" && sessionStorage.getItem('activeDashboardTab')) {
			var activeTab = sessionStorage.getItem('activeDashboardTab');
			$('.tabs-nav a[href="' + activeTab + '"]').trigger('click');
		}
	}
	
	/**
	 * Initialize quick actions
	 */
	function initQuickActions() {
		// Handle revision creation
		$(document).on('click', '.create-revision-link', function(e) {
			e.preventDefault();
			
			var postId = $(this).data('post-id');
			var nonce = $(this).data('nonce');
			
			if (!nonce) {
				alert("Error: Security token missing. Please refresh the page and try again.");
				return;
			}
			
			if (confirm(tmDashboard.confirm_create_revision || 'Are you sure you want to create a new revision of this test method?')) {
				// Show loading state
				var $button = $(this);
				var originalText = $button.text();
				$button.text('Creating...').addClass('disabled').prop('disabled', true);
				
				// Send AJAX request
				$.ajax({
					url: tmDashboard.ajaxurl,
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
							$button.text(originalText).removeClass('disabled').prop('disabled', false);
							alert(response.data || 'An error occurred while creating the revision. Please try again.');
						}
					},
					error: function() {
						$button.text(originalText).removeClass('disabled').prop('disabled', false);
						alert('An error occurred while communicating with the server. Please try again.');
					}
				});
			}
		});
		
		// Handle dashboard actions
		$('.dashboard-action-button').on('click', function(e) {
			e.preventDefault();
			
			var $button = $(this);
			var action = $button.data('action');
			var postId = $button.data('post-id');
			var confirmMsg = $button.data('confirm');
			
			if (confirmMsg && !confirm(confirmMsg)) {
				return;
			}
			
			// Show loading state
			var originalText = $button.text();
			$button.text('Processing...').addClass('disabled').prop('disabled', true);
			
			// Send AJAX request
			$.ajax({
				url: tmDashboard.ajaxurl,
				type: 'POST',
				data: {
					action: 'tm_dashboard_action',
					tm_action: action,
					post_id: postId,
					nonce: tmDashboard.nonce
				},
				success: function(response) {
					if (response.success) {
						if (response.data.reload) {
							window.location.reload();
						} else if (response.data.redirect) {
							window.location.href = response.data.redirect;
						} else {
							$button.text(originalText).removeClass('disabled').prop('disabled', false);
							alert(response.data.message || 'Action completed successfully.');
						}
					} else {
						$button.text(originalText).removeClass('disabled').prop('disabled', false);
						alert(response.data || 'An error occurred. Please try again.');
					}
				},
				error: function() {
					$button.text(originalText).removeClass('disabled').prop('disabled', false);
					alert('An error occurred while communicating with the server. Please try again.');
				}
			});
		});
	}
	
	/**
	 * Initialize help modal
	 */
	function initHelpModal() {
		// Add help button to header
		var $header = $('.dashboard-header');
		var $helpButton = $('<a href="#" class="help-button" style="position: absolute; top: 20px; right: 20px;"><span class="dashicons dashicons-editor-help"></span> Help</a>');
		
		$header.css('position', 'relative').append($helpButton);
		
		// Show modal when help button is clicked
		$helpButton.on('click', function(e) {
			e.preventDefault();
			$('#dashboard-help-modal').show();
		});
		
		// Hide modal when close button or background is clicked
		$('.dashboard-modal-close, .dashboard-modal-close-btn').on('click', function() {
			$('#dashboard-help-modal').hide();
		});
		
		// Close modal when clicking outside of content
		$(window).on('click', function(e) {
			if ($(e.target).hasClass('dashboard-modal')) {
				$('#dashboard-help-modal').hide();
			}
		});
		
		// Close modal with escape key
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $('#dashboard-help-modal').is(':visible')) {
				$('#dashboard-help-modal').hide();
			}
		});
	}
	
	/**
	 * Initialize dashboard widget refresh
	 */
	function initWidgetRefresh() {
		// Add refresh button to dashboard widgets
		$('.enhanced-status-widget, .enhanced-reviews-widget').each(function() {
			var $widget = $(this);
			var $parent = $widget.parent();
			var $refreshButton = $('<button type="button" class="widget-refresh-button" style="position: absolute; top: 10px; right: 10px;"><span class="dashicons dashicons-update"></span></button>');
			
			// Only add if parent has relative positioning
			if ($parent.css('position') !== 'relative') {
				$parent.css('position', 'relative');
			}
			
			$parent.append($refreshButton);
			
			// Set widget type
			if ($widget.hasClass('enhanced-status-widget')) {
				$refreshButton.data('widget-type', 'status');
			} else if ($widget.hasClass('enhanced-reviews-widget')) {
				$refreshButton.data('widget-type', 'reviews');
			}
		});
		
		// Handle refresh button click
		$(document).on('click', '.widget-refresh-button', function() {
			var $button = $(this);
			var $widget = $button.data('widget-type') === 'status' ? 
						  $('.enhanced-status-widget') : 
						  $('.enhanced-reviews-widget');
			
			// Show loading state
			$button.addClass('spin');
			$widget.css('opacity', '0.5');
			
			// Send AJAX request
			$.ajax({
				url: tmDashboard.ajaxurl,
				type: 'POST',
				data: {
					action: 'tm_dashboard_refresh',
					widget_type: $button.data('widget-type'),
					nonce: tmDashboard.nonce
				},
				success: function(response) {
					// Remove loading state
					$button.removeClass('spin');
					$widget.css('opacity', '1');
					
					if (response.success) {
						$widget.html(response.data.content);
					} else {
						alert(tmDashboard.error || 'Error loading widget data.');
					}
				},
				error: function() {
					// Remove loading state
					$button.removeClass('spin');
					$widget.css('opacity', '1');
					
					alert(tmDashboard.error || 'Error loading widget data.');
				}
			});
		});
	}
	
	// Add spin animation for refresh buttons
	$('<style>')
		.prop('type', 'text/css')
		.html(`
			@keyframes spin {
				from { transform: rotate(0deg); }
				to { transform: rotate(360deg); }
			}
			.widget-refresh-button.spin .dashicons {
				animation: spin 1s linear infinite;
			}
			.widget-refresh-button {
				background: none;
				border: none;
				cursor: pointer;
				padding: 0;
				color: #aaa;
			}
			.widget-refresh-button:hover {
				color: #0073aa;
			}
		`)
		.appendTo('head');
	
})(jQuery);