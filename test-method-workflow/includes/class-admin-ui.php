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