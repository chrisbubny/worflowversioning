<?php
/**
 * Post Locking Functionality
 *
 * @package TestMethodWorkflow
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Test Method post locking class
 */
class TestMethod_PostLocking {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// AJAX handlers for locking/unlocking
		add_action('wp_ajax_unlock_test_method', array($this, 'unlock_test_method'));
		add_action('wp_ajax_lock_test_method', array($this, 'lock_test_method'));
		
		// Check if post is locked before allowing edit
		add_action('load-post.php', array($this, 'check_locked_status'));
		
		// Add unlock button to Gutenberg editor
		add_action('admin_footer', array($this, 'add_unlock_button_to_editor'));
		
		// Filter post content to show locked status on frontend
		add_filter('the_content', array($this, 'add_locked_status_to_content'), 20);
		
		// Filter user capabilities for locked posts
		add_filter('user_has_cap', array($this, 'modify_caps_for_locked_posts'), 10, 4);
		
		// Prevent REST API editing for locked posts
		add_filter('rest_pre_dispatch', array($this, 'check_rest_api_lock'), 10, 3);
	}
	
	/**
	 * AJAX handler to unlock a test method
	 */
	public function unlock_test_method() {
		try {
			// Check nonce
			check_ajax_referer('test_method_workflow', 'nonce');
			
			// Check permissions
			if (!current_user_can('unlock_test_methods')) {
				wp_send_json_error(__('Permission denied', 'test-method-workflow'));
				return;
			}
			
			$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
			
			if (!$post_id) {
				wp_send_json_error(__('Invalid post ID', 'test-method-workflow'));
				return;
			}
			
			// Get the post
			$post = get_post($post_id);
			if (!$post || $post->post_type !== 'test_method') {
				wp_send_json_error(__('Invalid test method', 'test-method-workflow'));
				return;
			}
			
			// IMPORTANT: Only update the lock status, preserve the current workflow status
			update_post_meta($post_id, '_is_locked', false);
			
			// Get current post status and workflow status
			$current_post_status = $post->post_status;
			$current_workflow_status = get_post_meta($post_id, '_workflow_status', true);
			
			// Record the current status for reference in history
			$status_message = "unlocked for editing (previous status: $current_workflow_status)";
			
			// Record unlock action
			$this->add_to_unlock_history($post_id);
			
			// Send notification
			do_action('tmw_send_notification', $post_id, 'unlocked');
			
			wp_send_json_success(array(
				'message' => __('Test method unlocked successfully. You can now edit this test method.', 'test-method-workflow'),
				'reload' => true
			));
		} catch (Exception $e) {
			wp_send_json_error('Error: ' . $e->getMessage());
		}
	}
	 
	 /**
	  * Create a revision for a post
	  */
	 private function create_revision_for_post($post_id) {
		 $post = get_post($post_id);
		 if (!$post) {
			 return false;
		 }
		 
		 // Get current version
		 $current_version = get_post_meta($post_id, '_current_version_number', true);
		 if (empty($current_version)) {
			 $current_version = '0.1';
		 }
		 
		 $revision_title = $post->post_title . ' - ' . __('Revision', 'test-method-workflow');
		 
		 $revision_args = array(
			 'post_title' => $revision_title,
			 'post_content' => $post->post_content,
			 'post_excerpt' => $post->post_excerpt,
			 'post_type' => 'test_method',
			 'post_status' => 'draft',
			 'post_author' => get_current_user_id(),
			 'comment_status' => $post->comment_status,
			 'ping_status' => $post->ping_status,
			 'meta_input' => array(
				 '_is_revision' => '1',
				 '_revision_parent' => $post_id,
				 '_workflow_status' => 'draft',
				 '_current_version_number' => $current_version,
				 '_revision_version' => $current_version,
				 '_approvals' => array()
			 )
		 );
		 
		 $revision_id = wp_insert_post($revision_args);
		 
		 if (is_wp_error($revision_id)) {
			 return false;
		 }
		 
		 // Copy taxonomies
		 $taxonomies = get_object_taxonomies($post->post_type);
		 foreach ($taxonomies as $taxonomy) {
			 $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
			 if (!is_wp_error($terms)) {
				 wp_set_object_terms($revision_id, $terms, $taxonomy);
			 }
		 }
		 
		 // Add record to revision history of parent
		 $revision_history = get_post_meta($post_id, '_revision_history', true);
		 if (!is_array($revision_history)) {
			 $revision_history = array();
		 }
		 
		 $revision_history[] = array(
			 'version' => count($revision_history) + 1,
			 'user_id' => get_current_user_id(),
			 'date' => time(),
			 'status' => 'revision created on unlock',
			 'revision_id' => $revision_id,
			 'version_number' => $current_version
		 );
		 
		 update_post_meta($post_id, '_revision_history', $revision_history);
		 
		 return $revision_id;
	 }
	 
	/**
	 * AJAX handler to lock a test method
	 */
	public function lock_test_method() {
		// Check nonce
		check_ajax_referer('test_method_workflow', 'nonce');
		
		// Check permissions
		if (!current_user_can('lock_test_methods')) {
			wp_send_json_error(__('Permission denied', 'test-method-workflow'));
			return;
		}
		
		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		
		if (!$post_id) {
			wp_send_json_error(__('Invalid post ID', 'test-method-workflow'));
			return;
		}
		
		// Get the post
		$post = get_post($post_id);
		if (!$post || $post->post_type !== 'test_method') {
			wp_send_json_error(__('Invalid test method', 'test-method-workflow'));
			return;
		}
		
		// Lock the post
		update_post_meta($post_id, '_is_locked', true);
		
		// Record lock action in history
		$this->add_to_lock_history($post_id);
		
		// Send notification
		do_action('tmw_send_notification', $post_id, 'locked');
		
		wp_send_json_success(array(
			'message' => __('Test method locked successfully', 'test-method-workflow'),
			'reload' => true
		));
	}
	
	/**
	 * Check if post is locked before allowing edit
	 */
	public function check_locked_status() {
		// Only check in admin
		if (!is_admin()) {
			return;
		}
		
		if (!isset($_GET['post'])) {
			return;
		}
		
		$post_id = intval($_GET['post']);
		$post = get_post($post_id);
		
		// Only apply to test_method post type
		if (!$post || $post->post_type !== 'test_method') {
			return;
		}
		
		// Check if post is locked
		$is_locked = get_post_meta($post_id, '_is_locked', true);
		
		if ($is_locked) {
			// Check if current user is admin or tp_admin (they can still edit)
			$user = wp_get_current_user();
			$user_roles = (array) $user->roles;
			
			if (!array_intersect($user_roles, array('administrator', 'tp_admin'))) {
				// Redirect to view the post instead of editing
				wp_redirect(get_permalink($post_id));
				exit;
			}
		}
	}
	
	/**
	 * Add unlock button to Gutenberg editor
	 */
	public function add_unlock_button_to_editor() {
		global $post;
		
		// Only on test_method edit screen
		if (!is_admin() || !$post || $post->post_type !== 'test_method') {
			return;
		}
		
		// Only for admins and tp_admins
		$user = wp_get_current_user();
		$user_roles = (array) $user->roles;
		if (!array_intersect($user_roles, array('administrator', 'tp_admin'))) {
			return;
		}
		
		// Check if post is locked
		$is_locked = get_post_meta($post->ID, '_is_locked', true);
		if (!$is_locked) {
			return;
		}
		
		// Create nonce
		$nonce = wp_create_nonce('test_method_workflow');
		
		?>
		<style>
		/* Custom styles for unlock button */
		.tmw-unlock-floating-button {
			position: fixed;
			bottom: 30px;
			right: 30px;
			background: #fff;
			border: 1px solid #0071a1;
			color: #0071a1;
			padding: 10px 15px;
			border-radius: 4px;
			font-weight: bold;
			cursor: pointer;
			z-index: 9999;
			box-shadow: 0 2px 5px rgba(0,0,0,0.2);
		}
		.tmw-unlock-floating-button:hover {
			background: #f1f1f1;
		}
		</style>
		
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Add floating button to the page
			$('body').append(
				'<button id="tmw-unlock-button" class="tmw-unlock-floating-button"><?php echo esc_js(__('Unlock Test Method', 'test-method-workflow')); ?></button>'
			);
			
			// Add click handler
			$('#tmw-unlock-button').on('click', function(e) {
				e.preventDefault();
				
				if (confirm('<?php echo esc_js(__('Are you sure you want to unlock this test method? This will allow editing while maintaining its published status.', 'test-method-workflow')); ?>')) {
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'unlock_test_method',
							post_id: <?php echo $post->ID; ?>,
							nonce: '<?php echo $nonce; ?>'
						},
						success: function(response) {
							if (response.success) {
								alert('<?php echo esc_js(__('Test method unlocked successfully.', 'test-method-workflow')); ?>');
								location.reload();
							} else {
								alert(response.data || '<?php echo esc_js(__('An error occurred.', 'test-method-workflow')); ?>');
							}
						},
						error: function() {
							alert('<?php echo esc_js(__('An error occurred. Please try again.', 'test-method-workflow')); ?>');
						}
					});
				}
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Add locked status to content on frontend
	 */
	public function add_locked_status_to_content($content) {
		global $post;
		
		if (is_singular('test_method') && $post) {
			$is_locked = get_post_meta($post->ID, '_is_locked', true);
			$current_version = get_post_meta($post->ID, '_current_version_number', true);
			
			$version_info = '';
			if (!empty($current_version)) {
				$version_info = '<div class="test-method-version">' . 
					sprintf(__('Version: %s', 'test-method-workflow'), esc_html($current_version)) . 
				'</div>';
			}
			
			if ($is_locked) {
				$notice = '<div class="test-method-locked-notice">';
				$notice .= '<p><strong>' . __('Note:', 'test-method-workflow') . '</strong> ' . 
						   __('This test method is locked and cannot be edited without administrator approval.', 'test-method-workflow') . '</p>';
				$notice .= '</div>';
				
				return $version_info . $notice . $content;
			}
			
			return $version_info . $content;
		}
		
		return $content;
	}
	
	/**
	 * Modify capabilities for locked posts
	 */
