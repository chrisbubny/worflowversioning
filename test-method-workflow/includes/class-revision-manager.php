<?php
/**
 * Revision Management
 *
 * @package TestMethodWorkflow
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Test Method revision manager class
 */
class TestMethod_RevisionManager {
	
public function __construct() {
	// Add meta box for revision management
	add_action('add_meta_boxes', array($this, 'add_revision_meta_box'));
	
	// AJAX handlers for revision creation and publishing
	add_action('wp_ajax_create_test_method_revision', array($this, 'create_revision'));
	add_action('wp_ajax_publish_test_method_revision', array($this, 'publish_revision'));
	add_action('wp_ajax_publish_approved_revision', array($this, 'publish_approved_revision'));
	add_action('wp_ajax_restore_test_method_version', array($this, 'restore_version'));
	
	// Add filter to admin list to show revisions
	add_filter('parse_query', array($this, 'filter_admin_list_by_revision'));
	
	// Add filter dropdown to admin
	add_action('restrict_manage_posts', array($this, 'add_revision_filter_dropdown'));
	
	// Add diff comparison functionality
	add_action('admin_enqueue_scripts', array($this, 'enqueue_diff_assets'));
}

/**
 * AJAX handler for restoring a version
 */
public function restore_version() {
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
	$version_number = get_post_meta($version_id, '_current_version_number', true);
	if (empty($version_number)) {
		// Try to find it in version history
		$version_history = get_post_meta($post_id, '_version_history', true);
		if (is_array($version_history)) {
			foreach ($version_history as $version) {
				if (isset($version['version_id']) && $version['version_id'] == $version_id) {
					$version_number = $version['version_number'];
					break;
				}
			}
		}
	}
	
	// Update revision history
	$revision_history = get_post_meta($post_id, '_revision_history', true);
	if (!is_array($revision_history)) {
		$revision_history = array();
	}
	
	$revision_history[] = array(
		'version' => count($revision_history) + 1,
		'user_id' => get_current_user_id(),
		'date' => time(),
		'status' => 'version restored',
		'note' => sprintf(
			__('Restored content from version %s', 'test-method-workflow'),
			$version_number ? $version_number : __('unknown', 'test-method-workflow')
		)
	);
	
	update_post_meta($post_id, '_revision_history', $revision_history);
	
	// Also update version history if it exists
	$version_history = get_post_meta($post_id, '_version_history', true);
	if (is_array($version_history)) {
		$version_history[] = array(
			'version' => count($version_history) + 1,
			'user_id' => get_current_user_id(),
			'date' => time(),
			'status' => 'version restored',
			'version_number' => $version_number ? $version_number : __('unknown', 'test-method-workflow'),
			'note' => sprintf(
				__('Restored content from version %s', 'test-method-workflow'),
				$version_number ? $version_number : __('unknown', 'test-method-workflow')
			),
			'content' => $old_content
		);
		
		update_post_meta($post_id, '_version_history', $version_history);
	}
	
	// Set a notice that will be shown on page reload
	set_transient('tmw_version_restored_' . $post_id, true, 60);
	
	wp_send_json_success(array(
		'message' => __('Version restored successfully', 'test-method-workflow')
	));
}

/**
 * Enqueue assets for diff functionality
 */
public function enqueue_diff_assets($hook) {
	global $post;
	
	// Only on post.php and for test_method post type
	if ($hook == 'post.php' && $post && $post->post_type == 'test_method') {
		// Only if post is a revision or has revisions
		$is_revision = get_post_meta($post->ID, '_is_revision', true);
		$revision_parent = get_post_meta($post->ID, '_revision_parent', true);
		$has_revisions = !empty($this->get_post_revisions($post->ID));
		
		if ($is_revision || $revision_parent || $has_revisions) {
			// Enqueue WP's diff library
			wp_enqueue_style('wp-diff');
			
			// Add custom CSS for diff view
			wp_add_inline_style('wp-diff', '
				.diff-view h3 {
					margin-bottom: 15px;
					padding-bottom: 10px;
					border-bottom: 1px solid #ddd;
				}
				table.diff {
					width: 100%;
					border-collapse: collapse;
					margin: 20px 0;
					font-family: monospace;
					font-size: 13px;
				}
				table.diff col.diffsplit {
					width: 48%;
				}
				table.diff col.diffmarker {
					width: 2%;
					text-align: center;
				}
				table.diff col.diffsign {
					width: 2%;
					text-align: center;
				}
				table.diff th {
					padding: 10px;
					background: #f5f5f5;
					border: 1px solid #ddd;
					font-weight: bold;
					text-align: left;
				}
				table.diff td {
					padding: 8px;
					border: 1px solid #ddd;
					vertical-align: top;
					word-wrap: break-word;
					white-space: pre-wrap;
				}
				table.diff td.diff-linenum {
					background-color: #f9f9f9;
					color: #999;
					text-align: right;
				}
				table.diff .diff-context {
					background-color: #fff;
				}
				table.diff .diff-addedline {
					background-color: #e6ffed;
				}
				table.diff .diff-addedline ins {
					background-color: #acf2bd;
					text-decoration: none;
					display: inline-block;
					padding: 1px 4px;
					border-radius: 2px;
				}
				table.diff .diff-deletedline {
					background-color: #ffeef0;
				}
				table.diff .diff-deletedline del {
					background-color: #fdb8c0;
					text-decoration: none;
					display: inline-block;
					padding: 1px 4px;
					border-radius: 2px;
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
				.version-comparison-content {
					position: relative;
					background-color: white;
					margin: 5% auto;
					padding: 20px;
					width: 90%;
					max-width: 1000px;
					max-height: 80vh;
					overflow: auto;
				}
				.close-comparison {
					position: absolute;
					top: 10px;
					right: 20px;
					font-size: 28px;
					cursor: pointer;
				}
			');
		}
	}
}

/**
 * AJAX handler for creating revisions - Enhanced with versioning
 */
public function create_revision() {
	// Check if there's a nonce in the request
	if (!isset($_POST['nonce'])) {
		wp_send_json_error('Security nonce is missing');
		return;
	}
	
	// Check nonce
	check_ajax_referer('test_method_revision_nonce', 'nonce');
	
	// Check permissions
	if (!current_user_can('edit_test_methods')) {
		wp_send_json_error('Permission denied: You do not have permission to create revisions.');
		return;
	}
	
	$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
	$version_type = isset($_POST['version_type']) ? sanitize_text_field($_POST['version_type']) : 'minor';
	
	if (!$post_id) {
		wp_send_json_error('Invalid post ID: The post ID provided is invalid.');
		return;
	}
	
	$post = get_post($post_id);
	
	if (!$post || $post->post_type !== 'test_method') {
		wp_send_json_error('Invalid test method: The requested post does not exist or is not a test method.');
		return;
	}
	
	// Check if a revision already exists for this post
	$existing_revisions = $this->get_post_revisions($post_id);
	
	if (!empty($existing_revisions)) {
		wp_send_json_error('A revision for this test method already exists. Please complete or delete the existing revision before creating a new one.');
		return;
	}
	
	// Get current version information for the revision
	$current_version = get_post_meta($post_id, '_current_version_number', true);
	if (empty($current_version)) {
		$current_version = '0.0';
	}
	
	// Calculate next version based on version_type
	$version_parts = explode('.', $current_version);
	$major = isset($version_parts[0]) ? intval($version_parts[0]) : 0;
	$minor = isset($version_parts[1]) ? intval($version_parts[1]) : 0;
	
	if ($version_type === 'minor') {
		$next_version = $major . '.' . ($minor + 1);
	} else if ($version_type === 'major') {
		$next_version = ($major + 1) . '.0';
	} else {
		$next_version = $current_version; // Default - no change
	}
	
	// Get version note if provided
	$version_note = isset($_POST['version_note']) ? sanitize_textarea_field($_POST['version_note']) : '';
	if (empty($version_note)) {
		$version_note = sprintf(__('Revision for %s update', 'test-method-workflow'), $version_type);
	}
	
	$revision_title = $post->post_title . ' - ' . __('Revision v', 'test-method-workflow') . $next_version;
	
	// Create new revision post with additional logging
	try {
		// Get the content
		$post_content = isset($_POST['post_content']) ? $_POST['post_content'] : $post->post_content;
		
		$revision_args = array(
			'post_title' => $revision_title,
			'post_content' => $post_content,
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
				'_current_version_number' => $next_version, // Set the incremented version
				'_revision_version' => $current_version,
				'_approvals' => array(),
				'_cpt_version' => $version_type,
				'_version_already_incremented' => 'yes', // Add this flag
				'_cpt_version_note' => $version_note // Add version note
			)
		);
		
		$revision_id = wp_insert_post($revision_args);
		
		if (is_wp_error($revision_id)) {
			wp_send_json_error('Failed to create revision: ' . $revision_id->get_error_message());
			return;
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
			'status' => 'revision created for ' . $version_type . ' version change',
			'revision_id' => $revision_id,
			'version_number' => $current_version,
			'next_version' => $next_version,
			'note' => $version_note
		);
		
		update_post_meta($post_id, '_revision_history', $revision_history);
		
		// Also update version history if available
		$version_history = get_post_meta($post_id, '_version_history', true);
		if (is_array($version_history)) {
			$version_history[] = array(
				'version' => count($version_history) + 1,
				'user_id' => get_current_user_id(),
				'date' => time(),
				'status' => 'revision_created',
				'version_number' => $current_version,
				'next_version' => $next_version,
				'note' => $version_note,
				'revision_id' => $revision_id,
				'content' => $post->post_content
			);
			
			update_post_meta($post_id, '_version_history', $version_history);
		}
		
		// Send notification
		do_action('tmw_send_notification', $revision_id, 'revision_created');
		
		// Success!
		wp_send_json_success(array(
			'message' => sprintf(
				__('Revision created successfully for version %s update. You will be redirected to edit it.', 'test-method-workflow'),
				$next_version
			),
			'revision_id' => $revision_id,
			'edit_url' => get_edit_post_link($revision_id, 'raw')
		));
		
	} catch (Exception $e) {
		wp_send_json_error('Error creating revision: ' . $e->getMessage());
	}
}

/**
 * AJAX handler for publishing revisions - Enhanced with versioning
 */
public function publish_revision() {
	// Check nonce
	check_ajax_referer('test_method_revision_nonce', 'nonce');
	
	// Check permissions
	if (!current_user_can('publish_test_methods')) {
		wp_send_json_error('Permission denied');
		return;
	}
	
	$revision_id = isset($_POST['revision_id']) ? intval($_POST['revision_id']) : 0;
	$parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
	
	if (!$revision_id || !$parent_id) {
		wp_send_json_error('Invalid post IDs');
		return;
	}
	
	$revision = get_post($revision_id);
	$parent = get_post($parent_id);
	
	if (!$revision || !$parent || $revision->post_type !== 'test_method' || $parent->post_type !== 'test_method') {
		wp_send_json_error('Invalid test methods');
		return;
	}
	
	// Verify this is actually a revision of the parent
	$revision_parent = get_post_meta($revision_id, '_revision_parent', true);
	if ($revision_parent != $parent_id) {
		wp_send_json_error('Revision does not match parent');
		return;
	}
	
	// Check workflow status
	$workflow_status = get_post_meta($revision_id, '_workflow_status', true);
	
	if ($workflow_status !== 'approved') {
		wp_send_json_error('This revision must be approved before publishing');
		return;
	}
	
	// Get current version information
	$parent_version = get_post_meta($parent_id, '_current_version_number', true);
	$revision_version = get_post_meta($revision_id, '_current_version_number', true);
	$version_note = get_post_meta($revision_id, '_cpt_version_note', true);
	
	if (empty($parent_version)) {
		$parent_version = '0.1';
	}
	
	// Store the original parent content for diff history
	$parent_content = $parent->post_content;
	
	// Update parent with revision content, but KEEP original post_name (slug/permalink)
		$parent_slug = $parent->post_name;
		
		$update_args = array(
			'ID' => $parent_id,
			'post_title' => $revision->post_title,
			'post_content' => $revision->post_content,
			'post_excerpt' => $revision->post_excerpt,
			'post_name' => $parent_slug // Ensure we keep the original URL/slug
		);
		
		$result = wp_update_post($update_args);
		
		if (is_wp_error($result)) {
			wp_send_json_error('Error updating parent: ' . $result->get_error_message());
			return;
		}
		
		// Copy taxonomies
		$taxonomies = get_object_taxonomies($revision->post_type);
		foreach ($taxonomies as $taxonomy) {
			$terms = wp_get_object_terms($revision_id, $taxonomy, array('fields' => 'ids'));
			if (!is_wp_error($terms)) {
				wp_set_object_terms($parent_id, $terms, $taxonomy);
			}
		}
		
		// Get approval history from revision
		$revision_approvals = get_post_meta($revision_id, '_approvals', true);
		if (is_array($revision_approvals)) {
			// Merge with parent approvals to maintain history
			$parent_approvals = get_post_meta($parent_id, '_approvals', true);
			if (!is_array($parent_approvals)) {
				$parent_approvals = array();
			}
			
			// Add a separator to indicate these are from a revision
			$parent_approvals[] = array(
				'user_id' => get_current_user_id(),
				'date' => time(),
				'status' => 'revision_separator',
				'comment' => sprintf(__('Revision %s published', 'test-method-workflow'), $revision_version),
				'version' => $revision_version
			);
			
			// Add all revision approvals to the parent
			foreach ($revision_approvals as $approval) {
				$parent_approvals[] = $approval;
			}
			
			// Update parent approvals
			update_post_meta($parent_id, '_approvals', $parent_approvals);
		}
		
		// Copy relevant meta fields (exclude workflow meta)
		$exclude_meta_keys = array(
			'_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date',
			'_workflow_status', '_is_locked', '_approvals', '_revision_parent',
			'_is_revision', '_awaiting_final_approval', '_revision_history',
			'_version_already_incremented' // Don't copy this flag
		);
		
		$meta_keys = get_post_custom_keys($revision_id);
		if ($meta_keys) {
			foreach ($meta_keys as $meta_key) {
				if (!in_array($meta_key, $exclude_meta_keys) && !wp_is_protected_meta($meta_key)) {
					$meta_values = get_post_meta($revision_id, $meta_key, true);
					update_post_meta($parent_id, $meta_key, $meta_values);
				}
			}
		}
		
		// Update version if changed
		if (!empty($revision_version) && $revision_version !== $parent_version) {
			update_post_meta($parent_id, '_current_version_number', $revision_version);
		}
		
		// Copy version note
		if (!empty($version_note)) {
			update_post_meta($parent_id, '_cpt_version_note', $version_note);
		}
		
		// Lock the parent
		update_post_meta($parent_id, '_is_locked', true);
		update_post_meta($parent_id, '_workflow_status', 'publish');
		
		// Make sure parent is published
		if ($parent->post_status !== 'publish') {
			wp_update_post(array(
				'ID' => $parent_id,
				'post_status' => 'publish'
			));
		}
		
		// Also merge revision history with diff information
		$revision_history = get_post_meta($revision_id, '_revision_history', true);
		if (is_array($revision_history)) {
			$parent_history = get_post_meta($parent_id, '_revision_history', true);
			if (!is_array($parent_history)) {
				$parent_history = array();
			}
			
			// Add a separator entry with diff information
			$parent_history[] = array(
				'version' => count($parent_history) + 1,
				'user_id' => get_current_user_id(),
				'date' => time(),
				'status' => 'revision_published',
				'revision_id' => $revision_id,
				'version_number' => $revision_version,
				'previous_content' => $parent_content, // Store old content for future diff
				'new_content' => $revision->post_content, // Store new content for future diff
				'note' => $version_note
			);
			
			// Add all revision history to parent
			foreach ($revision_history as $history_item) {
				$history_item['from_revision'] = true; // Mark as coming from revision
				$parent_history[] = $history_item;
			}
			
			update_post_meta($parent_id, '_revision_history', $parent_history);
		}
		
		// Update version history as well
		$version_history = get_post_meta($parent_id, '_version_history', true);
		if (is_array($version_history)) {
			// Add new entry for the published revision
			$version_history[] = array(
				'version' => count($version_history) + 1,
				'user_id' => get_current_user_id(),
				'date' => time(),
				'status' => 'revision_published',
				'version_number' => $revision_version,
				'revision_id' => $revision_id,
				'note' => $version_note,
				'previous_content' => $parent_content,
				'content' => $revision->post_content
			);
			
			update_post_meta($parent_id, '_version_history', $version_history);
		}
		
		// Trash the revision or change status to show it's been applied
		wp_update_post(array(
			'ID' => $revision_id,
			'post_status' => 'trash'
		));
		
		// Send notification
		do_action('tmw_send_notification', $parent_id, 'revision_published');
		
		wp_send_json_success(array(
			'message' => __('Revision published successfully', 'test-method-workflow'),
			'parent_id' => $parent_id,
			'edit_url' => get_edit_post_link($parent_id, 'raw')
		));
	}
	
/**
	 * Enhanced version comparison tool
	 * Add this to class-revision-manager.php or create a new file
	 */
	public function enhanced_compare_test_method_versions() {
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
		
		// Get content for comparison
		$current_content = $current_post->post_content;
		$version_content = $version_post->post_content;
		
		// Get current version for display
		$current_version = get_post_meta($current_id, "_current_version_number", true);
		if (empty($current_version)) {
			$current_version = 'Current';
		}
		
		// Process HTML content for comparison
		require_once(ABSPATH . 'wp-includes/class-wp-text-diff-renderer-table.php');
		require_once(ABSPATH . 'wp-includes/class-wp-text-diff-renderer-inline.php');
		
		// Create more detailed line-by-line diff
		$left_lines = explode("\n", $this->prepare_content_for_diff($version_content));
		$right_lines = explode("\n", $this->prepare_content_for_diff($current_content));
		
		$diff = new Text_Diff($left_lines, $right_lines);
		
		// Use table renderer for side-by-side comparison
		$renderer = new WP_Text_Diff_Renderer_Table(array(
			'show_split_view' => true,
			'title_left' => sprintf(__('Version %s', 'test-method-workflow'), $version_number),
			'title_right' => sprintf(__('Current Version %s', 'test-method-workflow'), $current_version),
		));
		
		// Get metadata differences
		$meta_diff = $this->get_meta_differences($version_id, $current_id);
		
		// Create detailed report
		$output = '<div class="version-diff-report">';
		$output .= '<h3>' . sprintf(
			__('Comparing Version %s with Current Version %s', 'test-method-workflow'),
			esc_html($version_number),
			esc_html($current_version)
		) . '</h3>';
		
		// Add metadata differences
		if (!empty($meta_diff)) {
			$output .= '<div class="meta-differences">';
			$output .= '<h4>' . __('Metadata Changes', 'test-method-workflow') . '</h4>';
			$output .= '<ul>';
			foreach ($meta_diff as $key => $values) {
				$output .= '<li><strong>' . esc_html($key) . ':</strong> ';
				$output .= esc_html($values['old']) . ' → ' . esc_html($values['new']);
				$output .= '</li>';
			}
			$output .= '</ul>';
			$output .= '</div>';
		}
		
		$output .= '<div class="content-differences">';
		$output .= '<h4>' . __('Content Changes', 'test-method-workflow') . '</h4>';
		
		if ($diff->isEmpty()) {
			$output .= '<p class="notice notice-info">' . __('No textual differences found between versions.', 'test-method-workflow') . '</p>';
		} else {
			$output .= $renderer->render($diff);
		}
		
		$output .= '</div>';
		
		// Add restore button
		$output .= '<div class="comparison-actions">';
		$output .= '<button type="button" class="button restore-this-version" data-post-id="' . $current_id . '" data-version-id="' . $version_id . '" data-nonce="' . wp_create_nonce('test_method_version_restore') . '">';
		$output .= __('Restore to This Version', 'test-method-workflow');
		$output .= '</button>';
		$output .= '</div>';
		
		$output .= '</div>';
		
		wp_send_json_success(array(
			'html' => $output
		));
	}
	
	/**
	 * Helper function to prepare content for diff
	 */
	private function prepare_content_for_diff($content) {
		// Strip shortcodes but preserve structure
		$content = preg_replace('/\[([^\]]*)\]/', '[...]', $content);
		
		// Replace HTML tags with markers
		$content = str_replace(array('<br>', '<br />', '<br/>'), "\n", $content);
		$content = str_replace(array('<p>', '</p>'), array("\n<p>", "</p>\n"), $content);
		$content = str_replace(array('<div>', '</div>'), array("\n<div>", "</div>\n"), $content);
		$content = str_replace(array('<h1>', '</h1>'), array("\n<h1>", "</h1>\n"), $content);
		$content = str_replace(array('<h2>', '</h2>'), array("\n<h2>", "</h2>\n"), $content);
		$content = str_replace(array('<h3>', '</h3>'), array("\n<h3>", "</h3>\n"), $content);
		
		// Normalize line breaks
		$content = str_replace("\r\n", "\n", $content);
		
		// Split at actual paragraphs
		$content = preg_replace('/\n{2,}/', "\n\n", $content);
		
		return $content;
	}
	
	/**
	 * Get differences in metadata between versions
	 */
	private function get_meta_differences($version_id, $current_id) {
		$tracked_meta_keys = array(
			'_current_version_number' => __('Version Number', 'test-method-workflow'),
			'_cpt_version_note' => __('Version Note', 'test-method-workflow')
			// Add other relevant meta fields here
		);
		
		$differences = array();
		
		foreach ($tracked_meta_keys as $key => $label) {
			$version_value = get_post_meta($version_id, $key, true);
			$current_value = get_post_meta($current_id, $key, true);
			
			if ($version_value !== $current_value) {
				$differences[$label] = array(
					'old' => !empty($version_value) ? $version_value : __('(empty)', 'test-method-workflow'),
					'new' => !empty($current_value) ? $current_value : __('(empty)', 'test-method-workflow')
				);
			}
		}
		
		return $differences;
	}
	
	/**
	 * Filter admin list by revision status
	 */
	public function filter_admin_list_by_revision($query) {
		global $pagenow, $post_type;
		
		// Only on test method list screen
		if (!is_admin() || $pagenow !== 'edit.php' || $post_type !== 'test_method' || !$query->is_main_query()) {
			return $query;
		}
		
		// Check if filter is active
		$revision_status = isset($_GET['revision_status']) ? $_GET['revision_status'] : '';
		
		if ($revision_status === 'parent') {
			// Show only parent posts
			$meta_query = $query->get('meta_query');
			if (!is_array($meta_query)) {
				$meta_query = array();
			}
			
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key' => '_is_revision',
					'compare' => 'NOT EXISTS'
				),
				array(
					'key' => '_is_revision',
					'value' => '1',
					'compare' => '!='
				)
			);
			
			$query->set('meta_query', $meta_query);
		} elseif ($revision_status === 'revision') {
			// Show only revisions
			$meta_query = $query->get('meta_query');
			if (!is_array($meta_query)) {
				$meta_query = array();
			}
			
			$meta_query[] = array(
				'key' => '_is_revision',
				'value' => '1',
				'compare' => '='
			);
			
			$query->set('meta_query', $meta_query);
		}
		
