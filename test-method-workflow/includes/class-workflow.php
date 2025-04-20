<?php
/**
 * Workflow Management - Modified for multiple post types
 *
 * @package TestMethodWorkflow
 */

// Exit if accessed directly
if (!defined("ABSPATH")) {
	exit();
}

/**
 * Workflow controller class
 */
class TestMethod_Workflow
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
	// Add meta boxes
		add_action("add_meta_boxes", [$this, "add_workflow_meta_boxes"]);
	
		// Save post meta
		add_action("save_post", [$this, "save_workflow_meta"], 10, 2);
	
		// AJAX actions for workflow
		add_action("wp_ajax_submit_for_review", [$this, "submit_for_review"]);
		add_action("wp_ajax_approve_test_method", [$this, "approve_test_method"]);
		add_action("wp_ajax_reject_test_method", [$this, "reject_test_method"]);
		add_action("wp_ajax_publish_approved_post", [$this, "publish_approved_post"]);
		add_action("wp_ajax_submit_for_final_approval", [$this, "submit_for_final_approval"]);
		add_action("wp_ajax_cancel_approval_request", [$this, "cancel_approval_request"]);
		
		// AJAX actions for related test method
		add_action("wp_ajax_reset_related_test_method", [$this, "reset_related_test_method"]);
		
		// AJAX actions for versioning
		add_action("wp_ajax_create_new_version", [$this, "create_new_version"]);
		add_action("wp_ajax_compare_test_method_versions", [$this, "compare_test_method_versions"]);
		add_action("wp_ajax_restore_test_method_version", [$this, "restore_test_method_version"]);
	
		// Prevent unauthorized publishing
		add_action("save_post", [$this, "prevent_unauthorized_publishing"], 10, 2);
	
		// Custom admin notices
		add_action("admin_notices", [$this, "workflow_admin_notices"]);
		
		// Register Gutenberg sidebar for workflow actions
		add_action('init', [$this, 'register_workflow_sidebar']);
		
		// Enqueue assets for version management
		add_action('admin_enqueue_scripts', [$this, 'enqueue_version_assets']);
	}
	
	/**
	 * Enqueue assets for version management
	 */
	public function enqueue_version_assets($hook) {
		global $post;
		
		// Only on post.php and for workflow post types
		if ($hook == 'post.php' && $post && in_array($post->post_type, array('ccg-version', 'tp-version'))) {
			// Enqueue WP's diff library
			wp_enqueue_style('wp-diff');
			
			// Add custom CSS for version comparison
			wp_add_inline_style('wp-diff', '
				.version-info {
					background-color: #f0f9ff;
					padding: 12px;
					border-left: 4px solid #0073aa;
					margin-bottom: 15px;
				}
				.create-new-version-section {
					margin: 15px 0;
					padding: 12px;
					background: #f0f6fb;
					border: 1px solid #ddd;
					border-radius: 4px;
				}
				.version-type-selection label {
					display: block;
					margin-bottom: 5px;
				}
				.status-approved {
					color: #00a32a;
					font-weight: bold;
				}
				.status-rejected {
					color: #d63638;
					font-weight: bold;
				}
				#version-comparison-modal {
					position: fixed;
					z-index: 999;
					left: 0;
					top: 0;
					width: 100%;
					height: 100%;
					background-color: rgba(0,0,0,0.6);
					display: none;
				}
				#version-comparison-content {
					max-height: 70vh;
					overflow: auto;
				}
				table.diff {
					width: 100%;
					border-collapse: collapse;
					margin: 20px 0;
					font-family: monospace;
					font-size: 13px;
				}
				table.diff th {
					padding: 6px 10px;
					background: #f5f5f5;
					border: 1px solid #ddd;
				}
				table.diff td {
					padding: 6px 10px;
					border: 1px solid #ddd;
					vertical-align: top;
					word-wrap: break-word;
					white-space: pre-wrap;
				}
				table.diff .diff-addedline {
					background-color: #e6ffed;
				}
				table.diff .diff-deletedline {
					background-color: #ffeef0;
				}
				table.diff .diff-addedline ins {
					background-color: #acf2bd;
					text-decoration: none;
				}
				table.diff .diff-deletedline del {
					background-color: #fdb8c0;
					text-decoration: none;
				}
			');
		}
	}

	/**
	 * Register Gutenberg sidebar for workflow actions
	 */
	public function register_workflow_sidebar() {
		// Only if Gutenberg is available
		if (function_exists('register_block_type')) {
			// Register script
			wp_register_script(
				'test-method-workflow-sidebar',
				plugin_dir_url(dirname(__FILE__)) . 'js/workflow-sidebar.js',
				array('wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-plugins', 'wp-i18n'),
				filemtime(plugin_dir_path(dirname(__FILE__)) . 'js/workflow-sidebar.js'),
				true
			);
			
			// Register the sidebar
			register_block_type('test-method-workflow/sidebar', array(
				'editor_script' => 'test-method-workflow-sidebar',
			));
			
			// Add approvers list for select dropdown
			wp_localize_script('test-method-workflow-sidebar', 'testMethodWorkflow', array(
				'approversList' => $this->get_approvers_list(),
				'ajaxurl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('test_method_workflow'),
				'strings' => array(
					'confirm_submit' => __('Are you sure you want to submit this for review?', 'test-method-workflow'),
					'confirm_approve' => __('Are you sure you want to approve this?', 'test-method-workflow'),
					'confirm_reject' => __('Are you sure you want to reject this?', 'test-method-workflow'),
					'confirm_cancel' => __('Are you sure you want to cancel the approval request?', 'test-method-workflow'),
					'confirm_publish' => __('Are you sure you want to publish this approved content?', 'test-method-workflow'),
				)
			));
		}
	}
	
	/**
	 * Get list of users who can approve content
	 */
	private function get_approvers_list() {
		$approvers = array();
		
		// Get users with approver or admin role
		$users = get_users(array(
			'role__in' => array('tp_approver', 'tp_admin', 'administrator'),
		));
		
		foreach ($users as $user) {
			$approvers[] = array(
				'id' => $user->ID,
				'name' => $user->display_name,
				'email' => $user->user_email,
				'role' => implode(', ', $user->roles),
			);
		}
		
		return $approvers;
	}

	/**
	 * Add workflow meta boxes
	 */
	public function add_workflow_meta_boxes()
	{
		// Add workflow meta box to all workflow post types
		foreach (array('test_method', 'ccg-version', 'tp-version') as $post_type) {
			// For test_method, only add locking meta box
			if ($post_type === 'test_method') {
				add_meta_box(
					"test_method_locking",
					__("Test Method Locking", "test-method-workflow"),
					[$this, "locking_meta_box_callback"],
					$post_type,
					"side",
					"high"
				);
			} else {
				// Add full workflow meta boxes for ccg-version and tp-version
				add_meta_box(
					"test_method_workflow",
					__("Workflow Status", "test-method-workflow"),
					[$this, "workflow_meta_box_callback"],
					$post_type,
					"side",
					"high"
				);
				
				add_meta_box(
					"test_method_approvals",
					__("Approvals & Comments", "test-method-workflow"),
					[$this, "approvals_meta_box_callback"],
					$post_type,
					"normal",
					"high"
				);
				
				add_meta_box(
					"test_method_relation",
					__("Related Test Method", "test-method-workflow"),
					[$this, "relation_meta_box_callback"],
					$post_type,
					"side",
					"default"
				);
			}
		}
	}
	
	/**
	 * Locking meta box callback for test_method
	 */
	public function locking_meta_box_callback($post) {
		wp_nonce_field("test_method_workflow_meta_box", "test_method_workflow_nonce");
		
		$is_locked = get_post_meta($post->ID, "_is_locked", true);
		
		echo '<div class="locking-status-container">';
		
		// Show current status
		if ($is_locked) {
			echo '<p><strong>' . __("Status:", "test-method-workflow") . '</strong> <span class="locked-status">' . 
				__("Locked", "test-method-workflow") . '</span></p>';
		} else {
			echo '<p><strong>' . __("Status:", "test-method-workflow") . '</strong> <span class="unlocked-status">' . 
				__("Unlocked", "test-method-workflow") . '</span></p>';
		}
		
		// Get current user role
		$user = wp_get_current_user();
		$user_roles = (array) $user->roles;
		
		// Only TP Admin and Administrator can lock/unlock
		if (array_intersect($user_roles, array('tp_admin', 'administrator'))) {
			if ($is_locked) {
				echo '<button type="button" class="button unlock-test-method" data-post-id="' . $post->ID . '">' . 
					__("Unlock Test Method", "test-method-workflow") . '</button>';
				echo '<p class="description">' . __("Unlocking will allow editing of this test method.", "test-method-workflow") . '</p>';
			} else {
				echo '<button type="button" class="button button-primary lock-test-method" data-post-id="' . $post->ID . '">' . 
					__("Lock Test Method", "test-method-workflow") . '</button>';
				echo '<p class="description">' . __("Locking will prevent editing of this test method.", "test-method-workflow") . '</p>';
			}
		}
		
		echo '</div>';
	}

	/**
	 * Workflow meta box callback
	 */
	public function workflow_meta_box_callback($post)
	{
		wp_nonce_field("test_method_workflow_meta_box", "test_method_workflow_nonce");

		$workflow_status = get_post_meta($post->ID, "_workflow_status", true);
		$is_locked = get_post_meta($post->ID, "_is_locked", true);
		$approvals = get_post_meta($post->ID, "_approvals", true);
		$approval_count = is_array($approvals) ? count($approvals) : 0;
		$awaiting_final_approval = get_post_meta($post->ID, "_awaiting_final_approval", true);
		$is_revision = get_post_meta($post->ID, "_is_revision", true);
		$assigned_approvers = get_post_meta($post->ID, "_assigned_approvers", true);
		
		if (!$workflow_status) {
			$workflow_status = "draft";
		}

		$statuses = [
			"draft" => __("Draft", "test-method-workflow"),
			"pending_review" => __("Pending Review", "test-method-workflow"),
			"pending_final_approval" => __("Awaiting Final Approval", "test-method-workflow"),
			"approved" => __("Approved", "test-method-workflow"),
			"rejected" => __("Rejected", "test-method-workflow"),
			"publish" => __("Published", "test-method-workflow"),
			"locked" => __("Locked", "test-method-workflow"),
		];

		echo '<div class="workflow-status-container">';
		echo "<p><strong>" . __("Current Status:", "test-method-workflow") . "</strong> " .
			(isset($statuses[$workflow_status]) ? $statuses[$workflow_status] : ucfirst($workflow_status)) .
			"</p>";

		// Show revision info if applicable
		if ($is_revision) {
			$parent_id = get_post_meta($post->ID, "_revision_parent", true);
			$parent_post = get_post($parent_id);
			if ($parent_post) {
				echo "<p><strong>" . __("Revision of:", "test-method-workflow") . "</strong> " .
					'<a href="' . get_edit_post_link($parent_id) . '">' .
					esc_html($parent_post->post_title) . "</a></p>";
			}
		}
		
		// Show assigned approvers if any
		if (!empty($assigned_approvers) && is_array($assigned_approvers)) {
			echo "<p><strong>" . __("Assigned Approvers:", "test-method-workflow") . "</strong> ";
			$approver_names = array();
			foreach ($assigned_approvers as $approver_id) {
				$user_info = get_userdata($approver_id);
				if ($user_info) {
					$approver_names[] = $user_info->display_name;
				}
			}
			echo implode(', ', $approver_names);
			echo "</p>";
		}
		
		// Show approval count if there are any approvals
		if ($approval_count > 0) {
			echo "<p><strong>" . __("Approvals:", "test-method-workflow") . "</strong> " .
				$approval_count . " " . __("of 2 required", "test-method-workflow") . "</p>";
		}

		// Get current user role
		$user = wp_get_current_user();
		$user_roles = (array) $user->roles;
		$user_id = get_current_user_id();

		// Check if current user has already approved
		$user_has_approved = false;
		if (is_array($approvals)) {
			foreach ($approvals as $approval) {
				if ($approval["user_id"] == $user_id && $approval["status"] == "approved") {
					$user_has_approved = true;
					break;
				}
			}
		}

		// Initial submit for review button - only for draft or rejected status
		if ($workflow_status == "draft" || $workflow_status == "rejected") {
			if (array_intersect($user_roles, ["tp_contributor", "tp_approver", "tp_admin", "administrator"])) {
				echo "<p>" . __("When ready, submit this for review:", "test-method-workflow") . "</p>";
				
				// Approver selection dropdown for non-classic editors
				echo '<div class="approver-selection" style="margin-bottom: 10px;">
					<label for="assigned_approvers">' . __("Select Approvers:", "test-method-workflow") . '</label>
					<select id="assigned_approvers" name="assigned_approvers[]" multiple style="width: 100%; max-width: 100%;">';
				
				$approvers = $this->get_approvers_list();
				foreach ($approvers as $approver) {
					$selected = is_array($assigned_approvers) && in_array($approver['id'], $assigned_approvers) ? 'selected' : '';
					echo '<option value="' . $approver['id'] . '" ' . $selected . '>' . $approver['name'] . ' (' . $approver['role'] . ')</option>';
				}
				
				echo '</select>
					<p class="description">' . __("Hold Ctrl/Cmd to select multiple approvers", "test-method-workflow") . '</p>
				</div>';
				
				echo '<button type="button" class="button button-primary submit-for-review" data-post-id="' . $post->ID . '">' .
					__("Submit for Review", "test-method-workflow") . "</button>";
			}
		}
		
		// For posts under review, show cancel request button for authors
		if (($workflow_status == "pending_review" || $workflow_status == "pending_final_approval") && $post->post_author == $user_id) {
			echo '<button type="button" class="button button-secondary cancel-approval-request" data-post-id="' . $post->ID . '">' .
				__("Cancel Approval Request", "test-method-workflow") . "</button>";
			echo '<p class="description">' . __("This will return the post to draft status for editing.", "test-method-workflow") . '</p>';
		}
		
		// Final approval buttons - for TP Approvers and above
		if (($workflow_status == "pending_review" || $workflow_status == "pending_final_approval") &&
			array_intersect($user_roles, ["tp_approver", "tp_admin", "administrator"])) {
			
			// If user hasn't approved yet, show approve/reject buttons
			if (!$user_has_approved) {
				echo '<div class="approval-actions">';
				echo "<p>" . __("Review this content:", "test-method-workflow") . "</p>";
				echo '<button type="button" class="button button-primary approve-test-method" data-post-id="' . $post->ID . '">' .
					__("Approve", "test-method-workflow") . "</button> ";
				echo '<button type="button" class="button button-secondary reject-test-method" data-post-id="' . $post->ID . '">' .
					__("Reject", "test-method-workflow") . "</button>";
				echo "</div>";
			}
		}

		// Submit for final approval button - only if one approval exists and not already awaiting final approval
		if ($workflow_status == "pending_review" && $approval_count == 1 && !$awaiting_final_approval) {
			if (array_intersect($user_roles, ["tp_admin", "administrator"]) || $post->post_author == $user_id) {
				echo '<button type="button" class="button button-primary submit-for-final-approval" data-post-id="' . $post->ID . '">' .
					__("Submit for Final Approval", "test-method-workflow") . "</button>";
			}
		}
		
		// If awaiting final approval, show message
		if ($workflow_status == "pending_final_approval" || $awaiting_final_approval) {
			echo '<p class="final-approval-message">' .
				__("This content is awaiting final approval. Another approver needs to review it.", "test-method-workflow") .
				"</p>";
		}

		// If post is approved and user is TP admin or admin, show publish button
		if ($workflow_status == "approved" && $approval_count >= 2) {
			if (array_intersect($user_roles, ["tp_admin", "administrator"])) {
				echo '<p class="description">' .
					__("This content has been approved and is ready to publish.", "test-method-workflow") .
					"</p>";
				
				echo '<button type="button" class="button button-primary publish-approved-post" data-post-id="' . $post->ID . '">' .
					__("Publish Approved Content", "test-method-workflow") .
										"</button>";
								} else {
									echo '<p class="description">' .
										__("This content has been approved and is awaiting publishing by an administrator.", "test-method-workflow") .
										"</p>";
								}
							}
					
							echo "</div>";
						}
						
						/**
						 * Relation meta box callback - For selecting related test_method
						 */
