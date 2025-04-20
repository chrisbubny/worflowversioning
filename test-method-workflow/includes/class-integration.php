<?php
/**
 * Test Method Dashboard Integration
 *
 * This file integrates the enhanced dashboard into the Test Method Workflow plugin.
 * 
 * @package TestMethodWorkflow
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Test Method Integration class
 */
class TestMethod_Integration {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Load enhanced dashboard
		$this->load_enhanced_dashboard();
		
		// Add integration hooks
		$this->add_integration_hooks();
	}
	
	/**
	 * Load enhanced dashboard
	 */
	private function load_enhanced_dashboard() {
		require_once dirname(__FILE__) . '/class-enhanced-dashboard.php';
		new TestMethod_EnhancedDashboard();
	}
	
	/**
	 * Add integration hooks
	 */
	private function add_integration_hooks() {
		// Add version and status information to admin columns
		add_filter('manage_test_method_posts_columns', array($this, 'add_workflow_status_column'));
		add_action('manage_test_method_posts_custom_column', array($this, 'display_workflow_status_column'), 10, 2);
		
		// Add improved filters to admin list
		add_action('restrict_manage_posts', array($this, 'add_improved_filters'));
		
		// Add dashboard link to admin bar
		add_action('admin_bar_menu', array($this, 'add_dashboard_link_to_admin_bar'), 999);
		
		// Improve notification display
		add_action('admin_notices', array($this, 'improved_workflow_notices'));
		
		// Intercept approval/rejection actions for better UX
		add_action('wp_ajax_improved_approve_test_method', array($this, 'handle_improved_approve'));
		add_action('wp_ajax_improved_reject_test_method', array($this, 'handle_improved_reject'));
	}
	
	/**
	 * Add workflow status column
	 */
	public function add_workflow_status_column($columns) {
		$new_columns = array();
		
		foreach ($columns as $key => $value) {
			$new_columns[$key] = $value;
			
			if ($key === 'title') {
				$new_columns['workflow_status'] = __('Workflow Status', 'test-method-workflow');
			}
		}
		
		return $new_columns;
	}
	
	/**
	 * Display workflow status column
	 */
	public function display_workflow_status_column($column, $post_id) {
		if ($column === 'workflow_status') {
			$workflow_status = get_post_meta($post_id, '_workflow_status', true);
			$is_locked = get_post_meta($post_id, '_is_locked', true);
			$is_revision = get_post_meta($post_id, '_is_revision', true);
			
			// Determine status label and class
			$status_label = '';
			$status_class = '';
			
			if ($is_locked) {
				$status_label = __('Locked', 'test-method-workflow');
				$status_class = 'locked';
			} elseif ($workflow_status === 'draft') {
				$status_label = __('Draft', 'test-method-workflow');
				$status_class = 'draft';
			} elseif ($workflow_status === 'pending_review') {
				$status_label = __('Pending Review', 'test-method-workflow');
				$status_class = 'pending';
			} elseif ($workflow_status === 'pending_final_approval') {
				$status_label = __('Awaiting Final Approval', 'test-method-workflow');
				$status_class = 'final-approval';
			} elseif ($workflow_status === 'approved') {
				$status_label = __('Approved', 'test-method-workflow');
				$status_class = 'approved';
			} elseif ($workflow_status === 'rejected') {
				$status_label = __('Rejected', 'test-method-workflow');
				$status_class = 'rejected';
			} elseif ($workflow_status === 'publish') {
				$status_label = __('Published', 'test-method-workflow');
				$status_class = 'published';
			}
			
			// Show revision indicator if applicable
			if ($is_revision) {
				$parent_id = get_post_meta($post_id, '_revision_parent', true);
				$parent_post = get_post($parent_id);
				
				$revision_info = '';
				if ($parent_post) {
					$revision_info = ' <span class="revision-of">(' . 
								   __('Revision of', 'test-method-workflow') . ' ' . 
								   '<a href="' . get_edit_post_link($parent_id) . '">' . 
								   esc_html($parent_post->post_title) . '</a>)</span>';
				}
				
				echo '<span class="status-badge status-' . esc_attr($status_class) . '">' . 
					 esc_html($status_label) . '</span>' . $revision_info;
			} else {
				echo '<span class="status-badge status-' . esc_attr($status_class) . '">' . 
					 esc_html($status_label) . '</span>';
			}
			
			// Show approval progress if in review
			if ($workflow_status === 'pending_review' || $workflow_status === 'pending_final_approval') {
				$approvals = get_post_meta($post_id, '_approvals', true);
				$approval_count = is_array($approvals) ? count($approvals) : 0;
				
				echo ' <span class="approval-count">(' . 
					 sprintf(__('%d/2 approvals', 'test-method-workflow'), $approval_count) . 
					 ')</span>';
			}
			
			// Show version number
			$version = get_post_meta($post_id, '_current_version_number', true);
			if (!empty($version) && $version !== '0.0') {
				echo ' <span class="version-number">v' . esc_html($version) . '</span>';
			}
		}
	}
	
	/**
	 * Add improved filters
	 */
	public function add_improved_filters($post_type) {
		if ($post_type !== 'test_method') {
			return;
		}
		
		// Workflow status filter
		$workflow_status = isset($_GET['workflow_status']) ? sanitize_text_field($_GET['workflow_status']) : '';
		
		echo '<select name="workflow_status" class="workflow-status-filter">';
		echo '<option value="">' . __('All Workflow Statuses', 'test-method-workflow') . '</option>';
		echo '<option value="draft" ' . selected($workflow_status, 'draft', false) . '>' . __('Draft', 'test-method-workflow') . '</option>';
		echo '<option value="pending_review" ' . selected($workflow_status, 'pending_review', false) . '>' . __('Pending Review', 'test-method-workflow') . '</option>';
		echo '<option value="pending_final_approval" ' . selected($workflow_status, 'pending_final_approval', false) . '>' . __('Awaiting Final Approval', 'test-method-workflow') . '</option>';
		echo '<option value="approved" ' . selected($workflow_status, 'approved', false) . '>' . __('Approved', 'test-method-workflow') . '</option>';
		echo '<option value="rejected" ' . selected($workflow_status, 'rejected', false) . '>' . __('Rejected', 'test-method-workflow') . '</option>';
		echo '<option value="publish" ' . selected($workflow_status, 'publish', false) . '>' . __('Published', 'test-method-workflow') . '</option>';
		echo '<option value="locked" ' . selected($workflow_status, 'locked', false) . '>' . __('Locked', 'test-method-workflow') . '</option>';
		echo '</select>';
		
		// Approval filter
		$needs_approval = isset($_GET['needs_approval']) ? sanitize_text_field($_GET['needs_approval']) : '';
		
		echo '<select name="needs_approval" class="needs-approval-filter">';
		echo '<option value="">' . __('Any Approval Status', 'test-method-workflow') . '</option>';
		echo '<option value="needs_my_approval" ' . selected($needs_approval, 'needs_my_approval', false) . '>' . __('Needs My Approval', 'test-method-workflow') . '</option>';
		echo '<option value="i_approved" ' . selected($needs_approval, 'i_approved', false) . '>' . __('I Approved', 'test-method-workflow') . '</option>';
		echo '<option value="i_rejected" ' . selected($needs_approval, 'i_rejected', false) . '>' . __('I Rejected', 'test-method-workflow') . '</option>';
		echo '</select>';
		
		// Revision filter (already implemented in the existing code)
		
		// Add some CSS for the filters
		echo '<style>
			.workflow-status-filter,
			.needs-approval-filter {
				margin-right: 6px;
			}
			
			.status-badge {
				display: inline-block;
				padding: 3px 8px;
				border-radius: 12px;
				font-size: 12px;
				font-weight: normal;
			}
			
			.status-badge.status-draft {
				background-color: #f5f5f5;
				color: #444;
			}
			
			.status-badge.status-pending {
				background-color: #fff8e5;
				color: #b26200;
			}
			
			.status-badge.status-final-approval {
				background-color: #e5efff;
				color: #0066cc;
			}
			
			.status-badge.status-approved {
				background-color: #ecf9ec;
				color: #2e8540;
			}
			
			.status-badge.status-rejected {
				background-color: #fbeaea;
				color: #dc3232;
			}
			
			.status-badge.status-published {
				background-color: #e5f5fa;
				color: #0073aa;
			}
			
			.status-badge.status-locked {
				background-color: #f0f0f0;
				color: #555;
			}
			
			.revision-of {
				font-size: 11px;
				color: #666;
			}
			
			.approval-count {
				font-size: 11px;
				color: #666;
				font-style: italic;
			}
			
			.version-number {
				display: inline-block;
				font-size: 11px;
				background: #e5f5fa;
				color: #0073aa;
				padding: 1px 5px;
				border-radius: 3px;
				margin-left: 5px;
			}
		</style>';
	}
	
	/**
	 * Add dashboard link to admin bar
	 */
	public function add_dashboard_link_to_admin_bar($admin_bar) {
		// Check if current user has access to test methods
		if (!current_user_can('edit_test_methods')) {
			return;
		}
		
		// Add Test Method Dashboard link to admin bar
		$admin_bar->add_node(array(
			'id'    => 'test-method-dashboard',
			'title' => __('Test Method Dashboard', 'test-method-workflow'),
			'href'  => admin_url('admin.php?page=test-method-dashboard'),
			'meta'  => array(
				'title' => __('View Test Method Dashboard', 'test-method-workflow'),
			)
		));
		
		// For approvers: Add pending reviews if any
		$user = wp_get_current_user();
		$roles = (array) $user->roles;
		
		if (array_intersect($roles, array('tp_approver', 'tp_admin', 'administrator'))) {
			$pending_reviews = $this->get_pending_reviews_count();
			
			if ($pending_reviews > 0) {
				$admin_bar->add_node(array(
					'id'     => 'test-method-pending-reviews',
					'title'  => sprintf(
						__('Pending Reviews %s', 'test-method-workflow'),
						'<span class="awaiting-mod count-' . $pending_reviews . '"><span class="pending-count">' . $pending_reviews . '</span></span>'
					),
					'href'   => admin_url('edit.php?post_type=test_method&workflow_status=pending_review'),
					'parent' => 'test-method-dashboard',
					'meta'   => array(
						'title' => __('View pending test method reviews', 'test-method-workflow'),
					)
				));
			}
		}
		
		// For TP Admin: Add approved items ready to publish
		if (array_intersect($roles, array('tp_admin', 'administrator'))) {
			$approved_count = $this->get_approved_count();
			
			if ($approved_count > 0) {
				$admin_bar->add_node(array(
					'id'     => 'test-method-ready-publish',
					'title'  => sprintf(
						__('Ready to Publish %s', 'test-method-workflow'),
						'<span class="awaiting-mod count-' . $approved_count . '"><span class="pending-count">' . $approved_count . '</span></span>'
					),
					'href'   => admin_url('edit.php?post_type=test_method&workflow_status=approved'),
					'parent' => 'test-method-dashboard',
					'meta'   => array(
						'title' => __('View test methods ready to publish', 'test-method-workflow'),
					)
				));
			}
		}
	}
	
	/**
	 * Get pending reviews count for current user
	 */
	private function get_pending_reviews_count() {
		global $wpdb;
		$user_id = get_current_user_id();
		$count = 0;
		
		// Get all test methods with pending_review or pending_final_approval status
		$status_query = $wpdb->prepare(
			"SELECT posts.ID 
			FROM {$wpdb->posts} AS posts
			INNER JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
			WHERE posts.post_type = %s
			AND meta.meta_key = '_workflow_status'
			AND meta.meta_value IN ('pending_review', 'pending_final_approval')",
			'test_method'
		);
		
		$pending_posts = $wpdb->get_col($status_query);
		
		// Check each post to see if user has already reviewed it
		foreach ($pending_posts as $post_id) {
			$approvals = get_post_meta($post_id, '_approvals', true);
			$already_reviewed = false;
			
			if (is_array($approvals)) {
				foreach ($approvals as $approval) {
					if (isset($approval['user_id']) && $approval['user_id'] == $user_id) {
						$already_reviewed = true;
						break;
					}
				}
			}
			
			if (!$already_reviewed) {
				$count++;
			}
		}
		
		return $count;
	}
	
	/**
	 * Get count of approved test methods
	 */
	private function get_approved_count() {
		global $wpdb;
		
		$query = $wpdb->prepare(
			"SELECT COUNT(*) 
			FROM {$wpdb->posts} AS posts
			INNER JOIN {$wpdb->postmeta} AS meta ON posts.ID = meta.post_id
			WHERE posts.post_type = %s
			AND meta.meta_key = '_workflow_status'
			AND meta.meta_value = 'approved'",
			'test_method'
		);
		
		return intval($wpdb->get_var($query));
	}
	
	/**
	 * Improved workflow notices
	 */
	public function improved_workflow_notices() {
		global $pagenow, $post;
		
		// Only on post edit screen for test methods
		if ($pagenow !== 'post.php' || !$post || $post->post_type !== 'test_method') {
			return;
		}
		
		$workflow_status = get_post_meta($post->ID, '_workflow_status', true);
		$is_locked = get_post_meta($post->ID, '_is_locked', true);
		$is_revision = get_post_meta($post->ID, '_is_revision', true);
		
		// Get user role
		$user = wp_get_current_user();
		$user_roles = (array) $user->roles;
		$user_id = get_current_user_id();
		
		// Display improved locked notice
		if ($is_locked) {
			if (array_intersect($user_roles, array('administrator', 'tp_admin'))) {
				// Admin notice
				?>
				<div class="notice notice-info is-dismissible">
					<h3 style="margin-top: 0.5em; margin-bottom: 0.5em;">
						<span class="dashicons dashicons-lock" style="color: #0073aa;"></span> 
						<?php _e('This test method is locked', 'test-method-workflow'); ?>
					</h3>
					<p>
						<?php _e('As an administrator, you can make direct edits to this locked test method.', 'test-method-workflow'); ?>
						<?php _e('However, for significant changes, we recommend creating a revision instead to follow the standard approval process.', 'test-method-workflow'); ?>
					</p>
					<p>
						<?php 
						$nonce = wp_create_nonce('test_method_revision_nonce');
						?>
						<a href="#" class="button create-revision-link" data-post-id="<?php echo $post->ID; ?>" data-nonce="<?php echo $nonce; ?>">
							<?php _e('Create Revision', 'test-method-workflow'); ?>
						</a>
						<a href="#" class="button unlock-test-method" data-post-id="<?php echo $post->ID; ?>" data-nonce="<?php echo wp_create_nonce('test_method_workflow'); ?>">
							<?php _e('Unlock Test Method', 'test-method-workflow'); ?>
						</a>
					</p>
				</div>
				<?php
			} else {
				// Regular user notice
				?>
				<div class="notice notice-warning">
					<h3 style="margin-top: 0.5em; margin-bottom: 0.5em;">
						<span class="dashicons dashicons-lock" style="color: #dc3232;"></span> 
						<?php _e('This test method is locked and cannot be edited', 'test-method-workflow'); ?>
					</h3>
					<p>
						<?php _e('This test method has been published and is now locked for editing.', 'test-method-workflow'); ?>
						<?php _e('If you need to make changes, please contact an administrator or create a revision.', 'test-method-workflow'); ?>
					</p>
					<p>
						<?php 
						$nonce = wp_create_nonce('test_method_revision_nonce');
						?>
						<a href="#" class="button create-revision-link" data-post-id="<?php echo $post->ID; ?>" data-nonce="<?php echo $nonce; ?>">
							<?php _e('Create Revision', 'test-method-workflow'); ?>
						</a>
					</p>
				</div>
				<?php
			}
		}
		
		// Display improved revision notice
		if ($is_revision) {
			$parent_id = get_post_meta($post->ID, '_revision_parent', true);
			$parent_post = get_post($parent_id);
			
			if ($parent_post) {
				?>
				<div class="notice notice-info is-dismissible">
					<h3 style="margin-top: 0.5em; margin-bottom: 0.5em;">
						<span class="dashicons dashicons-backup" style="color: #0073aa;"></span> 
						<?php _e('You are editing a revision', 'test-method-workflow'); ?>
					</h3>
					<p>
						<?php printf(__('This is a revision of <a href="%s">%s</a>.', 'test-method-workflow'), 
							get_edit_post_link($parent_id), esc_html($parent_post->post_title)); ?>
						<?php _e('This revision will need to go through the approval process before it can replace the original.', 'test-method-workflow'); ?>
					</p>
				</div>
				<?php
			}
		}
		
		// Display notice for posts in review
		if ($workflow_status === 'pending_review' || $workflow_status === 'pending_final_approval') {
			if (array_intersect($user_roles, array('tp_approver', 'tp_admin', 'administrator'))) {
				// Check if user has already reviewed
				$approvals = get_post_meta($post->ID, '_approvals', true);
				$user_already_reviewed = false;
				
				if (is_array($approvals)) {
					foreach ($approvals as $approval) {
						if (isset($approval['user_id']) && $approval['user_id'] == $user_id) {
							$user_already_reviewed = true;
							break;
						}
					}
				}
				
				if (!$user_already_reviewed) {
					// Show enhanced review notice with improved buttons
					?>
					<div class="notice notice-info is-dismissible">
						<h3 style="margin-top: 0.5em; margin-bottom: 0.5em;">
							<span class="dashicons dashicons-visibility" style="color: #0073aa;"></span> 
							<?php _e('This test method needs your review', 'test-method-workflow'); ?>
						</h3>
						<p>
							<?php _e('Please review the content and provide your approval or rejection below.', 'test-method-workflow'); ?>
							<?php 
							if ($workflow_status === 'pending_final_approval') {
								_e('This test method has already received initial approval and is awaiting final approval.', 'test-method-workflow');
							}
							?>
						</p>
						<div class="improved-review-form" style="background: #f9f9f9; padding: 10px; border: 1px solid #eee; margin-bottom: 10px;">
							<p>
								<label for="improved-approval-comment" style="display: block; margin-bottom: 5px; font-weight: bold;">
									<?php _e('Your Comments', 'test-method-workflow'); ?>:
								</label>
								<textarea id="improved-approval-comment" style="width: 100%; min-height: 80px;"></textarea>
							</p>
							<div class="approval-buttons" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
								<button type="button" class="button button-primary improved-approve-button" data-post-id="<?php echo $post->ID; ?>" data-nonce="<?php echo wp_create_nonce('approve_test_method_nonce'); ?>">
									<span class="dashicons dashicons-yes" style="margin-right: 5px;"></span> 
									<?php _e('Approve', 'test-method-workflow'); ?>
								</button>
								<button type="button" class="button improved-reject-button" data-post-id="<?php echo $post->ID; ?>" data-nonce="<?php echo wp_create_nonce('reject_test_method_nonce'); ?>">
									<span class="dashicons dashicons-no" style="margin-right: 5px;"></span> 
									<?php _e('Reject', 'test-method-workflow'); ?>
								</button>
							</div>
						</div>
					</div>
					<script>
					jQuery(document).ready(function($) {
						// Handle improved approve button
						$('.improved-approve-button').on('click', function() {
							var comment = $('#improved-approval-comment').val();
							if (!comment) {
								alert("<?php _e('Please add approval comments before approving.', 'test-method-workflow'); ?>");
								return;
							}
							
							if (confirm("<?php _e('Are you sure you want to approve this test method?', 'test-method-workflow'); ?>")) {
								var $button = $(this);
								var originalText = $button.html();
								$button.html('<?php _e('Processing...', 'test-method-workflow'); ?>').prop('disabled', true);
								
								$.ajax({
									url: ajaxurl,
									type: 'POST',
									data: {
										action: 'improved_approve_test_method',
										post_id: $(this).data('post-id'),
										comment: comment,
										nonce: $(this).data('nonce')
									},
									success: function(response) {
										if (response.success) {
											alert("<?php _e('Test method approved successfully', 'test-method-workflow'); ?>");
											location.reload();
										} else {
											$button.html(originalText).prop('disabled', false);
											alert(response.data || "<?php _e('An error occurred', 'test-method-workflow'); ?>");
										}
									},
									error: function() {
										$button.html(originalText).prop('disabled', false);
										alert("<?php _e('An error occurred. Please try again.', 'test-method-workflow'); ?>");
									}
								});
							}
						});
						
						// Handle improved reject button
						$('.improved-reject-button').on('click', function() {
							var comment = $('#improved-approval-comment').val();
							if (!comment) {
								alert("<?php _e('Please add rejection comments before rejecting.', 'test-method-workflow'); ?>");
								return;
							}
							
							if (confirm("<?php _e('Are you sure you want to reject this test method?', 'test-method-workflow'); ?>")) {
								var $button = $(this);
								var originalText = $button.html();
								$button.html('<?php _e('Processing...', 'test-method-workflow'); ?>').prop('disabled', true);
								
								$.ajax({
									url: ajaxurl,
									type: 'POST',
									data: {
										action: 'improved_reject_test_method',
										post_id: $(this).data('post-id'),
										comment: comment,
										nonce: $(this).data('nonce')
									},
									success: function(response) {
										if (response.success) {
											alert("<?php _e('Test method rejected', 'test-method-workflow'); ?>");
											location.reload();
										} else {
											$button.html(originalText).prop('disabled', false);
											alert(response.data || "<?php _e('An error occurred', 'test-method-workflow'); ?>");
										}
									},
									error: function() {
										$button.html(originalText).prop('disabled', false);
										alert("<?php _e('An error occurred. Please try again.', 'test-method-workflow'); ?>");
									}
								});
							}
						});
					});
					</script>
					<?php
				} else {
					// Show already reviewed notice
					?>
					<div class="notice notice-info is-dismissible">
						<p>
							<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> 
							<?php _e('You have already reviewed this test method.', 'test-method-workflow'); ?>
						</p>
					</div>
					<?php
				}
			}
		}
		
		// Display notice for approved methods ready to publish (for admin only)
		if ($workflow_status === 'approved' && array_intersect($user_roles, array('tp_admin', 'administrator'))) {
			?>
			<div class="notice notice-success is-dismissible">
				<h3 style="margin-top: 0.5em; margin-bottom: 0.5em;">
					<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> 
					<?php _e('This test method is approved and ready to publish', 'test-method-workflow'); ?>
				</h3>
				<p>
					<?php _e('This test method has received all required approvals and is ready to be published.', 'test-method-workflow'); ?>
				</p>
				<p>
					<button type="button" class="button button-primary publish-approved-post" data-post-id="<?php echo $post->ID; ?>" data-nonce="<?php echo wp_create_nonce('test_method_workflow'); ?>">
						<span class="dashicons dashicons-visibility" style="margin-right: 5px;"></span> 
						<?php 
						if ($is_revision) {
							_e('Publish Approved Revision', 'test-method-workflow');
						} else {
							_e('Publish Approved Test Method', 'test-method-workflow');
						}
						?>
					</button>
				</p>
			</div>
			<?php
		}
	}
	
	/**
	 * Handle improved approve method
	 */
	public function handle_improved_approve() {
		// Check nonce
		check_ajax_referer('approve_test_method_nonce', 'nonce');
		
		// Check permissions
		if (!current_user_can('approve_test_methods')) {
			wp_send_json_error('Permission denied');
			return;
		}
		
		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		$comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
		
		if (!$post_id) {
			wp_send_json_error('Invalid post ID');
			return;
		}
		
		// Get current approvals
		$approvals = get_post_meta($post_id, '_approvals', true);
		if (!is_array($approvals)) {
			$approvals = array();
		}
		
		$current_user_id = get_current_user_id();
		
		// Get current version
		$current_version = get_post_meta($post_id, '_current_version_number', true);
		if (empty($current_version)) {
			$current_version = '0.1';
		}
		
		// Check if user has already approved
		$already_approved = false;
		foreach ($approvals as $key => $approval) {
			if ($approval['user_id'] == $current_user_id) {
				// Update existing approval
				$approvals[$key] = array(
					'user_id' => $current_user_id,
					'date' => time(),
					'status' => 'approved',
					'comment' => $comment,
					'version' => $current_version
				);
				$already_approved = true;
				break;
			}
		}
		
		if (!$already_approved) {
			// Add new approval
			$approvals[] = array(
				'user_id' => $current_user_id,
				'date' => time(),
				'status' => 'approved',
				'comment' => $comment,
				'version' => $current_version
			);
		}
		
		update_post_meta($post_id, '_approvals', $approvals);
		
		// Update workflow status if we have two approvals
		if (count($approvals) >= 2) {
			update_post_meta($post_id, '_workflow_status', 'approved');
			update_post_meta($post_id, '_awaiting_final_approval', false);
			
			// Add to revision history
			$this->add_to_revision_history($post_id, 'approved');
			
			// Send notification to TP Admins
			do_action('tmw_send_notification', $post_id, 'approved');
		} elseif (count($approvals) == 1) {
			// First approval
			$awaiting_final = get_post_meta($post_id, '_awaiting_final_approval', true);
			if ($awaiting_final) {
				update_post_meta($post_id, '_workflow_status', 'pending_final_approval');
			}
		}
		
		wp_send_json_success(array(
			'message' => __('Test method approved successfully', 'test-method-workflow'),
			'approval_count' => count($approvals),
		));
	}
	
	/**
	 * Handle improved reject method
	 */
	public function handle_improved_reject() {
		// Check nonce
		check_ajax_referer('reject_test_method_nonce', 'nonce');
		
		// Check permissions
		if (!current_user_can('reject_test_methods')) {
			wp_send_json_error('Permission denied');
			return;
		}
		
		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		$comment = isset($_POST['comment']) ? sanitize_textarea_field($_POST['comment']) : '';
		
		if (!$post_id) {
			wp_send_json_error('Invalid post ID');
			return;
		}
		
		if (empty($comment)) {
			wp_send_json_error(__('Please provide rejection comments', 'test-method-workflow'));
			return;
		}
		
		// Get current approvals
		$approvals = get_post_meta($post_id, '_approvals', true);
		$current_user_id = get_current_user_id();
		
		// Get current version
		$current_version = get_post_meta($post_id, '_current_version_number', true);
		if (empty($current_version)) {
			$current_version = '0.1';
		}
		
		// Check if user has already approved/rejected
		$updated = false;
		if (is_array($approvals)) {
			foreach ($approvals as $key => $approval) {
				if ($approval['user_id'] == $current_user_id) {
					// Update existing entry instead of adding a new one
					$approvals[$key] = array(
						'user_id' => $current_user_id,
						'date' => time(),
						'status' => 'rejected',
						'comment' => $comment,
						'version' => $current_version
					);
					$updated = true;
					break;
				}
			}
		} else {
			$approvals = array();
		}
		
		if (!$updated) {
			// Add new rejection
			$approvals[] = array(
				'user_id' => $current_user_id,
				'date' => time(),
				'status' => 'rejected',
				'comment' => $comment,
				'version' => $current_version
			);
		}
		
		update_post_meta($post_id, '_approvals', $approvals);
		update_post_meta($post_id, '_workflow_status', 'rejected');
		update_post_meta($post_id, '_awaiting_final_approval', false);
		
		// Update post status
		wp_update_post(array(
			'ID' => $post_id,
			'post_status' => 'draft',
		));
		
		// Add to revision history
		$this->add_to_revision_history($post_id, 'rejected');
		
		// Send notification
		do_action('tmw_send_notification', $post_id, 'rejected');
		
		wp_send_json_success(array(
			'message' => __('Test method rejected', 'test-method-workflow'),
			'reload' => true,
		));
	}
	
	/**
	 * Add entry to revision history
	 */
	private function add_to_revision_history($post_id, $status) {
		$revision_history = get_post_meta($post_id, '_revision_history', true);
		
		if (!is_array($revision_history)) {
			$revision_history = array();
		}
		
		// Get current version
		$current_version = get_post_meta($post_id, '_current_version_number', true);
		if (empty($current_version)) {
			$current_version = '0.1';
		}
		
		$revision_history[] = array(
			'version' => count($revision_history) + 1,
			'user_id' => get_current_user_id(),
			'date' => time(),
			'status' => $status,
			'version_number' => $current_version
		);
		
		update_post_meta($post_id, '_revision_history', $revision_history);
	}
}

// Initialize integration
new TestMethod_Integration();