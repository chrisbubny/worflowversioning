<?php
/**
 * Enhanced Test Method Dashboard
 * 
 * Improves the user experience and functionality of the test method dashboard
 * based on user feedback and testing.
 *
 * @package TestMethodWorkflow
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Enhanced Test Method Dashboard class
 */
class TestMethod_EnhancedDashboard {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Replace the original dashboard
		add_action('admin_menu', array($this, 'replace_dashboard_page'), 20);
		
		// Enqueue dashboard-specific styles and scripts
		add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
		
		// Add dashboard widgets
		add_action('wp_dashboard_setup', array($this, 'add_enhanced_dashboard_widgets'));
		
		// Add AJAX handlers for dashboard actions
		add_action('wp_ajax_tm_dashboard_refresh', array($this, 'ajax_refresh_dashboard_widget'));
		add_action('wp_ajax_tm_dashboard_action', array($this, 'ajax_handle_dashboard_action'));
	}
	
	/**
	 * Replace the original dashboard with enhanced version
	 */
	public function replace_dashboard_page() {
		// Remove the old dashboard menu item
		remove_menu_page('test-method-dashboard');
		
		// Add the new dashboard with the same capability but higher priority
		add_menu_page(
			__('Test Method Dashboard', 'test-method-workflow'),
			__('Test Method Dashboard', 'test-method-workflow'),
			'read',
			'test-method-dashboard',
			array($this, 'render_enhanced_dashboard'),
			'dashicons-clipboard',
			2
		);
	}
	
	/**
	 * Enqueue dashboard assets
	 */
	public function enqueue_dashboard_assets($hook) {
		if ($hook != 'toplevel_page_test-method-dashboard') {
			return;
		}
		
		// Register and enqueue dashboard styles
		wp_register_style(
			'test-method-dashboard-enhanced',
			plugin_dir_url(dirname(__FILE__)) . 'css/test-method-dashboard-enhanced.css',
			array(),
			'1.0.0'
		);
		wp_enqueue_style('test-method-dashboard-enhanced');
		
		// Register and enqueue dashboard scripts
		wp_register_script(
			'test-method-dashboard-enhanced',
			plugin_dir_url(dirname(__FILE__)) . 'js/test-method-dashboard-enhanced.js',
			array('jquery'),
			'1.0.0',
			true
		);
		
		// Localize the script with data and translations
		wp_localize_script('test-method-dashboard-enhanced', 'tmDashboard', array(
			'ajaxurl' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('test_method_dashboard_nonce'),
			'refreshing' => __('Refreshing...', 'test-method-workflow'),
			'error' => __('Error loading data', 'test-method-workflow'),
			'confirm_review' => __('Are you sure you want to review this test method?', 'test-method-workflow'),
			'confirm_publish' => __('Are you sure you want to publish this test method?', 'test-method-workflow')
		));
		
		wp_enqueue_script('test-method-dashboard-enhanced');
	}
	
	/**
	 * Add enhanced dashboard widgets to WP Dashboard
	 */
	public function add_enhanced_dashboard_widgets() {
		$user = wp_get_current_user();
		$roles = (array) $user->roles;
		
		// For TP roles, add enhanced widgets
		if (array_intersect($roles, array('tp_contributor', 'tp_approver', 'tp_admin'))) {
			// Remove old widgets first
			remove_meta_box('test_method_status_widget', 'dashboard', 'normal');
			remove_meta_box('test_method_reviews_widget', 'dashboard', 'normal');
			
			// Add new enhanced widgets
			wp_add_dashboard_widget(
				'test_method_enhanced_status_widget',
				__('Test Method Status', 'test-method-workflow'),
				array($this, 'render_enhanced_status_widget')
			);
			
			// For approvers and admins, add pending reviews widget
			if (array_intersect($roles, array('tp_approver', 'tp_admin', 'administrator'))) {
				wp_add_dashboard_widget(
					'test_method_enhanced_reviews_widget',
					__('Pending Test Method Reviews', 'test-method-workflow'),
					array($this, 'render_enhanced_reviews_widget')
				);
			}
		}
	}
	
	/**
	 * Render the enhanced main dashboard
	 */
    private static $rendered = false;

	public function render_enhanced_dashboard() {
        if (self::$rendered) return;

        self::$rendered = true;

		$user = wp_get_current_user();
		$roles = (array) $user->roles;
		
		echo '<div class="wrap test-method-dashboard-enhanced">';
		
		// Dashboard header with role-specific welcome message
		echo '<div class="dashboard-header">';
		echo '<h1>' . __('Test Method Dashboard', 'test-method-workflow') . '</h1>';
		
		if (in_array('tp_admin', $roles) || in_array('administrator', $roles)) {
			echo '<p class="welcome-message">' . 
				 __('Welcome to your administrator dashboard. Here you can monitor all test methods, approve content, and manage the publishing workflow.', 'test-method-workflow') . 
				 '</p>';
		} elseif (in_array('tp_approver', $roles)) {
			echo '<p class="welcome-message">' . 
				 __('Welcome to your approver dashboard. Review pending test methods and track your approval history.', 'test-method-workflow') . 
				 '</p>';
		} else {
			echo '<p class="welcome-message">' . 
				 __('Welcome to your contributor dashboard. Create and manage your test methods and track their approval status.', 'test-method-workflow') . 
				 '</p>';
		}
		echo '</div>';
		
		// Visual workflow explanation
		echo '<div class="workflow-explanation">';
		echo '<h2>' . __('Test Method Workflow', 'test-method-workflow') . '</h2>';
		echo '<div class="workflow-steps">';
		echo '<div class="step step-draft"><span class="step-number">1</span><span class="step-name">' . __('Draft', 'test-method-workflow') . '</span></div>';
		echo '<div class="step-arrow">→</div>';
		echo '<div class="step step-pending"><span class="step-number">2</span><span class="step-name">' . __('Pending Review', 'test-method-workflow') . '</span></div>';
		echo '<div class="step-arrow">→</div>';
		echo '<div class="step step-approval"><span class="step-number">3</span><span class="step-name">' . __('Awaiting Final Approval', 'test-method-workflow') . '</span></div>';
		echo '<div class="step-arrow">→</div>';
		echo '<div class="step step-approved"><span class="step-number">4</span><span class="step-name">' . __('Approved', 'test-method-workflow') . '</span></div>';
		echo '<div class="step-arrow">→</div>';
		echo '<div class="step step-published"><span class="step-number">5</span><span class="step-name">' . __('Published', 'test-method-workflow') . '</span></div>';
		echo '</div>';
		echo '</div>';
		
		// Different dashboard based on role
		if (in_array('tp_admin', $roles) || in_array('administrator', $roles)) {
			$this->render_enhanced_admin_dashboard();
		} elseif (in_array('tp_approver', $roles)) {
			$this->render_enhanced_approver_dashboard();
		} else {
			$this->render_enhanced_contributor_dashboard();
		}
		
		echo '</div>'; // .wrap
		
		// Add dashboard tour/help modal
		$this->render_dashboard_help_modal();
	}
	
	/**
	 * Render the enhanced admin dashboard
	 */
	private function render_enhanced_admin_dashboard() {
		// Get counts with optimized queries
		$counts = $this->get_test_method_counts();
		
		// Dashboard cards/stats with visual indicators
		echo '<div class="dashboard-stats-container">';
		echo '<div class="stats-header"><h2>' . __('Overview', 'test-method-workflow') . '</h2></div>';
		echo '<div class="dashboard-stats">';
		echo $this->render_stat_box('draft', $counts['draft'], __('Drafts', 'test-method-workflow'), 'edit.php?post_type=test_method&workflow_status=draft');
		echo $this->render_stat_box('pending', $counts['pending_review'], __('Pending Review', 'test-method-workflow'), 'edit.php?post_type=test_method&workflow_status=pending_review');
		echo $this->render_stat_box('approved', $counts['approved'], __('Ready for Publishing', 'test-method-workflow'), 'edit.php?post_type=test_method&workflow_status=approved');
		echo $this->render_stat_box('published', $counts['published'], __('Published', 'test-method-workflow'), 'edit.php?post_type=test_method&post_status=publish');
		echo '</div>';
		echo '</div>';
		
		// Action required section
		echo '<div class="dashboard-action-container">';
		echo '<div class="action-header"><h2>' . __('Actions Required', 'test-method-workflow') . '</h2></div>';
		
		echo '<div class="dashboard-tabs">';
		echo '<ul class="tabs-nav">';
		echo '<li class="active"><a href="#tab-publish">' . __('Ready to Publish', 'test-method-workflow') . ' <span class="count">' . $counts['approved'] . '</span></a></li>';
		echo '<li><a href="#tab-review">' . __('Needs Review', 'test-method-workflow') . ' <span class="count">' . $counts['pending_review'] . '</span></a></li>';
		echo '<li><a href="#tab-recent">' . __('Recently Published', 'test-method-workflow') . '</a></li>';
		echo '</ul>';
		
		// Tab content - Ready to Publish
		echo '<div id="tab-publish" class="tab-content active">';
		$this->render_admin_publish_tab();
		echo '</div>';
		
		// Tab content - Needs Review
		echo '<div id="tab-review" class="tab-content">';
		$this->render_admin_review_tab();
		echo '</div>';
		
		// Tab content - Recently Published
		echo '<div id="tab-recent" class="tab-content">';
		$this->render_admin_recent_tab();
		echo '</div>';
		
		echo '</div>'; // .dashboard-tabs
		echo '</div>'; // .dashboard-action-container
		
		// Quick actions section
		echo '<div class="dashboard-quick-actions">';
		echo '<h2>' . __('Quick Actions', 'test-method-workflow') . '</h2>';
		echo '<div class="quick-action-buttons">';
		echo '<a href="' . admin_url('post-new.php?post_type=test_method') . '" class="button button-primary quick-action-button">';
		echo '<span class="dashicons dashicons-plus-alt"></span> ' . __('Create New Test Method', 'test-method-workflow');
		echo '</a>';
		
		echo '<a href="' . admin_url('edit.php?post_type=test_method') . '" class="button quick-action-button">';
		echo '<span class="dashicons dashicons-list-view"></span> ' . __('View All Test Methods', 'test-method-workflow');
		echo '</a>';
		
		echo '<a href="' . admin_url('edit.php?post_type=test_method&workflow_status=locked') . '" class="button quick-action-button">';
		echo '<span class="dashicons dashicons-lock"></span> ' . __('Manage Locked Test Methods', 'test-method-workflow');
		echo '</a>';
		
		echo '</div>'; // .quick-action-buttons
		echo '</div>'; // .dashboard-quick-actions
	}
	
	/**
	 * Render ready to publish tab content for admin
	 */
	private function render_admin_publish_tab() {
		// Get approved methods with optimized query
		$approved_methods = $this->get_test_methods_by_status('approved');
		
		if (!empty($approved_methods)) {
			echo '<table class="tm-dashboard-table">';
			echo '<thead><tr>';
			echo '<th>' . __('Title', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Author', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Version', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Last Updated', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Actions', 'test-method-workflow') . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';
			
			foreach ($approved_methods as $post) {
				$author = get_userdata($post->post_author);
				$version = get_post_meta($post->ID, '_current_version_number', true);
				if (empty($version)) $version = '0.1';
				
				$is_revision = get_post_meta($post->ID, '_is_revision', true);
				$action_label = $is_revision ? __('Review & Publish Revision', 'test-method-workflow') : __('Review & Publish', 'test-method-workflow');
				
				echo '<tr>';
				echo '<td class="title-cell"><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
				echo '<td>' . esc_html($author->display_name) . '</td>';
				echo '<td>' . esc_html($version) . '</td>';
				echo '<td>' . get_the_modified_date('M j, Y @ g:i a', $post->ID) . '</td>';
				echo '<td class="actions-cell">';
				echo '<a href="' . get_edit_post_link($post->ID) . '" class="button button-primary">' . $action_label . '</a>';
				echo '</td>';
				echo '</tr>';
			}
			
			echo '</tbody></table>';
		} else {
			echo '<div class="empty-state">';
			echo '<div class="empty-state-icon"><span class="dashicons dashicons-yes-alt"></span></div>';
			echo '<h3>' . __('All caught up!', 'test-method-workflow') . '</h3>';
			echo '<p>' . __('There are no test methods waiting to be published at this time.', 'test-method-workflow') . '</p>';
			echo '</div>';
		}
	}
	
	/**
	 * Render needs review tab content for admin
	 */
	private function render_admin_review_tab() {
		// Get pending methods with optimized query
		$pending_methods = $this->get_test_methods_by_status('pending_review');
		$final_approval_methods = $this->get_test_methods_by_status('pending_final_approval');
		
		// Combine and sort by modified date
		$review_methods = array_merge($pending_methods, $final_approval_methods);
		usort($review_methods, function($a, $b) {
			return strtotime($b->post_modified) - strtotime($a->post_modified);
		});
		
		if (!empty($review_methods)) {
			echo '<table class="tm-dashboard-table">';
			echo '<thead><tr>';
			echo '<th>' . __('Title', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Author', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Status', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Submitted', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Actions', 'test-method-workflow') . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';
			
			foreach ($review_methods as $post) {
				$author = get_userdata($post->post_author);
				$workflow_status = get_post_meta($post->ID, '_workflow_status', true);
				$status_label = $workflow_status === 'pending_final_approval' ? 
					__('Needs Final Approval', 'test-method-workflow') : 
					__('Pending Review', 'test-method-workflow');
				$status_class = $workflow_status === 'pending_final_approval' ? 'final-approval' : 'pending';
				
				echo '<tr>';
				echo '<td class="title-cell"><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
				echo '<td>' . esc_html($author->display_name) . '</td>';
				echo '<td><span class="status-badge status-' . $status_class . '">' . $status_label . '</span></td>';
				echo '<td>' . get_the_date('M j, Y', $post->ID) . '</td>';
				echo '<td class="actions-cell">';
				echo '<a href="' . get_edit_post_link($post->ID) . '" class="button button-primary">' . __('Review', 'test-method-workflow') . '</a>';
				echo '</td>';
				echo '</tr>';
			}
			
			echo '</tbody></table>';
		} else {
			echo '<div class="empty-state">';
			echo '<div class="empty-state-icon"><span class="dashicons dashicons-yes-alt"></span></div>';
			echo '<h3>' . __('All caught up!', 'test-method-workflow') . '</h3>';
			echo '<p>' . __('There are no test methods waiting for review at this time.', 'test-method-workflow') . '</p>';
			echo '</div>';
		}
	}
	
	/**
	 * Render recently published tab content for admin
	 */
	private function render_admin_recent_tab() {
		// Get recently published methods
		$published_methods = $this->get_test_methods_by_status('publish', 10);
		
		if (!empty($published_methods)) {
			echo '<table class="tm-dashboard-table">';
			echo '<thead><tr>';
			echo '<th>' . __('Title', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Author', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Version', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Published', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Actions', 'test-method-workflow') . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';
			
			foreach ($published_methods as $post) {
				$author = get_userdata($post->post_author);
				$version = get_post_meta($post->ID, '_current_version_number', true);
				if (empty($version)) $version = '0.1';
				
				echo '<tr>';
				echo '<td class="title-cell"><a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
				echo '<td>' . esc_html($author->display_name) . '</td>';
				echo '<td>' . esc_html($version) . '</td>';
				echo '<td>' . get_the_date('M j, Y', $post->ID) . '</td>';
				echo '<td class="actions-cell">';
				echo '<a href="' . get_permalink($post->ID) . '" class="button">' . __('View', 'test-method-workflow') . '</a> ';
				echo '<a href="' . get_edit_post_link($post->ID) . '" class="button">' . __('Edit', 'test-method-workflow') . '</a>';
				echo '</td>';
				echo '</tr>';
			}
			
			echo '</tbody></table>';
		} else {
			echo '<div class="empty-state">';
			echo '<div class="empty-state-icon"><span class="dashicons dashicons-info"></span></div>';
			echo '<h3>' . __('No published test methods', 'test-method-workflow') . '</h3>';
			echo '<p>' . __('Once test methods are published, they will appear here.', 'test-method-workflow') . '</p>';
			echo '</div>';
		}
	}
	
	/**
	 * Render the enhanced approver dashboard
	 */
	private function render_enhanced_approver_dashboard() {
		// Get counts
		$pending_count = $this->get_test_method_count('pending_review');
		$final_approval_count = $this->get_test_method_count('pending_final_approval');
		$my_approved_count = $this->get_my_approval_count('approved');
		$my_rejected_count = $this->get_my_approval_count('rejected');
		
		// Dashboard stats with visual indicators
		echo '<div class="dashboard-stats-container">';
		echo '<div class="stats-header"><h2>' . __('Your Review Stats', 'test-method-workflow') . '</h2></div>';
		echo '<div class="dashboard-stats">';
		echo $this->render_stat_box('pending', $pending_count + $final_approval_count, __('Awaiting Review', 'test-method-workflow'), 'edit.php?post_type=test_method&workflow_status=pending_review');
		echo $this->render_stat_box('approved', $my_approved_count, __('You Approved', 'test-method-workflow'));
		echo $this->render_stat_box('rejected', $my_rejected_count, __('You Rejected', 'test-method-workflow'));
		echo '</div>';
		echo '</div>';
		
		// Methods needing review section
		echo '<div class="dashboard-pending-reviews">';
		echo '<h2>' . __('Test Methods Awaiting Your Review', 'test-method-workflow') . '</h2>';
		echo '<div class="review-container">';
		
		$pending_methods = $this->get_test_methods_by_status('pending_review');
		$final_approval_methods = $this->get_test_methods_by_status('pending_final_approval');
		
		// Combine and filter out already reviewed
		$review_methods = array_merge($pending_methods, $final_approval_methods);
		$filtered_methods = array();
		foreach ($review_methods as $post) {
			if (!$this->user_already_reviewed($post->ID)) {
				$filtered_methods[] = $post;
			}
		}
		
		if (!empty($filtered_methods)) {
			echo '<table class="tm-dashboard-table">';
			echo '<thead><tr>';
			echo '<th>' . __('Title', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Author', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Status', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Submitted', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Actions', 'test-method-workflow') . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';
			
			foreach ($filtered_methods as $post) {
				$author = get_userdata($post->post_author);
				$workflow_status = get_post_meta($post->ID, '_workflow_status', true);
				$status_label = $workflow_status === 'pending_final_approval' ? 
					__('Needs Final Approval', 'test-method-workflow') : 
					__('Pending First Review', 'test-method-workflow');
				$status_class = $workflow_status === 'pending_final_approval' ? 'final-approval' : 'pending';
				
				$approvals = get_post_meta($post->ID, '_approvals', true);
				$approval_count = is_array($approvals) ? count($approvals) : 0;
				
				echo '<tr>';
				echo '<td class="title-cell"><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
				echo '<td>' . esc_html($author->display_name) . '</td>';
				echo '<td><span class="status-badge status-' . $status_class . '">' . $status_label . '</span></td>';
				echo '<td>' . get_the_date('M j, Y', $post->ID) . '</td>';
				echo '<td class="actions-cell">';
				echo '<a href="' . get_edit_post_link($post->ID) . '" class="button button-primary">' . __('Review', 'test-method-workflow') . '</a>';
				echo '</td>';
				echo '</tr>';
			}
			
			echo '</tbody></table>';
		} else {
			echo '<div class="empty-state">';
			echo '<div class="empty-state-icon"><span class="dashicons dashicons-yes-alt"></span></div>';
			echo '<h3>' . __('All caught up!', 'test-method-workflow') . '</h3>';
			echo '<p>' . __('There are no test methods waiting for your review at this time.', 'test-method-workflow') . '</p>';
			echo '</div>';
		}
		
		echo '</div>'; // .review-container
		echo '</div>'; // .dashboard-pending-reviews
		
		// Your recent reviews section
		echo '<div class="dashboard-recent-reviews">';
		echo '<h2>' . __('Your Recent Reviews', 'test-method-workflow') . '</h2>';
		
		$your_reviews = $this->get_your_reviewed_methods(8);
		
		if (!empty($your_reviews)) {
			echo '<table class="tm-dashboard-table">';
			echo '<thead><tr>';
			echo '<th>' . __('Title', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Author', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Your Decision', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Date', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Actions', 'test-method-workflow') . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';
			
			foreach ($your_reviews as $review) {
				$post = get_post($review['post_id']);
				if (!$post) continue;
				
				$author = get_userdata($post->post_author);
				$status_class = $review['status'] === 'approved' ? 'status-approved' : 'status-rejected';
				
				echo '<tr>';
				echo '<td class="title-cell"><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
				echo '<td>' . esc_html($author->display_name) . '</td>';
				echo '<td><span class="status-badge ' . $status_class . '">' . ucfirst($review['status']) . '</span></td>';
				echo '<td>' . date_i18n(get_option('date_format'), $review['date']) . '</td>';
				echo '<td class="actions-cell">';
				echo '<a href="' . get_edit_post_link($post->ID) . '" class="button">' . __('View Test Method', 'test-method-workflow') . '</a>';
				echo '</td>';
				echo '</tr>';
			}
			
			echo '</tbody></table>';
		} else {
			echo '<div class="empty-state">';
			echo '<div class="empty-state-icon"><span class="dashicons dashicons-info"></span></div>';
			echo '<h3>' . __('No review history', 'test-method-workflow') . '</h3>';
			echo '<p>' . __('Once you review test methods, your history will appear here.', 'test-method-workflow') . '</p>';
			echo '</div>';
		}
		
		echo '</div>'; // .dashboard-recent-reviews
		
		// Quick actions section
		echo '<div class="dashboard-quick-actions">';
		echo '<h2>' . __('Quick Actions', 'test-method-workflow') . '</h2>';
		echo '<div class="quick-action-buttons">';
		echo '<a href="' . admin_url('post-new.php?post_type=test_method') . '" class="button button-primary quick-action-button">';
		echo '<span class="dashicons dashicons-plus-alt"></span> ' . __('Create New Test Method', 'test-method-workflow');
		echo '</a>';
		
		echo '<a href="' . admin_url('edit.php?post_type=test_method') . '" class="button quick-action-button">';
		echo '<span class="dashicons dashicons-list-view"></span> ' . __('View All Test Methods', 'test-method-workflow');
		echo '</a>';
		
		echo '</div>'; // .quick-action-buttons
		echo '</div>'; // .dashboard-quick-actions
	}
	
	/**
	 * Render the enhanced contributor dashboard
	 */
	private function render_enhanced_contributor_dashboard() {
		$user_id = get_current_user_id();
		
		// Get counts with optimized queries
		$draft_count = $this->get_user_test_method_count($user_id, 'draft');
		$pending_count = $this->get_user_test_method_count($user_id, 'pending_review');
		$rejected_count = $this->get_user_test_method_count($user_id, 'rejected');
		$published_count = $this->get_user_test_method_count($user_id, 'publish');
		
		// Dashboard stats with visual indicators
		echo '<div class="dashboard-stats-container">';
		echo '<div class="stats-header"><h2>' . __('Your Content Overview', 'test-method-workflow') . '</h2></div>';
		echo '<div class="dashboard-stats">';
		echo $this->render_stat_box('draft', $draft_count, __('Your Drafts', 'test-method-workflow'));
		echo $this->render_stat_box('pending', $pending_count, __('Pending Review', 'test-method-workflow'));
		echo $this->render_stat_box('rejected', $rejected_count, __('Rejected', 'test-method-workflow'));
		echo $this->render_stat_box('published', $published_count, __('Published', 'test-method-workflow'));
		echo '</div>';
		
		echo '<p class="create-button-wrap">';
		echo '<a href="' . admin_url('post-new.php?post_type=test_method') . '" class="button button-primary button-hero">';
		echo '<span class="dashicons dashicons-plus-alt"></span> ' . __('Create New Test Method', 'test-method-workflow');
		echo '</a>';
		echo '</p>';
		echo '</div>'; // .dashboard-stats-container
		
		// Dashboard tabs for contributor
		echo '<div class="dashboard-tabs contributor-tabs">';
		echo '<ul class="tabs-nav">';
		echo '<li class="active"><a href="#tab-drafts">' . __('Your Drafts', 'test-method-workflow') . ' <span class="count">' . $draft_count . '</span></a></li>';
		echo '<li><a href="#tab-pending">' . __('Under Review', 'test-method-workflow') . ' <span class="count">' . $pending_count . '</span></a></li>';
		echo '<li><a href="#tab-rejected">' . __('Rejected', 'test-method-workflow') . ' <span class="count">' . $rejected_count . '</span></a></li>';
		echo '<li><a href="#tab-published">' . __('Published', 'test-method-workflow') . ' <span class="count">' . $published_count . '</span></a></li>';
		echo '</ul>';
		
		// Tab content - Your Drafts
		echo '<div id="tab-drafts" class="tab-content active">';
		$this->render_contributor_drafts_tab($user_id);
		echo '</div>';
		
		// Tab content - Under Review
		echo '<div id="tab-pending" class="tab-content">';
		$this->render_contributor_pending_tab($user_id);
		echo '</div>';
		
		// Tab content - Rejected
		echo '<div id="tab-rejected" class="tab-content">';
		$this->render_contributor_rejected_tab($user_id);
		echo '</div>';
		
		// Tab content - Published
		echo '<div id="tab-published" class="tab-content">';
		$this->render_contributor_published_tab($user_id);
		echo '</div>';
		
		echo '</div>'; // .dashboard-tabs
		
		// Progress tracker for pending methods
		if ($pending_count > 0) {
			echo '<div class="approval-progress-tracker">';
			echo '<h2>' . __('Approval Progress', 'test-method-workflow') . '</h2>';
			
			$pending_items = $this->get_user_test_methods($user_id, 'pending_review');
			$final_approval_items = $this->get_user_test_methods($user_id, 'pending_final_approval');
			$pending_items = array_merge($pending_items, $final_approval_items);
			
			foreach ($pending_items as $post) {
				$approvals = get_post_meta($post->ID, '_approvals', true);
				$approval_count = is_array($approvals) ? count($approvals) : 0;
				$workflow_status = get_post_meta($post->ID, '_workflow_status', true);
				$final_approval = $workflow_status === 'pending_final_approval';
				
				echo '<div class="approval-tracker-item">';
				echo '<div class="approval-tracker-title">';
				echo '<a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a>';
				echo '<span class="approval-date">Submitted: ' . get_the_date('M j, Y', $post->ID) . '</span>';
				echo '</div>';
				
				// Progress bar
				echo '<div class="approval-progress-bar">';
				echo '<div class="approval-progress-step ' . ($approval_count >= 0 ? 'completed' : '') . '">1. ' . __('Submitted', 'test-method-workflow') . '</div>';
				echo '<div class="approval-progress-step ' . ($approval_count >= 1 ? 'completed' : '') . '">2. ' . __('First Approval', 'test-method-workflow') . '</div>';
				echo '<div class="approval-progress-step ' . ($approval_count >= 2 ? 'completed' : '') . '">3. ' . __('Final Approval', 'test-method-workflow') . '</div>';
				echo '<div class="approval-progress-step">4. ' . __('Published', 'test-method-workflow') . '</div>';
				echo '</div>';
				
				// Current status
				echo '<div class="approval-status-display">';
				if ($approval_count == 0) {
					echo '<span class="status-message">' . __('Awaiting first review', 'test-method-workflow') . '</span>';
				} elseif ($approval_count == 1) {
					if ($final_approval) {
						echo '<span class="status-message">' . __('Awaiting final approval', 'test-method-workflow') . '</span>';
					} else {
						echo '<span class="status-message">' . __('First approval received', 'test-method-workflow') . '</span>';
					}
				} elseif ($approval_count >= 2) {
					echo '<span class="status-message status-complete">' . __('Fully approved, awaiting publishing', 'test-method-workflow') . '</span>';
				}
				echo '</div>';
				
				echo '</div>'; // .approval-tracker-item
			}
			
			echo '</div>'; // .approval-progress-tracker
		}
	}
	
	/**
	 * Render drafts tab for contributor
	 */
	private function render_contributor_drafts_tab($user_id) {
		$drafts = $this->get_user_test_methods($user_id, 'draft');
		
		if (!empty($drafts)) {
			echo '<table class="tm-dashboard-table">';
			echo '<thead><tr>';
			echo '<th>' . __('Title', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Version', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Last Modified', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Actions', 'test-method-workflow') . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';
			
			foreach ($drafts as $post) {
				$version = get_post_meta($post->ID, '_current_version_number', true);
				if (empty($version)) $version = '0.0';
				
				echo '<tr>';
				echo '<td class="title-cell"><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
				echo '<td>' . esc_html($version) . '</td>';
				echo '<td>' . get_the_modified_date('M j, Y @ g:i a', $post->ID) . '</td>';
				echo '<td class="actions-cell">';
				echo '<a href="' . get_edit_post_link($post->ID) . '" class="button button-primary">' . __('Edit', 'test-method-workflow') . '</a>';
				echo '</td>';
				echo '</tr>';
			}
			
			echo '</tbody></table>';
		} else {
			echo '<div class="empty-state">';
			echo '<div class="empty-state-icon"><span class="dashicons dashicons-edit"></span></div>';
			echo '<h3>' . __('No drafts yet', 'test-method-workflow') . '</h3>';
			echo '<p>' . __('Create a new test method to get started.', 'test-method-workflow') . '</p>';
			echo '<a href="' . admin_url('post-new.php?post_type=test_method') . '" class="button button-primary">' . __('Create New Test Method', 'test-method-workflow') . '</a>';
			echo '</div>';
		}
	}
	
	/**
	 * Render pending tab for contributor
	 */
	private function render_contributor_pending_tab($user_id) {
		$pending = $this->get_user_test_methods($user_id, 'pending_review');
		$final_approval = $this->get_user_test_methods($user_id, 'pending_final_approval');
		$approved = $this->get_user_test_methods($user_id, 'approved');
		
		// Combine all in-progress items
		$pending_items = array_merge($pending, $final_approval, $approved);
		
		if (!empty($pending_items)) {
			echo '<table class="tm-dashboard-table">';
			echo '<thead><tr>';
			echo '<th>' . __('Title', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Submitted', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Version', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Status', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Progress', 'test-method-workflow') . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';
			
			foreach ($pending_items as $post) {
				$version = get_post_meta($post->ID, '_current_version_number', true);
				if (empty($version)) $version = '0.1';
				
				$workflow_status = get_post_meta($post->ID, '_workflow_status', true);
				$approvals = get_post_meta($post->ID, '_approvals', true);
				$approval_count = is_array($approvals) ? count($approvals) : 0;
				
				// Determine status label and class
				$status_label = '';
				$status_class = '';
				
				if ($workflow_status === 'approved') {
					$status_label = __('Approved', 'test-method-workflow');
					$status_class = 'status-approved';
				} elseif ($workflow_status === 'pending_final_approval') {
					$status_label = __('Awaiting Final Approval', 'test-method-workflow');
					$status_class = 'status-final-approval';
				} else {
					$status_label = __('Pending Review', 'test-method-workflow');
					$status_class = 'status-pending';
				}
				
				// Calculate progress percentage
				$progress_percent = 0;
				if ($workflow_status === 'approved') {
					$progress_percent = 100;
				} elseif ($workflow_status === 'pending_final_approval' || $approval_count >= 1) {
					$progress_percent = 66;
				} elseif ($approval_count > 0) {
					$progress_percent = 33;
				}
				
				echo '<tr>';
				echo '<td class="title-cell"><a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
				echo '<td>' . get_the_date('M j, Y', $post->ID) . '</td>';
				echo '<td>' . esc_html($version) . '</td>';
				echo '<td><span class="status-badge ' . $status_class . '">' . $status_label . '</span></td>';
				echo '<td class="progress-cell">';
				echo '<div class="progress-bar-container">';
				echo '<div class="progress-bar" style="width: ' . $progress_percent . '%"></div>';
				echo '</div>';
				echo '<span class="progress-text">' . $approval_count . '/2 Approvals</span>';
				echo '</td>';
				echo '</tr>';
			}
			
			echo '</tbody></table>';
		} else {
			echo '<div class="empty-state">';
			echo '<div class="empty-state-icon"><span class="dashicons dashicons-clipboard"></span></div>';
			echo '<h3>' . __('No test methods under review', 'test-method-workflow') . '</h3>';
			echo '<p>' . __('Once you submit a test method for review, it will appear here.', 'test-method-workflow') . '</p>';
			echo '</div>';
		}
	}
	
	/**
	 * Render rejected tab for contributor
	 */
	private function render_contributor_rejected_tab($user_id) {
		$rejected = $this->get_user_test_methods($user_id, 'rejected');
		
		if (!empty($rejected)) {
			echo '<table class="tm-dashboard-table">';
			echo '<thead><tr>';
			echo '<th>' . __('Title', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Rejected By', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Rejection Date', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Comments', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Actions', 'test-method-workflow') . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';
			
			foreach ($rejected as $post) {
				$approvals = get_post_meta($post->ID, '_approvals', true);
				$rejection_comment = '';
				$rejected_by = '';
				$rejection_date = '';
				
				if (is_array($approvals)) {
					foreach ($approvals as $approval) {
						if ($approval['status'] === 'rejected') {
							$rejection_comment = isset($approval['comment']) ? $approval['comment'] : '';
							$user_info = get_userdata($approval['user_id']);
							$rejected_by = $user_info ? $user_info->display_name : '';
							$rejection_date = isset($approval['date']) ? date('M j, Y', $approval['date']) : '';
							break;
						}
					}
				}
				
				echo '<tr>';
				echo '<td class="title-cell"><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
				echo '<td>' . esc_html($rejected_by) . '</td>';
				echo '<td>' . esc_html($rejection_date) . '</td>';
				echo '<td class="comment-cell">' . esc_html($rejection_comment) . '</td>';
				echo '<td class="actions-cell">';
				echo '<a href="' . get_edit_post_link($post->ID) . '" class="button button-primary">' . __('Edit & Resubmit', 'test-method-workflow') . '</a>';
				echo '</td>';
				echo '</tr>';
			}
			
			echo '</tbody></table>';
		} else {
			echo '<div class="empty-state">';
			echo '<div class="empty-state-icon"><span class="dashicons dashicons-yes-alt"></span></div>';
			echo '<h3>' . __('No rejected test methods', 'test-method-workflow') . '</h3>';
			echo '<p>' . __('Great job! You don\'t have any rejected test methods.', 'test-method-workflow') . '</p>';
			echo '</div>';
		}
	}
	
	/**
	 * Render published tab for contributor
	 */
	private function render_contributor_published_tab($user_id) {
		$published = $this->get_user_test_methods($user_id, 'publish');
		
		if (!empty($published)) {
			echo '<table class="tm-dashboard-table">';
			echo '<thead><tr>';
			echo '<th>' . __('Title', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Version', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Published', 'test-method-workflow') . '</th>';
			echo '<th>' . __('Actions', 'test-method-workflow') . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';
			
			foreach ($published as $post) {
				$version = get_post_meta($post->ID, '_current_version_number', true);
				if (empty($version)) $version = '0.1';
				
				echo '<tr>';
				echo '<td class="title-cell"><a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
				echo '<td>' . esc_html($version) . '</td>';
				echo '<td>' . get_the_date('M j, Y', $post->ID) . '</td>';
				echo '<td class="actions-cell">';
				echo '<a href="' . get_permalink($post->ID) . '" class="button button-primary">' . __('View', 'test-method-workflow') . '</a> ';
				
				// Check if the post is locked
				$is_locked = get_post_meta($post->ID, '_is_locked', true);
				if ($is_locked) {
					echo '<a href="#" class="button create-revision-link" data-post-id="' . $post->ID . '" data-nonce="' . wp_create_nonce('test_method_revision_nonce') . '">' . 
					__('Create Revision', 'test-method-workflow') . '</a>';
				} else {
					echo '<a href="' . get_edit_post_link($post->ID) . '" class="button">' . __('Edit', 'test-method-workflow') . '</a>';
				}
				
				echo '</td>';
				echo '</tr>';
			}
			
			echo '</tbody></table>';
		} else {
			echo '<div class="empty-state">';
			echo '<div class="empty-state-icon"><span class="dashicons dashicons-media-document"></span></div>';
			echo '<h3>' . __('No published test methods', 'test-method-workflow') . '</h3>';
			echo '<p>' . __('You don\'t have any published test methods yet.', 'test-method-workflow') . '</p>';
			echo '</div>';
		}
	}
	
	/**
	 * Render enhanced status widget
	 */
	public function render_enhanced_status_widget() {
		$user = wp_get_current_user();
		$roles = (array) $user->roles;
		$user_id = get_current_user_id();
		
		echo '<div class="enhanced-status-widget">';
		
		if (in_array('tp_admin', $roles) || in_array('administrator', $roles)) {
			// Admin stats
			$pending_count = $this->get_test_method_count('pending_review');
			$approved_count = $this->get_test_method_count('approved');
			
			echo '<ul class="status-summary">';
			echo '<li class="status-item status-pending"><span class="status-count">' . $pending_count . '</span> ' . __('Pending Review', 'test-method-workflow') . '</li>';
			echo '<li class="status-item status-approved"><span class="status-count">' . $approved_count . '</span> ' . __('Ready to Publish', 'test-method-workflow') . '</li>';
			echo '</ul>';
			
			if ($approved_count > 0) {
				echo '<div class="action-needed">';
				echo '<p>' . sprintf(_n('%s test method is ready to publish.', '%s test methods are ready to publish.', $approved_count, 'test-method-workflow'), $approved_count) . '</p>';
				echo '<a href="' . admin_url('admin.php?page=test-method-dashboard') . '" class="button button-primary">' . __('Go to Dashboard', 'test-method-workflow') . '</a>';
				echo '</div>';
			}
		} elseif (in_array('tp_approver', $roles)) {
			// Approver stats
			$pending_count = $this->get_test_method_count('pending_review');
			$final_approval_count = $this->get_test_method_count('pending_final_approval');
			$my_approved_count = $this->get_my_approval_count('approved');
			
			echo '<ul class="status-summary">';
			echo '<li class="status-item status-pending"><span class="status-count">' . ($pending_count + $final_approval_count) . '</span> ' . __('Awaiting Review', 'test-method-workflow') . '</li>';
			echo '<li class="status-item status-approved"><span class="status-count">' . $my_approved_count . '</span> ' . __('You\'ve Approved', 'test-method-workflow') . '</li>';
			echo '</ul>';
			
			// Get methods that need this approver's review
			$pending_your_review = $this->get_pending_your_review();
			if (count($pending_your_review) > 0) {
				echo '<div class="action-needed">';
				echo '<p>' . sprintf(_n('%s test method is awaiting your review.', '%s test methods are awaiting your review.', count($pending_your_review), 'test-method-workflow'), count($pending_your_review)) . '</p>';
				echo '<a href="' . admin_url('admin.php?page=test-method-dashboard') . '" class="button button-primary">' . __('Go to Dashboard', 'test-method-workflow') . '</a>';
				echo '</div>';
			}
		} else {
			// Contributor stats
			$draft_count = $this->get_user_test_method_count($user_id, 'draft');
			$pending_count = $this->get_user_test_method_count($user_id, 'pending_review');
			$rejected_count = $this->get_user_test_method_count($user_id, 'rejected');
			
			echo '<ul class="status-summary">';
			echo '<li class="status-item status-draft"><span class="status-count">' . $draft_count . '</span> ' . __('Drafts', 'test-method-workflow') . '</li>';
			echo '<li class="status-item status-pending"><span class="status-count">' . $pending_count . '</span> ' . __('Pending Review', 'test-method-workflow') . '</li>';
			echo '<li class="status-item status-rejected"><span class="status-count">' . $rejected_count . '</span> ' . __('Rejected', 'test-method-workflow') . '</li>';
			echo '</ul>';
			
			if ($rejected_count > 0) {
				echo '<div class="action-needed">';
				echo '<p>' . sprintf(_n('You have %s rejected test method that needs attention.', 'You have %s rejected test methods that need attention.', $rejected_count, 'test-method-workflow'), $rejected_count) . '</p>';
				echo '<a href="' . admin_url('admin.php?page=test-method-dashboard') . '" class="button button-primary">' . __('Go to Dashboard', 'test-method-workflow') . '</a>';
				echo '</div>';
			}
		}
		
		echo '</div>'; // .enhanced-status-widget
	}
	
	/**
	 * Render enhanced reviews widget
	 */
	public function render_enhanced_reviews_widget() {
		// Get pending methods that this user hasn't reviewed
		$pending_your_review = $this->get_pending_your_review();
		
		echo '<div class="enhanced-reviews-widget">';
		
		if (!empty($pending_your_review)) {
			echo '<ul class="pending-reviews-list">';
			
			foreach ($pending_your_review as $post) {
				$workflow_status = get_post_meta($post->ID, '_workflow_status', true);
				$status_label = $workflow_status === 'pending_final_approval' ? 
					__('Needs Final Approval', 'test-method-workflow') : 
					__('Pending First Review', 'test-method-workflow');
				$status_class = $workflow_status === 'pending_final_approval' ? 'final-approval' : 'pending';
				
				echo '<li class="review-item ' . $status_class . '">';
				echo '<div class="review-title"><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></div>';
				echo '<div class="review-meta">';
				echo '<span class="review-author">' . get_the_author_meta('display_name', $post->post_author) . '</span>';
				echo '<span class="review-date">' . get_the_date('M j, Y', $post->ID) . '</span>';
				echo '<span class="review-status status-' . $status_class . '">' . $status_label . '</span>';
				echo '</div>';
				echo '<a href="' . get_edit_post_link($post->ID) . '" class="button button-small">' . __('Review', 'test-method-workflow') . '</a>';
				echo '</li>';
			}
			
			echo '</ul>';
			
			echo '<p class="view-all-link"><a href="' . admin_url('admin.php?page=test-method-dashboard') . '">' . __('View all pending reviews', 'test-method-workflow') . ' &rarr;</a></p>';
		} else {
			echo '<div class="empty-reviews">';
			echo '<p>' . __('No test methods currently need your review.', 'test-method-workflow') . '</p>';
			echo '</div>';
		}
		
		echo '</div>'; // .enhanced-reviews-widget
	}
	
	/**
	 * Get test methods that need this user's review
	 */
	private function get_pending_your_review() {
		$user_id = get_current_user_id();
		$pending_methods = $this->get_test_methods_by_status('pending_review');
		$final_approval_methods = $this->get_test_methods_by_status('pending_final_approval');
		
		// Combine and filter out already reviewed
		$review_methods = array_merge($pending_methods, $final_approval_methods);
		$filtered_methods = array();
		foreach ($review_methods as $post) {
			if (!$this->user_already_reviewed($post->ID)) {
				$filtered_methods[] = $post;
			}
		}
		
		return $filtered_methods;
	}
	
	/**
	 * Render stat box
	 */
	private function render_stat_box($type, $count, $label, $link = '') {
		$output = '<div class="stat-box ' . esc_attr($type) . '">';
		$output .= '<span class="stat-icon dashicons dashicons-' . $this->get_stat_icon($type) . '"></span>';
		$output .= '<h2>' . esc_html($count) . '</h2>';
		$output .= '<p>' . esc_html($label) . '</p>';
		
		if (!empty($link)) {
			$output .= '<a href="' . esc_url(admin_url($link)) . '" class="stat-link">' . __('View', 'test-method-workflow') . '</a>';
		}
		
		$output .= '</div>';
		
		return $output;
	}
	
	/**
	 * Get icon for stat box
	 */
	private function get_stat_icon($type) {
		switch ($type) {
			case 'draft':
				return 'edit';
			case 'pending':
				return 'visibility';
			case 'approved':
				return 'yes-alt';
			case 'rejected':
				return 'dismiss';
			case 'published':
				return 'media-document';
			default:
				return 'admin-customizer';
		}
	}
	
	/**
	 * Render dashboard help modal
	 */
	private function render_dashboard_help_modal() {
		// Modal HTML structure
		echo '<div id="dashboard-help-modal" class="dashboard-modal" style="display: none;">';
		echo '<div class="dashboard-modal-content">';
		echo '<span class="dashboard-modal-close">&times;</span>';
		echo '<h2>' . __('Test Method Dashboard Help', 'test-method-workflow') . '</h2>';
		
		echo '<div class="dashboard-modal-body">';
		echo '<h3>' . __('Workflow Overview', 'test-method-workflow') . '</h3>';
		echo '<p>' . __('Test Method posts follow this workflow:', 'test-method-workflow') . '</p>';
		echo '<ol>';
		echo '<li>' . __('Create a draft test method.', 'test-method-workflow') . '</li>';
		echo '<li>' . __('Submit for review when ready.', 'test-method-workflow') . '</li>';
		echo '<li>' . __('Two approvers must review and approve the content.', 'test-method-workflow') . '</li>';
		echo '<li>' . __('Once approved, an administrator can publish the test method.', 'test-method-workflow') . '</li>';
		echo '<li>' . __('Published test methods are locked for editing.', 'test-method-workflow') . '</li>';
		echo '<li>' . __('To make changes to a published test method, create a revision.', 'test-method-workflow') . '</li>';
		echo '<li>' . __('Revisions follow the same workflow before they can be published.', 'test-method-workflow') . '</li>';
		echo '</ol>';
		
		// Role-specific help
		$user = wp_get_current_user();
		$roles = (array) $user->roles;
		
		if (in_array('tp_admin', $roles) || in_array('administrator', $roles)) {
			echo '<h3>' . __('Administrator Features', 'test-method-workflow') . '</h3>';
			echo '<ul>';
			echo '<li>' . __('Monitor all test methods across the workflow.', 'test-method-workflow') . '</li>';
			echo '<li>' . __('Review and approve test methods.', 'test-method-workflow') . '</li>';
			echo '<li>' . __('Publish approved test methods.', 'test-method-workflow') . '</li>';
			echo '<li>' . __('Manage locked content and create or approve revisions.', 'test-method-workflow') . '</li>';
			echo '</ul>';
		} elseif (in_array('tp_approver', $roles)) {
			echo '<h3>' . __('Approver Features', 'test-method-workflow') . '</h3>';
			echo '<ul>';
			echo '<li>' . __('Review submitted test methods.', 'test-method-workflow') . '</li>';
			echo '<li>' . __('Approve or reject with comments.', 'test-method-workflow') . '</li>';
			echo '<li>' . __('Track your review history.', 'test-method-workflow') . '</li>';
			echo '<li>' . __('Create your own test methods.', 'test-method-workflow') . '</li>';
			echo '</ul>';
		} else {
			echo '<h3>' . __('Contributor Features', 'test-method-workflow') . '</h3>';
			echo '<ul>';
			echo '<li>' . __('Create and edit test methods.', 'test-method-workflow') . '</li>';
			echo '<li>' . __('Submit test methods for review.', 'test-method-workflow') . '</li>';
			echo '<li>' . __('Track approval progress.', 'test-method-workflow') . '</li>';
			echo '<li>' . __('Revise rejected test methods and resubmit.', 'test-method-workflow') . '</li>';
			echo '</ul>';
		}
		
		echo '</div>'; // .dashboard-modal-body
		
		echo '<div class="dashboard-modal-footer">';
		echo '<button class="button button-primary dashboard-modal-close-btn">' . __('Got it!', 'test-method-workflow') . '</button>';
		echo '</div>';
		
		echo '</div>'; // .dashboard-modal-content
		echo '</div>'; // #dashboard-help-modal
	}
	
	/**
	 * AJAX handler for refreshing dashboard widget
	 */
	public function ajax_refresh_dashboard_widget() {
		// Check nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'test_method_dashboard_nonce')) {
			wp_send_json_error('Invalid security token.');
			exit;
		}
		
		$widget_type = isset($_POST['widget_type']) ? sanitize_text_field($_POST['widget_type']) : '';
		
		ob_start();
		
		if ($widget_type === 'status') {
			$this->render_enhanced_status_widget();
		} elseif ($widget_type === 'reviews') {
			$this->render_enhanced_reviews_widget();
		}
		
		$widget_content = ob_get_clean();
		
		wp_send_json_success(array(
			'content' => $widget_content
		));
	}
	
	/**
	 * AJAX handler for dashboard actions
	 */
	public function ajax_handle_dashboard_action() {
		// Check nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'test_method_dashboard_nonce')) {
			wp_send_json_error('Invalid security token.');
			exit;
		}
		
		$action = isset($_POST['tm_action']) ? sanitize_text_field($_POST['tm_action']) : '';
		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		
		if (!$post_id) {
			wp_send_json_error('Invalid post ID.');
			exit;
		}
		
		switch ($action) {
			case 'approve':
				// This would need integration with the workflow approval process
				wp_send_json_success(array(
					'message' => __('Please use the full review page to approve test methods.', 'test-method-workflow'),
					'redirect' => get_edit_post_link($post_id, '')
				));
				break;
				
			case 'reject':
				// This would need integration with the workflow rejection process
				wp_send_json_success(array(
					'message' => __('Please use the full review page to reject test methods.', 'test-method-workflow'),
					'redirect' => get_edit_post_link($post_id, '')
				));
				break;
				
			case 'publish':
				// This would need integration with the workflow publishing process
				wp_send_json_success(array(
					'message' => __('Please use the full editor to publish approved test methods.', 'test-method-workflow'),
					'redirect' => get_edit_post_link($post_id, '')
				));
				break;
				
			default:
				wp_send_json_error('Invalid action.');
				break;
		}
	}
	
	/**
	 * Get all test method counts
	 * 
	 * @return array Counts for each status
	 */
	private function get_test_method_counts() {
		global $wpdb;
		
		// Get all status counts in a single query for better performance
		$query = $wpdb->prepare(
			"SELECT meta.meta_value AS status, COUNT(*) AS count
			FROM {$wpdb->posts} AS posts
			LEFT JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
			WHERE posts.post_type = %s
			AND meta.meta_key = '_workflow_status'
			GROUP BY meta.meta_value",
			'test_method'
		);
		
		$results = $wpdb->get_results($query);
		
		// Also get published count
		$published_query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			WHERE post_type = %s AND post_status = 'publish'",
			'test_method'
		);
		
		$published_count = $wpdb->get_var($published_query);
		
		// Format the results
		$counts = array(
			'draft' => 0,
			'pending_review' => 0,
			'pending_final_approval' => 0,
			'approved' => 0,
			'rejected' => 0,
			'published' => intval($published_count)
		);
		
		if ($results) {
			foreach ($results as $result) {
				if (isset($counts[$result->status])) {
					$counts[$result->status] = intval($result->count);
				}
			}
		}
		
		return $counts;
	}
	
	/**
	 * Get test method count by status
	 * 
	 * @param string $status Workflow status
	 * @return int Count of test methods with this status
	 */
	private function get_test_method_count($status) {
		global $wpdb;
		
		$query = $wpdb->prepare(
			"SELECT COUNT(*) 
			FROM {$wpdb->posts} AS posts
			INNER JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
			WHERE posts.post_type = %s
			AND meta.meta_key = '_workflow_status'
			AND meta.meta_value = %s",
			'test_method',
			$status
		);
		
		return intval($wpdb->get_var($query));
	}
	
	/**
	 * Get user test method count by status
	 * 
	 * @param int $user_id User ID
	 * @param string $status Workflow status
	 * @return int Count of user's test methods with this status
	 */
	private function get_user_test_method_count($user_id, $status) {
		global $wpdb;
		
		if ($status === 'publish') {
			$query = $wpdb->prepare(
				"SELECT COUNT(*) 
				FROM {$wpdb->posts} 
				WHERE post_type = %s
				AND post_status = 'publish'
				AND post_author = %d",
				'test_method',
				$user_id
			);
			
			return intval($wpdb->get_var($query));
		}
		
		$query = $wpdb->prepare(
			"SELECT COUNT(*) 
			FROM {$wpdb->posts} AS posts
			INNER JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
			WHERE posts.post_type = %s
			AND posts.post_author = %d
			AND meta.meta_key = '_workflow_status'
			AND meta.meta_value = %s",
			'test_method',
			$user_id,
			$status
		);
		
		return intval($wpdb->get_var($query));
	}
	
	/**
	 * Get test methods by status
	 * 
	 * @param string $status Workflow status
	 * @param int $limit Maximum number of posts to return
	 * @return array Array of post objects
	 */
	private function get_test_methods_by_status($status, $limit = -1) {
		if ($status === 'publish') {
			$args = array(
				'post_type' => 'test_method',
				'post_status' => 'publish',
				'posts_per_page' => $limit,
				'orderby' => 'date',
				'order' => 'DESC'
			);
			
			$query = new WP_Query($args);
			return $query->posts;
		}
		
		$args = array(
			'post_type' => 'test_method',
			'post_status' => 'any',
			'posts_per_page' => $limit,
			'orderby' => 'modified',
			'order' => 'DESC',
			'meta_query' => array(
				array(
					'key' => '_workflow_status',
					'value' => $status,
					'compare' => '='
				)
			)
		);
		
		$query = new WP_Query($args);
		return $query->posts;
	}
	
	/**
	 * Get user test methods by status
	 * 
	 * @param int $user_id User ID
	 * @param string $status Workflow status
	 * @param int $limit Maximum number of posts to return
	 * @return array Array of post objects
	 */
	private function get_user_test_methods($user_id, $status, $limit = -1) {
		if ($status === 'publish') {
			$args = array(
				'post_type' => 'test_method',
				'post_status' => 'publish',
				'author' => $user_id,
				'posts_per_page' => $limit,
				'orderby' => 'date',
				'order' => 'DESC'
			);
			
			$query = new WP_Query($args);
			return $query->posts;
		}
		
		$args = array(
			'post_type' => 'test_method',
			'post_status' => 'any',
			'author' => $user_id,
			'posts_per_page' => $limit,
			'orderby' => 'modified',
			'order' => 'DESC',
			'meta_query' => array(
				array(
					'key' => '_workflow_status',
					'value' => $status,
					'compare' => '='
				)
			)
		);
		
		$query = new WP_Query($args);
		return $query->posts;
	}
	
	/**
	 * Get count of approvals by current user
	 * 
	 * @param string $status Approval status (approved or rejected)
	 * @return int Count of approvals/rejections
	 */
	private function get_my_approval_count($status) {
		global $wpdb;
		$user_id = get_current_user_id();
		
		$count = 0;
		$meta_rows = $wpdb->get_results(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_approvals'"
		);
		
		foreach ($meta_rows as $row) {
			$approvals = maybe_unserialize($row->meta_value);
			
			if (is_array($approvals)) {
				foreach ($approvals as $approval) {
					if (isset($approval['user_id']) && $approval['user_id'] == $user_id && 
						isset($approval['status']) && $approval['status'] == $status) {
						$count++;
						break;
					}
				}
			}
		}
		
		return $count;
	}
	
	/**
	 * Check if user already reviewed a post
	 * 
	 * @param int $post_id Post ID
	 * @return bool Whether the current user has already reviewed this post
	 */
	private function user_already_reviewed($post_id) {
		$user_id = get_current_user_id();
		$approvals = get_post_meta($post_id, '_approvals', true);
		
		if (is_array($approvals)) {
			foreach ($approvals as $approval) {
				if (isset($approval['user_id']) && $approval['user_id'] == $user_id) {
					return true;
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Get recent reviewed methods by current user
	 * 
	 * @param int $limit Maximum number of reviews to return
	 * @return array Array of review data
	 */
	private function get_your_reviewed_methods($limit = -1) {
		global $wpdb;
		$user_id = get_current_user_id();
		
		$reviews = array();
		$meta_rows = $wpdb->get_results(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_approvals'"
		);
		
		foreach ($meta_rows as $row) {
			$approvals = maybe_unserialize($row->meta_value);
			
			if (is_array($approvals)) {
				foreach ($approvals as $approval) {
					if (isset($approval['user_id']) && $approval['user_id'] == $user_id &&
						isset($approval['status']) && in_array($approval['status'], array('approved', 'rejected'))) {
						$reviews[] = array(
							'post_id' => $row->post_id,
							'status' => $approval['status'],
							'date' => $approval['date'],
							'comment' => isset($approval['comment']) ? $approval['comment'] : ''
						);
						break;
					}
				}
			}
		}
		
		// Sort by date, newest first
		usort($reviews, function($a, $b) {
			return $b['date'] - $a['date'];
		});
		
		// Limit results if needed
		if ($limit > 0 && count($reviews) > $limit) {
			$reviews = array_slice($reviews, 0, $limit);
		}
		
		return $reviews;
	}
}