<?php
/**
 * Plugin Name: Test Method Workflow
 * Description: Custom workflow for Test Method, CCG Version, and TP Version post types with approval process and version control
 * Version: 2.0.0
 * Author: Spire Communications
 * Text Domain: test-method-workflow
 * Domain Path: /languages
 * License: GPL-2.0+
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class TestMethodWorkflow {
    const VERSION = '2.0.0';
    private static $instance = null;
    private $plugin_dir;
    private $plugin_url;

    private function __construct() {
        $this->plugin_dir = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->init();
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init() {
        $this->include_files();
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        $this->init_components();
        $this->register_hooks();
    }

    private function include_files() {
        require_once $this->plugin_dir . 'includes/class-post-type.php';
        require_once $this->plugin_dir . 'includes/class-roles-capabilities.php';
        require_once $this->plugin_dir . 'includes/class-workflow.php';
        require_once $this->plugin_dir . 'includes/class-version-control.php';
        require_once $this->plugin_dir . 'includes/class-post-locking.php';
        require_once $this->plugin_dir . 'includes/class-notifications.php';
        require_once $this->plugin_dir . 'includes/class-access-control.php';
        require_once $this->plugin_dir . 'includes/class-revision-manager.php';
        require_once $this->plugin_dir . 'includes/class-admin-ui.php';
        require_once $this->plugin_dir . 'includes/class-enhanced-dashboard.php';
        require_once $this->plugin_dir . 'includes/class-integration.php'; 
    }
    
    private function init_components() {
        new TestMethod_PostType();
        new TestMethod_RolesCapabilities();
        new TestMethod_Workflow();
        new TestMethod_VersionControl();
        new TestMethod_PostLocking();
        new TestMethod_Notifications();
        new TestMethod_AccessControl();
        new TestMethod_RevisionManager();
        new TestMethod_AdminUI();
        new TestMethod_Integration(); 
    }

    private function register_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('admin_footer', array($this, 'restrict_publish_button'), 20);
        add_action('wp_ajax_set_version_before_publish', array($this, 'set_version_before_publish'));
        add_action('transition_post_status', array($this, 'track_post_status'), 10, 3);
    }

    public function restrict_publish_button() {
        global $post_type, $post;
        if (!in_array($post_type, array('test_method', 'ccg-version', 'tp-version')) || empty($post)) return;
    
        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;
        $is_admin = array_intersect($user_roles, array('administrator', 'tp_admin'));
        $post_status = get_post_status($post);
        $workflow_status = get_post_meta($post->ID, '_workflow_status', true);
        $is_locked = get_post_meta($post->ID, '_is_locked', true);
        $is_revision = get_post_meta($post->ID, '_is_revision', true);
        $is_new_post = isset($_GET['post']) ? false : true; // If post ID isn't in URL, it's a new post
    
        // Different handling for test_method vs. other workflow post types
        if ($post_type === 'test_method') {
            // For test_method, only admin can publish/update
            if (!$is_admin) {
                echo '<style>
                    #publishing-action .button-primary.button-large { display: none !important; }
                    .editor-post-publish-panel__toggle, .editor-post-publish-button { display: none !important; }
                </style>';
                return;
            }
            
            // For admin editing test_method, allow publishing
            return;
        }
        
        // For ccg-version and tp-version, implement full workflow logic
        
        // Hide publish button for all non-admins
        if (!$is_admin) {
            echo '<style>
                #publishing-action .button-primary.button-large { display: none !important; }
                .editor-post-publish-panel__toggle, .editor-post-publish-button { display: none !important; }
            </style>';
            return;
        }
        
        // Hide publish button for new posts, even for admins
        if ($is_new_post) {
            echo '<style>
                #publishing-action .button-primary.button-large { display: none !important; }
                .editor-post-publish-panel__toggle, .editor-post-publish-button { display: none !important; }
            </style>';
            echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                // Extra measure to ensure publish button stays hidden in Gutenberg
                if (wp.data && wp.data.subscribe) {
                    wp.data.subscribe(function() {
                        $(".editor-post-publish-panel__toggle, .editor-post-publish-button").css("display", "none");
                    });
                }
            });
            </script>';
            return;
        }
    
        // For existing posts being edited by admins
        if ($is_admin) {
            // For admins, customize the publish/update button behavior
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Handle classic editor
                $('#publish').click(function(e) {
                    <?php if ($post_status != 'publish' || $is_revision): ?>
                    // Check if post is approved
                    <?php if ($workflow_status === 'approved'): ?>
                    // Post is approved, proceed with publish
                    return true;
                    <?php else: ?>
                    // Post is not approved, prevent publish
                    e.preventDefault();
                    alert("This content requires approval before it can be published.");
                    return false;
                    <?php endif; ?>
                    <?php else: ?>
                    // For already published posts being edited by admins
                    e.preventDefault();
                    
                    var updateType = prompt("Is this a basic update or version change? Type: basic / version", "basic");
                    
                    if (updateType && updateType.toLowerCase() === 'version') {
                        // Version change requires a revision
                        var versionType = prompt("Select version type: minor or major", "minor");
                        
                        if (versionType === 'minor' || versionType === 'major') {
                            // Create a revision
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'create_test_method_revision',
                                    post_id: <?php echo $post->ID; ?>,
                                    version_type: versionType,
                                    nonce: '<?php echo wp_create_nonce('test_method_revision_nonce'); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        alert('Created a new revision for approval process. Redirecting to revision editor...');
                                        window.location.href = response.data.edit_url;
                                    } else {
                                        alert(response.data || 'Error creating revision');
                                    }
                                },
                                error: function() {
                                    alert('An error occurred. Please try again.');
                                }
                            });
                            
                            return false;
                        }
                    } else {
                        // Basic update - set version type to none
                        if ($('#version_update_type').length) {
                            $('#version_update_type').val('none');
                        } else {
                            $('form#post').append('<input type="hidden" name="version_update_type" value="none">');
                        }
                        
                        // Continue with the normal save
                        $(this).unbind('click');
                        $(this).trigger('click');
                    }
                    <?php endif; ?>
                });
    
                // Handle Gutenberg editor
                if (wp.data && wp.data.subscribe) {
                    const unsubscribe = wp.data.subscribe(function() {
                        const publishButton = document.querySelector('.editor-post-publish-button:not(.is-busy)');
                        if (publishButton && !publishButton.getAttribute('data-custom-handler')) {
                            publishButton.setAttribute('data-custom-handler', 'true');
    
                            publishButton.addEventListener('click', function(e) {
                                <?php if ($post_status != 'publish' || $is_revision): ?>
                                // Check if post is approved
                                <?php if ($workflow_status === 'approved'): ?>
                                // Post is approved, proceed with publish
                                return true;
                                <?php else: ?>
                                // Post is not approved, prevent publish
                                e.preventDefault();
                                e.stopPropagation();
                                alert("This content requires approval before it can be published.");
                                return false;
                                <?php endif; ?>
                                <?php else: ?>
                                // For already published posts being edited by admins
                                e.preventDefault();
                                e.stopPropagation();
                                
                                var updateType = prompt("Is this a basic update or version change? Type: basic / version", "basic");
                                
                                if (updateType && updateType.toLowerCase() === 'version') {
                                    // Version change requires a revision
                                    var versionType = prompt("Select version type: minor or major", "minor");
                                    
                                    if (versionType === 'minor' || versionType === 'major') {
                                        // Prevent default save action
                                        e.preventDefault();
                                        e.stopPropagation();
                                        
                                        // Save current content first to ensure we don't lose changes
                                        wp.data.dispatch('core/editor').savePost();
                                        
                                        // Now create a revision with the latest content
                                        var formData = new FormData();
                                        formData.append('action', 'create_test_method_revision');
                                        formData.append('post_id', <?php echo $post->ID; ?>);
                                        formData.append('version_type', versionType);
                                        formData.append('nonce', '<?php echo wp_create_nonce('test_method_revision_nonce'); ?>');
                                        
                                        fetch(ajaxurl, { 
                                            method: 'POST', 
                                            body: formData 
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.success) {
                                                alert('Created a new revision for approval process. Redirecting to revision editor...');
                                                window.location.href = data.data.edit_url;
                                            } else {
                                                alert(data.data || 'Error creating revision');
                                            }
                                        })
                                        .catch(error => {
                                            console.error('Error:', error);
                                            alert('An error occurred while creating the revision. Please try again.');
                                        });
                                        
                                        return;
                                    }
                                } else {
                                    // Basic update
                                    var formData = new FormData();
                                    formData.append('action', 'set_version_before_publish');
                                    formData.append('post_id', <?php echo $post->ID; ?>);
                                    formData.append('version_type', 'none');
                                    formData.append('nonce', '<?php echo wp_create_nonce('set_version_nonce'); ?>');
                                    
                                    fetch(ajaxurl, { method: 'POST', body: formData }).then(function() {
                                        publishButton.removeAttribute('data-custom-handler');
                                        publishButton.click();
                                    });
                                }
                                <?php endif; ?>
                            }, { once: true });
                        }
                    });
                }
            });
            </script>
            <?php
        }
    }
    
    public function set_version_before_publish() {
        try {
            check_ajax_referer('set_version_nonce', 'nonce');
            if (!current_user_can('publish_test_methods') && 
                !current_user_can('publish_ccg_versions') && 
                !current_user_can('publish_tp_versions')) {
                wp_send_json_error('Permission denied');
                return;
            }
            
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $version_type = isset($_POST['version_type']) ? sanitize_text_field($_POST['version_type']) : '';
            
            if (!$post_id || !$version_type) {
                wp_send_json_error('Invalid data');
                return;
            }
            
            $post = get_post($post_id);
            if (!$post || !in_array($post->post_type, array('test_method', 'ccg-version', 'tp-version'))) {
                wp_send_json_error('Invalid post type');
                return;
            }
            
            // Store the version type for later use
            update_post_meta($post_id, '_cpt_version', $version_type);
            
            // Get current version for display/calculation
            $current_version = get_post_meta($post_id, '_current_version_number', true);
            if (empty($current_version)) {
                $current_version = '0.0';
            }
    
            // Track that we're changing the version to prevent double-increment
            update_post_meta($post_id, '_version_being_updated', true);
            
            // For 'none' version type (basic updates), DO NOT change version
            if ($version_type === 'none') {
                // Add to version history, but don't change version
                $this->add_to_version_history($post_id, $current_version, 'basic update');
                
                // Clear the version being updated flag
                delete_post_meta($post_id, '_version_being_updated');
                
                wp_send_json_success(array(
                    'message' => __('Basic update - no version change', 'test-method-workflow'),
                    'version' => $current_version
                ));
                return;
            }
            
            // If this is a new post or first submission, increment from 0.0 to 0.1
            $workflow_status = get_post_meta($post_id, '_workflow_status', true);
            $is_first_submission = ($workflow_status === 'draft' || empty($workflow_status)) && $current_version === '0.0';
            
            if ($is_first_submission) {
                update_post_meta($post_id, '_current_version_number', '0.1');
                $current_version = '0.1';
            } else if ($version_type !== 'none') {
                // Calculate new version number
                $version_parts = explode('.', $current_version);
                $major = isset($version_parts[0]) ? intval($version_parts[0]) : 0;
                $minor = isset($version_parts[1]) ? intval($version_parts[1]) : 0;
                
                if ($version_type === 'minor') {
                    $new_version = $major . '.' . ($minor + 1);
                    update_post_meta($post_id, '_current_version_number', $new_version);
                    $current_version = $new_version;
                } else if ($version_type === 'major') {
                    $new_version = ($major + 1) . '.0';
                    update_post_meta($post_id, '_current_version_number', $new_version);
                    $current_version = $new_version;
                }
            }
        
            // Add to version history
            $this->add_to_version_history($post_id, $current_version, $version_type === 'none' ? 'basic update' : 'version type set for publish');
            
            wp_send_json_success(array(
                'message' => sprintf(__('Version type set to %s', 'test-method-workflow'), $version_type),
                'version' => $current_version
            ));
        } catch (Exception $e) {
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    private function add_to_version_history($post_id, $version, $status) {
        $revision_history = get_post_meta($post_id, '_revision_history', true);
        if (!is_array($revision_history)) $revision_history = array();
        $revision_history[] = array(
            'version' => count($revision_history) + 1,
            'user_id' => get_current_user_id(),
            'date' => time(),
            'status' => $status,
            'version_number' => $version
        );
        update_post_meta($post_id, '_revision_history', $revision_history);
    }

    public function activate() {
        $this->create_directories();
        $post_type = new TestMethod_PostType();
        $post_type->register_post_types();
        $roles = new TestMethod_RolesCapabilities();
        $roles->setup_roles_capabilities();
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    private function create_directories() {
        $dirs = array('css', 'js', 'templates');
        foreach ($dirs as $dir) {
            $path = $this->plugin_dir . $dir;
            if (!file_exists($path)) wp_mkdir_p($path);
        }
    }
    
    public function track_post_status($new_status, $old_status, $post) {
        if (!in_array($post->post_type, array('test_method', 'ccg-version', 'tp-version'))) {
            return;
        }
        
        // Store previous status for reference
        update_post_meta($post->ID, '_previous_status', $old_status);
    }

    public function admin_enqueue_scripts($hook) {
        global $post_type;
        
        // Register Workflow Sidebar script for Gutenberg
        if (function_exists('register_block_type')) {
            wp_register_script(
                'test-method-workflow-sidebar',
                $this->plugin_url . 'js/workflow-sidebar.js',
                array('wp-blocks', 'wp-element', 'wp-edit-post', 'wp-components', 'wp-data', 'wp-plugins', 'wp-i18n'),
                self::VERSION,
                true
            );
            
            // Add approvers list for select dropdown
            $approvers = $this->get_approvers_list();
            wp_localize_script('test-method-workflow-sidebar', 'testMethodWorkflow', array(
                'approversList' => $approvers,
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
            
            // Enqueue scripts
            wp_enqueue_script('test-method-workflow-sidebar');
        }
        
        // Only load on workflow post types
        if (!in_array($post_type, array('test_method', 'ccg-version', 'tp-version'))) {
            return;
        }
        
        wp_enqueue_style('test-method-workflow', $this->plugin_url . 'css/test-method-workflow.css', array(), self::VERSION);
        wp_enqueue_style('test-method-dashboard', $this->plugin_url . 'css/test-method-dashboard.css', array(), self::VERSION);
        wp_enqueue_style('admin-css', $this->plugin_url . 'css/admin.css', array(), self::VERSION);
        wp_enqueue_script('test-method-workflow', $this->plugin_url . 'js/test-method-workflow.js', array('jquery'), self::VERSION, true);
        wp_enqueue_script('test-method-revision', $this->plugin_url . 'js/test-method-revision.js', array('jquery'), self::VERSION, true);
        
        wp_localize_script('test-method-workflow', 'testMethodWorkflow', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('test_method_workflow'),
            'strings' => array(
                'confirm_submit' => __('Are you sure you want to submit this for review?', 'test-method-workflow'),
                'confirm_approve' => __('Are you sure you want to approve this?', 'test-method-workflow'),
                'confirm_reject' => __('Are you sure you want to reject this?', 'test-method-workflow'),
                'confirm_unlock' => __('Are you sure you want to unlock this for editing? This will move it back to draft status.', 'test-method-workflow'),
                'confirm_publish_revision' => __('Are you sure you want to publish this approved revision? It will replace the original content.', 'test-method-workflow')
            )
        ));
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

    public function frontend_enqueue_scripts() {
        global $post;
        
        if (is_singular(array('test_method', 'ccg-version', 'tp-version'))) {
            wp_enqueue_style('test-method-frontend', $this->plugin_url . 'css/test-method-frontend.css', array(), self::VERSION);
        }
    }

    public function load_textdomain() {
        load_plugin_textdomain('test-method-workflow', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function get_plugin_dir() {
        return $this->plugin_dir;
    }

    public function get_plugin_url() {
        return $this->plugin_url;
    }
}

function test_method_workflow() {
    return TestMethodWorkflow::get_instance();
}

test_method_workflow();