<?php
/**
 * Version Control Management
 *
 * @package TestMethodWorkflow
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Test Method version control class
 */
class TestMethod_VersionControl {
	
	/**
	 * Meta keys
	 */
	private $version_meta_key = '_current_version_number';
	private $version_type_meta_key = '_cpt_version';
	private $version_note_meta_key = '_cpt_version_note';
	private $revision_version_meta_key = '_revision_version';
	private $version_archive_meta_key = '_cpt_version_archived';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Add meta box for version control
		add_action('add_meta_boxes', array($this, 'add_version_meta_box'));
		
		// Save version data
		add_action('save_post_test_method', array($this, 'save_version_data'), 10, 2);
		
		// Auto-increment version on publish based on version type
		add_action('publish_test_method', array($this, 'handle_version_on_publish'), 10, 1);
		
		// Add version info to post content
		add_filter('the_content', array($this, 'add_version_info_to_content'), 20);
		
		// Add version column to admin
		add_filter('manage_test_method_posts_columns', array($this, 'add_version_column'));
		add_action('manage_test_method_posts_custom_column', array($this, 'display_version_column'), 10, 2);
	}
	
	/**
	 * Add version control meta box
	 */
	public function add_version_meta_box() {
		add_meta_box(
			'test_method_version',
			__('Version Control', 'test-method-workflow'),
			array($this, 'version_meta_box_callback'),
			'test_method',
			'side',
			'default'
		);
	}
	
	/**
	 * Version control meta box callback
	 */
	public function version_meta_box_callback($post) {
		wp_nonce_field('test_method_version_nonce', 'test_method_version_nonce');
		
		$current_version = get_post_meta($post->ID, $this->version_meta_key, true);
		
		if (empty($current_version)) {
			$current_version = '0.0';
		}
		
		// Parse version into major and minor
		$version_parts = explode('.', $current_version);
		$major = isset($version_parts[0]) ? intval($version_parts[0]) : 0;
		$minor = isset($version_parts[1]) ? intval($version_parts[1]) : 0;
		
		// Calculate next versions
		$next_minor = $major . '.' . ($minor + 1);
		$next_major = ($major + 1) . '.0';
		
		// Get version note
		$version_note = get_post_meta($post->ID, $this->version_note_meta_key, true);
		
		// Check if current version is archived
		$is_archived = get_post_meta($post->ID, $this->version_archive_meta_key, true);
		
		// Check if post is a revision
		$is_revision = get_post_meta($post->ID, '_is_revision', true);
		$revision_parent = get_post_meta($post->ID, '_revision_parent', true);
		
		// Check if user can edit version info
		$can_edit_version = current_user_can('manage_options') || current_user_can('tp_admin');
		
		echo '<div class="version-control-container">';
		
		if ($is_revision) {
			$parent_post = get_post($revision_parent);
			$parent_version = get_post_meta($revision_parent, $this->version_meta_key, true);
			
			echo '<p><strong>' . __('Parent Version:', 'test-method-workflow') . '</strong> ' . esc_html($parent_version) . '</p>';
			echo '<p><strong>' . __('Revision Of:', 'test-method-workflow') . '</strong> <a href="' . get_edit_post_link($revision_parent) . '">' . esc_html($parent_post->post_title) . '</a></p>';
		}
		
		echo '<p><strong>' . __('Current Version:', 'test-method-workflow') . '</strong> ' . esc_html($current_version);
		if ($is_archived) {
			echo ' <span class="archived-badge" style="display: inline-block; background: #e5e5e5; border-radius: 3px; padding: 1px 5px; font-size: 11px; margin-left: 5px;">' . __('Archived', 'test-method-workflow') . '</span>';
		}
		echo '</p>';
		
		if ($can_edit_version) {
			echo '<p>';
			echo '<label for="version_update_type">' . __('Version Update:', 'test-method-workflow') . '</label><br>';
			echo '<select name="version_update_type" id="version_update_type">';
			echo '<option value="none">' . __('No Change', 'test-method-workflow') . '</option>';
			echo '<option value="minor">' . __('Minor', 'test-method-workflow') . ' (' . esc_html($next_minor) . ')</option>';
			echo '<option value="major">' . __('Major', 'test-method-workflow') . ' (' . esc_html($next_major) . ')</option>';
			echo '<option value="custom">' . __('Custom', 'test-method-workflow') . '</option>';
			echo '</select>';
			echo '</p>';
			
			echo '<div id="custom_version_container" style="display: none;">';
			echo '<p>';
			echo '<label for="custom_version">' . __('Custom Version:', 'test-method-workflow') . '</label><br>';
			echo '<input type="text" name="custom_version" id="custom_version" value="' . esc_attr($current_version) . '" />';
			echo '</p>';
			echo '</div>';
		}
		
		echo '<p>';
		echo '<label for="version_note">' . __('Version Note:', 'test-method-workflow') . '</label><br>';
		echo '<textarea name="version_note" id="version_note" style="width: 100%;" rows="3"' . (!$can_edit_version ? ' readonly' : '') . '>' . esc_textarea($version_note) . '</textarea>';
		echo '</p>';
		
		if ($can_edit_version) {
			echo '<p>';
			echo '<label for="archive_version">';
			echo '<input type="checkbox" name="archive_version" id="archive_version" value="1" ' . checked($is_archived, true, false) . ' />';
			echo __('Archive this version', 'test-method-workflow');
			echo '</label>';
			echo '<span class="description">' . __('Archived versions are accessible via the front-end', 'test-method-workflow') . '</span>';
			echo '</p>';
		}
		
		echo '</div>';
		
		// Add JavaScript for custom version toggling
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Toggle custom version field
			$('#version_update_type').on('change', function() {
				if ($(this).val() === 'custom') {
					$('#custom_version_container').show();
				} else {
					$('#custom_version_container').hide();
				}
			});
		});
		</script>
		<?php
	}
	
	/**
	 * Save version data
	 */
	public function save_version_data($post_id, $post) {
		// Check if this is an autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		
		// Check post type
		if ($post->post_type !== 'test_method') {
			return;
		}
		
		// Verify nonce
		if (!isset($_POST['test_method_version_nonce']) || !wp_verify_nonce($_POST['test_method_version_nonce'], 'test_method_version_nonce')) {
			return;
		}
		
		// Check permissions
		if (!current_user_can('edit_post', $post_id)) {
			return;
		}
		
		// Check if user can edit version info
		$can_edit_version = current_user_can('manage_options') || current_user_can('tp_admin');
		
		if ($can_edit_version) {
			// Get current version
			$current_version = get_post_meta($post_id, $this->version_meta_key, true);
			if (empty($current_version)) {
				$current_version = '0.0';
			}
			
			// Parse current version
			$version_parts = explode('.', $current_version);
			$major = isset($version_parts[0]) ? intval($version_parts[0]) : 0;
			$minor = isset($version_parts[1]) ? intval($version_parts[1]) : 0;
			
			// Determine new version
			$new_version = $current_version; // Default to no change
			
			if (isset($_POST['version_update_type'])) {
				switch ($_POST['version_update_type']) {
					case 'minor':
						$new_version = $major . '.' . ($minor + 1);
						break;
						
					case 'major':
						$new_version = ($major + 1) . '.0';
						break;
						
					case 'custom':
						if (!empty($_POST['custom_version'])) {
							$new_version = sanitize_text_field($_POST['custom_version']);
						}
						break;
				}
			}
			
			// Only update if version changed
			if ($new_version !== $current_version) {
				update_post_meta($post_id, $this->version_meta_key, $new_version);
				
				// Store version type
				if (isset($_POST['version_update_type']) && $_POST['version_update_type'] !== 'none') {
					update_post_meta($post_id, $this->version_type_meta_key, sanitize_text_field($_POST['version_update_type']));
				}
				
				// Add to revision history
				$this->add_to_version_history($post_id, $new_version, 'version updated');
			}
			
			// Save archive status
			$archive_version = isset($_POST['archive_version']) ? 1 : 0;
			update_post_meta($post_id, $this->version_archive_meta_key, $archive_version);
		}
		
		// Save version note (all roles can add notes)
		if (isset($_POST['version_note'])) {
			$version_note = sanitize_textarea_field($_POST['version_note']);
			update_post_meta($post_id, $this->version_note_meta_key, $version_note);
		}
	}
	
	/**
	 * Handle version updates on publish
	 */