public function modify_caps_for_locked_posts($allcaps, $caps, $args, $user) {
		 // Only check edit post capability
		 if (!isset($args[0]) || $args[0] !== 'edit_post') {
			 return $allcaps;
		 }
		 
		 // Only if we have a post ID
		 if (!isset($args[2])) {
			 return $allcaps;
		 }
		 
		 $post_id = $args[2];
		 $post = get_post($post_id);
		 
		 // Only for test_method post type
		 if (!$post || $post->post_type !== 'test_method') {
			 return $allcaps;
		 }
		 
		 // Check if locked
		 $is_locked = get_post_meta($post_id, '_is_locked', true);
		 
		 if ($is_locked) {
			 // If user is admin or tp_admin, allow edit capabilities
			 $user_roles = (array) $user->roles;
			 
			 if (array_intersect($user_roles, array('administrator', 'tp_admin'))) {
				 foreach ($caps as $cap) {
					 $allcaps[$cap] = true;
				 }
			 } else {
				 // For non-admins, remove edit capability for locked posts
				 foreach ($caps as $cap) {
					 $allcaps[$cap] = false;
				 }
			 }
		 }
		 
		 return $allcaps;
	 }
	
	/**
	 * Check REST API requests for locked posts
	 */
public function check_rest_api_lock($dispatch_result, $server, $request) {
		 $route = $request->get_route();
		 
		 // Only check test_method routes
		 if (strpos($route, '/wp/v2/test_method/') !== false) {
			 $method = $request->get_method();
			 
			 // Only check modifying methods
			 if (in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'))) {
				 // Get post ID from route
				 preg_match('/\/wp\/v2\/test_method\/(\d+)/', $route, $matches);
				 
				 if (isset($matches[1])) {
					 $post_id = $matches[1];
					 $is_locked = get_post_meta($post_id, '_is_locked', true);
					 
					 if ($is_locked) {
						 $user = wp_get_current_user();
						 $user_roles = (array) $user->roles;
						 
						 // Always allow admins to edit locked posts
						 if (array_intersect($user_roles, array('administrator', 'tp_admin'))) {
							 return $dispatch_result; // Allow the request
						 } else {
							 return new WP_Error(
								 'test_method_locked',
								 __('This test method is locked and cannot be modified.', 'test-method-workflow'),
								 array('status' => 403)
							 );
						 }
					 }
				 }
			 }
		 }
		 
		 return $dispatch_result;
	 }
	 
	/**
	 * Add to unlock history
	 */
	private function add_to_unlock_history($post_id) {
		$user = wp_get_current_user();
		$post = get_post($post_id);
		
		$unlock_log = array(
			'user_id' => get_current_user_id(),
			'username' => $user->display_name,
			'date' => current_time('mysql'),
			'action' => 'unlock',
			'previous_status' => $post->post_status
		);
		
		// Add to revision history
		$revision_history = get_post_meta($post_id, '_revision_history', true);
		if (!is_array($revision_history)) {
			$revision_history = array();
		}
		
		$revision_history[] = array(
			'version' => count($revision_history) + 1,
			'user_id' => get_current_user_id(),
			'date' => time(),
			'status' => 'unlocked for editing'
		);
		
		update_post_meta($post_id, '_revision_history', $revision_history);
		
		// Save unlock log
		update_post_meta($post_id, '_unlock_history', $unlock_log);
	}
	
	/**
	 * Add to lock history
	 */
	private function add_to_lock_history($post_id) {
		$user = wp_get_current_user();
		
		$lock_log = array(
			'user_id' => get_current_user_id(),
			'username' => $user->display_name,
			'date' => current_time('mysql'),
			'action' => 'lock'
		);
		
		// Add to revision history
		$revision_history = get_post_meta($post_id, '_revision_history', true);
		if (!is_array($revision_history)) {
			$revision_history = array();
		}
		
		$revision_history[] = array(
			'version' => count($revision_history) + 1,
			'user_id' => get_current_user_id(),
			'date' => time(),
			'status' => 'locked'
		);
		
		update_post_meta($post_id, '_revision_history', $revision_history);
		
		// Save lock log
		update_post_meta($post_id, '_lock_history', $lock_log);
	}
}