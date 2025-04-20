<?php
/**
 * Admin UI Management
 *
 * @package TestMethodWorkflow
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Test Method Admin UI class
 */
class TestMethod_AdminUI {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Add status filter dropdown
		add_action('restrict_manage_posts', array($this, 'add_workflow_status_filter'));
		
		// Modify admin list query
		add_filter('parse_query', array($this, 'modify_admin_list_query'));
		
		// Add admin notices
		add_action('admin_notices', array($this, 'admin_notices'));
		
		// Add custom row actions
		add_filter('post_row_actions', array($this, 'modify_row_actions'), 10, 2);
		
		// Enqueue admin styles and scripts
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
		
		// Add help tab
		add_action('admin_head', array($this, 'add_help_tab'));
		
		// Remove quick edit for locked posts
		add_filter('post_row_actions', array($this, 'remove_quick_edit_for_locked'), 10, 2);
	}
	
	/**
	 * Add workflow status filter dropdown
	 */
/**
	  * Add workflow status filter dropdown
	  */
	 public function add_workflow_status_filter($post_type) {
		 if ($post_type !== 'test_method') {
			 return;
		 }
		 
		 $workflow_status = isset($_GET['workflow_status']) ? $_GET['workflow_status'] : '';
		 
		 $statuses = array(
			 '' => __('All Statuses', 'test-method-workflow'),
			 'draft' => __('Draft', 'test-method-workflow'),
			 'pending_review' => __('Pending Review', 'test-method-workflow'),
			 'pending_final_approval' => __('Awaiting Final Approval', 'test-method-workflow'),
			 'approved' => __('Approved', 'test-method-workflow'),
			 'rejected' => __('Rejected', 'test-method-workflow'),
			 'locked' => __('Locked', 'test-method-workflow')
		 );
		 
		 echo '<select name="workflow_status">';
		 
		 foreach ($statuses as $status => $label) {
			 echo '<option value="' . esc_attr($status) . '" ' . selected($workflow_status, $status, false) . '>' . 
				  esc_html($label) . '</option>';
		 }
		 
		 echo '</select>';
	 }
			
			/**
			 * Modify admin list query
			 */
			public function modify_admin_list_query($query) {
				global $pagenow;
				
				if (is_admin() && $pagenow == 'edit.php' && isset($query->query['post_type']) && 
					$query->query['post_type'] == 'test_method' && isset($_GET['workflow_status']) && 
					!empty($_GET['workflow_status'])) {
						
					$workflow_status = sanitize_text_field($_GET['workflow_status']);
					
					$meta_query = $query->get('meta_query');
					if (!is_array($meta_query)) {
						$meta_query = array();
					}
					
					$meta_query[] = array(
						'key'     => '_workflow_status',
						'value'   => $workflow_status,
						'compare' => '='
					);
					
					$query->set('meta_query', $meta_query);
				}
				
				return $query;
			}
			
			/**
			 * Add admin notices
			 */
			public function admin_notices() {
				global $pagenow, $post;
				
				// Only on post edit screen for test methods
				if ($pagenow != 'post.php' || !$post || $post->post_type != 'test_method') {
					return;
				}
				
				// Display notice for locked posts
				$is_locked = get_post_meta($post->ID, '_is_locked', true);
				if ($is_locked) {
					// Check user role
					$user = wp_get_current_user();
					$user_roles = (array) $user->roles;
					
					if (array_intersect($user_roles, array('administrator', 'tp_admin'))) {
						// Admin notice
						?>
						<div class="notice notice-warning">
							<p>
								<strong><?php _e('This test method is locked.', 'test-method-workflow'); ?></strong> 
								<?php _e('As an administrator, you can make changes, but consider creating a revision instead if this is published.', 'test-method-workflow'); ?>
							</p>
						</div>
						<?php
					} else {
						// Regular user notice
						?>
						<div class="notice notice-error">
							<p>
								<strong><?php _e('This test method is locked and cannot be edited.', 'test-method-workflow'); ?></strong> 
								<?php _e('Please contact an administrator if you need to make changes.', 'test-method-workflow'); ?>
							</p>
						</div>
						<?php
					}
				}
				
				// Display notice for revision posts
				$is_revision = get_post_meta($post->ID, '_is_revision', true);
				if ($is_revision) {
					$parent_id = get_post_meta($post->ID, '_revision_parent', true);
					$parent_post = get_post($parent_id);
					
					if ($parent_post) {
						?>
						<div class="notice notice-info">
							<p>
								<strong><?php _e('This is a revision.', 'test-method-workflow'); ?></strong> 
								<?php printf(__('You are editing a revision of <a href="%s">%s</a>.', 'test-method-workflow'), 
									get_edit_post_link($parent_id), esc_html($parent_post->post_title)); ?>
							</p>
						</div>
						<?php
					}
				}
				
				// Display pending review notice for approvers
				$workflow_status = get_post_meta($post->ID, '_workflow_status', true);
				if ($workflow_status == 'pending_review' || $workflow_status == 'pending_final_approval') {
					$user = wp_get_current_user();
					$user_roles = (array) $user->roles;
					
					if (array_intersect($user_roles, array('tp_approver', 'tp_admin', 'administrator'))) {
						// Check if user has already reviewed
						$user_id = get_current_user_id();
						$approvals = get_post_meta($post->ID, '_approvals', true);
						$already_reviewed = false;
						
						if (is_array($approvals)) {
							foreach ($approvals as $approval) {
								if ($approval['user_id'] == $user_id) {
									$already_reviewed = true;
									break;
								}
							}
						}
						
						if (!$already_reviewed) {
							?>
							<div class="notice notice-info">
								<p>
									<strong><?php _e('This test method needs your review.', 'test-method-workflow'); ?></strong> 
									<?php _e('Please review the content and approve or reject using the buttons in the Test Method Workflow box.', 'test-method-workflow'); ?>
								</p>
							</div>
							<?php
						}
					}
				}
			}
			
			/**
			 * Modify row actions
			 */
			 public function modify_row_actions($actions, $post) {
				 if ($post->post_type !== 'test_method') {
					 return $actions;
				 }
				 
				 // Get workflow information
				 $workflow_status = get_post_meta($post->ID, '_workflow_status', true);
				 $is_locked = get_post_meta($post->ID, '_is_locked', true);
				 $is_revision = get_post_meta($post->ID, '_is_revision', true);
				 
				 // Add custom actions based on status
				 if ($workflow_status == 'pending_review' || $workflow_status == 'pending_final_approval') {
					 $user = wp_get_current_user();
					 $user_roles = (array) $user->roles;
					 
					 if (array_intersect($user_roles, array('tp_approver', 'tp_admin', 'administrator'))) {
						 // Add review action
						 $actions['review'] = '<a href="' . get_edit_post_link($post->ID) . '">' . 
											 __('Review', 'test-method-workflow') . '</a>';
					 }
				 }
				 
				 // Add create revision action for published or locked posts
				 if (($post->post_status == 'publish' || $is_locked) && !$is_revision) {
					 $user = wp_get_current_user();
					 $user_roles = (array) $user->roles;
					 
					 // Only show for users that can create revisions
					 if (array_intersect($user_roles, array('tp_contributor', 'tp_approver', 'tp_admin', 'administrator'))) {
						 if ($is_locked && !array_intersect($user_roles, array('administrator', 'tp_admin'))) {
							 // Non-admins can't create revisions of locked posts
						 } else {
							 $nonce = wp_create_nonce('test_method_revision_nonce');
							 $actions['create_revision'] = '<a href="#" class="create-revision-link" data-post-id="' . $post->ID . '" data-nonce="' . $nonce . '">' . 
														  __('Create Revision', 'test-method-workflow') . '</a>';
						 }
					 }
				 }
				 
				 // Remove edit link for locked posts for non-admins
				 if ($is_locked) {
					 $user = wp_get_current_user();
					 $user_roles = (array) $user->roles;
					 
					 if (!array_intersect($user_roles, array('administrator', 'tp_admin'))) {
						 unset($actions['edit']);
						 unset($actions['inline hide-if-no-js']);
					 }
				 }
				 
				 return $actions;
			 }
			 
			/**
			 * Remove quick edit for locked posts
			 */
			public function remove_quick_edit_for_locked($actions, $post) {
				if ($post->post_type !== 'test_method') {
					return $actions;
				}
				
				$is_locked = get_post_meta($post->ID, '_is_locked', true);
				
				if ($is_locked) {
					$user = wp_get_current_user();
					$user_roles = (array) $user->roles;
					
					if (!array_intersect($user_roles, array('administrator', 'tp_admin'))) {
						unset($actions['inline hide-if-no-js']);
					}
				}
				
				return $actions;
			}
			
			/**
			 * Enqueue admin assets
			 */
			public function enqueue_admin_assets($hook) {
				global $post_type;
				
				// Only load on test method post type pages
				if ($post_type != 'test_method') {
					return;
				}
				
				// Admin list page
				if ($hook == 'edit.php') {
					// Add inline script for create revision links
					add_action('admin_footer', function() {
						?>
						<script type="text/javascript">
						jQuery(document).ready(function($) {
							// Handle create revision links
							$(document).on('click', '.create-revision-link', function(e) {
								e.preventDefault();
								
								var postId = $(this).data('post-id');
								var nonce = $(this).data('nonce');
								
								if (confirm('<?php _e('Are you sure you want to create a new revision of this test method?', 'test-method-workflow'); ?>')) {
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
												alert(response.data || '<?php _e('An error occurred.', 'test-method-workflow'); ?>');
											}
										},
										error: function() {
											alert('<?php _e('An error occurred.', 'test-method-workflow'); ?>');
										}
									});
								}
							});
						});
						</script>
						<?php
					});
				}
			}
			
			/**
			 * Add help tab
			 */
			public function add_help_tab() {
				$screen = get_current_screen();
				
				if (!$screen || $screen->post_type !== 'test_method') {
					return;
				}
				
				// Help tab for test method list
				if ($screen->base === 'edit') {
					$screen->add_help_tab(array(
						'id'      => 'test_method_workflow_help',
						'title'   => __('Test Method Workflow', 'test-method-workflow'),
						'content' => $this->get_list_help_content(),
					));
				}
				
				// Help tab for test method edit
				if ($screen->base === 'post' && $screen->post_type === 'test_method') {
					$screen->add_help_tab(array(
						'id'      => 'test_method_workflow_help',
						'title'   => __('Test Method Workflow', 'test-method-workflow'),
						'content' => $this->get_edit_help_content(),
					));
				}
			}
			
			/**
			 * Fix admin table status display
			 * Add to class-admin-ui.php or modify display_workflow_status_column method
			 */
			public function improved_display_workflow_status_column($column, $post_id) {
				if ($column === 'workflow_status') {
					$workflow_status = get_post_meta($post_id, "_workflow_status", true);
					$post_status = get_post_status($post_id);
					$is_locked = get_post_meta($post_id, "_is_locked", true);
					$is_revision = get_post_meta($post_id, "_is_revision", true);
					
					// Ensure workflow status is consistent with post status
					$this->sync_status_if_needed($post_id, $workflow_status, $post_status);
					
					// Get the updated workflow status (in case it was changed by sync)
					$workflow_status = get_post_meta($post_id, "_workflow_status", true);
					
					// Status display mappings
					$status_classes = array(
						'draft' => 'draft',
						'pending_review' => 'pending',
						'pending_final_approval' => 'final-approval',
						'approved' => 'approved',
						'rejected' => 'rejected',
						'publish' => 'published',
						'locked' => 'locked'
					);
					
					$status_labels = array(
						'draft' => __('Draft', 'test-method-workflow'),
						'pending_review' => __('Pending Review', 'test-method-workflow'),
						'pending_final_approval' => __('Awaiting Final Approval', 'test-method-workflow'),
						'approved' => __('Approved', 'test-method-workflow'),
						'rejected' => __('Rejected', 'test-method-workflow'),
						'publish' => __('Published', 'test-method-workflow'),
						'locked' => __('Locked', 'test-method-workflow')
					);
					
					// If no workflow status, set default based on post status
					if (empty($workflow_status)) {
						$workflow_status = $post_status === 'publish' ? 'publish' : 'draft';
					}
					
					// Get class and label
					$class = isset($status_classes[$workflow_status]) ? $status_classes[$workflow_status] : 'draft';
					$label = isset($status_labels[$workflow_status]) ? $status_labels[$workflow_status] : ucfirst($workflow_status);
					
					// For locked items, override with locked status
					if ($is_locked) {
						$class = 'locked';
						$label = __('Locked', 'test-method-workflow');
					}
					
					// Display status badge
					echo '<span class="status-badge status-' . esc_attr($class) . '">' . esc_html($label) . '</span>';
					
					// Show revision indicator if applicable
					if ($is_revision) {
						$parent_id = get_post_meta($post_id, "_revision_parent", true);
						$parent_post = get_post($parent_id);
						
						if ($parent_post) {
							echo ' <span class="revision-indicator">' . 
								 esc_html__('(Revision of', 'test-method-workflow') . ' ' . 
								 '<a href="' . get_edit_post_link($parent_id) . '">' . 
								 esc_html($parent_post->post_title) . '</a>)</span>';
						}
					}
					
					// Show approval count for items in review
					if ($workflow_status === 'pending_review' || $workflow_status === 'pending_final_approval') {
						$approvals = get_post_meta($post_id, "_approvals", true);
						$approval_count = is_array($approvals) ? count($approvals) : 0;
						
						echo ' <span class="approval-count">(' . 
							 sprintf(__('%d/2 approvals', 'test-method-workflow'), $approval_count) . 
							 ')</span>';
					}
					
					// Show version number
					$version = get_post_meta($post_id, "_current_version_number", true);
					if (!empty($version) && $version !== '0.0') {
						echo ' <span class="version-badge">v' . esc_html($version) . '</span>';
					}
				}
			}
			
			/**
			 * Ensure consistency between workflow status and post status
			 */
			private function sync_status_if_needed($post_id, $workflow_status, $post_status) {
				$needs_update = false;
				$new_workflow_status = $workflow_status;
				$new_post_status = $post_status;
				
				// Rules for status consistency
				if ($post_status === 'publish' && $workflow_status !== 'publish') {
					// Published post should have publish workflow status
					$new_workflow_status = 'publish';
					$needs_update = true;
				} else if ($post_status === 'pending' && !in_array($workflow_status, array('pending_review', 'pending_final_approval'))) {
					// Pending post should have a pending workflow status
					$new_workflow_status = 'pending_review';
					$needs_update = true;
				} else if ($post_status === 'draft' && $workflow_status === 'publish') {
					// Draft post cannot have publish workflow status
					$new_workflow_status = 'draft';
					$needs_update = true;
				}
				
				// Update if needed
				if ($needs_update) {
					update_post_meta($post_id, "_workflow_status", $new_workflow_status);
				}
			}
			
			/**
			 * Add version management help tab
			 * Add to class-version-control.php or class-admin-ui.php
			 */
			public function add_version_management_help_tab() {
				$screen = get_current_screen();
				
				if (!$screen) return;
				
				// Only add to our post types
				if (!in_array($screen->post_type, array('test_method', 'ccg-version', 'tp-version'))) {
					return;
				}
				
				// Add help tabs
				$screen->add_help_tab(array(
					'id' => 'version_management_help',
					'title' => __('Version Management', 'test-method-workflow'),
					'content' => $this->get_version_management_help_content()
				));
				
				$screen->add_help_tab(array(
					'id' => 'version_comparison_help',
					'title' => __('Comparing Versions', 'test-method-workflow'),
					'content' => $this->get_version_comparison_help_content()
				));
				
				$screen->add_help_tab(array(
					'id' => 'version_restore_help',
					'title' => __('Restoring Versions', 'test-method-workflow'),
					'content' => $this->get_version_restore_help_content()
				));
			}
			
			/**
			 * Get help content for version management
			 */
			private function get_version_management_help_content() {
				$content = '<h2>' . __('Version Management Guide', 'test-method-workflow') . '</h2>';
				
				$content .= '<h3>' . __('How Versioning Works', 'test-method-workflow') . '</h3>';
				$content .= '<p>' . __('The version control system uses a standard Major.Minor format (e.g., 1.2):', 'test-method-workflow') . '</p>';
				$content .= '<ul>';
				$content .= '<li><strong>' . __('Major Version:', 'test-method-workflow') . '</strong> ' .
							__('Increments the first number (e.g., 1.0 to 2.0). Use for significant changes that might affect compatibility or introduce major new features.', 'test-method-workflow') . '</li>';
				$content .= '<li><strong>' . __('Minor Version:', 'test-method-workflow') . '</strong> ' .
							__('Increments the second number (e.g., 1.1 to 1.2). Use for smaller changes, bug fixes, or minor enhancements.', 'test-method-workflow') . '</li>';
				$content .= '<li><strong>' . __('No Change:', 'test-method-workflow') . '</strong> ' .
							__('Keeps the current version number. Use for small edits that don\'t warrant a version change.', 'test-method-workflow') . '</li>';
				$content .= '</ul>';
				
				$content .= '<h3>' . __('When Versions Change', 'test-method-workflow') . '</h3>';
				$content .= '<p>' . __('Version numbers only change when:', 'test-method-workflow') . '</p>';
				$content .= '<ol>';
				$content .= '<li>' . __('You explicitly select a version change type (Major, Minor, or Custom) in the Version Control box.', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('A new document is first submitted for review (automatically changes from 0.0 to 0.1).', 'test-method-workflow') . '</li>';
				$content .= '</ol>';
				
				$content .= '<p><strong>' . __('Important:', 'test-method-workflow') . '</strong> ' .
							__('Versions do NOT automatically change when:', 'test-method-workflow') . '</p>';
				$content .= '<ul>';
				$content .= '<li>' . __('You submit a document for review (after the first submission)', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('The document receives approvals', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('The document is published', 'test-method-workflow') . '</li>';
				$content .= '</ul>';
				
				$content .= '<h3>' . __('How to Change a Version', 'test-method-workflow') . '</h3>';
				$content .= '<ol>';
				$content .= '<li>' . __('In the "Version Control" box on the right side of the edit screen, select your desired version update type:', 'test-method-workflow') . '</li>';
				$content .= '<ul>';
				$content .= '<li>' . __('Minor: For small changes (increments the second number)', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Major: For significant changes (increments the first number, resets second to 0)', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Custom: To manually specify a version number', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('No Change: To keep the current version number', 'test-method-workflow') . '</li>';
				$content .= '</ul>';
				$content .= '<li>' . __('Add a version note to document what changed in this version', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Save or update the document to apply the version change', 'test-method-workflow') . '</li>';
				$content .= '</ol>';
				
				$content .= '<h3>' . __('Version History', 'test-method-workflow') . '</h3>';
				$content .= '<p>' . __('All version changes are tracked in the revision history, visible in the "Approvals & Comments" section. The history records:', 'test-method-workflow') . '</p>';
				$content .= '<ul>';
				$content .= '<li>' . __('Who made the change', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('When it occurred', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('What version number was assigned', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Any version notes that were added', 'test-method-workflow') . '</li>';
				$content .= '</ul>';
				
				return $content;
			}
			
			/**
			 * Get help content for version comparison
			 */
			private function get_version_comparison_help_content() {
				$content = '<h2>' . __('Comparing Versions', 'test-method-workflow') . '</h2>';
				
				$content .= '<p>' . __('The version comparison tool helps you see differences between different versions of your content:', 'test-method-workflow') . '</p>';
				
				$content .= '<h3>' . __('How to Compare Versions', 'test-method-workflow') . '</h3>';
				$content .= '<ol>';
				$content .= '<li>' . __('Go to the "Approvals & Comments" section on the edit screen', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('In the Revision History table, locate the version you want to compare', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Click the "Compare" button for that version', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('A modal window will open showing a side-by-side comparison', 'test-method-workflow') . '</li>';
				$content .= '</ol>';
				
				$content .= '<h3>' . __('Understanding the Comparison', 'test-method-workflow') . '</h3>';
				$content .= '<ul>';
				$content .= '<li><span style="background-color:#e6ffed; padding:2px 4px;">' . __('Green highlighted text', 'test-method-workflow') . '</span>: ' .
							__('Content added in the newer version', 'test-method-workflow') . '</li>';
				$content .= '<li><span style="background-color:#ffeef0; padding:2px 4px;">' . __('Red highlighted text', 'test-method-workflow') . '</span>: ' .
							__('Content removed from the older version', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('The left side shows the older version, the right side shows the newer version', 'test-method-workflow') . '</li>';
				$content .= '</ul>';
				
				$content .= '<h3>' . __('Metadata Comparison', 'test-method-workflow') . '</h3>';
				$content .= '<p>' . __('In addition to content changes, the comparison shows differences in:', 'test-method-workflow') . '</p>';
				$content .= '<ul>';
				$content .= '<li>' . __('Version numbers', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Version notes', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Other relevant metadata', 'test-method-workflow') . '</li>';
				$content .= '</ul>';
				
				return $content;
			}
			
		private function get_version_restore_help_content() {
			$content = '<h2>' . __('Restoring Previous Versions', 'test-method-workflow') . '</h2>';
			
			$content .= '<p>' . __('If you need to revert to a previous version, you can use the restore functionality:', 'test-method-workflow') . '</p>';
			
			$content .= '<h3>' . __('How to Restore a Version', 'test-method-workflow') . '</h3>';
			$content .= '<ol>';
			$content .= '<li>' . __('First, compare the version you want to restore to make sure it\'s the correct one', 'test-method-workflow') . '</li>';
			$content .= '<li>' . __('Click the "Restore" button for that version in the Revision History table', 'test-method-workflow') . '</li>';
			$content .= '<li>' . __('Confirm your decision when prompted', 'test-method-workflow') . '</li>';
			$content .= '<li>' . __('The content will be reverted to that version, but a new history entry will be created', 'test-method-workflow') . '</li>';
			$content .= '<li>' . __('You\'ll need to save the document to ensure the restored version is preserved', 'test-method-workflow') . '</li>';
			$content .= '</ol>';
			
			$content .= '<h3>' . __('Important Notes About Restoration', 'test-method-workflow') . '</h3>';
			$content .= '<ul>';
			$content .= '<li>' . __('Restoring a version only reverts the content - it does not change the current version number', 'test-method-workflow') . '</li>';
			$content .= '<li>' . __('The restoration is recorded in the revision history for accountability', 'test-method-workflow') . '</li>';
			$content .= '<li>' . __('If you want to publish the restored version, you\'ll need to follow the normal workflow (submit for review, get approvals, etc.)', 'test-method-workflow') . '</li>';
			$content .= '<li>' . __('Only administrators and TP Admins can restore previous versions', 'test-method-workflow') . '</li>';
			$content .= '</ul>';
			
			$content .= '<p><strong>' . __('Tip:', 'test-method-workflow') . '</strong> ' .
						__('If you need to make further changes after restoring a version, consider creating a new version number to clearly identify it as a new revision.', 'test-method-workflow') . '</p>';
			
			return $content;
		}
			
			/**
			 * Get list help content
			 */
			private function get_list_help_content() {
				$content = '<h2>' . __('Test Method Workflow', 'test-method-workflow') . '</h2>';
				$content .= '<p>' . __('Test Method posts follow a specific workflow:', 'test-method-workflow') . '</p>';
				$content .= '<ul>';
				$content .= '<li><strong>' . __('Draft', 'test-method-workflow') . '</strong> - ' . __('Initial state or rejected test methods.', 'test-method-workflow') . '</li>';
				$content .= '<li><strong>' . __('Pending Review', 'test-method-workflow') . '</strong> - ' . __('Submitted for review by approvers.', 'test-method-workflow') . '</li>';
				$content .= '<li><strong>' . __('Awaiting Final Approval', 'test-method-workflow') . '</strong> - ' . __('Has one approval and needs a second.', 'test-method-workflow') . '</li>';
				$content .= '<li><strong>' . __('Approved', 'test-method-workflow') . '</strong> - ' . __('Has received two approvals and is ready to publish.', 'test-method-workflow') . '</li>';
				$content .= '<li><strong>' . __('Rejected', 'test-method-workflow') . '</strong> - ' . __('Rejected by an approver.', 'test-method-workflow') . '</li>';
				$content .= '<li><strong>' . __('Published', 'test-method-workflow') . '</strong> - ' . __('Published and locked for editing.', 'test-method-workflow') . '</li>';
				$content .= '</ul>';
				$content .= '<p>' . __('Use the "Workflow Status" filter to view test methods in a specific state.', 'test-method-workflow') . '</p>';
				
				return $content;
			}
			
			/**
			 * Get edit help content
			 */
			private function get_edit_help_content() {
				$content = '<h2>' . __('Test Method Workflow', 'test-method-workflow') . '</h2>';
				$content .= '<p>' . __('Test Method posts follow this workflow:', 'test-method-workflow') . '</p>';
				$content .= '<ol>';
				$content .= '<li>' . __('Create a draft test method.', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Submit for review when ready.', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Two approvers must review and approve the content.', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Once approved, an administrator can publish the test method.', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Published test methods are locked for editing.', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('To make changes to a published test method, create a revision.', 'test-method-workflow') . '</li>';
				$content .= '<li>' . __('Revisions follow the same workflow before they can be published.', 'test-method-workflow') . '</li>';
				$content .= '</ol>';
				$content .= '<p>' . __('Use the version control options to track major and minor version changes.', 'test-method-workflow') . '</p>';
				
				return $content;
			}
		}