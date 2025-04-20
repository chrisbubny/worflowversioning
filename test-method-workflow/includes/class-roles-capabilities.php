<?php
/**
 * Roles and Capabilities Management - Modified for multiple post types
 *
 * @package TestMethodWorkflow
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Test Method roles and capabilities class
 */
class TestMethod_RolesCapabilities {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Setup roles and capabilities
        add_action('admin_init', array($this, 'setup_roles_capabilities'));
        
        // Filter user capabilities for workflow
        add_filter('user_has_cap', array($this, 'filter_user_capabilities'), 10, 4);
        
        // Map meta caps for workflow post types
        add_filter('map_meta_cap', array($this, 'map_workflow_meta_caps'), 10, 4);
    }
    
    /**
     * Setup custom roles and capabilities
     */
    public function setup_roles_capabilities() {
        // Add custom roles if they don't exist
        if (!get_role('tp_contributor')) {
            add_role('tp_contributor', 'TP Contributor', array(
                'read' => true,
            ));
        }
        
        if (!get_role('tp_approver')) {
            add_role('tp_approver', 'TP Approver', array(
                'read' => true,
            ));
        }
        
        if (!get_role('tp_admin')) {
            add_role('tp_admin', 'TP Admin', array(
                'read' => true,
            ));
        }
        
        // Get role objects
        $admin = get_role('administrator');
        $tp_admin = get_role('tp_admin');
        $tp_approver = get_role('tp_approver');
        $tp_contributor = get_role('tp_contributor');
        
        // Define capabilities for each post type
        $this->add_test_method_capabilities($admin, $tp_admin, $tp_approver, $tp_contributor);
        $this->add_ccg_version_capabilities($admin, $tp_admin, $tp_approver, $tp_contributor);
        $this->add_tp_version_capabilities($admin, $tp_admin, $tp_approver, $tp_contributor);
    }
    
    /**
     * Add test_method post type capabilities
     */
    private function add_test_method_capabilities($admin, $tp_admin, $tp_approver, $tp_contributor) {
        // Define capabilities for test methods
        $capabilities = array(
            // Post type capabilities
            'edit_test_method',
            'read_test_method',
            'delete_test_method',
            'edit_test_methods',
            'edit_others_test_methods',
            'publish_test_methods',
            'read_private_test_methods',
            'delete_test_methods',
            'delete_private_test_methods',
            'delete_published_test_methods',
            'delete_others_test_methods',
            'edit_private_test_methods',
            'edit_published_test_methods',
            
            // Locking capabilities (only for test_method)
            'lock_test_methods',
            'unlock_test_methods',
        );
        
        // Add all capabilities to admin
        if ($admin) {
            foreach ($capabilities as $cap) {
                $admin->add_cap($cap);
            }
        }
        
        // TP Admin capabilities (can do everything)
        if ($tp_admin) {
            foreach ($capabilities as $cap) {
                $tp_admin->add_cap($cap);
            }
        }
        
        // TP Approver capabilities
        if ($tp_approver) {
            $tp_approver->add_cap('edit_test_methods');
            $tp_approver->add_cap('edit_test_method');
            $tp_approver->add_cap('read_test_method');
            $tp_approver->add_cap('read_private_test_methods');
            $tp_approver->add_cap('edit_others_test_methods');
            // Don't grant locking/unlocking or publishing capabilities
        }
        
        // TP Contributor capabilities
        if ($tp_contributor) {
            $tp_contributor->add_cap('edit_test_methods');
            $tp_contributor->add_cap('edit_test_method');
            $tp_contributor->add_cap('read_test_method');
            $tp_contributor->add_cap('read_private_test_methods');
            // Don't grant locking/unlocking or publishing capabilities
        }
    }
    
    /**
     * Add ccg-version post type capabilities
     */
    private function add_ccg_version_capabilities($admin, $tp_admin, $tp_approver, $tp_contributor) {
        // Define capabilities for CCG versions
        $capabilities = array(
            // Post type capabilities
            'edit_ccg_version',
            'read_ccg_version',
            'delete_ccg_version',
            'edit_ccg_versions',
            'edit_others_ccg_versions',
            'publish_ccg_versions',
            'read_private_ccg_versions',
            'delete_ccg_versions',
            'delete_private_ccg_versions',
            'delete_published_ccg_versions',
            'delete_others_ccg_versions',
            'edit_private_ccg_versions',
            'edit_published_ccg_versions',
            
            // Workflow specific capabilities
            'approve_ccg_versions',
            'reject_ccg_versions',
            'cancel_approval_ccg_versions',
        );
        
        // Add all capabilities to admin
        if ($admin) {
            foreach ($capabilities as $cap) {
                $admin->add_cap($cap);
            }
        }
        
        // TP Admin capabilities (can do everything)
        if ($tp_admin) {
            foreach ($capabilities as $cap) {
                $tp_admin->add_cap($cap);
            }
        }
        
        // TP Approver capabilities
        if ($tp_approver) {
            $tp_approver->add_cap('edit_ccg_versions');
            $tp_approver->add_cap('edit_ccg_version');
            $tp_approver->add_cap('read_ccg_version');
            $tp_approver->add_cap('read_private_ccg_versions');
            $tp_approver->add_cap('edit_others_ccg_versions');
            $tp_approver->add_cap('approve_ccg_versions');
            $tp_approver->add_cap('reject_ccg_versions');
            $tp_approver->add_cap('edit_others_posts'); 
            // Approvers can't publish directly
        }
        
        // TP Contributor capabilities
        if ($tp_contributor) {
            $tp_contributor->add_cap('edit_ccg_versions');
            $tp_contributor->add_cap('edit_ccg_version');
            $tp_approver->add_cap('edit_others_posts'); 
            $tp_contributor->add_cap('read_ccg_version');
            $tp_contributor->add_cap('read_private_ccg_versions');
            $tp_contributor->add_cap('cancel_approval_ccg_versions');
            // Contributors can't approve, reject, or publish
        }
    }
    
    /**
     * Add tp-version post type capabilities
     */
    private function add_tp_version_capabilities($admin, $tp_admin, $tp_approver, $tp_contributor) {
        // Define capabilities for TP versions
        $capabilities = array(
            // Post type capabilities
            'edit_tp_version',
            'read_tp_version',
            'delete_tp_version',
            'edit_tp_versions',
            'edit_others_tp_versions',
            'publish_tp_versions',
            'read_private_tp_versions',
            'delete_tp_versions',
            'delete_private_tp_versions',
            'delete_published_tp_versions',
            'delete_others_tp_versions',
            'edit_private_tp_versions',
            'edit_published_tp_versions',
            
            // Workflow specific capabilities
            'approve_tp_versions',
            'reject_tp_versions',
            'cancel_approval_tp_versions',
        );
        
        // Add all capabilities to admin
        if ($admin) {
            foreach ($capabilities as $cap) {
                $admin->add_cap($cap);
            }
        }
        
        // TP Admin capabilities (can do everything)
        if ($tp_admin) {
            foreach ($capabilities as $cap) {
                $tp_admin->add_cap($cap);
            }
        }
        
        // TP Approver capabilities
        if ($tp_approver) {
            $tp_approver->add_cap('edit_tp_versions');
            $tp_approver->add_cap('edit_tp_version');
            $tp_approver->add_cap('read_tp_version');
            $tp_approver->add_cap('read_private_tp_versions');
            $tp_approver->add_cap('edit_others_tp_versions');
            $tp_approver->add_cap('approve_tp_versions');
            $tp_approver->add_cap('reject_tp_versions');
            $tp_approver->add_cap('edit_others_posts'); 
            // Approvers can't publish directly
        }
        
        // TP Contributor capabilities
        if ($tp_contributor) {
            $tp_contributor->add_cap('edit_tp_versions');
            $tp_contributor->add_cap('edit_tp_version');
            $tp_contributor->add_cap('read_tp_version');
            $tp_contributor->add_cap('read_private_tp_versions');
            $tp_contributor->add_cap('cancel_approval_tp_versions');
            // Contributors can't approve, reject, or publish
        }
    }
    
    /**
     * Filter user capabilities for workflow post types
     */
    public function filter_user_capabilities($allcaps, $caps, $args, $user) {
        // Only proceed if we're checking edit_post capability
        if (!isset($args[0]) || !in_array($args[0], array('edit_post', 'delete_post'))) {
            return $allcaps;
        }
        
        // Only proceed if we have a post ID
        if (!isset($args[2])) {
            return $allcaps;
        }
        
        $post_id = $args[2];
        $post = get_post($post_id);
        
        // Only apply to workflow post types
        if (!$post || !in_array($post->post_type, array('test_method', 'ccg-version', 'tp-version'))) {
            return $allcaps;
        }
        
        // Get workflow status, lock status, and user roles
        $workflow_status = get_post_meta($post_id, '_workflow_status', true);
        $is_locked = get_post_meta($post_id, '_is_locked', true);
        $user_roles = (array) $user->roles;
        $is_author = ($post->post_author == $user->ID);
        $cancel_requested = get_post_meta($post_id, '_cancel_approval', true);
        
        // Special handling for test_method post type - only lock/unlock functionality
        if ($post->post_type === 'test_method') {
            // For locked posts, only TP Admin and Administrator can edit
            if ($is_locked) {
                if (array_intersect($user_roles, array('tp_admin', 'administrator'))) {
                    foreach ($caps as $cap) {
                        $allcaps[$cap] = true;
                    }
                } else {
                    // Remove edit capability for locked posts for all other users
                    foreach ($caps as $cap) {
                        $allcaps[$cap] = false;
                    }
                }
            }
            
            return $allcaps;
        }
        
        // For ccg-version and tp-version, apply workflow logic
        
        // For TP Contributors
        if (in_array('tp_contributor', $user_roles)) {
            if ($is_author) {
                // Authors can edit their own posts if:
                // 1. Post is in draft or rejected status
                // 2. Cancel approval has been requested
                if ($workflow_status === 'draft' || $workflow_status === 'rejected' || $cancel_requested) {
                    foreach ($caps as $cap) {
                        $allcaps[$cap] = true;
                    }
                } else {
                    // Cannot edit posts in review, approved, or published status
                    foreach ($caps as $cap) {
                        $allcaps[$cap] = false;
                    }
                }
            } else {
                // Contributors cannot edit others' posts
                foreach ($caps as $cap) {
                    $allcaps[$cap] = false;
                }
            }
        }
        
        // For TP Approvers
        if (in_array('tp_approver', $user_roles)) {
            // Can view and approve/reject posts under review
            if ($workflow_status === 'pending_review' || $workflow_status === 'pending_final_approval') {
                foreach ($caps as $cap) {
                    $allcaps[$cap] = true;
                }
            } else if ($is_locked && !array_intersect($user_roles, array('tp_admin', 'administrator'))) {
                // Cannot edit locked posts unless admin
                foreach ($caps as $cap) {
                    $allcaps[$cap] = false;
                }
            }
        }
        
        // For TP Admins and Administrators
        if (array_intersect($user_roles, array('tp_admin', 'administrator'))) {
            // Can edit everything
            foreach ($caps as $cap) {
                $allcaps[$cap] = true;
            }
        }
        
        return $allcaps;
    }
    
    /**
     * Map meta capabilities for workflow post types
     */
    public function map_workflow_meta_caps($caps, $cap, $user_id, $args) {
        // Only handle edit_post, read_post, and delete_post caps
        if (!in_array($cap, array('edit_post', 'read_post', 'delete_post'))) {
            return $caps;
        }
        
        // Only proceed if we have a post ID
        if (!isset($args[0])) {
            return $caps;
        }
        
        $post_id = $args[0];
        $post = get_post($post_id);
        
        // Only apply to workflow post types
        if (!$post || !in_array($post->post_type, array('test_method', 'ccg-version', 'tp-version'))) {
            return $caps;
        }
        
        // Get post information
        $post_type = str_replace('-', '_', $post->post_type); // Convert ccg-version to ccg_version
        $is_locked = get_post_meta($post_id, '_is_locked', true);
        $workflow_status = get_post_meta($post_id, '_workflow_status', true);
        
        // Check user's role
        $user = get_userdata($user_id);
        if (!$user) {
            return $caps;
        }
        
        $user_roles = (array) $user->roles;
        
        // For locked posts, only admins can edit
        if ($is_locked && $cap === 'edit_post') {
            if (array_intersect($user_roles, array('tp_admin', 'administrator'))) {
                return array('edit_' . $post_type . 's');
            } else {
                return array('do_not_allow');
            }
        }
        
        // For posts under review, only approvers and admins can edit
        if (($workflow_status === 'pending_review' || $workflow_status === 'pending_final_approval') && $cap === 'edit_post') {
            if (array_intersect($user_roles, array('tp_approver', 'tp_admin', 'administrator'))) {
                return array('edit_' . $post_type . 's');
            } else if ($post->post_author == $user_id) {
                // Check if cancel approval was requested
                $cancel_requested = get_post_meta($post_id, '_cancel_approval', true);
                if ($cancel_requested) {
                    return array('edit_' . $post_type . 's');
                }
                return array('do_not_allow');
            } else {
                return array('do_not_allow');
            }
        }
        
        return $caps;
    }
}