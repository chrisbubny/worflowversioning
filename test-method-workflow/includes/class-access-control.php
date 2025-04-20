<?php
/**
 * Access Control Management
 *
 * @package TestMethodWorkflow
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Test Method access control class
 */
class TestMethod_AccessControl {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Modify Admin Menu for roles
		add_action('admin_menu', array($this, 'modify_admin_menu'), 999);
		
		// Add dashboard widgets
		add_action('wp_dashboard_setup', array($this, 'add_dashboard_widgets'));
		
		// Add custom dashboard page
		add_action('admin_menu', array($this, 'add_custom_dashboard'));
		
		// Filter test method list in admin
		add_action('pre_get_posts', array($this, 'filter_test_method_list'));
		
		// Restrict single post access
		add_action('template_redirect', array($this, 'restrict_single_post_access'));
		
		// Add test method filter for REST API
		add_filter('rest_test_method_query', array($this, 'filter_rest_api_queries'), 10, 2);
	}
	
	/**
	 * Modify Admin Menu for roles
	 */
	public function modify_admin_menu() {
		global $menu, $submenu;
		
		$user = wp_get_current_user();
		$roles = (array) $user->roles;
		
		// For TP Contributor, TP Approver and TP Admin, customize the menu
		if (array_intersect($roles, array('tp_contributor', 'tp_approver', 'tp_admin')) && !in_array('administrator', $roles)) {
			// Remove Posts menu
			remove_menu_page('edit.php');
			
			// Remove Comments menu
			remove_menu_page('edit-comments.php');
			
			// Remove appearance menu for non-admins
			remove_menu_page('themes.php');
			
			// Remove plugins menu
			remove_menu_page('plugins.php');
			
			// Remove tools menu
			remove_menu_page('tools.php');
			
			// Remove Settings menu
			remove_menu_page('options-general.php');
			
			// For TP Contributor, also remove users
			if (in_array('tp_contributor', $roles) && !array_intersect($roles, array('tp_approver', 'tp_admin', 'administrator'))) {
				remove_menu_page('users.php');
			}
		}
	}
	
	/**
	 * Add dashboard widgets
	 */
	public function add_dashboard_widgets() {
		$user = wp_get_current_user();
		$roles = (array) $user->roles;
		
		// For TP roles, add custom widgets
		if (array_intersect($roles, array('tp_contributor', 'tp_approver', 'tp_admin'))) {
			wp_add_dashboard_widget(
				'test_method_status_widget',
				__('Test Method Status', 'test-method-workflow'),
				array($this, 'render_status_widget')
			);
			
			// For approvers and admins, add pending reviews widget
			if (array_intersect($roles, array('tp_approver', 'tp_admin', 'administrator'))) {
				wp_add_dashboard_widget(
					'test_method_reviews_widget',
					__('Pending Reviews', 'test-method-workflow'),
					array($this, 'render_reviews_widget')
				);
			}
		}
	}
	
	/**
	 * Add custom dashboard page
	 */
	public function add_custom_dashboard() {
		$user = wp_get_current_user();
		$roles = (array) $user->roles;
		
		if (array_intersect($roles, array('tp_contributor', 'tp_approver', 'tp_admin'))) {
			add_menu_page(
				__('Test Method Dashboard', 'test-method-workflow'),
				__('Test Method Dashboard', 'test-method-workflow'),
				'read',
				'test-method-dashboard',
				array($this, 'render_custom_dashboard'),
				'dashicons-clipboard',
				2
			);
		}
	}
	
	/**
	 * Filter test method list in admin
	 */
	public function filter_test_method_list($query) {
	global $pagenow, $post_type;
	
	// Only on test_method list screen
	if (!is_admin() || $pagenow !== 'edit.php' || $post_type !== 'test_method' || !$query->is_main_query()) {
		return;
	}
		

	}
	
	/**
	 * Restrict single post access
	 */
	public function restrict_single_post_access() {
		if (!is_singular('test_method')) {
			return;
		}
		
		// Get current post
		global $post;
		
		// Check if user is logged in
		if (!is_user_logged_in()) {
			auth_redirect();
			exit;
		}
		
		// Get post status
		$post_status = $post->post_status;
		$is_published = ($post_status === 'publish');
		
		// If post is not published, check permissions
		if (!$is_published) {
			// Check if user has permission to view
			$can_view = current_user_can('read_test_method', $post->ID);
			
			if (!$can_view) {
				wp_redirect(home_url());
				exit;
			}
		}
	}
	
	/**
	 * Filter REST API queries
	 */
	public function filter_rest_api_queries($args, $request) {
	$user = wp_get_current_user();
	$roles = (array) $user->roles;
		
	
		
		return $args;
	}
	
	/**
	 * Render status widget
	 */
	public function render_status_widget() {
		$user = wp_get_current_user();
		$roles = (array) $user->roles;
		
		if (in_array('tp_admin', $roles) || in_array('administrator', $roles)) {
			// Admin stats
			$pending_count = $this->get_test_method_count('pending_review');
			$approved_count = $this->get_test_method_count('approved');
			
			echo '<div class="test-method-widget-stats">';
			echo '<p><strong>' . __('Pending Review:', 'test-method-workflow') . '</strong> ' . $pending_count . '</p>';
			echo '<p><strong>' . __('Approved & Ready:', 'test-method-workflow') . '</strong> ' . $approved_count . '</p>';
			echo '</div>';
			
			echo '<p><a href="' . admin_url('admin.php?page=test-method-dashboard') . '" class="button">' . 
				 __('View Dashboard', 'test-method-workflow') . '</a></p>';
		} elseif (in_array('tp_approver', $roles)) {
			// Approver stats
			$pending_count = $this->get_test_method_count('pending_review');
			$my_approved_count = $this->get_my_approval_count('approved');
			
			echo '<div class="test-method-widget-stats">';
			echo '<p><strong>' . __('Pending Review:', 'test-method-workflow') . '</strong> ' . $pending_count . '</p>';
			echo '<p><strong>' . __('You\'ve Approved:', 'test-method-workflow') . '</strong> ' . $my_approved_count . '</p>';
			echo '</div>';
			
			echo '<p><a href="' . admin_url('admin.php?page=test-method-dashboard') . '" class="button">' . 
				 __('View Dashboard', 'test-method-workflow') . '</a></p>';
		} else {
			// Contributor stats
			$user_id = get_current_user_id();
			$pending_count = $this->get_user_test_method_count($user_id, 'pending_review');
			$rejected_count = $this->get_user_test_method_count($user_id, 'rejected');
			
			echo '<div class="test-method-widget-stats">';
			echo '<p><strong>' . __('Your Pending Reviews:', 'test-method-workflow') . '</strong> ' . $pending_count . '</p>';
			echo '<p><strong>' . __('Rejected Methods:', 'test-method-workflow') . '</strong> ' . $rejected_count . '</p>';
			echo '</div>';
			
			echo '<p><a href="' . admin_url('post-new.php?post_type=test_method') . '" class="button">' . 
				 __('Create New Test Method', 'test-method-workflow') . '</a></p>';
		}
	}
	
	/**
	 * Render reviews widget
	 */
	public function render_reviews_widget() {
		$pending_methods = $this->get_test_methods_by_status('pending_review', 5);
		
		if (!empty($pending_methods)) {
			echo '<ul class="pending-reviews-list">';
			
			foreach ($pending_methods as $post) {
				// Skip if user already reviewed
				if ($this->user_already_reviewed($post->ID)) {
					continue;
				}
				
				echo '<li>';
				echo '<a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a>';
				echo ' ' . __('by', 'test-method-workflow') . ' ' . get_the_author_meta('display_name', $post->post_author);
				echo '</li>';
			}
			
			echo '</ul>';
			
			// Add view all link
			echo '<p><a href="' . admin_url('edit.php?post_type=test_method&workflow_status=pending_review') . '">' . 
				 __('View All Pending Reviews', 'test-method-workflow') . '</a></p>';
		} else {
			echo '<p>' . __('No test methods currently need your review.', 'test-method-workflow') . '</p>';
		}
	}
	
	/**
	 * Render custom dashboard
	 */
	public function render_custom_dashboard() {
		$user = wp_get_current_user();
		$roles = (array) $user->roles;
		
		echo '<div class="wrap test-method-dashboard">';
		echo '<h1>' . __('Test Method Dashboard', 'test-method-workflow') . '</h1>';
		
		// Different dashboard based on role
		if (in_array('tp_admin', $roles) || in_array('administrator', $roles)) {
			$this->render_admin_dashboard();
		} elseif (in_array('tp_approver', $roles)) {
			$this->render_approver_dashboard();
		} else {
			$this->render_contributor_dashboard();
		}
		
		echo '</div>';
	}
	
	/**
	 * Render admin dashboard
	 */
	private function render_admin_dashboard() {
		// Get counts
		$draft_count = $this->get_test_method_count('draft');
		$pending_count = $this->get_test_method_count('pending_review');
		$approved_count = $this->get_test_method_count('approved');
		$published_count = $this->get_test_method_count('publish');
		
		echo '<div class="test-method-stats">';
		echo '<div class="stat-box draft"><h2>' . $draft_count . '</h2><p>' . __('Drafts', 'test-method-workflow') . '</p></div>';
		echo '<div class="stat-box pending"><h2>' . $pending_count . '</h2><p>' . __('Pending Review', 'test-method-workflow') . '</p></div>';
		echo '<div class="stat-box approved"><h2>' . $approved_count . '</h2><p>' . __('Approved', 'test-method-workflow') . '</p></div>';
		echo '<div class="stat-box published"><h2>' . $published_count . '</h2><p>' . __('Published', 'test-method-workflow') . '</p></div>';
		echo '</div>';
		
		// Show posts needing action
		echo '<h2>' . __('Test Methods Needing Action', 'test-method-workflow') . '</h2>';
		
		// Approved methods ready for publishing
		$approved_methods = $this->get_test_methods_by_status('approved');
		
		if (!empty($approved_methods)) {
			echo '<h3>' . __('Ready for Publishing', 'test-method-workflow') . '</h3>';
			echo '<table class="widefat">';
			echo '<thead><tr><th>' . __('Title', 'test-method-workflow') . '</th><th>' . __('Author', 'test-method-workflow') . '</th><th>' . __('Date', 'test-method-workflow') . '</th><th>' . __('Actions', 'test-method-workflow') . '</th></tr></thead>';
			echo '<tbody>';
			
			foreach ($approved_methods as $post) {
				$author = get_userdata($post->post_author);
				echo '<tr>';
				echo '<td><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
				echo '<td>' . esc_html($author->display_name) . '</td>';
				echo '<td>' . get_the_date('', $post->ID) . '</td>';
				echo '<td><a href="' . get_edit_post_link($post->ID) . '" class="button button-primary">' . __('Review & Publish', 'test-method-workflow') . '</a></td>';
				echo '</tr>';
			}
			
			echo '</tbody></table>';
		} else {
			echo '<p>' . __('No test methods are currently ready for publishing.', 'test-method-workflow') . '</p>';
		}
		
		// Methods needing review
		$pending_methods = $this->get_test_methods_by_status('pending_review');
		
		if (!empty($pending_methods)) {
			echo '<h3>' . __('Pending Review', 'test-method-workflow') . '</h3>';
			echo '<table class="widefat">';
			echo '<thead><tr><th>' . __('Title', 'test-method-workflow') . '</th><th>' . __('Author', 'test-method-workflow') . '</th><th>' . __('Date', 'test-method-workflow') . '</th><th>' . __('Actions', 'test-method-workflow') . '</th></tr></thead>';
			echo '<tbody>';
			
			foreach ($pending_methods as $post) {
				$author = get_userdata($post->post_author);
				echo '<tr>';
				echo '<td><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
							echo '<td>' . esc_html($author->display_name) . '</td>';
							echo '<td>' . get_the_date('', $post->ID) . '</td>';
							echo '<td><a href="' . get_edit_post_link($post->ID) . '" class="button">' . __('Review', 'test-method-workflow') . '</a></td>';
							echo '</tr>';
						}
						
						echo '</tbody></table>';
					}
					
					// Recently published methods
					$published_methods = $this->get_test_methods_by_status('publish', 5);
					
					if (!empty($published_methods)) {
						echo '<h3>' . __('Recently Published', 'test-method-workflow') . '</h3>';
						echo '<table class="widefat">';
						echo '<thead><tr><th>' . __('Title', 'test-method-workflow') . '</th><th>' . __('Author', 'test-method-workflow') . '</th><th>' . __('Date', 'test-method-workflow') . '</th><th>' . __('Actions', 'test-method-workflow') . '</th></tr></thead>';
						echo '<tbody>';
						
						foreach ($published_methods as $post) {
							$author = get_userdata($post->post_author);
							echo '<tr>';
							echo '<td><a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
							echo '<td>' . esc_html($author->display_name) . '</td>';
							echo '<td>' . get_the_date('', $post->ID) . '</td>';
							echo '<td>';
							echo '<a href="' . get_permalink($post->ID) . '" class="button">' . __('View', 'test-method-workflow') . '</a> ';
							echo '<a href="' . get_edit_post_link($post->ID) . '" class="button">' . __('Edit', 'test-method-workflow') . '</a>';
							echo '</td>';
							echo '</tr>';
						}
						
						echo '</tbody></table>';
					}
				}
				
				/**
				 * Render approver dashboard
				 */
				private function render_approver_dashboard() {
					// Get counts
					$pending_count = $this->get_test_method_count('pending_review');
					$my_approved_count = $this->get_my_approval_count('approved');
					$my_rejected_count = $this->get_my_approval_count('rejected');
					
					echo '<div class="test-method-stats">';
					echo '<div class="stat-box pending"><h2>' . $pending_count . '</h2><p>' . __('Pending Review', 'test-method-workflow') . '</p></div>';
					echo '<div class="stat-box approved"><h2>' . $my_approved_count . '</h2><p>' . __('You Approved', 'test-method-workflow') . '</p></div>';
					echo '<div class="stat-box rejected"><h2>' . $my_rejected_count . '</h2><p>' . __('You Rejected', 'test-method-workflow') . '</p></div>';
					echo '</div>';
					
					// Show methods needing review
					echo '<h2>' . __('Test Methods Needing Review', 'test-method-workflow') . '</h2>';
					
					$pending_methods = $this->get_test_methods_by_status('pending_review');
					
					if (!empty($pending_methods)) {
						echo '<table class="widefat">';
						echo '<thead><tr><th>' . __('Title', 'test-method-workflow') . '</th><th>' . __('Author', 'test-method-workflow') . '</th><th>' . __('Date', 'test-method-workflow') . '</th><th>' . __('Actions', 'test-method-workflow') . '</th></tr></thead>';
						echo '<tbody>';
						
						foreach ($pending_methods as $post) {
							// Skip if user already reviewed
							if ($this->user_already_reviewed($post->ID)) {
								continue;
							}
							
							$author = get_userdata($post->post_author);
							echo '<tr>';
							echo '<td><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
							echo '<td>' . esc_html($author->display_name) . '</td>';
							echo '<td>' . get_the_date('', $post->ID) . '</td>';
							echo '<td><a href="' . get_edit_post_link($post->ID) . '" class="button button-primary">' . __('Review', 'test-method-workflow') . '</a></td>';
							echo '</tr>';
						}
						
						echo '</tbody></table>';
					} else {
						echo '<p>' . __('No test methods currently need your review.', 'test-method-workflow') . '</p>';
					}
					
					// Show methods you've reviewed
					echo '<h2>' . __('Your Recent Reviews', 'test-method-workflow') . '</h2>';
					
					$your_reviews = $this->get_your_reviewed_methods(5);
					
					if (!empty($your_reviews)) {
						echo '<table class="widefat">';
						echo '<thead><tr><th>' . __('Title', 'test-method-workflow') . '</th><th>' . __('Author', 'test-method-workflow') . '</th><th>' . __('Your Decision', 'test-method-workflow') . '</th><th>' . __('Date', 'test-method-workflow') . '</th></tr></thead>';
						echo '<tbody>';
						
						foreach ($your_reviews as $review) {
							$post = get_post($review['post_id']);
							if (!$post) continue;
							
							$author = get_userdata($post->post_author);
							$status_class = $review['status'] === 'approved' ? 'status-approved' : 'status-rejected';
							
							echo '<tr>';
							echo '<td><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
							echo '<td>' . esc_html($author->display_name) . '</td>';
							echo '<td><span class="' . $status_class . '">' . ucfirst($review['status']) . '</span></td>';
							echo '<td>' . date_i18n(get_option('date_format'), $review['date']) . '</td>';
							echo '</tr>';
						}
						
						echo '</tbody></table>';
					} else {
						echo '<p>' . __('You haven\'t reviewed any test methods yet.', 'test-method-workflow') . '</p>';
					}
				}
				
				/**
				 * Render contributor dashboard
				 */
				private function render_contributor_dashboard() {
					$user_id = get_current_user_id();
					
					// Get counts
					$draft_count = $this->get_user_test_method_count($user_id, 'draft');
					$pending_count = $this->get_user_test_method_count($user_id, 'pending_review');
					$rejected_count = $this->get_user_test_method_count($user_id, 'rejected');
					$published_count = $this->get_user_test_method_count($user_id, 'publish');
					
					echo '<div class="test-method-stats">';
					echo '<div class="stat-box draft"><h2>' . $draft_count . '</h2><p>' . __('Drafts', 'test-method-workflow') . '</p></div>';
					echo '<div class="stat-box pending"><h2>' . $pending_count . '</h2><p>' . __('Pending Review', 'test-method-workflow') . '</p></div>';
					echo '<div class="stat-box rejected"><h2>' . $rejected_count . '</h2><p>' . __('Rejected', 'test-method-workflow') . '</p></div>';
					echo '<div class="stat-box published"><h2>' . $published_count . '</h2><p>' . __('Published', 'test-method-workflow') . '</p></div>';
					echo '</div>';
					
					echo '<p><a href="' . admin_url('post-new.php?post_type=test_method') . '" class="button button-primary">' . __('Create New Test Method', 'test-method-workflow') . '</a></p>';
					
					// Show your drafts
					if ($draft_count > 0) {
						echo '<h3>' . __('Your Drafts', 'test-method-workflow') . '</h3>';
						
						$drafts = $this->get_user_test_methods($user_id, 'draft');
						
						echo '<table class="widefat">';
						echo '<thead><tr><th>' . __('Title', 'test-method-workflow') . '</th><th>' . __('Last Modified', 'test-method-workflow') . '</th><th>' . __('Actions', 'test-method-workflow') . '</th></tr></thead>';
						echo '<tbody>';
						
						foreach ($drafts as $post) {
							echo '<tr>';
							echo '<td><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
							echo '<td>' . get_the_modified_date('', $post) . '</td>';
							echo '<td>';
							echo '<a href="' . get_edit_post_link($post->ID) . '" class="button">' . __('Edit', 'test-method-workflow') . '</a> ';
							echo '</td>';
							echo '</tr>';
						}
						
						echo '</tbody></table>';
					}
					
					// Show your rejected test methods
					if ($rejected_count > 0) {
						echo '<h3>' . __('Rejected Test Methods', 'test-method-workflow') . '</h3>';
						
						$rejected = $this->get_user_test_methods($user_id, 'rejected');
						
						echo '<table class="widefat">';
						echo '<thead><tr><th>' . __('Title', 'test-method-workflow') . '</th><th>' . __('Rejected By', 'test-method-workflow') . '</th><th>' . __('Comments', 'test-method-workflow') . '</th><th>' . __('Actions', 'test-method-workflow') . '</th></tr></thead>';
						echo '<tbody>';
						
						foreach ($rejected as $post) {
							$approvals = get_post_meta($post->ID, '_approvals', true);
							$rejection_comment = '';
							$rejected_by = '';
							
							if (is_array($approvals)) {
								foreach ($approvals as $approval) {
									if ($approval['status'] === 'rejected') {
										$rejection_comment = isset($approval['comment']) ? $approval['comment'] : '';
										$user_info = get_userdata($approval['user_id']);
										$rejected_by = $user_info ? $user_info->display_name : '';
										break;
									}
								}
							}
							
							echo '<tr>';
							echo '<td><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
							echo '<td>' . esc_html($rejected_by) . '</td>';
							echo '<td>' . esc_html($rejection_comment) . '</td>';
							echo '<td>';
							echo '<a href="' . get_edit_post_link($post->ID) . '" class="button">' . __('Edit & Resubmit', 'test-method-workflow') . '</a>';
							echo '</td>';
							echo '</tr>';
						}
						
						echo '</tbody></table>';
					}
					
					// Show your pending methods
					if ($pending_count > 0) {
						echo '<h3>' . __('Pending Review', 'test-method-workflow') . '</h3>';
						
						$pending = $this->get_user_test_methods($user_id, 'pending_review');
						
						echo '<table class="widefat">';
						echo '<thead><tr><th>' . __('Title', 'test-method-workflow') . '</th><th>' . __('Submitted', 'test-method-workflow') . '</th><th>' . __('Status', 'test-method-workflow') . '</th></tr></thead>';
						echo '<tbody>';
						
						foreach ($pending as $post) {
							$approvals = get_post_meta($post->ID, '_approvals', true);
							$approval_count = is_array($approvals) ? count($approvals) : 0;
							
							echo '<tr>';
							echo '<td><a href="' . get_permalink($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
							echo '<td>' . get_the_date('', $post->ID) . '</td>';
							echo '<td>' . sprintf(__('%d of 2 approvals', 'test-method-workflow'), $approval_count) . '</td>';
							echo '</tr>';
						}
						
						echo '</tbody></table>';
					}
				}
				
				/**
				 * Get test method count by status
				 */
				private function get_test_method_count($status) {
					$args = array(
						'post_type' => 'test_method',
						'post_status' => 'any',
						'posts_per_page' => -1,
						'fields' => 'ids',
						'meta_query' => array(
							array(
								'key' => '_workflow_status',
								'value' => $status,
								'compare' => '='
							)
						)
					);
					
					$query = new WP_Query($args);
					return $query->found_posts;
				}
				
				/**
				 * Get user test method count by status
				 */
				private function get_user_test_method_count($user_id, $status) {
					$args = array(
						'post_type' => 'test_method',
						'post_status' => 'any',
						'author' => $user_id,
						'posts_per_page' => -1,
						'fields' => 'ids',
						'meta_query' => array(
							array(
								'key' => '_workflow_status',
								'value' => $status,
								'compare' => '='
							)
						)
					);
					
					$query = new WP_Query($args);
					return $query->found_posts;
				}
				
				/**
				 * Get test methods by status
				 */
				private function get_test_methods_by_status($status, $limit = -1) {
					$args = array(
						'post_type' => 'test_method',
						'post_status' => 'any',
						'posts_per_page' => $limit,
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
				 */
				private function get_user_test_methods($user_id, $status) {
					$args = array(
						'post_type' => 'test_method',
						'post_status' => 'any',
						'author' => $user_id,
						'posts_per_page' => -1,
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
				 * Get my approval count
				 */
				private function get_my_approval_count($status) {
					global $wpdb;
					$user_id = get_current_user_id();
					
					$count = 0;
					$posts = $wpdb->get_results("
						SELECT post_id, meta_value 
						FROM {$wpdb->postmeta} 
						WHERE meta_key = '_approvals'
					");
					
					foreach ($posts as $post) {
						$approvals = maybe_unserialize($post->meta_value);
						
						if (is_array($approvals)) {
							foreach ($approvals as $approval) {
								if (isset($approval['user_id']) && $approval['user_id'] == $user_id && isset($approval['status']) && $approval['status'] == $status) {
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
				 * Get your reviewed methods
				 */
				private function get_your_reviewed_methods($limit = -1) {
					global $wpdb;
					$user_id = get_current_user_id();
					
					$reviews = array();
					$posts = $wpdb->get_results("
						SELECT post_id, meta_value 
						FROM {$wpdb->postmeta} 
						WHERE meta_key = '_approvals'
					");
					
					foreach ($posts as $post) {
						$approvals = maybe_unserialize($post->meta_value);
						
						if (is_array($approvals)) {
							foreach ($approvals as $approval) {
								if (isset($approval['user_id']) && $approval['user_id'] == $user_id) {
									$reviews[] = array(
										'post_id' => $post->post_id,
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