public function handle_version_on_publish($post_id) {
		 // Check if version is already being updated via AJAX to prevent double increment
		 $version_being_updated = get_post_meta($post_id, '_version_being_updated', true);
		 if ($version_being_updated) {
			 // Clear the flag and exit
			 delete_post_meta($post_id, '_version_being_updated');
			 return;
		 }
	 
		 // Get current state
		 $workflow_status = get_post_meta($post_id, '_workflow_status', true);
		 $version_type = get_post_meta($post_id, $this->version_type_meta_key, true);
		 $current_version = get_post_meta($post_id, $this->version_meta_key, true);
		 
		 // If no version change is needed, exit early
		 if (empty($version_type) || $version_type === 'none' || $version_type === 'no_change') {
			 return;
		 }
		 
		 // CRITICAL FIX: Skip version increment entirely during workflow publishing process
		 if (defined('TMW_PUBLISHING_APPROVED') && TMW_PUBLISHING_APPROVED) {
			 // Reset version type to avoid re-incrementing on next save
			 update_post_meta($post_id, $this->version_type_meta_key, 'none');
			 return;
		 }
		 
		 // For regular publishing (not through workflow), handle version increment
		 if (empty($current_version)) {
			 $current_version = '0.1';
		 }
		 
		 $version_parts = explode('.', $current_version);
		 $major = isset($version_parts[0]) ? intval($version_parts[0]) : 0;
		 $minor = isset($version_parts[1]) ? intval($version_parts[1]) : 0;
		 
		 $new_version = $current_version;
		 
		 if ($version_type === 'minor') {
			 $new_version = $major . '.' . ($minor + 1);
		 } elseif ($version_type === 'major') {
			 $new_version = ($major + 1) . '.0';
		 } elseif ($version_type === 'custom') {
			 // Custom version was already set in save_version_data
			 return;
		 }
		 
		 if ($new_version !== $current_version) {
			 update_post_meta($post_id, $this->version_meta_key, $new_version);
			 
			 // Add to revision history
			 $this->add_to_version_history($post_id, $new_version, 'version updated on publish');
		 }
		 
		 // Reset version type to avoid re-incrementing on next save
		 update_post_meta($post_id, $this->version_type_meta_key, 'none');
	 }
	
	/**
	 * Add version column to admin
	 */
	public function add_version_column($columns) {
		$new_columns = array();
		
		foreach ($columns as $key => $value) {
			$new_columns[$key] = $value;
			
			if ($key === 'title') {
				$new_columns['version'] = __('Version', 'test-method-workflow');
			}
		}
		
		return $new_columns;
	}
	
	/**
	 * Display version in admin column
	 */
	public function display_version_column($column, $post_id) {
		if ($column === 'version') {
			$version = get_post_meta($post_id, $this->version_meta_key, true);
			echo !empty($version) ? esc_html($version) : '0.1';
			
			// Show if archived
			$is_archived = get_post_meta($post_id, $this->version_archive_meta_key, true);
			if ($is_archived) {
				echo ' <span class="archived-badge" style="display: inline-block; background: #e5e5e5; border-radius: 3px; padding: 1px 5px; font-size: 11px;">' . __('Archived', 'test-method-workflow') . '</span>';
			}
			
			// Show if revision
			$is_revision = get_post_meta($post_id, '_is_revision', true);
			if ($is_revision) {
				$parent_id = get_post_meta($post_id, '_revision_parent', true);
				if ($parent_id) {
					$parent_version = get_post_meta($parent_id, $this->version_meta_key, true);
					echo ' <span class="revision-badge" style="display: inline-block; background: #e5f5fa; border-radius: 3px; padding: 1px 5px; font-size: 11px; border: 1px solid #0073aa; color: #0073aa;">' . 
						 __('Revision of', 'test-method-workflow') . ' ' . (!empty($parent_version) ? $parent_version : '0.1') . '</span>';
				}
			}
		}
	}
	
	/**
	 * Add version info to content
	 */
	public function add_version_info_to_content($content) {
		global $post;
		
		if (is_singular('test_method') && $post) {
			$current_version = get_post_meta($post->ID, $this->version_meta_key, true);
			$version_note = get_post_meta($post->ID, $this->version_note_meta_key, true);
			$is_archived = get_post_meta($post->ID, $this->version_archive_meta_key, true);
			
			if (empty($current_version)) {
				$current_version = '0.1';
			}
			
			$version_info = '<div class="test-method-version-info">';
			$version_info .= '<p class="version-number"><strong>' . __('Version:', 'test-method-workflow') . '</strong> ' . 
							esc_html($current_version) . '</p>';
			
			if (!empty($version_note)) {
				$version_info .= '<p class="version-note"><strong>' . __('Version Note:', 'test-method-workflow') . '</strong> ' . 
								esc_html($version_note) . '</p>';
			}
			
			if ($is_archived) {
				$version_info .= '<p class="version-archived"><em>' . __('This is an archived version.', 'test-method-workflow') . '</em></p>';
			}
			
			$version_info .= '</div>';
			
			return $version_info . $content;
		}
		
		return $content;
	}
	
	/**
	 * Add to version history
	 */
	 private function add_to_version_history($post_id, $version, $status) {
		 $revision_history = get_post_meta($post_id, "_revision_history", true);
		 
		 if (!is_array($revision_history)) {
			 $revision_history = array();
		 }
		 
		 $revision_history[] = array(
			 "version" => count($revision_history) + 1,
			 "user_id" => get_current_user_id(),
			 "date" => time(),
			 "status" => $status,
			 "version_number" => $version
		 );
		 
		 update_post_meta($post_id, "_revision_history", $revision_history);
	 }
}