/**
						  * Relation meta box callback - For selecting related test_method
						  */
						 public function relation_meta_box_callback($post) {
							 wp_nonce_field("test_method_workflow_meta_box", "test_method_workflow_nonce");
							 
							 $related_test_method_id = get_post_meta($post->ID, '_related_test_method', true);
							 $workflow_status = get_post_meta($post->ID, '_workflow_status', true);
							 $user = wp_get_current_user();
							 $user_roles = (array) $user->roles;
							 $is_admin = array_intersect($user_roles, array('administrator', 'tp_admin'));
							 
							 // If already selected and not admin or not in draft status, show as read-only
							 if (!empty($related_test_method_id) && $workflow_status !== 'draft') {
								 $test_method = get_post($related_test_method_id);
								 if ($test_method) {
									 echo '<div class="related-test-method-display">';
									 echo '<p><strong>' . __('Related Test Method:', 'test-method-workflow') . '</strong> ';
									 echo esc_html($test_method->post_title);
									 
									 // If not admin, show locked message
									 if (!$is_admin) {
										 echo ' <span class="description">(' . __('Locked - contact admin to change', 'test-method-workflow') . ')</span>';
									 }
									 
									 echo '</p>';
									 echo '<input type="hidden" name="related_test_method" value="' . esc_attr($related_test_method_id) . '">';
									 echo '</div>';
									 
									 // For admins, add option to reset the relationship
									 if ($is_admin) {
										 echo '<div class="admin-options" style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #ddd;">';
										 echo '<details>';
										 echo '<summary>' . __('Admin Options', 'test-method-workflow') . '</summary>';
										 echo '<p>' . __('As an administrator, you can reset the related test method:', 'test-method-workflow') . '</p>';
										 
										 // Get all test methods for selection
										 $test_methods = get_posts(array(
											 'post_type' => 'test_method',
											 'numberposts' => -1,
											 'orderby' => 'title',
											 'order' => 'ASC',
										 ));
										 
										 echo '<p>';
										 echo '<select name="related_test_method" id="related_test_method" style="width: 100%;">';
										 echo '<option value="">' . __('-- Select a Test Method --', 'test-method-workflow') . '</option>';
										 
										 foreach ($test_methods as $test_method) {
											 $selected = selected($related_test_method_id, $test_method->ID, false);
											 echo '<option value="' . $test_method->ID . '" ' . $selected . '>' . 
												 esc_html($test_method->post_title) . '</option>';
										 }
										 
										 echo '</select>';
										 echo '</p>';
										 
										 echo '<p class="description">' . __('Warning: Changing the related test method will affect which test method is updated when this content is published.', 'test-method-workflow') . '</p>';
										 echo '</details>';
										 echo '</div>';
									 }
								 }
							 } else {
								 // Get all test methods for selection
								 $test_methods = get_posts(array(
									 'post_type' => 'test_method',
									 'numberposts' => -1,
									 'orderby' => 'title',
									 'order' => 'ASC',
								 ));
								 
								 echo '<select name="related_test_method" id="related_test_method" style="width: 100%;" required>';
								 echo '<option value="">' . __('-- Select a Test Method --', 'test-method-workflow') . '</option>';
								 
								 foreach ($test_methods as $test_method) {
									 $selected = selected($related_test_method_id, $test_method->ID, false);
									 echo '<option value="' . $test_method->ID . '" ' . $selected . '>' . 
										 esc_html($test_method->post_title) . '</option>';
								 }
								 
								 echo '</select>';
								 echo '<p class="description">' . __('Select the Test Method that this content will update when published. This is required and cannot be changed after submission without admin approval.', 'test-method-workflow') . '</p>';
								 
								 // Add field validation via JavaScript
								 ?>
								 <script type="text/javascript">
								 jQuery(document).ready(function($) {
									 // Validate on form submit
									 $('form#post').on('submit', function(e) {
										 var relatedTestMethod = $('#related_test_method').val();
										 if (relatedTestMethod === '') {
											 e.preventDefault();
											 alert('<?php echo esc_js(__('Please select a Related Test Method. This is required.', 'test-method-workflow')); ?>');
											 $('#related_test_method').focus();
											 return false;
										 }
										 return true;
									 });
									 
									 // Also validate when clicking submit for review button
									 $(document).on('click', '.submit-for-review', function(e) {
										 var relatedTestMethod = $('#related_test_method').val();
										 if (relatedTestMethod === '') {
											 e.preventDefault();
											 alert('<?php echo esc_js(__('Please select a Related Test Method before submitting for review.', 'test-method-workflow')); ?>');
											 $('#related_test_method').focus();
											 return false;
										 }
									 });
								 });
								 </script>
								 <?php
							 }
							 
							 // Add a validation message for users
							 if ($workflow_status == 'draft' && empty($related_test_method_id)) {
								 echo '<div class="notice notice-warning inline" style="margin: 10px 0 0; padding: 8px;">';
								 echo '<p><strong>' . __('Required:', 'test-method-workflow') . '</strong> ' . 
									 __('You must select a Test Method before this content can be submitted for review.', 'test-method-workflow') . '</p>';
								 echo '</div>';
							 }
						 }
					
			/**
			 * Approvals meta box callback with enhanced version management
			 */
			public function approvals_meta_box_callback($post)
			{
				$approvals = $this->get_post_approvals($post->ID);
				$revision_history = $this->get_revision_history($post->ID);
				$current_version = get_post_meta($post->ID, "_current_version_number", true);
				$workflow_status = get_post_meta($post->ID, "_workflow_status", true);
				$user = wp_get_current_user();
				$user_roles = (array) $user->roles;
				$is_admin = array_intersect($user_roles, array('administrator', 'tp_admin'));
			
				// Current version display
				echo '<div class="version-info">';
				echo "<h3>" . __("Version Information", "test-method-workflow") . "</h3>";
				echo "<p><strong>" . __("Current Version:", "test-method-workflow") . "</strong> " .
					(!empty($current_version) ? esc_html($current_version) : "0.1") . "</p>";
			
				// Version note
				$version_note = get_post_meta($post->ID, "_cpt_version_note", true);
				if (!empty($version_note)) {
					echo "<p><strong>" . __("Version Note:", "test-method-workflow") . "</strong> " .
						esc_html($version_note) . "</p>";
				}
				echo "</div>";
				
				// Create New Version button - only show after approval or if published
				if (($workflow_status == 'approved' && count($approvals) >= 2) || 
					$workflow_status == 'publish' || $post->post_status == 'publish') {
					
					echo '<div class="create-new-version-section" style="margin: 15px 0; padding: 12px; background: #f0f6fb; border: 1px solid #ddd; border-radius: 4px;">';
					echo '<h3>' . __('Create New Version', 'test-method-workflow') . '</h3>';
					echo '<p>' . __('Create a new version of this content for editing:', 'test-method-workflow') . '</p>';
					
					// Parse current version
					$version_parts = explode('.', $current_version);
					$major = isset($version_parts[0]) ? intval($version_parts[0]) : 0;
					$minor = isset($version_parts[1]) ? intval($version_parts[1]) : 0;
					
					// Calculate next versions
					$next_minor = $major . '.' . ($minor + 1);
					$next_major = ($major + 1) . '.0';
					
					echo '<div class="version-type-selection" style="margin-bottom: 10px;">';
					echo '<p><label><input type="radio" name="new_version_type" value="minor" checked> ' . 
						__('Minor Update', 'test-method-workflow') . ' (' . $current_version . ' → ' . $next_minor . ')</label></p>';
					echo '<p><label><input type="radio" name="new_version_type" value="major"> ' . 
						__('Major Update', 'test-method-workflow') . ' (' . $current_version . ' → ' . $next_major . ')</label></p>';
					echo '</div>';
					
					echo '<div class="version-note-field" style="margin-bottom: 10px;">';
					echo '<p><label for="new_version_note">' . __('Version Change Note:', 'test-method-workflow') . '</label><br>';
					echo '<textarea id="new_version_note" name="new_version_note" rows="3" style="width: 100%;" placeholder="' . 
						esc_attr__('Describe what changes will be made in this new version', 'test-method-workflow') . '"></textarea></p>';
					echo '</div>';
					
					echo '<p><button type="button" class="button button-primary create-new-version-btn" data-post-id="' . $post->ID . '">' . 
						__('Create New Version', 'test-method-workflow') . '</button></p>';
					echo '</div>';
					
					// Add JavaScript for creating new versions
					?>
					<script type="text/javascript">
					jQuery(document).ready(function($) {
						$('.create-new-version-btn').on('click', function() {
							var postId = $(this).data('post-id');
							var versionType = $('input[name="new_version_type"]:checked').val();
							var versionNote = $('#new_version_note').val();
							
							if (!versionNote) {
								alert('<?php echo esc_js(__('Please enter a version note describing the changes.', 'test-method-workflow')); ?>');
								$('#new_version_note').focus();
								return;
							}
							
							if (confirm('<?php echo esc_js(__('Are you sure you want to create a new version? This will create a new draft for editing.', 'test-method-workflow')); ?>')) {
								$(this).prop('disabled', true).text('<?php echo esc_js(__('Creating...', 'test-method-workflow')); ?>');
								
								$.ajax({
									url: ajaxurl,
									type: 'POST',
									data: {
										action: 'create_new_version',
										post_id: postId,
										version_type: versionType,
										version_note: versionNote,
										nonce: '<?php echo wp_create_nonce('test_method_version_create'); ?>'
									},
									success: function(response) {
										if (response.success) {
											alert(response.data.message);
											
											if (response.data.redirect) {
												window.location.href = response.data.redirect;
											} else {
												location.reload();
											}
										} else {
											alert(response.data || '<?php echo esc_js(__('An error occurred', 'test-method-workflow')); ?>');
											$('.create-new-version-btn').prop('disabled', false).text('<?php echo esc_js(__('Create New Version', 'test-method-workflow')); ?>');
										}
									},
									error: function() {
										alert('<?php echo esc_js(__('An error occurred. Please try again.', 'test-method-workflow')); ?>');
										$('.create-new-version-btn').prop('disabled', false).text('<?php echo esc_js(__('Create New Version', 'test-method-workflow')); ?>');
									}
								});
							}
						});
					});
					</script>
					<?php
				}
			
				// Approval history
				echo '<div class="approval-history">';
				echo "<h3>" . __("Approval History", "test-method-workflow") . "</h3>";
			
				if (empty($approvals) || !is_array($approvals)) {
					echo "<p>" . __("No approvals yet.", "test-method-workflow") . "</p>";
				} else {
					echo '<table class="widefat" style="margin-bottom: 20px;">';
					echo "<thead>";
					echo "<tr>";
					echo "<th>" . __("User", "test-method-workflow") . "</th>";
					echo "<th>" . __("Date", "test-method-workflow") . "</th>";
					echo "<th>" . __("Status", "test-method-workflow") . "</th>";
					echo "<th>" . __("Version", "test-method-workflow") . "</th>";
					echo "<th>" . __("Comments", "test-method-workflow") . "</th>";
					echo "</tr>";
					echo "</thead>";
					echo "<tbody>";
					foreach ($approvals as $approval) {
						$user_info = get_userdata($approval["user_id"]);
						$username = $user_info
							? $user_info->display_name
							: __("Unknown User", "test-method-workflow");
						$status_class =
							$approval["status"] == "approved"
								? "status-approved"
								: "status-rejected";
						$version = isset($approval["version"]) ? $approval["version"] : $current_version;
			
						echo "<tr>";
						echo "<td>" . esc_html($username) . "</td>";
						echo "<td>" .
							date_i18n(
								get_option("date_format") .
									" " .
									get_option("time_format"),
								$approval["date"]
							) .
							"</td>";
						echo '<td><span class="' .
							$status_class .
							'">' .
							ucfirst($approval["status"]) .
							"</span></td>";
						echo "<td>" . esc_html($version) . "</td>";
						echo "<td>" . esc_html($approval["comment"]) . "</td>";
						echo "</tr>";
					}
			
					echo "</tbody>";
					echo "</table>";
				}
				echo "</div>";
			
				// Add comment field for approvers
				if (($workflow_status == "pending_review" || $workflow_status == "pending_final_approval") &&
					array_intersect($user_roles, ["tp_approver", "tp_admin", "administrator"])) {
					// Check if user has already approved/rejected
					$user_id = get_current_user_id();
					$already_reviewed = false;
			
					if (is_array($approvals)) {
						foreach ($approvals as $approval) {
							if ($approval["user_id"] == $user_id) {
								$already_reviewed = true;
								break;
							}
						}
					}
					if (!$already_reviewed) {
						echo '<div class="approval-comment-container">';
						echo "<h3>" . __("Add Your Review", "test-method-workflow") . "</h3>";
						echo '<p><textarea id="approval-comment" placeholder="' .
							esc_attr__("Add your approval or rejection comments", "test-method-workflow") .
							'" rows="4" style="width: 100%;"></textarea></p>';
						echo "</div>";
					} else {
						echo "<p><em>" . __("You have already reviewed this content.", "test-method-workflow") . "</em></p>";
					}
				}
				
				// Revision history
				echo '<div class="revision-history">';
				echo "<h3>" . __("Revision History", "test-method-workflow") . "</h3>";
			
				if (empty($revision_history) || !is_array($revision_history)) {
					echo "<p>" . __("No revision history available.", "test-method-workflow") . "</p>";
				} else {
					echo '<table class="widefat">';
					echo "<thead>";
					echo "<tr>";
					echo "<th>" . __("Version", "test-method-workflow") . "</th>";
					echo "<th>" . __("User", "test-method-workflow") . "</th>";
					echo "<th>" . __("Date", "test-method-workflow") . "</th>";
					echo "<th>" . __("Status", "test-method-workflow") . "</th>";
					echo "<th>" . __("Version Number", "test-method-workflow") . "</th>";
					echo ($is_admin ? "<th>" . __("Actions", "test-method-workflow") . "</th>" : "");
					echo "</tr>";
					echo "</thead>";
					echo "<tbody>";
			
					foreach ($revision_history as $revision) {
						$user_info = get_userdata($revision["user_id"]);
						$username = $user_info
							? $user_info->display_name
							: __("Unknown User", "test-method-workflow");
						$version_number = isset($revision["version_number"]) ? $revision["version_number"] : "-";
						$has_version_id = isset($revision["version_id"]) && !empty($revision["version_id"]);
			
						echo "<tr>";
						echo "<td>" .
							(isset($revision["version"]) ? $revision["version"] : "-") .
							"</td>";
						echo "<td>" . esc_html($username) . "</td>";
						echo "<td>" .
							date_i18n(
								get_option("date_format") .
									" " .
									get_option("time_format"),
								$revision["date"]
							) .
							"</td>";
						echo "<td>" . ucfirst($revision["status"]) . "</td>";
						echo "<td>" . esc_html($version_number) . "</td>";
						
						// Add compare/restore buttons for admins
						if ($is_admin) {
							echo "<td>";
							if ($has_version_id) {
								echo '<button type="button" class="button button-small compare-version-btn" data-current="' . $post->ID . 
									 '" data-version="' . $revision["version_id"] . '" data-version-number="' . 
									 esc_attr($version_number) . '">' . 
									 __('Compare', 'test-method-workflow') . '</button> ';
								
								echo '<button type="button" class="button button-small restore-version-btn" data-post-id="' . $post->ID . 
									 '" data-version-id="' . $revision["version_id"] . '" data-version-number="' . 
									 esc_attr($version_number) . '">' . 
									 __('Restore', 'test-method-workflow') . '</button>';
							}
							echo "</td>";
						}
						
						echo "</tr>";
					}
			
					echo "</tbody>";
					echo "</table>";
				}
				echo "</div>";
				
				// Add modal for version comparison
				if ($is_admin) {
					?>
					<div id="version-comparison-modal" style="display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.6);">
						<div style="position:relative; background-color:white; margin:5% auto; padding:20px; width:90%; max-width:1000px; max-height:80vh; overflow:auto;">
							<span id="close-comparison" style="position:absolute; top:10px; right:20px; font-size:28px; cursor:pointer;">&times;</span>
							<h2><?php _e('Version Comparison', 'test-method-workflow'); ?></h2>
							<div id="version-comparison-content"></div>
						</div>
					</div>
					
					<script type="text/javascript">
					jQuery(document).ready(function($) {
						// Compare version button
						$('.compare-version-btn').on('click', function() {
							var currentId = $(this).data('current');
							var versionId = $(this).data('version');
							var versionNumber = $(this).data('version-number');
							
							// Show modal and loading indicator
							$('#version-comparison-modal').show();
							$('#version-comparison-content').html('<p><?php echo esc_js(__('Loading comparison...', 'test-method-workflow')); ?></p>');
							
							// Load comparison via AJAX
							$.ajax({
								url: ajaxurl,
								type: 'POST',
								data: {
									action: 'compare_test_method_versions',
									current_id: currentId,
									version_id: versionId,
									version_number: versionNumber,
									nonce: '<?php echo wp_create_nonce('test_method_version_comparison'); ?>'
								},
								success: function(response) {
									if (response.success) {
										$('#version-comparison-content').html(response.data.html);
									} else {
										$('#version-comparison-content').html('<p class="error">' + (response.data || '<?php echo esc_js(__('An error occurred', 'test-method-workflow')); ?>') + '</p>');
									}
								},
								error: function() {
									$('#version-comparison-content').html('<p class="error"><?php echo esc_js(__('An error occurred. Please try again.', 'test-method-workflow')); ?></p>');
								}
							});
						});
						
						// Restore version button
						$('.restore-version-btn').on('click', function() {
							var postId = $(this).data('post-id');
							var versionId = $(this).data('version-id');
							var versionNumber = $(this).data('version-number');
							
							if (confirm('<?php echo esc_js(__('Are you sure you want to restore version', 'test-method-workflow')); ?> ' + versionNumber + '? <?php echo esc_js(__('This will revert the current content to this version.', 'test-method-workflow')); ?>')) {
								$(this).prop('disabled', true).text('<?php echo esc_js(__('Restoring...', 'test-method-workflow')); ?>');
								
								$.ajax({
									url: ajaxurl,
									type: 'POST',
									data: {
										action: 'restore_test_method_version',
										post_id: postId,
										version_id: versionId,
										nonce: '<?php echo wp_create_nonce('test_method_version_restore'); ?>'
									},
									success: function(response) {
										if (response.success) {
											alert(response.data.message);
											location.reload();
										} else {
											alert(response.data || '<?php echo esc_js(__('An error occurred', 'test-method-workflow')); ?>');
											$('.restore-version-btn').prop('disabled', false).text('<?php echo esc_js(__('Restore', 'test-method-workflow')); ?>');
										}
									},
									error: function() {
										alert('<?php echo esc_js(__('An error occurred. Please try again.', 'test-method-workflow')); ?>');
										$('.restore-version-btn').prop('disabled', false).text('<?php echo esc_js(__('Restore', 'test-method-workflow')); ?>');
									}
								});
							}
						});
						
						// Close comparison modal
						$('#close-comparison').on('click', function() {
							$('#version-comparison-modal').hide();
						});
						
						// Close modal when clicking outside
						$(window).on('click', function(e) {
							if ($(e.target).is('#version-comparison-modal')) {
								$('#version-comparison-modal').hide();
							}
						});
					});
					</script>
					
					<style>
					.version-info {
						background-color: #f0f9ff;
						padding: 12px;
						border-left: 4px solid #0073aa;
						margin-bottom: 15px;
					}
					.create-new-version-section {
						border-radius: 4px;
					}
					.version-type-selection label {
						display: block;
						margin-bottom: 5px;
					}
					.status-approved {
						color: #00a32a;
						font-weight: bold;
					}
					.status-rejected {
						color: #d63638;
						font-weight: bold;
					}
					</style>
					<?php
				}
			}
					
						/**
						 * Save workflow meta
						 */
						public function save_workflow_meta($post_id, $post) {
							// Check if our nonce is set
							if (!isset($_POST["test_method_workflow_nonce"])) {
								return;
							}
						
							// Verify the nonce
							if (!wp_verify_nonce($_POST["test_method_workflow_nonce"], "test_method_workflow_meta_box")) {
								return;
							}
						
							// If this is an autosave, we don't want to do anything
							if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) {
								return;
							}
						
							// Check post type - only process our workflow post types
							if (!in_array($post->post_type, array('test_method', 'ccg-version', 'tp-version'))) {
								return;
							}
							
							// Check user permissions
							$cap_type = str_replace('-', '_', $post->post_type);
							if (!current_user_can("edit_{$cap_type}", $post_id)) {
								return;
							}
							
							// For test_method, only handle lock status 
							if ($post->post_type === 'test_method') {
								// Only save lock status
								if (isset($_POST['is_locked'])) {
									update_post_meta($post_id, '_is_locked', $_POST['is_locked'] ? true : false);
								}
								return;
							}
							
							// For ccg-version and tp-version, handle workflow status and related test method
							
							// Get the current workflow status
							$workflow_status = get_post_meta($post_id, "_workflow_status", true);
							
							// If no workflow status is set yet, initialize it to 'draft'
							if (!$workflow_status) {
								update_post_meta($post_id, "_workflow_status", "draft");
							}
							
							// Save related test method if provided
							if (isset($_POST['related_test_method'])) {
								update_post_meta($post_id, '_related_test_method', intval($_POST['related_test_method']));
							}
							
							// Save assigned approvers if provided
							if (isset($_POST['assigned_approvers']) && is_array($_POST['assigned_approvers'])) {
								$approvers = array_map('intval', $_POST['assigned_approvers']);
								update_post_meta($post_id, '_assigned_approvers', $approvers);
							}
							
							// Initialize approvals array if it doesn't exist
							$approvals = get_post_meta($post_id, "_approvals", true);
							if (!is_array($approvals)) {
								update_post_meta($post_id, "_approvals", array());
							}
							
							// Save version data if provided
							if (isset($_POST["version_update_type"]) && $_POST["version_update_type"] !== "none") {
								$current_version = get_post_meta($post_id, "_current_version_number", true);
								if (empty($current_version)) {
									$current_version = "0.1";
								}
						
								// Parse version into major and minor
								$version_parts = explode(".", $current_version);
								$major = isset($version_parts[0]) ? intval($version_parts[0]) : 0;
								$minor = isset($version_parts[1]) ? intval($version_parts[1]) : 0;
						
								$new_version = $current_version; // Default to no change
						
								switch ($_POST["version_update_type"]) {
									case "minor":
										$new_version = $major . "." . ($minor + 1);
										break;
						
									case "major":
										$new_version = ($major + 1) . ".0";
										break;
						
									case "custom":
										if (!empty($_POST["custom_version"])) {
											$new_version = sanitize_text_field($_POST["custom_version"]);
										}
										break;
								}
						
								// Update version if changed
								if ($new_version !== $current_version) {
									update_post_meta($post_id, "_current_version_number", $new_version);
									
									// Add version change to revision history
									$this->add_to_revision_history($post_id, "version change from {$current_version} to {$new_version}");
								}
							}
							
							// Save version note
							if (isset($_POST["version_note"])) {
								$version_note = sanitize_textarea_field($_POST["version_note"]);
								update_post_meta($post_id, "_cpt_version_note", $version_note);
							}
						}
					
						/**
						 * Prevent unauthorized publishing
						 */
						public function prevent_unauthorized_publishing($post_id, $post) {
							// Skip if this is an autosave
							if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
								return;
							}
							
							// Skip if this is our custom AJAX publishing or if we're currently handling an approved publish
							if ((defined("DOING_AJAX") && DOING_AJAX && isset($_POST["action"]) && $_POST["action"] === "publish_approved_post") || 
								defined("TMW_PUBLISHING_APPROVED")) {
								return;
							}
							
							// Only apply to workflow post types
							if (!in_array($post->post_type, array('test_method', 'ccg-version', 'tp-version'))) {
								return;
							}
							
							// Only check on status transition to publish
							if ($post->post_status !== "publish") {
								return;
							}
							
							// Special handling for test_method - any admin can publish/update
							if ($post->post_type === 'test_method') {
								$user = wp_get_current_user();
								$user_roles = (array) $user->roles;
								
								// Only admin and tp_admin can publish test_method
								if (!array_intersect($user_roles, array('tp_admin', 'administrator'))) {
									remove_action('save_post', array($this, "prevent_unauthorized_publishing"), 10);
									
									wp_update_post(array(
										'ID' => $post_id,
										'post_status' => 'draft',
									));
									
									// Add flag for admin notice
									set_transient("tmw_publish_denied_" . $post_id, true, 60);
									
									add_action('save_post', array($this, "prevent_unauthorized_publishing"), 10, 2);
								}
								
								return;
							}
							
							// For ccg-version and tp-version, check workflow status and approvals
							
							// Get current user role
							$user = wp_get_current_user();
							$user_roles = (array) $user->roles;
							
							// If user is admin or tp_admin, check if post is approved
							if (array_intersect($user_roles, array('administrator', 'tp_admin'))) {
								// Get the previous status
								$previous_status = get_post_meta($post_id, '_previous_status', true);
								
								// Check if this is a previously published post (an update to existing content)
								if ($previous_status === 'publish') {
									// Allow the update to proceed without requiring approval
									// Still lock the post
									update_post_meta($post_id, '_is_locked', true);
									update_post_meta($post_id, '_workflow_status', "publish");
									return;
								}
								
								// For new posts, check approval status
								$workflow_status = get_post_meta($post_id, "_workflow_status", true);
								$approvals = get_post_meta($post_id, "_approvals", true);
								$approval_count = is_array($approvals) ? count($approvals) : 0;
								
								if ($workflow_status !== "approved" || $approval_count < 2) {
									// Stop publishing
									remove_action('save_post', array($this, "prevent_unauthorized_publishing"), 10);
									
									wp_update_post(array(
										'ID' => $post_id,
										'post_status' => 'draft',
									));
									
									// Add flag for admin notice
									set_transient("tmw_approval_required_" . $post_id, true, 60);
									
									add_action('save_post', array($this, "prevent_unauthorized_publishing"), 10, 2);
									
									return;
								}
								
								// Post is approved and being published by admin/tp_admin
								// Lock the post and update the related test_method
								update_post_meta($post_id, "_is_locked", true);
								update_post_meta($post_id, "_workflow_status", "publish");
								
								// Update related test_method if one is set
								$related_test_method_id = get_post_meta($post_id, '_related_test_method', true);
								if ($related_test_method_id) {
									$this->update_related_test_method($post_id, $related_test_method_id);
								}
								
								return;
							}
							
							// For non-admin users, prevent publishing entirely
							remove_action('save_post', array($this, "prevent_unauthorized_publishing"), 10);
							
							wp_update_post(array(
								'ID' => $post_id,
								'post_status' => 'draft',
							));
							
							// Add flag for admin notice
							set_transient("tmw_publish_denied_" . $post_id, true, 60);
							
							add_action('save_post', array($this, "prevent_unauthorized_publishing"), 10, 2);
						}
						
						/**
						 * Update related test_method with version content
						 */
						private function update_related_test_method($version_id, $test_method_id) {
							$version_post = get_post($version_id);
							$test_method = get_post($test_method_id);
							
							if (!$version_post || !$test_method) {
								return;
							}
							
							// Update test_method with version content
							wp_update_post(array(
								'ID' => $test_method_id,
								'post_content' => $version_post->post_content,
								// Keep the original title
							));
							
							// Get version number from version post
							$version_number = get_post_meta($version_id, '_current_version_number', true);
							if (!empty($version_number)) {
								// Update test_method version number
								update_post_meta($test_method_id, '_current_version_number', $version_number);
							}
							
							// Lock the test_method
							update_post_meta($test_method_id, '_is_locked', true);
							
							// Add version info to test_method revision history
							$this->add_to_revision_history(
								$test_method_id, 
								'updated from ' . $version_post->post_type . ' version: ' . $version_number
							);
						}
					
						/**
						 * Display admin notices for workflow actions
						 */
						public function workflow_admin_notices() {
							global $post;
						
							if (!$post) {
								return;
							}
						
							$post_id = $post->ID;
							
							// Check if this is a workflow post type
							if (!in_array($post->post_type, array('test_method', 'ccg-version', 'tp-version'))) {
								return;
							}
						
							// Publish denied notice
							if (get_transient("tmw_publish_denied_" . $post_id)) {
								delete_transient("tmw_publish_denied_" . $post_id);
								?>
								<div class="notice notice-error is-dismissible">
									<p><?php _e("You do not have permission to publish this content. Only TP Admin or Administrator can publish approved content.", "test-method-workflow"); ?></p>
								</div>
								<?php
							}
							
							// Approval required notice
							if (get_transient("tmw_approval_required_" . $post_id)) {
								delete_transient("tmw_approval_required_" . $post_id);
								?>
								<div class="notice notice-error is-dismissible">
									<p><?php _e("This content requires two approvals before it can be published.", "test-method-workflow"); ?></p>
								</div>
								<?php
							}
							
							// Version restored notice
							if (isset($_GET["version_restored"]) && $_GET["version_restored"] == 1) {
								?>
								<div class="notice notice-success is-dismissible">
									<p><?php _e("Previous version successfully restored. Save to keep these changes.", "test-method-workflow"); ?></p>
								</div>
								<?php
							}
							
							// Approval canceled notice
							if (get_transient("tmw_approval_canceled_" . $post_id)) {
								delete_transient("tmw_approval_canceled_" . $post_id);
								?>
								<div class="notice notice-info is-dismissible">
									<p><?php _e("The approval request has been canceled. The content is now in draft status.", "test-method-workflow"); ?></p>
								</div>
								<?php
							}
						}
						
						/**
						 * AJAX handler for restoring a version
						 */
						public function restore_test_method_version() {
							// Check nonce
							check_ajax_referer('test_method_version_restore', 'nonce');
							
							// Check permissions - only admins can restore
							if (!current_user_can('administrator') && !current_user_can('tp_admin')) {
								wp_send_json_error(__('Permission denied', 'test-method-workflow'));
								return;
							}
							
							$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
							$version_id = isset($_POST['version_id']) ? intval($_POST['version_id']) : 0;
							
							if (!$post_id || !$version_id) {
								wp_send_json_error(__('Invalid parameters', 'test-method-workflow'));
								return;
							}
							
							// Get both posts
							$current_post = get_post($post_id);
							$version_post = get_post($version_id);
							
							if (!$current_post || !$version_post) {
								wp_send_json_error(__('Posts not found', 'test-method-workflow'));
								return;
							}
							
							// Store current content for history
							$old_content = $current_post->post_content;
							
							// Update current post with version content
							wp_update_post(array(
								'ID' => $post_id,
								'post_content' => $version_post->post_content
							));
							
							// Get version number
							$version_number = get_post_meta($version_id, "_current_version_number", true);
							
							// Update revision history
							$revision_history = $this->get_revision_history($post_id);
							
							$revision_history[] = array(
								'version' => count($revision_history) + 1,
								'user_id' => get_current_user_id(),
								'date' => time(),
								'status' => 'version_restored',
								'version_number' => $version_number,
								'note' => sprintf(
									__('Restored content from version %s', 'test-method-workflow'),
									$version_number
								)
							);
							
							update_post_meta($post_id, "_revision_history", $revision_history);
							
							// Set a notice that will be shown on page reload
							set_transient('tmw_version_restored_' . $post_id, true, 60);
							
							wp_send_json_success(array(
								'message' => __('Version restored successfully', 'test-method-workflow')
							));
						}
						
						/**
						 * Clean content for diff comparison
						 */
						private function clean_content_for_diff($content) {
							// Remove shortcodes
							$content = strip_shortcodes($content);
							
							// Remove HTML comments
							$content = preg_replace('/<!--(.|\s)*?-->/', '', $content);
							
							// Convert HTML entities
							$content = html_entity_decode($content);
							
							// First save HTML tags with line breaks
							$content = str_replace(array('<br>', '<br />', '<br/>'), "\n", $content);
							$content = str_replace(array('<p>', '</p>', '<div>', '</div>', '<h1>', '</h1>', '<h2>', '</h2>', '<h3>', '</h3>'), "\n", $content);
							
							// Then strip remaining tags
							$content = strip_tags($content);
							
							// Normalize whitespace
							$content = preg_replace('/\s+/', ' ', $content);
							
							// Trim lines
							$lines = explode("\n", $content);
							$lines = array_map('trim', $lines);
							$content = implode("\n", $lines);
							
							return $content;
						}
						
						/**
						 * AJAX handler for resetting related test method
						 */
						public function reset_related_test_method() {
							// Check nonce for security
							check_ajax_referer('test_method_workflow', 'nonce');
							
							// Check permissions - only admins can reset
							if (!current_user_can('administrator') && !current_user_can('tp_admin')) {
								wp_send_json_error(__('Permission denied', 'test-method-workflow'));
								return;
							}
							
							$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
							
							if (!$post_id) {
								wp_send_json_error(__('Invalid post ID', 'test-method-workflow'));
								return;
							}
							
							// Log this action
							$user = wp_get_current_user();
							$old_related_id = get_post_meta($post_id, '_related_test_method', true);
							$old_related_title = '';
							
							if ($old_related_id) {
								$old_related = get_post($old_related_id);
								if ($old_related) {
									$old_related_title = $old_related->post_title;
								}
							}
							
							// Add to revision history
							$revision_history = $this->get_revision_history($post_id);
							if (!is_array($revision_history)) {
								$revision_history = array();
							}
							
							$revision_history[] = array(
								"version" => count($revision_history) + 1,
								"user_id" => get_current_user_id(),
								"date" => time(),
								"status" => 'related test method reset',
								"notes" => sprintf('Related test method "%s" was reset by %s', 
												 $old_related_title, $user->display_name),
								"version_number" => get_post_meta($post_id, '_current_version_number', true)
							);
							
							update_post_meta($post_id, "_revision_history", $revision_history);
							
							// Reset the related test method
							delete_post_meta($post_id, '_related_test_method');
							
							wp_send_json_success(array(
								'message' => __('Related test method has been reset. You can now select a different test method.', 'test-method-workflow'),
							));
						}
						
						/**
						 * AJAX handler for cancelling approval request
						 */
						public function cancel_approval_request() {
							// Check nonce
							check_ajax_referer("test_method_workflow", "nonce");
							
							$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
							
							if (!$post_id) {
								wp_send_json_error("Invalid post ID");
								return;
							}
							
							$post = get_post($post_id);
							
							// Verify user is the author
							if ($post->post_author != get_current_user_id() && !current_user_can('edit_others_' . str_replace('-', '_', $post->post_type) . 's')) {
								wp_send_json_error("Permission denied");
								return;
							}
							
							// Return to draft status
							update_post_meta($post_id, '_workflow_status', 'draft');
							update_post_meta($post_id, '_awaiting_final_approval', false);
							update_post_meta($post_id, '_cancel_approval', true);
							
							// Update post status
							wp_update_post(array(
								'ID' => $post_id,
								'post_status' => 'draft',
							));
							
							// Add to revision history
							$this->add_to_revision_history($post_id, 'approval request canceled');
							
							// Set notice
							set_transient("tmw_approval_canceled_" . $post_id, true, 60);
							
							wp_send_json_success(array(
								'message' => __('Approval request has been canceled. You can now edit the content.', 'test-method-workflow'),
								'reload' => true,
							));
						}
					
						/**
						 * submit_for_review method 
						 * Only increment version when explicitly requested
						 */
						public function submit_for_review() {
							// Check nonce for security
							check_ajax_referer("test_method_workflow", "nonce");
							
							// Get post ID and validate
							$post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;
							
							// ... (existing validation code) ...
							
							// Get version information if provided
							$version_type = isset($_POST["version_type"]) 
								? sanitize_text_field($_POST["version_type"])
								: "";
							
							// Get current version number
							$current_version = get_post_meta($post_id, "_current_version_number", true);
							
							// Check if this is a first submission (keep this logic)
							if (empty($current_version) || $current_version === '0.0') {
								// For first submission, always increment to 0.1
								update_post_meta($post_id, "_current_version_number", "0.1");
							} else if (!empty($version_type) && $version_type !== "none") {
								// IMPORTANT CHANGE: Only update version if explicitly requested
								// and not set to "none" (meaning no change)
								$version_parts = explode(".", $current_version);
								$major = isset($version_parts[0]) ? intval($version_parts[0]) : 0;
								$minor = isset($version_parts[1]) ? intval($version_parts[1]) : 0;
							
								if ($version_type === "minor") {
									$new_version = $major . "." . ($minor + 1);
									update_post_meta($post_id, "_current_version_number", $new_version);
									update_post_meta($post_id, "_cpt_version", "minor");
								} elseif ($version_type === "major") {
									$new_version = ($major + 1) . ".0";
									update_post_meta($post_id, "_current_version_number", $new_version);
									update_post_meta($post_id, "_cpt_version", "major");
								}
							}
							
							// Add a flag to prevent other processes from modifying the version
							update_post_meta($post_id, "_version_already_set", true);
							
							// Reset cancel approval flag
							delete_post_meta($post_id, '_cancel_approval');
							
							// Continue with the rest of the method...
							
							// Update workflow status
							update_post_meta($post_id, "_workflow_status", "pending_review");
							
							// Update post status - use 'pending' for WordPress native status
							wp_update_post(array(
								"ID" => $post_id,
								"post_status" => "pending",
							));
							
							// Reset approvals
							update_post_meta($post_id, "_approvals", array());
							
							// Add to revision history
							$this->add_to_revision_history($post_id, "submitted for review");
							
							// Send notification to approvers
							do_action("tmw_send_notification", $post_id, "submitted_for_review");
							
							wp_send_json_success(array(
								"message" => __("Content submitted for review", "test-method-workflow"),
								"reload" => true,
							));
						}				
						/**
						 * AJAX handler for creating a new version
						 */
						public function create_new_version() {
							// Check nonce
							check_ajax_referer('test_method_version_create', 'nonce');
							
							$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
							$version_type = isset($_POST['version_type']) ? sanitize_text_field($_POST['version_type']) : 'minor';
							$version_note = isset($_POST['version_note']) ? sanitize_textarea_field($_POST['version_note']) : '';
							
							if (!$post_id) {
								wp_send_json_error('Invalid post ID');
								return;
							}
							
							// Check if user has permission
							if (!current_user_can('edit_post', $post_id)) {
								wp_send_json_error('Permission denied');
								return;
							}
							
							$post = get_post($post_id);
							
							if (!$post || !in_array($post->post_type, array('ccg-version', 'tp-version'))) {
								wp_send_json_error('Invalid post type');
								return;
							}
							
							// Get current version
							$current_version = get_post_meta($post_id, "_current_version_number", true);
							if (empty($current_version)) {
								$current_version = '0.1';
							}
							
							// Calculate new version
							$version_parts = explode('.', $current_version);
							$major = isset($version_parts[0]) ? intval($version_parts[0]) : 0;
							$minor = isset($version_parts[1]) ? intval($version_parts[1]) : 0;
							
							if ($version_type === 'minor') {
								$new_version = $major . '.' . ($minor + 1);
							} else if ($version_type === 'major') {
								$new_version = ($major + 1) . '.0';
							} else {
								$new_version = $current_version; // Default no change
							}
							
							// Get the related test method
							$related_test_method_id = get_post_meta($post_id, '_related_test_method', true);
							
							// Create a new draft
							$new_post_data = array(
								'post_title' => $post->post_title . ' - v' . $new_version,
								'post_content' => $post->post_content,
								'post_excerpt' => $post->post_excerpt,
								'post_type' => $post->post_type,
								'post_status' => 'draft',
								'post_author' => get_current_user_id(),
								'comment_status' => $post->comment_status,
								'ping_status' => $post->ping_status,
							);
							
							$new_post_id = wp_insert_post($new_post_data);
							
							if (is_wp_error($new_post_id)) {
								wp_send_json_error($new_post_id->get_error_message());
								return;
							}
							
							// Copy relevant meta 
							if ($related_test_method_id) {
								update_post_meta($new_post_id, '_related_test_method', $related_test_method_id);
							}
							
							// Set version information
							update_post_meta($new_post_id, "_current_version_number", $new_version);
							update_post_meta($new_post_id, "_cpt_version_note", $version_note);
							update_post_meta($new_post_id, "_workflow_status", 'draft');
							update_post_meta($new_post_id, "_version_parent", $post_id);
							update_post_meta($new_post_id, "_approvals", array()); // Reset approvals
							
							// Copy taxonomies
							$taxonomies = get_object_taxonomies($post->post_type);
							foreach ($taxonomies as $taxonomy) {
								$terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
								if (!empty($terms) && !is_wp_error($terms)) {
									wp_set_object_terms($new_post_id, $terms, $taxonomy);
								}
							}
							
							// Update version history on original post
							$revision_history = $this->get_revision_history($post_id);
							
							$revision_history[] = array(
								'version' => count($revision_history) + 1,
								'user_id' => get_current_user_id(),
								'date' => time(),
								'status' => 'new_version_created',
								'version_number' => $current_version,
								'next_version' => $new_version,
								'note' => $version_note,
								'version_id' => $new_post_id
							);
							
							update_post_meta($post_id, "_revision_history", $revision_history);
							
							// Add basic version history to new post
							$new_history = array(
								array(
									"version" => 1,
									"user_id" => get_current_user_id(),
									"date" => time(),
									"status" => "created_from_previous_version",
									"version_number" => $new_version,
									"parent_id" => $post_id,
									"parent_version" => $current_version,
									"note" => $version_note
								)
							);
							update_post_meta($new_post_id, "_revision_history", $new_history);
							
							wp_send_json_success(array(
								'message' => sprintf(
									__('New version %s created successfully. You will be redirected to edit it.', 'test-method-workflow'),
									$new_version
								),
								'redirect' => get_edit_post_link($new_post_id, 'url')
							));
						}
						
						/**
						 * AJAX handler for comparing versions
						 */
						public function compare_test_method_versions() {
							// Check nonce
							check_ajax_referer('test_method_version_comparison', 'nonce');
							
							$current_id = isset($_POST['current_id']) ? intval($_POST['current_id']) : 0;
							$version_id = isset($_POST['version_id']) ? intval($_POST['version_id']) : 0;
							$version_number = isset($_POST['version_number']) ? sanitize_text_field($_POST['version_number']) : '';
							
							if (!$current_id || !$version_id) {
								wp_send_json_error('Invalid parameters');
								return;
							}
							
							// Get both posts
							$current_post = get_post($current_id);
							$version_post = get_post($version_id);
							
							if (!$current_post || !$version_post) {
								wp_send_json_error('Posts not found');
								return;
							}
							
							// Clean content for diff comparison
							$left_text = $this->clean_content_for_diff($version_post->post_content);
							$right_text = $this->clean_content_for_diff($current_post->post_content);
							
							// Split content into lines
							$left_lines = explode("\n", $left_text);
							$right_lines = explode("\n", $right_text);
							
							// Use WordPress diff engine
							if (!class_exists('WP_Text_Diff_Renderer_Table')) {
								require_once ABSPATH . WPINC . '/wp-diff.php';
							}
							
							$diff = new \Text_Diff($left_lines, $right_lines);
							$renderer = new \WP_Text_Diff_Renderer_Table(array(
								'show_split_view' => true,
							));
							
							// Get current version for display
							$current_version = get_post_meta($current_id, "_current_version_number", true);
							if (empty($current_version)) {
								$current_version = 'Current';
							}
							
							// Build output HTML
							$output = '<div class="diff-view">';
							$output .= '<h3>' . sprintf(
								__('Comparing Version %s with Current Version %s', 'test-method-workflow'),
								esc_html($version_number),
								esc_html($current_version)
							) . '</h3>';
							
							if ($diff->isEmpty()) {
								$output .= '<p class="notice notice-info">' . __('No textual differences found between versions.', 'test-method-workflow') . '</p>';
							} else {
								$output .= $renderer->render($diff);
							}
							
							$output .= '</div>';
							
							wp_send_json_success(array(
								'html' => $output
							));
						}
								
						/**
						 * AJAX handler for approving content
						 */
						public function approve_test_method() {
							// Check nonce for security
							check_ajax_referer("test_method_workflow", "nonce");
						
							$post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;
							$comment = isset($_POST["comment"]) ? sanitize_textarea_field($_POST["comment"]) : "";
						
							if (!$post_id) {
								wp_send_json_error("Invalid post ID");
								return;
							}
							
							$post = get_post($post_id);
							$post_type_cap = str_replace('-', '_', $post->post_type) . 's';
							
							// Check permissions
							if (!current_user_can("approve_" . $post_type_cap)) {
								wp_send_json_error("Permission denied");
								return;
							}
							
							// Get current approvals
							$approvals = $this->get_post_approvals($post_id);
							$current_user_id = get_current_user_id();
						
							// Get current version
							$current_version = get_post_meta($post_id, "_current_version_number", true);
							if (empty($current_version)) {
								$current_version = '0.1';
							}
						
							// Check if user has already approved
							foreach ($approvals as $key => $approval) {
								if ($approval["user_id"] == $current_user_id) {
									// Update existing approval
									$approvals[$key] = array(
										"user_id" => $current_user_id,
										"date" => time(),
										"status" => "approved",
										"comment" => $comment,
										"version" => $current_version  // Add version info
									);
						
									update_post_meta($post_id, "_approvals", $approvals);
						
									wp_send_json_success(array(
										"message" => __("Your approval has been updated", "test-method-workflow"),
										"approval_count" => count($approvals),
									));
									return;
								}
							}
						
							// Add new approval
							$approvals[] = array(
								"user_id" => $current_user_id,
								"date" => time(),
								"status" => "approved",
								"comment" => $comment,
								"version" => $current_version  // Add version info
							);
						
							update_post_meta($post_id, "_approvals", $approvals);
						
							// Update workflow status if we have two approvals
							if (count($approvals) >= 2) {
								update_post_meta($post_id, "_workflow_status", "approved");
								update_post_meta($post_id, "_awaiting_final_approval", false);
						
								// Add to revision history
								$this->add_to_revision_history($post_id, "approved");
						
								// Send notification to TP Admins
								do_action("tmw_send_notification", $post_id, "approved");
							} elseif (count($approvals) == 1) {
								// First approval
								$awaiting_final = get_post_meta($post_id, "_awaiting_final_approval", true);
								if ($awaiting_final) {
									update_post_meta($post_id, "_workflow_status", "pending_final_approval");
								}
							}
							
							wp_send_json_success(array(
								"message" => __("Content approved successfully", "test-method-workflow"),
								"approval_count" => count($approvals),
								"reload" => true,
							));
						}
						
						/**
						 * AJAX handler for rejecting content
						 */
						public function reject_test_method() {
							// Check nonce for security
							check_ajax_referer("test_method_workflow", "nonce");
						
							$post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;
							$comment = isset($_POST["comment"]) ? sanitize_textarea_field($_POST["comment"]) : "";
						
							if (!$post_id) {
								wp_send_json_error("Invalid post ID");
								return;
							}
							
							$post = get_post($post_id);
							$post_type_cap = str_replace('-', '_', $post->post_type) . 's';
							
							// Check permissions
							if (!current_user_can("reject_" . $post_type_cap)) {
								wp_send_json_error("Permission denied");
								return;
							}
						
							if (empty($comment)) {
								wp_send_json_error(__("Please provide rejection comments", "test-method-workflow"));
								return;
							}
						
							// Get current approvals
							$approvals = $this->get_post_approvals($post_id);
							$current_user_id = get_current_user_id();
						
							// Get current version
							$current_version = get_post_meta($post_id, "_current_version_number", true);
							if (empty($current_version)) {
								$current_version = '0.1';
							}
						
							// Check if user has already approved/rejected
							$updated = false;
							foreach ($approvals as $key => $approval) {
								if ($approval["user_id"] == $current_user_id) {
									// Update existing entry instead of adding a new one
									$approvals[$key] = array(
										"user_id" => $current_user_id,
										"date" => time(),
										"status" => "rejected",
										"comment" => $comment,
										"version" => $current_version  // Add version info
									);
									$updated = true;
									break;
								}
							}
							
							if (!$updated) {
								// Add new rejection
								$approvals[] = array(
									"user_id" => $current_user_id,
									"date" => time(),
									"status" => "rejected",
									"comment" => $comment,
									"version" => $current_version  // Add version info
								);
							}
						
							update_post_meta($post_id, "_approvals", $approvals);
							update_post_meta($post_id, "_workflow_status", "rejected");
							update_post_meta($post_id, "_awaiting_final_approval", false);
						
							// Update post status
							wp_update_post(array(
								"ID" => $post_id,
								"post_status" => "draft",
							));
						
							// Add to revision history
							$this->add_to_revision_history($post_id, "rejected");
						
							// Send notification
							do_action("tmw_send_notification", $post_id, "rejected");
						
							wp_send_json_success(array(
								"message" => __("Content rejected", "test-method-workflow"),
								"reload" => true,
							));
						}
						
						/**
						 * AJAX handler for submitting for final approval
						 */
						public function submit_for_final_approval() {
							// Check nonce for security
							check_ajax_referer("test_method_workflow", "nonce");
						
							$post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;
						
							if (!$post_id) {
								wp_send_json_error("Invalid post ID");
								return;
							}
							
							$post = get_post($post_id);
							
							// Check if the current user is either the author or has admin permissions
							if ($post->post_author != get_current_user_id() && 
								!current_user_can('edit_others_' . str_replace('-', '_', $post->post_type) . 's')) {
								wp_send_json_error("Permission denied");
								return;
							}
							
							// Update meta to indicate awaiting final approval
							update_post_meta($post_id, "_awaiting_final_approval", true);
							update_post_meta($post_id, "_workflow_status", "pending_final_approval");
						
							// Add to revision history
							$this->add_to_revision_history($post_id, "submitted for final approval");
						
							// Send notification
							do_action("tmw_send_notification", $post_id, "final_approval_requested");
						
							wp_send_json_success(array(
								"message" => __("Request for final approval sent successfully", "test-method-workflow"),
								"reload" => true,
							));
						}
						
						/**
						 * AJAX handler for publishing approved content
						 */
						public function publish_approved_post() {
							// Check nonce for security
							check_ajax_referer("test_method_workflow", "nonce");
						
							$post_id = isset($_POST["post_id"]) ? intval($_POST["post_id"]) : 0;
						
							if (!$post_id) {
								wp_send_json_error("Invalid post ID");
								return;
							}
							
							$post = get_post($post_id);
							$post_type_cap = str_replace('-', '_', $post->post_type) . 's';
							
							// Check if user can publish
							if (!current_user_can("publish_" . $post_type_cap)) {
								wp_send_json_error("Permission denied");
								return;
							}
						
							// Verify content is approved
							$workflow_status = get_post_meta($post_id, "_workflow_status", true);
							$approvals = get_post_meta($post_id, "_approvals", true);
							$approval_count = is_array($approvals) ? count($approvals) : 0;
						
							if ($workflow_status !== "approved" || $approval_count < 2) {
								wp_send_json_error(__("This content has not been fully approved.", "test-method-workflow"));
								return;
							}
							
							// Define constant to bypass the prevention hook
							if (!defined("TMW_PUBLISHING_APPROVED")) {
								define("TMW_PUBLISHING_APPROVED", true);
							}
						
							// First update the workflow status and lock the post BEFORE publishing
							update_post_meta($post_id, "_workflow_status", "publish");
							update_post_meta($post_id, "_is_locked", true);
						
							// Now publish the post
							$result = wp_update_post(array(
								"ID" => $post_id,
								"post_status" => "publish",
							), true);
						
							// Check if there was an error updating the post
							if (is_wp_error($result)) {
								wp_send_json_error(__("Error publishing content:", "test-method-workflow") . " " . $result->get_error_message());
								return;
							}
						
							// Add to revision history
							$this->add_to_revision_history($post_id, 'published');
						
							// Send notification
							do_action("tmw_send_notification", $post_id, "published");
							
							// Update related test_method if one is set
							$related_test_method_id = get_post_meta($post_id, '_related_test_method', true);
							if ($related_test_method_id) {
								$this->update_related_test_method($post_id, $related_test_method_id);
							}
						
							wp_send_json_success(array(
								"message" => __("Content published successfully.", "test-method-workflow"),
								"reload" => true,
							));
						}
						
						/**
						 * Get post approvals
						 */
						private function get_post_approvals($post_id) {
							$approvals = get_post_meta($post_id, "_approvals", true);
							return is_array($approvals) ? $approvals : array();
						}
						
						/**
						 * Get revision history
						 */
						private function get_revision_history($post_id) {
							$revision_history = get_post_meta($post_id, "_revision_history", true);
							return is_array($revision_history) ? $revision_history : array();
						}
						
						/**
						 * Add entry to revision history
						 */
						private function add_to_revision_history($post_id, $status) {
							$revision_history = $this->get_revision_history($post_id);
							$current_version = get_post_meta($post_id, "_current_version_number", true);
							
							// Ensure we always have a valid version number
							if (empty($current_version)) {
								$current_version = '0.1';
							}
							
							$revision_history[] = array(
								"version" => count($revision_history) + 1,
								"user_id" => get_current_user_id(),
								"date" => time(),
								"status" => $status,
								"version_number" => $current_version  // Make sure this is consistently set
							);
							
							update_post_meta($post_id, "_revision_history", $revision_history);
						}
					}
