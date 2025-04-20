<?php
/**
 * Notifications Management
 *
 * @package TestMethodWorkflow
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Test Method notifications class
 */
class TestMethod_Notifications {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		// Register notification hooks
		add_action('tmw_send_notification', array($this, 'send_notification'), 10, 2);
	}
	
	/**
	 * Send notification based on workflow event
	 *
	 * @param int $post_id Post ID
	 * @param string $event Workflow event (submitted_for_review, approved, rejected, etc.)
	 */
	public function send_notification($post_id, $event) {
		$post = get_post($post_id);
		
		if (!$post || $post->post_type !== 'test_method') {
			return;
		}
		
		$post_title = $post->post_title;
		$post_edit_link = admin_url('post.php?post=' . $post_id . '&action=edit');
		$post_view_link = get_permalink($post_id);
		$current_user = wp_get_current_user();
		
		switch ($event) {
			case 'submitted_for_review':
				$this->send_submission_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user);
				break;
				
			case 'approved':
				$this->send_approval_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user);
				break;
				
			case 'rejected':
				$this->send_rejection_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user);
				break;
				
			case 'final_approval_requested':
				$this->send_final_approval_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user);
				break;
				
			case 'published':
				$this->send_published_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user);
				break;
				
			case 'unlocked':
				$this->send_unlocked_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user);
				break;
				
			case 'locked':
				$this->send_locked_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user);
				break;
				
			case 'revision_created':
				$this->send_revision_created_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user);
				break;
				
			case 'revision_published':
				$this->send_revision_published_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user);
				break;
		}
	}
	
	/**
	 * Send notification when test method is submitted for review
	 */
	private function send_submission_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user) {
		$subject = sprintf(__('[Test Method] New submission requires review: %s', 'test-method-workflow'), $post_title);
		
		$message = sprintf(__("A new test method has been submitted for review:\n\n", 'test-method-workflow'));
		$message .= sprintf(__("Title: %s\n", 'test-method-workflow'), $post_title);
		$message .= sprintf(__("Submitted by: %s\n\n", 'test-method-workflow'), $current_user->display_name);
		$message .= sprintf(__("To review this test method, please click the following link:\n%s\n\n", 'test-method-workflow'), $post_edit_link);
		$message .= sprintf(__("To view this test method on the site, please click the following link:\n%s\n\n", 'test-method-workflow'), $post_view_link);
		$message .= __("Thank you for your time.", 'test-method-workflow');
		
		// Send to all approvers and admins
		$recipients = $this->get_workflow_recipients('approvers');
		
		$this->send_emails($recipients, $subject, $message);
	}
	
	/**
	 * Send notification when test method is approved
	 */
	private function send_approval_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user) {
		$approvals = get_post_meta($post->ID, '_approvals', true);
		$approval_count = is_array($approvals) ? count($approvals) : 0;
		
		// Only send notification if we have all required approvals
		if ($approval_count < 2) {
			return;
		}
		
		$subject = sprintf(__('[Test Method] Ready for publishing: %s', 'test-method-workflow'), $post_title);
		
		$message = sprintf(__("A test method has received the required approvals and is ready for publishing:\n\n", 'test-method-workflow'));
		$message .= sprintf(__("Title: %s\n", 'test-method-workflow'), $post_title);
		$message .= sprintf(__("Last approved by: %s\n\n", 'test-method-workflow'), $current_user->display_name);
		$message .= sprintf(__("To review and publish this test method, please click the following link:\n%s\n\n", 'test-method-workflow'), $post_edit_link);
		$message .= sprintf(__("To view this test method on the site, please click the following link:\n%s\n\n", 'test-method-workflow'), $post_view_link);
		$message .= __("Thank you.", 'test-method-workflow');
		
		// Send to admins only
		$recipients = $this->get_workflow_recipients('admins');
		
		$this->send_emails($recipients, $subject, $message);
		
		// Also notify the author that their post has been approved
		if ($post->post_author != $current_user->ID) {
			$author = get_userdata($post->post_author);
			
			if ($author) {
				$author_subject = sprintf(__('[Test Method] Your submission has been approved: %s', 'test-method-workflow'), $post_title);
				
				$author_message = sprintf(__("Good news! Your test method has been approved:\n\n", 'test-method-workflow'));
				$author_message .= sprintf(__("Title: %s\n\n", 'test-method-workflow'), $post_title);
				$author_message .= sprintf(__("It will now be reviewed by an administrator before publishing.\n\n", 'test-method-workflow'));
				$author_message .= sprintf(__("You can view your test method here:\n%s\n\n", 'test-method-workflow'), $post_view_link);
				
				wp_mail($author->user_email, $author_subject, $author_message);
			}
		}
	}
	
	/**
	 * Send notification when test method is rejected
	 */
	private function send_rejection_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user) {
		$approvals = get_post_meta($post->ID, '_approvals', true);
		
		// Get the latest rejection
		$rejection = null;
		if (is_array($approvals)) {
			foreach ($approvals as $approval) {
				if ($approval['status'] === 'rejected' && $approval['user_id'] === $current_user->ID) {
					$rejection = $approval;
					break;
				}
			}
		}
		
		if (!$rejection) {
			return;
		}
		
		$subject = sprintf(__('[Test Method] Your submission has been rejected: %s', 'test-method-workflow'), $post_title);
		
		$message = sprintf(__("Your test method submission has been rejected:\n\n", 'test-method-workflow'));
		$message .= sprintf(__("Title: %s\n", 'test-method-workflow'), $post_title);
		$message .= sprintf(__("Rejected by: %s\n", 'test-method-workflow'), $current_user->display_name);
		$message .= sprintf(__("Comments: %s\n\n", 'test-method-workflow'), $rejection['comment']);
		$message .= sprintf(__("To edit and resubmit this test method, please click the following link:\n%s\n\n", 'test-method-workflow'), $post_edit_link);
		$message .= __("Please address the reviewer's comments before resubmitting for approval.", 'test-method-workflow');
		
		// Send to author
		$author = get_userdata($post->post_author);
		
		if ($author) {
			wp_mail($author->user_email, $subject, $message);
		}
	}
	
	/**
	 * Send notification for final approval request
	 */
	private function send_final_approval_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user) {
		$approvals = get_post_meta($post->ID, '_approvals', true);
		$approver_ids = array();
		
		// Get IDs of users who have already approved
		if (is_array($approvals)) {
			foreach ($approvals as $approval) {
				$approver_ids[] = $approval['user_id'];
			}
		}
		
		$subject = sprintf(__('[Test Method] Final approval needed: %s', 'test-method-workflow'), $post_title);
		
		$message = sprintf(__("A test method needs final approval:\n\n", 'test-method-workflow'));
		$message .= sprintf(__("Title: %s\n", 'test-method-workflow'), $post_title);
		$message .= sprintf(__("This test method has received %d of 2 required approvals. Your review is needed for final approval.\n\n", 'test-method-workflow'), count($approver_ids));
		$message .= sprintf(__("To review this test method, please click the following link:\n%s\n\n", 'test-method-workflow'), $post_edit_link);
		$message .= sprintf(__("To view this test method on the site, please click the following link:\n%s\n\n", 'test-method-workflow'), $post_view_link);
		$message .= __("Thank you for your time.", 'test-method-workflow');
		
		// Get approvers who haven't approved yet
		$recipients = $this->get_workflow_recipients('approvers', $approver_ids);
		
		$this->send_emails($recipients, $subject, $message);
	}
	
	/**
	 * Send notification when test method is published
	 */
	private function send_published_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user) {
		$subject = sprintf(__('[Test Method] Your submission has been published: %s', 'test-method-workflow'), $post_title);
		
		$message = sprintf(__("Your test method has been published:\n\n", 'test-method-workflow'));
		$message .= sprintf(__("Title: %s\n", 'test-method-workflow'), $post_title);
		$message .= sprintf(__("Published by: %s\n\n", 'test-method-workflow'), $current_user->display_name);
		$message .= sprintf(__("You can view it here:\n%s\n\n", 'test-method-workflow'), $post_view_link);
		$message .= __("The content is now locked. If changes are needed, please contact an administrator.", 'test-method-workflow');
		
		// Send to author
		$author = get_userdata($post->post_author);
		
		if ($author && $author->ID != $current_user->ID) {
			wp_mail($author->user_email, $subject, $message);
		}
		
		// Also notify approvers
		$approvals = get_post_meta($post->ID, '_approvals', true);
		
		if (is_array($approvals)) {
			$approver_subject = sprintf(__('[Test Method] Test method you approved has been published: %s', 'test-method-workflow'), $post_title);
			
			foreach ($approvals as $approval) {
				if ($approval['user_id'] != $author->ID && $approval['user_id'] != $current_user->ID) {
					$approver = get_userdata($approval['user_id']);
					if ($approver) {
						wp_mail($approver->user_email, $approver_subject, $message);
					}
				}
			}
		}
	}
	
	/**
	 * Send notification when test method is unlocked
	 */
	private function send_unlocked_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user) {
		$subject = sprintf(__('[Test Method] Your test method has been unlocked: %s', 'test-method-workflow'), $post_title);
		
		$message = sprintf(__("Your test method has been unlocked by an administrator and is now available for editing:\n\n", 'test-method-workflow'));
		$message .= sprintf(__("Title: %s\n", 'test-method-workflow'), $post_title);
		$message .= sprintf(__("You can edit it here: %s\n\n", 'test-method-workflow'), $post_edit_link);
		$message .= __("The test method has been moved back to draft status.", 'test-method-workflow');
		
		// Send to author
		$author = get_userdata($post->post_author);
		
		if ($author && $author->ID != $current_user->ID) {
			wp_mail($author->user_email, $subject, $message);
		}
	}
	
	/**
	 * Send notification when test method is locked
	 */
	private function send_locked_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user) {
		$subject = sprintf(__('[Test Method] Your test method has been locked: %s', 'test-method-workflow'), $post_title);
		
		$message = sprintf(__("Your test method has been locked by an administrator:\n\n", 'test-method-workflow'));
		$message .= sprintf(__("Title: %s\n", 'test-method-workflow'), $post_title);
		$message .= sprintf(__("You can view it here: %s\n\n", 'test-method-workflow'), $post_view_link);
		$message .= __("The test method is now locked and cannot be edited without administrator approval.", 'test-method-workflow');
		
		// Send to author
		$author = get_userdata($post->post_author);
		
		if ($author && $author->ID != $current_user->ID) {
			wp_mail($author->user_email, $subject, $message);
		}
	}
	
	/**
	 * Send notification when a revision is created
	 */
	private function send_revision_created_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user) {
		// Get the parent post ID
		$parent_id = get_post_meta($post->ID, '_revision_parent', true);
		if (!$parent_id) {
			return;
		}
		
		$parent_post = get_post($parent_id);
		if (!$parent_post) {
			return;
		}
		
		$subject = sprintf(__('[Test Method] New revision created: %s', 'test-method-workflow'), $parent_post->post_title);
		
		$message = sprintf(__("A new revision has been created for the following test method:\n\n", 'test-method-workflow'));
		$message .= sprintf(__("Original: %s\n", 'test-method-workflow'), $parent_post->post_title);
		$message .= sprintf(__("Revision: %s\n", 'test-method-workflow'), $post_title);
		$message .= sprintf(__("Created by: %s\n\n", 'test-method-workflow'), $current_user->display_name);
		$message .= sprintf(__("To review this revision, please click the following link:\n%s\n\n", 'test-method-workflow'), $post_edit_link);
		$message .= __("This revision will need to go through the approval process before it can be published.", 'test-method-workflow');
		
		// Send to approvers
		$recipients = $this->get_workflow_recipients('approvers');
		$this->send_emails($recipients, $subject, $message);
	}
	
	/**
	 * Send notification when a revision is published
	 */
	private function send_revision_published_notification($post, $post_title, $post_edit_link, $post_view_link, $current_user) {
		$subject = sprintf(__('[Test Method] Revision published: %s', 'test-method-workflow'), $post_title);
		
		$message = sprintf(__("A revision has been published for the following test method:\n\n", 'test-method-workflow'));
		$message .= sprintf(__("Title: %s\n", 'test-method-workflow'), $post_title);
		$message .= sprintf(__("Published by: %s\n\n", 'test-method-workflow'), $current_user->display_name);
		$message .= sprintf(__("You can view it here:\n%s\n\n", 'test-method-workflow'), $post_view_link);
		$message .= __("The content is now locked. If changes are needed, a new revision will need to be created.", 'test-method-workflow');
		
		// Get revision history to find the revision author
		$revision_history = get_post_meta($post->ID, '_revision_history', true);
		$revision_author_id = 0;
		
		if (is_array($revision_history)) {
			foreach ($revision_history as $history_item) {
				if (isset($history_item['status']) && $history_item['status'] === 'revision created') {
					$revision_author_id = $history_item['user_id'];
					break;
				}
			}
		}
		
		// Send to the revision author
		if ($revision_author_id && $revision_author_id != $current_user->ID) {
			$author = get_userdata($revision_author_id);
			if ($author) {
				wp_mail($author->user_email, $subject, $message);
			}
		}
		
		// Send to the original post author
		if ($post->post_author != $current_user->ID && $post->post_author != $revision_author_id) {
			$original_author = get_userdata($post->post_author);
			if ($original_author) {
				wp_mail($original_author->user_email, $subject, $message);
			}
		}
	}
	
	/**
	 * Get workflow recipients by role
	 *
	 * @param string $group Either 'approvers' or 'admins'
	 * @param array $exclude_users Array of user IDs to exclude
	 * @return array Email addresses
	 */
	private function get_workflow_recipients($group, $exclude_users = array()) {
		$roles = array();
		
		if ($group === 'approvers') {
			$roles = array('tp_approver', 'tp_admin', 'administrator');
		} elseif ($group === 'admins') {
			$roles = array('tp_admin', 'administrator');
		}
		
		$users = get_users(array(
			'role__in' => $roles,
			'exclude' => $exclude_users
		));
		
		$emails = array();
		
		foreach ($users as $user) {
			$emails[] = $user->user_email;
		}
		
		return array_unique($emails);
	}
	
	/**
	 * Send emails to multiple recipients
	 *
	 * @param array $recipients Array of email addresses
	 * @param string $subject Email subject
	 * @param string $message Email message
	 * @return bool Whether any emails were sent
	 */
	private function send_emails($recipients, $subject, $message) {
		if (empty($recipients)) {
			return false;
		}
		
		$sent = false;
		
		foreach ($recipients as $recipient) {
			$result = wp_mail($recipient, $subject, $message);
			$sent = $sent || $result;
		}
		
		return $sent;
	}
}