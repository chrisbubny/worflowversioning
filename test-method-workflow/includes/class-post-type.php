<?php
/**
 * Modified Post Type Registration
 *
 * @package TestMethodWorkflow
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Test Method post type class - Modified for multiple post types
 */
class TestMethod_PostType {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register post types
        add_action('init', array($this, 'register_post_types'));
        
        // Register meta fields
        add_action('init', array($this, 'register_meta_fields'));
        
        // Force refresh all revisions
        add_filter('wp_revisions_to_keep', array($this, 'keep_all_revisions'), 10, 2);
        
        // Add post meta to post status transitions
        add_action('transition_post_status', array($this, 'handle_status_transition'), 10, 3);
        
        // Modify admin display for custom statuses
        add_filter('display_post_states', array($this, 'display_custom_post_states'), 10, 2);
        
        // Group post types under Criteria
        add_action('admin_menu', array($this, 'group_post_types_under_criteria'));
    }
    
    /**
     * Register the custom post types
     */
    public function register_post_types() {
        // Register test_method post type (existing)
        $this->register_test_method_post_type();
        
        // Register ccg-version post type
        $this->register_ccg_version_post_type();
        
        // Register tp-version post type
        $this->register_tp_version_post_type();
        
        // Register custom statuses
        $this->register_custom_statuses();
    }
    
    /**
     * Register test_method post type
     */
    private function register_test_method_post_type() {
        $labels = array(
            'name'               => _x('Test Methods', 'post type general name', 'test-method-workflow'),
            'singular_name'      => _x('Test Method', 'post type singular name', 'test-method-workflow'),
            'menu_name'          => _x('Test Methods', 'admin menu', 'test-method-workflow'),
            'name_admin_bar'     => _x('Test Method', 'add new on admin bar', 'test-method-workflow'),
            'add_new'            => _x('Add New', 'test method', 'test-method-workflow'),
            'add_new_item'       => __('Add New Test Method', 'test-method-workflow'),
            'new_item'           => __('New Test Method', 'test-method-workflow'),
            'edit_item'          => __('Edit Test Method', 'test-method-workflow'),
            'view_item'          => __('View Test Method', 'test-method-workflow'),
            'all_items'          => __('All Test Methods', 'test-method-workflow'),
            'search_items'       => __('Search Test Methods', 'test-method-workflow'),
            'parent_item_colon'  => __('Parent Test Methods:', 'test-method-workflow'),
            'not_found'          => __('No test methods found.', 'test-method-workflow'),
            'not_found_in_trash' => __('No test methods found in Trash.', 'test-method-workflow')
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // Will be added under Criteria menu
            'query_var'           => true,
            'rewrite'             => array('slug' => 'test-method'),
            'capability_type'     => array('test_method', 'test_methods'),
            'map_meta_cap'        => true,
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => array(
                'title',
                'editor',
                'author',
                'revisions',
                'custom-fields',
                'thumbnail'
            ),
            // Gutenberg support
            'show_in_rest'        => true,
            'rest_base'           => 'test-methods',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );
        
        register_post_type('test_method', $args);
    }
    
    /**
     * Register ccg-version post type
     */
    private function register_ccg_version_post_type() {
        $labels = array(
            'name'               => _x('CCG Versions', 'post type general name', 'test-method-workflow'),
            'singular_name'      => _x('CCG Version', 'post type singular name', 'test-method-workflow'),
            'menu_name'          => _x('CCG Versions', 'admin menu', 'test-method-workflow'),
            'name_admin_bar'     => _x('CCG Version', 'add new on admin bar', 'test-method-workflow'),
            'add_new'            => _x('Add New', 'ccg version', 'test-method-workflow'),
            'add_new_item'       => __('Add New CCG Version', 'test-method-workflow'),
            'new_item'           => __('New CCG Version', 'test-method-workflow'),
            'edit_item'          => __('Edit CCG Version', 'test-method-workflow'),
            'view_item'          => __('View CCG Version', 'test-method-workflow'),
            'all_items'          => __('All CCG Versions', 'test-method-workflow'),
            'search_items'       => __('Search CCG Versions', 'test-method-workflow'),
            'parent_item_colon'  => __('Parent CCG Versions:', 'test-method-workflow'),
            'not_found'          => __('No CCG versions found.', 'test-method-workflow'),
            'not_found_in_trash' => __('No CCG versions found in Trash.', 'test-method-workflow')
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // Will be added under Criteria menu
            'query_var'           => true,
            'rewrite'             => array('slug' => 'ccg-version'),
            'capability_type'     => array('ccg_version', 'ccg_versions'),
            'map_meta_cap'        => true,
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => array(
                'title',
                'editor',
                'author',
                'revisions',
                'custom-fields',
                'thumbnail'
            ),
            // Gutenberg support
            'show_in_rest'        => true,
            'rest_base'           => 'ccg-versions',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );
        
        register_post_type('ccg-version', $args);
    }
    
    /**
     * Register tp-version post type
     */
    private function register_tp_version_post_type() {
        $labels = array(
            'name'               => _x('TP Versions', 'post type general name', 'test-method-workflow'),
            'singular_name'      => _x('TP Version', 'post type singular name', 'test-method-workflow'),
            'menu_name'          => _x('TP Versions', 'admin menu', 'test-method-workflow'),
            'name_admin_bar'     => _x('TP Version', 'add new on admin bar', 'test-method-workflow'),
            'add_new'            => _x('Add New', 'tp version', 'test-method-workflow'),
            'add_new_item'       => __('Add New TP Version', 'test-method-workflow'),
            'new_item'           => __('New TP Version', 'test-method-workflow'),
            'edit_item'          => __('Edit TP Version', 'test-method-workflow'),
            'view_item'          => __('View TP Version', 'test-method-workflow'),
            'all_items'          => __('All TP Versions', 'test-method-workflow'),
            'search_items'       => __('Search TP Versions', 'test-method-workflow'),
            'parent_item_colon'  => __('Parent TP Versions:', 'test-method-workflow'),
            'not_found'          => __('No TP versions found.', 'test-method-workflow'),
            'not_found_in_trash' => __('No TP versions found in Trash.', 'test-method-workflow')
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => false, // Will be added under Criteria menu
            'query_var'           => true,
            'rewrite'             => array('slug' => 'tp-version'),
            'capability_type'     => array('tp_version', 'tp_versions'),
            'map_meta_cap'        => true,
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => null,
            'supports'            => array(
                'title',
                'editor',
                'author',
                'revisions',
                'custom-fields',
                'thumbnail'
            ),
            // Gutenberg support
            'show_in_rest'        => true,
            'rest_base'           => 'tp-versions',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        );
        
        register_post_type('tp-version', $args);
    }
    
    /**
     * Group post types under Criteria menu
     */
    public function group_post_types_under_criteria() {
        // Add main Criteria menu
        add_menu_page(
            __('Criteria', 'test-method-workflow'),
            __('Criteria', 'test-method-workflow'),
            'edit_posts',
            'criteria',
            function() {
                // Redirect to the test methods list as default
                wp_redirect(admin_url('edit.php?post_type=test_method'));
                exit;
            },
            'dashicons-clipboard',
            25
        );
        
        // Add Test Methods submenu
        add_submenu_page(
            'criteria',
            __('Test Methods', 'test-method-workflow'),
            __('Test Methods', 'test-method-workflow'),
            'edit_test_methods',
            'edit.php?post_type=test_method'
        );
        
        // Add CCG Versions submenu
        add_submenu_page(
            'criteria',
            __('CCG Versions', 'test-method-workflow'),
            __('CCG Versions', 'test-method-workflow'),
            'edit_ccg_versions',
            'edit.php?post_type=ccg-version'
        );
        
        // Add TP Versions submenu
        add_submenu_page(
            'criteria',
            __('TP Versions', 'test-method-workflow'),
            __('TP Versions', 'test-method-workflow'),
            'edit_tp_versions',
            'edit.php?post_type=tp-version'
        );
        
        // Remove main criteria first submenu (duplicate)
        remove_submenu_page('criteria', 'criteria');
    }
    
    /**
     * Register custom post statuses
     */
    private function register_custom_statuses() {
        // Pending Review status
        register_post_status('pending_review', array(
            'label'                     => _x('Pending Review', 'workflow status'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Pending Review <span class="count">(%s)</span>', 'Pending Review <span class="count">(%s)</span>'),
        ));
        
        // Pending Final Approval status
        register_post_status('pending_final_approval', array(
            'label'                     => _x('Pending Final Approval', 'workflow status'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Pending Final Approval <span class="count">(%s)</span>', 'Pending Final Approval <span class="count">(%s)</span>'),
        ));
        
        // Approved status
        register_post_status('approved', array(
            'label'                     => _x('Approved', 'workflow status'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>'),
        ));
        
        // Rejected status
        register_post_status('rejected', array(
            'label'                     => _x('Rejected', 'workflow status'),
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>'),
        ));
        
        // Locked status
        register_post_status('locked', array(
            'label'                     => _x('Locked', 'workflow status'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Locked <span class="count">(%s)</span>', 'Locked <span class="count">(%s)</span>'),
        ));
    }
    
    /**
     * Register meta fields for all post types
     */
    public function register_meta_fields() {
        // Register meta fields for test_method post type
        $this->register_test_method_meta_fields();
        
        // Register meta fields for ccg-version post type
        $this->register_ccg_version_meta_fields();
        
        // Register meta fields for tp-version post type
        $this->register_tp_version_meta_fields();
    }
    
    /**
     * Register meta fields for test_method post type
     */
    private function register_test_method_meta_fields() {
        // Only register locking-related meta for test_method
        register_post_meta('test_method', '_is_locked', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean',
            'auth_callback' => function() { 
                return current_user_can('edit_test_methods');
            },
            'default' => false
        ));
        
        // Current version number
        register_post_meta('test_method', '_current_version_number', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() { 
                return current_user_can('edit_test_methods');
            },
            'default' => '0.0'
        ));
    }
    
    /**
     * Register meta fields for ccg-version post type
     */
    private function register_ccg_version_meta_fields() {
        // Register all workflow-related meta for ccg-version
        $this->register_workflow_meta_fields('ccg-version');
        
        // Add related test_method ID field
        register_post_meta('ccg-version', '_related_test_method', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'auth_callback' => function() { 
                return current_user_can('edit_ccg_versions');
            }
        ));
    }
    
    /**
     * Register meta fields for tp-version post type
     */
    private function register_tp_version_meta_fields() {
        // Register all workflow-related meta for tp-version
        $this->register_workflow_meta_fields('tp-version');
        
        // Add related test_method ID field
        register_post_meta('tp-version', '_related_test_method', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'auth_callback' => function() { 
                return current_user_can('edit_tp_versions');
            }
        ));
    }
    
    /**
     * Register common workflow meta fields for a post type
     */
    private function register_workflow_meta_fields($post_type) {
        // Workflow status
        register_post_meta($post_type, '_workflow_status', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            },
            'default' => 'draft'
        ));
        
        // Approvals
        register_post_meta($post_type, '_approvals', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'object',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            }
        ));
        
        // Locked status
        register_post_meta($post_type, '_is_locked', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            },
            'default' => false
        ));
        
        // Awaiting final approval
        register_post_meta($post_type, '_awaiting_final_approval', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            },
            'default' => false
        ));
        
        // Assigned approvers
        register_post_meta($post_type, '_assigned_approvers', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'array',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            }
        ));
        
        // Revision parent
        register_post_meta($post_type, '_revision_parent', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'integer',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            }
        ));
        
        // Is revision
        register_post_meta($post_type, '_is_revision', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            },
            'default' => false
        ));
        
        // Revision history
        register_post_meta($post_type, '_revision_history', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'object',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            }
        ));
        
        // Version tracking
        register_post_meta($post_type, '_current_version_number', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            },
            'default' => '0.0'
        ));
        
        // Version change type (major, minor, none)
        register_post_meta($post_type, '_cpt_version', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            },
            'default' => 'no_change'
        ));
        
        // Version note
        register_post_meta($post_type, '_cpt_version_note', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            }
        ));
        
        // Cancel approval request
        register_post_meta($post_type, '_cancel_approval', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean',
            'auth_callback' => function() use ($post_type) { 
                return current_user_can('edit_' . str_replace('-', '_', $post_type) . 's');
            },
            'default' => false
        ));
    }
    
    /**
     * Keep all revisions for workflow post types
     */
    public function keep_all_revisions($num, $post) {
        if (in_array($post->post_type, array('test_method', 'ccg-version', 'tp-version'))) {
            return -1; // Keep all revisions
        }
        return $num;
    }
    
    /**
     * Handle post status transitions
     */
    public function handle_status_transition($new_status, $old_status, $post) {
        if (!in_array($post->post_type, array('test_method', 'ccg-version', 'tp-version'))) {
            return;
        }
        
        // When a post is published, automatically lock it
        if ($new_status === 'publish' && $old_status !== 'publish') {
            update_post_meta($post->ID, '_is_locked', true);
            
            // For test_method, just set as published without workflow status
            if ($post->post_type === 'test_method') {
                // Record in revision history
                $this->add_to_revision_history($post->ID, 'published and locked');
            } else {
                // For ccg-version and tp-version, update workflow status
                update_post_meta($post->ID, '_workflow_status', 'publish');
                
                // Record in revision history
                $this->add_to_revision_history($post->ID, 'published and locked');
                
                // If this is related to a test_method, update its content
                $related_test_method_id = get_post_meta($post->ID, '_related_test_method', true);
                if ($related_test_method_id) {
                    $this->update_related_test_method($post->ID, $related_test_method_id);
                }
            }
        }
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
        $version_type = get_post_meta($version_id, '_cpt_version', true);
        $this->add_to_revision_history(
            $test_method_id, 
            'updated from ' . $version_post->post_type . ' version: ' . $version_number
        );
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
    
    /**
     * Display custom post states in admin
     */
    public function display_custom_post_states($post_states, $post) {
        if (!in_array($post->post_type, array('test_method', 'ccg-version', 'tp-version'))) {
            return $post_states;
        }
        
        $workflow_status = get_post_meta($post->ID, '_workflow_status', true);
        $is_locked = get_post_meta($post->ID, '_is_locked', true);
        $is_revision = get_post_meta($post->ID, '_is_revision', true);
        
        // Add workflow status to post states
        if ($workflow_status && $workflow_status !== 'draft' && $workflow_status !== 'publish') {
            $status_label = ucfirst(str_replace('_', ' ', $workflow_status));
            $post_states['workflow_status'] = $status_label;
        }
        
        // Add locked state
        if ($is_locked) {
            $post_states['locked'] = __('Locked', 'test-method-workflow');
        }
        
        // Add revision state
        if ($is_revision) {
            $post_states['revision'] = __('Revision', 'test-method-workflow');
        }
        
        return $post_states;
    }
}