# Test Method Workflow

A WordPress plugin that implements a comprehensive workflow for Test Method post types with approval process and version control.

## Features

- Custom post type: Test Method
- Roles-based permissions system
- Workflow with approval process
- Version control with major/minor versioning
- Post locking for published content
- Revision management
- Notifications for workflow events
- Custom dashboard for different roles

## Roles and Capabilities

The plugin creates three custom user roles:

### TP Contributor
- Can create and edit test methods
- Can save draft test methods
- Can submit test methods for review
- Cannot publish test methods

### TP Approver
- All capabilities of TP Contributor
- Can approve or reject test methods
- Cannot publish test methods

### TP Admin
- All capabilities of TP Approver
- Can publish approved test methods
- Can unlock locked test methods

## Workflow Process

1. **Draft**: Initial state for new test methods
2. **Pending Review**: Submitted for review by approvers
3. **Awaiting Final Approval**: Has one approval and needs a second
4. **Approved**: Has received two approvals and is ready for publishing
5. **Published**: Published and locked for editing
6. **Locked**: Content is locked from editing unless unlocked by TP Admin or Administrator

## Version Control

- Supports major and minor versioning
- Version notes for tracking changes
- Version display on front-end
- Version history in admin

## Revision Management

- Create revisions of published/locked test methods
- Revisions follow the same approval workflow
- Publish revisions to replace parent content

## Installation

1. Upload the entire `test-method-workflow` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Set up user roles and permissions

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## Directory Structure

```
test-method-workflow/
├── css/
│   ├── test-method-dashboard.css
│   ├── test-method-frontend.css
│   └── test-method-workflow.css
├── includes/
│   ├── class-post-type.php
│   ├── class-roles-capabilities.php
│   ├── class-workflow.php
│   ├── class-version-control.php
│   ├── class-post-locking.php
│   ├── class-notifications.php
│   ├── class-access-control.php
│   ├── class-revision-manager.php
│   └── class-admin-ui.php
├── js/
│   ├── test-method-revision.js
│   └── test-method-workflow.js
├── test-method-workflow.php
└── README.md
```

## Usage

### For Contributors

1. Create a new test method draft
2. Fill in all required information
3. Submit for review when ready
4. Revise if rejected or wait for approval

### For Approvers

1. Review submitted test methods
2. Approve or reject with comments
3. For rejected test methods, provide clear feedback

### For Admins

1. Monitor the approval process
2. Publish approved test methods
3. Manage locked content
4. Create or approve revisions

## Custom Dashboard

The plugin provides a custom dashboard for all user roles:

- **TP Contributors**: View their test methods and status
- **TP Approvers**: View pending reviews and approval history
- **TP Admins**: Comprehensive view of all test methods and workflow statuses

## Support

For questions, feature requests, or bug reports, please contact the plugin author.

## License

GPL v2 or later