		return $query;
	}
	
	
	
	/**
	 * Add filter dropdown for revision status
	 */
	public function add_revision_filter_dropdown() {
		global $post_type;
		
		// Only for test method post type
		if ($post_type !== 'test_method') {
			return;
		}
		
		// Get current filter value
		$selected = isset($_GET['revision_status']) ? $_GET['revision_status'] : '';
		
		// Create dropdown
		?>
		<select name="revision_status">
			<option value=""><?php _e('All Test Methods', 'test-method-workflow'); ?></option>
			<option value="parent" <?php selected($selected, 'parent'); ?>><?php _e('Main Test Methods', 'test-method-workflow'); ?></option>
			<option value="revision" <?php selected($selected, 'revision'); ?>><?php _e('Revisions', 'test-method-workflow'); ?></option>
		</select>
		<?php
	}
	
	/**
	 * Add revision meta box to test method edit screen
	 */
	public function add_revision_meta_box() {
		// Only add for published and locked posts
		$screen = get_current_screen();
		if ($screen->base != 'post' || $screen->post_type != 'test_method') {
			return;
		}
		
		global $post;
		
		// Skip this meta box for revisions
		if (get_post_meta($post->ID, '_is_revision', true)) {
			return;
		}
		
		// Add meta box
		add_meta_box(
			'test_method_revision_manager',
			__('Revision Management', 'test-method-workflow'),
			array($this, 'revision_meta_box_callback'),
			'test_method',
			'side',
			'default'
		);
	}
	
	/**
	 * Add clean diff comparison to revision meta box
	 */
	public function revision_meta_box_callback($post) {
		wp_nonce_field('test_method_revision_nonce', 'test_method_revision_nonce');
		
		// Check if post has existing revisions
		$revisions = $this->get_post_revisions($post->ID);
		$has_revisions = !empty($revisions);
		
		// Check if post is locked
		$is_locked = get_post_meta($post->ID, '_is_locked', true);
		
		// Check user roles
		$user = wp_get_current_user();
		$user_roles = (array) $user->roles;
		$can_create_revision = array_intersect($user_roles, array('tp_contributor', 'tp_approver', 'tp_admin', 'administrator'));
		
		echo '<div class="revision-manager-container">';
		
		if ($has_revisions) {
			echo '<div class="notice notice-warning" style="margin: 5px 0;">';
			echo '<p>' . __('This test method already has an active revision. You must complete or delete the existing revision before creating a new one.', 'test-method-workflow') . '</p>';
			echo '</div>';
			
			echo '<h4>' . __('Active Revisions', 'test-method-workflow') . '</h4>';
			echo '<ul class="revision-list">';
			
			foreach ($revisions as $revision) {
				$workflow_status = get_post_meta($revision->ID, '_workflow_status', true);
				$status_display = ucfirst(str_replace('_', ' ', $workflow_status ?: 'draft'));
				$revision_version = get_post_meta($revision->ID, '_current_version_number', true);
				
				echo '<li>';
				echo '<a href="' . get_edit_post_link($revision->ID) . '">' . esc_html($revision->post_title) . '</a>';
				echo ' - <span class="revision-status">' . esc_html($status_display) . '</span>';
				
				// Add diff comparison button
				echo ' <button type="button" class="button button-small compare-revision-btn" data-parent="' . $post->ID . 
					'" data-revision="' . $revision->ID . '">'. __('Compare Changes', 'test-method-workflow') . '</button>';
				
				echo '</li>';
			}
			
			echo '</ul>';
			
			// Add comparison modal container
			echo '<div id="revision-comparison-modal" style="display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.6);">
				<div class="version-comparison-content" style="position:relative; background-color:white; margin:5% auto; padding:20px; width:90%; max-width:1000px; max-height:80vh; overflow:auto;">
					<span class="close-comparison" style="position:absolute; top:10px; right:20px; font-size:28px; cursor:pointer;">&times;</span>
					<h2>' . __('Revision Comparison', 'test-method-workflow') . '</h2>
					<div id="revision-comparison-result"></div>
				</div>
			</div>';
			
			// Add JavaScript for comparison
			?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Compare revision button
				$('.compare-revision-btn').on('click', function() {
					var parentId = $(this).data('parent');
					var revisionId = $(this).data('revision');
					
					// Show the modal
					$('#revision-comparison-modal').show();
					$('#revision-comparison-result').html('<p><?php echo esc_js(__('Loading comparison...', 'test-method-workflow')); ?></p>');
					
					// Execute AJAX call to generate diff
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'compare_test_method_revision',
							parent_id: parentId,
							revision_id: revisionId,
							nonce: '<?php echo wp_create_nonce('test_method_revision_nonce'); ?>'
						},
						success: function(response) {
							if (response.success) {
								$('#revision-comparison-result').html(response.data.html);
							} else {
								$('#revision-comparison-result').html('<p class="error">' + 
									(response.data || '<?php echo esc_js(__('An error occurred loading the comparison', 'test-method-workflow')); ?>') + 
									'</p>');
							}
						},
						error: function() {
							$('#revision-comparison-result').html('<p class="error"><?php echo esc_js(__('An error occurred. Please try again.', 'test-method-workflow')); ?></p>');
						}
					});
				});
				
				// Close modal
				$('.close-comparison').on('click', function() {
					$('#revision-comparison-modal').hide();
				});
				
				// Close modal when clicking outside
				$(window).on('click', function(e) {
					if ($(e.target).is('#revision-comparison-modal')) {
						$('#revision-comparison-modal').hide();
					}
				});
			});
			</script>
			<?php
		}
		
		// Show create revision button for locked published posts
		if ($post->post_status === 'publish' || $is_locked) {
			if ($can_create_revision) {
				if ($is_locked && !array_intersect($user_roles, array('administrator', 'tp_admin'))) {
					echo '<p>' . __('Only administrators can create revisions of locked test methods.', 'test-method-workflow') . '</p>';
				} else {
					if (!$has_revisions) {
						echo '<div class="create-revision-section" style="margin-top: 10px; padding: 10px; background: #f0f6fb; border: 1px solid #ddd; border-radius: 4px;">';
						echo '<h4>' . __('Create New Version', 'test-method-workflow') . '</h4>';
						echo '<p>' . __('Create a new revision to propose changes to this test method.', 'test-method-workflow') . '</p>';
						
						// Version type selection
						$current_version = get_post_meta($post->ID, '_current_version_number', true);
						if (empty($current_version)) {
							$current_version = '0.1';
						}
						
						// Parse current version
						$version_parts = explode('.', $current_version);
						$major = isset($version_parts[0]) ? intval($version_parts[0]) : 0;
						$minor = isset($version_parts[1]) ? intval($version_parts[1]) : 0;
						
						// Calculate next versions
						$next_minor = $major . '.' . ($minor + 1);
						$next_major = ($major + 1) . '.0';
						
						echo '<div class="version-type-selection" style="margin-bottom: 10px;">';
						echo '<p><label><input type="radio" name="revision_version_type" value="minor" checked> ' . 
							__('Minor Update', 'test-method-workflow') . ' (' . $current_version . ' → ' . $next_minor . ')</label></p>';
						echo '<p><label><input type="radio" name="revision_version_type" value="major"> ' . 
							__('Major Update', 'test-method-workflow') . ' (' . $current_version . ' → ' . $next_major . ')</label></p>';
						echo '</div>';
						
						// Version note field
						echo '<div class="version-note-field" style="margin-bottom: 10px;">';
						echo '<p><label for="revision_version_note">' . __('Version Note:', 'test-method-workflow') . '</label><br>';
						echo '<textarea id="revision_version_note" rows="3" style="width: 100%;" placeholder="' . 
							esc_attr__('Describe what changes you plan to make in this new version', 'test-method-workflow') . '"></textarea></p>';
						echo '</div>';
						
						// Include nonce directly in the data attribute
						$nonce = wp_create_nonce('test_method_revision_nonce');
						echo '<button type="button" class="button button-primary create-revision" data-post-id="' . $post->ID . '" data-nonce="' . $nonce . '">' . 
							__('Create New Revision', 'test-method-workflow') . '</button>';
						
						echo '</div>';
						
						// Add JavaScript for enhanced revision creation
						?>
						<script type="text/javascript">
						jQuery(document).ready(function($) {
							$('.create-revision').on('click', function() {
								var postId = $(this).data('post-id');
								var nonce = $(this).data('nonce');
								var versionType = $('input[name="revision_version_type"]:checked').val();
								var versionNote = $('#revision_version_note').val();
								
								if (!versionNote) {
									alert('<?php echo esc_js(__('Please enter a version note describing the changes you plan to make.', 'test-method-workflow')); ?>');
									$('#revision_version_note').focus();
									return;
								}
								
								// Get the current content from the editor
								var postContent = '';
								
								if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
									postContent = tinymce.activeEditor.getContent();
								} else {
									postContent = $('#content').val();
								}
								
								if (confirm('<?php echo esc_js(__('Are you sure you want to create a new revision? This will create a draft for editing.', 'test-method-workflow')); ?>')) {
									$(this).prop('disabled', true).text('<?php echo esc_js(__('Creating...', 'test-method-workflow')); ?>');
									
									$.ajax({
										url: ajaxurl,
										type: 'POST',
										data: {
											action: 'create_test_method_revision',
											post_id: postId,
											nonce: nonce,
											version_type: versionType,
											version_note: versionNote,
											post_content: postContent
										},
										success: function(response) {
											if (response.success) {
												alert(response.data.message);
												window.location.href = response.data.edit_url;
											} else {
												alert(response.data || '<?php echo esc_js(__('An error occurred', 'test-method-workflow')); ?>');
												$('.create-revision').prop('disabled', false).text('<?php echo esc_js(__('Create New Revision', 'test-method-workflow')); ?>');
											}
										},
										error: function() {
											alert('<?php echo esc_js(__('An error occurred. Please try again.', 'test-method-workflow')); ?>');
											$('.create-revision').prop('disabled', false).text('<?php echo esc_js(__('Create New Revision', 'test-method-workflow')); ?>');
										}
									});
								}
							});
						});
						</script>
						<?php
					}
				}
			}
		} else {
			echo '<p>' . __('You can create revisions once this test method is published.', 'test-method-workflow') . '</p>';
		}
		
		echo '</div>';
	}
	
	/**
	 * AJAX handler for comparing revisions
	 */
	public function compare_test_method_revision() {
		// Check nonce
		check_ajax_referer('test_method_revision_nonce', 'nonce');
		
		$parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
		$revision_id = isset($_POST['revision_id']) ? intval($_POST['revision_id']) : 0;
		
		if (!$parent_id || !$revision_id) {
			wp_send_json_error('Invalid post IDs');
			return;
		}
		
		$parent_post = get_post($parent_id);
		$revision_post = get_post($revision_id);
		
		if (!$parent_post || !$revision_post) {
			wp_send_json_error('One or both posts not found');
			return;
		}
		
		// Clean content for diff comparison (remove HTML markup)
		$parent_clean = $this->clean_content_for_diff($parent_post->post_content);
		$revision_clean = $this->clean_content_for_diff($revision_post->post_content);
		
		// Split content into lines
		$parent_lines = explode("\n", $parent_clean);
		$revision_lines = explode("\n", $revision_clean);
		
		// Use WordPress diff engine
		if (!class_exists('WP_Text_Diff_Renderer_Table')) {
			require_once ABSPATH . WPINC . '/wp-diff.php';
		}
		
		$diff = new \Text_Diff($parent_lines, $revision_lines);
		$renderer = new \WP_Text_Diff_Renderer_Table(array(
			'show_split_view' => true,
		));
		
		// Version information
		$parent_version = get_post_meta($parent_id, '_current_version_number', true);
		$revision_version = get_post_meta($revision_id, '_current_version_number', true);
		
		if (empty($parent_version)) {
			$parent_version = '0.1';
		}
		
		// Build output HTML
		$output = '<div class="diff-view">';
		$output .= '<h3>' . sprintf(
			__('Comparing Original (v%s) with Revision (v%s)', 'test-method-workflow'),
			esc_html($parent_version),
			esc_html($revision_version)
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
	}