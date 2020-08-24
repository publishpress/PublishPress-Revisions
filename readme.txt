=== PublishPress Revisions: Submit, Moderate, Schedule and Approve Revisions ===

Contributors: publishpress, kevinB, stevejburge, andergmartins
Author: PublishPress
Author URI: https://publishpress.com
Tags: revision, submit changes, workflow, collaboration, permissions, moderate, posts, schedule revisions
Requires at least: 4.9.7
Requires PHP: 5.6.20
Tested up to: 5.5
Stable tag: 2.3.11
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin gives you control over updating published content. Users can submit revisions. You can approve or reject them.

== Description ==

WordPress Revisions are a powerful way to track where your site has been. But what about where it's going?

To moderate or schedule changes to published posts, just install PublishPress Revisions and let the teamwork begin. There is no need to learn or configure complex new screens because PublishPress Revisions works with familiar WordPress UI, including the Gutenberg and the Classic Editor.

= Submit Revisions =
PublishPress Revisions allows your users to submit change requests for published posts. Your users can update posts using the normal WordPress editor, but their changes will not be published automatically. Instead, the changes are stored as a "pending revision" that can be approved or rejected.
[Click here to see how to submit revisions](https://publishpress.com/knowledge-base/revisions-start/).

= Schedule Revisions =
PublishPress Revisions allows you to schedule WordPress revisions to be published in the future. When you're editing a published post, all you need to do is select a future date and click "Schedule Revision". Your changes will be published at the specified time.
[Click here to see how to schedule revisions](https://publishpress.com/knowledge-base/schedule-revisions-future/).

= Manage and Moderate Revisions =
After you create a revision with PublishPress Revisions, you can find that revision on the Revision Queue screen. This screen shows you all the revisions that have been submitted for approval. Underneath each revision you can choose from several moderation tools: Edit, Delete, Preview and Compare.
[Click here to see how to manage and moderate revisions](https://publishpress.com/knowledge-base/schedule-or-publish-revisions/).

= Compare Revisions =
Pending and Scheduled Revisions can include changes to post content, categories, tags, featured image, page parent and other options. Each of these changes can be reviewed in the familiar Compare Revisions interface.
[Click here to see how to compare revisions](https://publishpress.com/knowledge-base/compare-revisions/).

= Frontend Moderation of Revisions =
It is possible to preview and moderate revisions via the frontend of your WordPress site. If you click Preview for a pending revision, you'll see a toolbar across the frontend of the site. This toolbar will change color so you can easily know the status of the revision. For example, if you're looking at a pending revision, the toolbar will be green. For scheduled revisions, the toolbar will be grey.
[Click here to see how to manage from the frontend of your site](https://publishpress.com/knowledge-base/publishing-revisions-frontend/).

= Email Notifications for Revisions =
PublishPress Revisions will notify Administrators and Editors when a new revision is submitted. They can log in to preview, compare and approve the changes. PublishPress Revisions can also send emails for revision approval and publication. The Settings screen lets you disable unwanted notifications.
[Click here for more on revision notifications](https://publishpress.com/knowledge-base/emails-revisionary/).

= Revision Permissions =
PublishPress Revisions works with the default WordPress user roles, and also introduces a Revisor role:

* Contributors can submit revisions to their own published posts.
* Revisors can submit revisions to posts and pages published by others.
* Authors, Editors and Administrators can approve revisions or schedule their own revisions.

To schedule changes to a published post, just set the desired future date before hitting Update.

By upgrading to Revisions Pro, you also gain advanced permissions control through the PublishPress Permissions Pro plugin. You can customize permissions by role or per-user, granting full editing or revision submission rights to specific posts, categories, or taxonomy terms.
[Click here for more on revision permissions](https://publishpress.com/knowledge-base/permissions-revisions).

= Additional Features in the Pro Version =
* Advanced Custom Fields integration
* BeaverBuilder integration (front end revision submission)
* WPML integration (revision queue follows language filter)
* Pods compatibility
* The Events Calendar compatibility
* WooCommerce compatibility
* Yoast SEO compatibility

= Join PublishPress and get the Pro plugins =
* [PublishPress Authors Pro](https://publishpress.com/authors) allows you to add multiple authors and guest authors to WordPress posts.
* [PublishPress Capabilities Pro](https://publishpress.com/capabilities) is the plugin to manage your WordPress user roles, permissions, and capabilities.
* [PublishPress Checklists Pro](https://publishpress.com/checklists) enables you to define tasks that must be completed before content is published.
* [PublishPress Permissions Pro](https://publishpress.com/permissions) is the plugin for advanced WordPress permissions.
* [PublishPress Pro](https://publishpress.com/publishpress) is the plugin for managing and scheduling WordPress content.
* [PublishPress Revisions Pro](https://publishpress.com/revisions) allows you to update your published pages with teamwork and precision.

The Pro versions of the PublishPress plugins are well worth your investment. The Pro versions have extra features and faster support. 
[Click here to join PublishPress](https://publishpress.com/pricing/).

Together, these plugins are a suite of powerful publishing tools for WordPress. If you need to create a professional workflow in WordPress, with moderation, revisions, permissions and more then you should try PublishPress.

= Bug Reports =
Bug reports for PublishPress Revisions are welcomed in our [repository on GitHub](https://github.com/publishpress/publishpress-revisions). Please note that GitHub is not a support forum, and that issues that aren't properly qualified as bugs will be closed.

= Follow the PublishPress team = 
Follow PublishPress on [Facebook](https://www.facebook.com/publishpress), [Twitter](https://www.twitter.com/publishpresscom) and [YouTube](https://www.youtube.com/publishpress)

== Screenshots ==

1. Low-level user submits a "Pending Revision" to a Published Page 
2. Email Notification Recipients (optionally adjustable)
3. Pending Revision Confirmation
4. Pending Revisions in Dashboard Right Now Count
5. Revision Queue: filterable list of Pending, Scheduled Revisions
6. Revision Queue actions
7. Pending Revision Revision Preview / Approval
8. Compare Pending and Scheduled Revisions using the familiar UI
9. Scheduled Revision Creation (unrestricted editor)
10. Scheduled Revision Confirmation
11. Scheduled Revision Preview
12. Scheduled Revisions in Publishing Soon list

== Changelog ==

= 2.3.11 - 13 Aug 2020 =
* Compat : WP 5.5 - "Pending Revision" checkbox sometimes ineffective
* Compat : WP 5.5 - Posts with pending or scheduled revisions stored had misplaced links in Gutenberg editor sidebar
* Compat : WP 5.5 - With Classic Editor, javascript errors in post editor
* Compat : WP 5.5 - PHP warning on post edit (deprecated function escape_attribute)
* Compat : WP 5.5 - Edit Revision screen - Duplicate Preview link, misaligned
* Compat : WP 5.5 - Edit Revision screen - View / Approve link misaligned
* Fixed : "Has Revision" post state displayed for posts that have comments but no revisions (since 2.3.10)
* Fixed : Scheduled revisions were not published under some conditions (since 2.3.9)
* Fixed : In some conditions, fatal error on Plugins screen

= 2.3.10 - 10 Aug 2020 =
* Fixed : Revisions submitted without modifying tags had tags removed
* Feature : Edit Posts screen - display "Has Revision" as a post state after post title

= 2.3.9 - 6 Aug 2020 =
* Fixed : Featured Image was removed from pending revision at creation
* Fixed : Scheduled revision publication failed under some conditions, caused post to be unpublished
* Fixed : Scheduled revisions could not be published ahead of schedule using "Publish Now" link on preview (since 2.3.4)
* Lang : Add German translation
* API : New filter 'revisionary_apply_revision_data' to adjust standard revision fields prior to publication

= 2.3.8 - 30 Jul 2020 =
* Feature : Revision Queue - new bulk action to Unschedule selected revisions
* Lang : Add Spanish translation
* Fixed : Revisors could not preview changes prior to submitting a pending revision
* Fixed : Classic Editor plugin - when "Edit (Classic)" link is used, Revisors did not have Update button recaptioned to "Submit Revision"
* Fixed : API - revisionary_enabled_post_types filter was not fully effective
* Compat : Public Post Preview - support preview link generation on Edit Revision screen

= 2.3.6 - 10 Jun 2020 =
* Fixed : After revision submission, preview link was not always to latest revision
* Fixed : Preview button on editor screen loaded preview with invalid thumbnail under some conditions
* Fixed : When network-activated, Network Settings menu item loaded site-specific settings screen

= 2.3.5 - 29 May 2020 =
* Fixed : Compare link on Editor screen linked to Edit Posts screen instead of Compare Revisions 

= 2.3.4 - 29 May 2020 =
* Fixed : Duplicate email notifications to users who have more than one WordPress role
* Change : Suppress email notification when an Administrator or Editor creates a pending revision, if constant REVISIONARY_LIMIT_ADMIN_NOTIFICATIONS is defined
* Compat : Relevanssi - Scheduled revisions were not published with Relevanssi active
* Fixed : Scheduled revision publication caused other scheduled revisions (for the same post) to be hidden from Revision Queue
* Fixed : has_revisions postmeta flag was not cleared when a post's last revision was deleted
* Fixed : Revision Queue - Search function did not work
* Fixed : Revision Queue link in Edit Posts / Edit Pages row was not removed after post's last pending or scheduled revision published or deleted

= 2.3.3 - 14 May 2020 =
* Compat : PublishPress Permissions - Fatal error on post creation

= 2.3.2 - 12 May 2020 =
* Fixed : Post meta flag "_rvy_has_revisions" was not cleared after last remaining pending / scheduled revision was published or deleted, affecting Revision Queue performance
* Fixed : Revision Queue listed uneditable revisions under some conditions
* Fixed : My Published Posts count was wrong under some conditions
* Fixed : Dashboard At a Glance link for Pending Post Revisions linked to Revision Queue without filtering display to Posts only
* Compat : PublishPress Permissions - suppress Permissions metaboxes on Edit Revision screen
* Fixed : Published post content cleared on pending revisions submission, on a minority of installations

= 2.2.4 - 6 Apr 2020 =
* Fixed : Possible fatal error loading Revisions screen on a small percentage of installations

= 2.2.3 - 3 Apr 2020 =
* Fixed : Classic Editor - Category and Post Tag revisions were not applied

= 2.2.2 - 2 Apr 2020 =
* Feature : Option to disable revision preview links for non-Administrators (to work around themes that force a 404 Not Found response) 
* Fixed : Inline styles were stripped or modifield on scheduled revision publication
* Fixed : Possible fatal error loading Revisions screen on a small percentage of installations
* Fixed : PHP Notice for deprecated function contextual_help_list()
* Change : Standardize sanitization of database queries 
* API: revisionary_enabled_post_types filter was not applied consistently
* Compat : CMS Tree Page View - Suppress Pending Revisions and Scheduled Revisions from Page Tree View

= 2.2.1 - 16 Mar 2020 =
* Fixed : Page Template was cleared on revision submission in some installations
* Fixed : Revision Queue - "Filter" link was ineffective in showing only revisions of the selected published post. This also applies to "View Revision Queue" link after revision creation.
* Fixed : Edit Revision - Move to Trash button did not work (and created new pending revision)
* Fixed : Duplicate email notifications for scheduled revision publication on some installations
* Fixed : Safeguard to prevent duplicate email notifications
* Feature : Plugin API - new filter 'revisionary_mail' allows adjustment to notification email address, title or message (or blockage of a particular email)
* Change : Pro top banner on Revisions screens
* Compat : New setting "Revision publication triggers API actions to mimic post update" causes save_post and transition_post_status actions to fire on revision publication
* Compat : Yoast SEO - Revision submission stripped accented characters and emojis out of FAQ block
* Compat : On revision publication, trigger 'transition_post_status' action, for plugins that use it

= 2.2 - 12 Feb 2020 =
* Feature : Email Notification - option to notify Editors and Administrators when a Pending Revision is approved
* Fixed : Block Editor - Custom Taxonomies, if unchanged, were not saved to revision. Publication of revision cleared custom taxonomies for published post.
* Fixed : Block Editor - Error setting Featured Image
* Fixed : Revisions submitted by Administrators or Editors using "Pending Revision" checkbox caused published post title and content to be cleared if a future publish date was also selected
* Compat : PublishPress Permissions Status Control - "Prevent Revisors from editing other users' drafts" setting also prevented other non-Editors from editing posts of a custom workflow status that uses custom capabilities (also requires PP Permissions Pro 2.9.1)
* Compat : Block data from some plugins had html formatting tags displayed as unicode character codes
* Fixed : Edit Revision screen - Date selector was displayed even if scheduled revisions feature disabled
* Fixed : Compare Pending Revisions - Non-administrators could not edit Scheduled Revisions
* Fixed : Compare Pending Revisions - for page slug change, original published slug was not displayed 
* Fixed : 'revisionary_skip_taxonomies' filter triggered a database error
* Fixed : PHP Notice if third party code registers a post type without defining the edit_published capability
* Fixed : PHP Notices on revision submission notification
* Change : By default, enable "Prevent Revisors from viewing others'" setting
* Change : Apply possible workaround for Revision Queue capability issues on some sites

= 2.1.8 - 15 Jan 2020 =
* Fixed : Custom Post Types did not have Pending Revisions or Scheduled Revisions available (since 2.1.7)
* Lang : Correct textdomain on numerous translation calls
* Lang : Improve translation string construction
* Lang : Support translation of Revisor role name
* Lang: Updated language files

= 2.1.7 - 13 Jan 2020 =
* Fixed : Excessive resource usage with some caching solutions
* Fixed : Multisite - Super Administrators without a site role could not access Revision Queue 
* Fixed : Classic Editor - After updating a revision, "View Post" message linked to published post instead of revision preview
* Feature : New filter 'revisionary_enabled_post_types', unset post types by key to disable PP Revisions involvement

= 2.1.6 - 23 Dec 2019 =
* Fixed : Edit Revision - Classic Editor "Approve" button ineffective
* Fixed : Edit Revision - Classic Editor "View / Approve" button loaded live preview (of unsaved changes) instead
* Compat : By default, prevent third party post query filtering on Revision Queue (to avoid non-display of Revisions)
* Compat : PressPermit Pro - Updating a saved revision caused it to be changed to a regular pending post

= 2.1.5 - 11 Dec 2019 =
* Compat : PressPermit Pro - Pending revision previews could be viewed by any user (including anonymous) if "Prevent Revisors from viewing others' revisions" disabled (since 2.1.4)
* Fixed : Contributors had other users' uneditable, unreadable revisions listed in Revision Queue
* Fixed : Revision Preview - Under some configurations, users with read-only access to revisions had no top bar in revision preview display
* Fixed : Revision Preview - Under some role configurations, users saw an ineffective "Publish" button in preview top bar
* Fixed : PHP warning for undefined index 'preview'

= 2.1.4 - 10 Dec 2019 =
* Fixed : Revision previews were not displayed to Editors under some configurations
* Feature : Separate settings for "Prevent Revisors from editing others'" and "Prevent Revisors from viewing others'"

= 2.1.3 - 6 Dec 2019 =
* Compat : Classic Editor plugin - View / Approve buttons missing on Edit Revision screen if Classic Editor active but settings default to Block Editor
* Compat : Classic Editor plugin - Javascript errors on Edit Post / Edit Revision screen if Classic Editor active but currently using Block Editor
* Compat : Thin Out Revisions plugin broke Preview / Approval buttons on Compare Pending Revisions screen
* Compat : Multiple Authors - Revision Queue "Post Author" links did not work for secondary authors
* Compat : Multiple Authors - Revision Queue "Post Author" links did not filter Revision Queue
* Compat : JReviews - Live preview from Edit Revision screen failed if JReviews plugin active
* Fixed : Preview Top Bar blocks admin bar dropdown menu if another fixed-position element on the page (other than #wpadminbar) has a z-index of 99999 or higher

= 2.1.2 - 4 Dec 2019 =
* Fixed : Scheduled Revisions were not published (since 2.1)
* Fixed : Edit Revision - Preview of unsaved revision did not work from Gutenberg
* Change : Edit Revision - Display "View / Approve" button if editor is unchanged from saved revision, otherwise "Preview" button for unsaved changes
* Fixed : Classic Editor - Preview caused "Update Revision" button to be recaptioned to "Save Draft"
* Feature : Support Post Slug revision
* Fixed : Other users' revisions were not listed in Revision Queue even if "Prevent Revisors from editing others' revisions" disabled
* Fixed : With "Prevent Revisors from editing others' revisions" setting enabled, Revisors and Authors could edit others' revisions by direct URL access
* Feature : Support list_others_revisions capability to grant read access to other users' revisions (applies if "Prevent Revisors from editing others' revisions" is enabled)
* Compat : PressPermit Pro - Revisors could not submit Beaver Builder revisions
* Compat : PressPermit Pro - Revision Exceptions ("Also these" category / taxonomy assignments) assigned to Authors were not applied correctly
* Compat : JReviews plugin

= 2.1.1 - 26 Nov 2019 =
* Compat : Multiple Authors - Fatal error on revision creation (since 2.1)

= 2.1 - 26 Nov 2019 =
* Feature : Bulk Approval / Publishing in Revision Queue
* Feature : Revision Edit: Approve Button on Editor screen
* Feature : Option for Approve, Edit buttons on Compare Revisions screen (instead of Preview button)
* Feature : Email Notification Buffer to avoid failures due to exceeding server send limits
* Fixed : Email Notification - For pending revision submission, submitter was misidentified on some sites
* Fixed : Revisors could restore previous revisions through manual URL access
* Fixed : Fatal error when WP_Privacy_Policy_Content::text_change_check() is triggered
* Fixed : "Pending Revision" checkbox was displayed in Gutenberg editor, even for unpublished posts
* Fixed : After clicking "Pending Revision" checkbox, unchecking did not prevent revision save
* Fixed : Revision Preview - unsaved changes to saved revision could not be previewed with WP 5.3
* Fixed : Revision Preview - top bar for edit / approval was not displayed on some sites
* Change : Revision Preview URL - Default to using published post slug with revision page_id argument, for better theme compatibility. Option to use Revision slug or ID only.
* Fixed : Edit Revision screen links to published post discarded customized slug
* Fixed : Classic Editor - "View / Approve" link from Edit Revision screen loaded wrong preview URL and no top bar display for approval
* Fixed : Classic Editor - No preview button was available to Revisors
* Fixed : Classic Editor - Invalid Revisions > Browse link displayed to Revisors
* Compat : Classic Editor plugin - with "Allow users to switch editors" enabled, non-default editor did not have correct javascript loaded for Revisions
* Compat : On themes that use a fixed position header, display preview top bar above header
* Compat : PressPermit Pro - revision preview could not be viewed by Contributors under some configurations 
* Fixed : On standard Compare Revisions screen (for past revisions), Preview and Manage button links did not update with slider selection change
* Fixed : Pending, Schedule Revision notification - invalid preview link in some emails
* Fixed : Trashed revisions were not identified as revisions in Edit Posts listing
* Fixed : Trashed revisions were not deleted on parent post deletion
* Fixed : Trashed revisions showed an invalid comment count value in Edit Posts listing
* Fixed : PHP Warning in Gutenberg editor when editing is not being limited to revision submission
* Compat : Multiple Authors - Compare Pending Revisions screen showed revisor as original post author under some conditions 
* Compat : Multiple Authors - Revision submission / approval caused published post author to be changed to revisor, under some conditions
* Compat : Plugin interaction caused published post permalink custom slug to be replaced with default permalink structure at revision publication, on some sites
* Change : Revision Queue - recaption "My Posts" to "My Published Posts"

= 2.0.12 - 29 Oct 2019 =
* Fixed : Fatal error on Post Preview

= 2.0.11 - 28 Oct 2019 =
* Fixed : Classic Editor - Post Preview showed last stored copy, not unsaved changes
* Fixed : Revision Preview top bar covered admin menu dropdown
* Fixed : Revision Edit - live preview showed revision author instead of published author (if Multiple Authors plugin not active)

= 2.0.10 - 25 Oct 2019 =
* Fixed : Post Preview showed last stored copy, not unsaved changes
* Fixed : Post Preview (to view unsaved changes) was not available when editing a revision
* Fixed : Revision Preview - Buttons were not clickable with some themes
* Fixed : Filter revisionary_default_pending_revision was not effective in Gutenberg (check Save as Revision checkbox by default)
* Compat : Multiple Authors - Incorrect author display in revision previews on some sites
* Compat : PressPermit - Database error on Revision Queue screen under some configurations

= 2.0.9 - 18 Oct 2019 =
* Fixed : Compare Pending Revisions screen - link redirected to Edit Posts screen for some post types

= 2.0.8 - 18 Oct 2019 =
* Change : PostMeta Failsafe: to avoid the possibility of accidental clearance, Featured Image removal is not revisioned, until further testing. API filter available for experimental usage with specified meta keys.
* Fixed : Featured Image, Page Template revisioning failed under some conditions
* Fixed : Scheduled Revisions created with Gutenberg stored selected terms to published post, previous terms to revision
* Fixed : Scheduled Revisions - If "Update Publish Date" enabled, 404 Not Found redirect after manually publishing a scheduled revision if the post type uses post date in permalink structure
* Fixed : Revision Preview - Buttons were not clickable with some themes
* Fixed : Settings - Disabling Pending or Scheduled Revisions did not remove UI from post editor
* Fixed : Settings - If Pending Revisions disabled, Revisor could still edit published posts

= 2.0.7 - 17 Oct 2019 =
* Fixed : Scheduled Revisions - published post tags and categories were stripped out on scheduled revision publication
* Fixed : Scheduled Revisions - manually publishing prior to scheduled time caused published post status to be set to Future (unpublished)

= 2.0.6 - 17 Oct 2019 =
* Fixed : Featured Image and Page Template revisions were not applied (but did work in PublishPress Revisions Pro)
* Fixed : Publishing a revision imported from Revisionary 1.x caused tags and categories to be stripped out

= 2.0.5 - 16 Oct 2019 =
* Fixed : Import script for Revisionary 1.x revisions did not run on plugin activation 
* Fixed : Administrators, Editors and Authors were blocked from Quick Edit
* Compat : Multiple Authors plugin
* Fixed : Pending Revisions - Published post date was not updated even if "Update Publish Date" setting enabled
* Change : Pending Revision Notification - Include link to Revision Queue
* Fixed : Pending Revision Notification - If enabled for author only, email was sent with a blank title and message
* Fixed : Empty Revision Queue was displayed to Subscribers with no Revision capabilities
* Fixed : PHP notices on Revision Queue screen

= 2.0.4 - 9 Oct 2019 =
* Change : On installation over Revisionary 1.x, display a "heads up" notice about plugin name change, admin menu and Revision Queue
* Fixed : Classic Editor - Revision Preview did not always include top bar (for Edit / Compare / Publish) if PressPermit Pro active
* Fixed : Revision Preview - Edit url did not work on installations with non-conventional admin paths, due to hardcoded /wp-admin
* Fixed : Schedule Revision notifications sent redundantly under some conditions
* Change : On Revision Edit, recaption Preview button to "View" to clarify that it's a preview of the saved revision, not unsaved changes. (Future release will make it a true preview).

= 2.0.3 - 3 Oct 2019 =
* Fixed : Revisionary settings could not be changed
* Fixed : Pending / Scheduled Revisions were listed in Revision Queue even if feature disabled in Revisions > Settings 
* Fixed : On post edit for revision, Revisors could not see the current or newly selected Featured Image
* Fixed : On revision edit, Administrators and Editors did not have Trash button available
* Fixed : Revisors could edit or delete their scheduled revisions
* Fixed : Scheduled revision publication did not work with "Asynchronous publishing" setting enabled
* Fixed : After revision publication reloading, the old revision preview returned "Not Found". Now redirects to published post and marks as "Current Revision"
* Fixed : PHP Notices throughout wp-admin when WP_DEBUG enabled
* Change : Revision Queue headline indicates when results are being filtered by post type, revision status, revision author or post author

= 2.0.2 - 2 Oct 2019 =
* Fixed : On post date change in Gutenberg editor, Publish button was recaptioned to "Schedule Revision" even on a past date selection (unless SCRIPT_DEBUG enabled)

= 2.0.1 - 2 Oct 2019 =
* Fixed : Fatal error if another copy of Revisionary already active

= 2.0.0 - 1 Oct 2019 =
* Feature : Submit revisions to Categories, Tags, Custom Terms, Page Parent, Featured Image, Page Template
* Feature : Revisions editable in Gutenberg, Classic Editor
* Feature : Voluntary pending revision submission by unrestricted editors in Gutenberg
* Feature : Revision Queue screen is a sortable, filterable list of pending and scheduled revisions for all post types
* Feature : Revision Queue screen includes "My Revisions" and "My Posts" filtering links
* Feature : Revision Queue - Published Posts have "History" link to compare past revisions
* Feature : Compare Revisions - for past revisions, add button links for "Preview / Restore" and "Manage"
* Feature : Compare Pending Revisions using standard WordPress UI (link from Editor or Revision Queue)
* Feature : Compare Scheduled Revisions using standard WordPress UI (link from Editor or Revision Queue)
* Feature : Compare Pending / Scheduled Revisions shows changes to Categories, Tags, Terms, Page Parent, Featured Image, Page Template
* Change : Improved styling for revision preview / approval top bar
* Feature : "Update Publish Date" setting for Pending Revisions (defaults to disabled)

= 1.3.8 - 30 Aug 2019 =
* Fixed : Revisors could Quick Edit published posts (changing post title, slug, author, date, parent or template) since version 1.3. This could be used to unpublish (but not publish) posts. Sites also running PressPermit Pro were not affected.
* Compat : PressPermit Pro - Under some configurations, Revisors were not allowed appropriate access (due to publish capability check)

= 1.3.7 - 24 May 2019 =
* Feature : Filter 'revisionary_default_pending_revision', return true to select "Send to Approval Queue" in Classic Editor by default

= 1.3.6 - 30 Apr 2019 =
* Fixed : Scheduled Revision publication updated post date even if "Update Publish Date" option disabled
* Fixed : Gutenberg: Pending, Scheduled Revisions did not work for post types with show_in_rest property set false 
* Fixed : PHP Notice if REST Posts query executed without a corresponding rest_base property set for post type 
* Fixed : Better hiding of non-applicable sidebar metaboxes when post is being edited for Pending Revision

= 1.3.5 - 3 Apr 2019 =
* Fixed : With Classic Editor, Revision submission reset Page Template

= 1.3.4 - 2 Apr 2019 =
* Fixed : Pending Revision Notifications were not sent from Gutenberg editor if configured to send "by default" (selectable recipients)

= 1.3.3 - 2 Apr 2019 =
* Fixed : Scheduled Revision preview: "Publish Now" link failed with a fatal error
* Change : Settings link in Plugins Row

= 1.3.2 - 29 Mar 2019 =
* Fixed : Email notifications were missing "Post" / "Page" caption
* Fixed : PHP notices with Classic Editor
* Fixed : With Classic Editor, revision approval from preview did not redirect back to Edit Posts / Pages screen
* Fixed : In Classic Editor, setting a future date did not recaption Publish button to "Schedule Revision" if post has private visibility

= 1.3.1 - 29 Mar 2019 =
* Fixed : Scheduled Revision publication stripped out categories and tags, if "Update Publish Date" setting enabled
* Fixed : Publish button was not recaptioned to Submit Revision under some conditions
* Change : With Gutenberg active, revision approval defaults to front end preview
* Feature : Better redirect logic following revision approval, scheduling or restoration (returns to screen that preview was linked from)
* Feature : Preview link in Notification Emails
* Feature : Previews of Scheduled Revisions and Pending Revisions with a future publish date include link to Revisions Manager to edit date
* Change : Dismissable welcome message: To allow a user to submit Revisions to your published posts, set their role to "Revisor" 

= 1.3.0 - 28 Mar 2019 =
* Feature : Gutenberg editor compatibility for Pending Revision, Scheduled Revision creation
* Feature : By default, Scheduled Revisions also update publish date. New checkbox on Revisions > Settings to restore previous behavior of leaving publish date unchanged.
* Feature : List Scheduled Revisions of any post type on Publishing Soon list in Activity dashboard widget
* Fixed : If Scheduled Revision was first site access after scheduled publication time, changes were not displayed until page reload
* Fixed : Scheduled post Revisions on Publishing Soon list in Activity dashboard widget had incorrect link
* Fixed : Past Revisions list on Revision Manager screen had invalid preview links
* Fixed : Better formatting for Publish Now / Schedule Now link
* Fixed : Editing revision publication date updated revision author, even if post content not changed
* Change : Use 12 hour format for revision dates
* Change : Pending Revision lists show submission date
* Change : Pending Revision lists show requested publication date if applicable 

= 1.2.7 - 13 Mar 2019 =
* Fixed : Pending Revision Notification on Multisite installations. Due to failure to apply settings, e-mail notifications defaulted to "By default" option, which failed for Pending Revisions prior to version 1.2.6.  
* Fixed : Multisite - If network-activated, Revisionary settings screens unavailable. Last stored network-wide settings (or hardcoded defaults) applied instead.
* Fixed : Multisite - If not network-activated, Revisionary settings screen was ineffective. Site-specific settings were stored, but network-wide settings or defaults applied instead.
* Fixed : "Display Hints" setting had no checkbox on Settings screen
* Change : Improved settings captions

= 1.2.6 - 13 Mar 2019 =
* Fixed : "Publishers to Notify" checkboxes were not displayed, and notifications not sent, if Email notification for Pending Revisions set to "By default"
* Fixed : Revision previews - PHP Warning and failure to output "Publish Now" header
* Change : Improved styling in "Publishers to Notify" metabox

= 1.2.5 - 25 Feb 2019 =
* Compat : TinyMCE Advanced - Failed to display editor on revision management screen
* Compat : Multisite - Incorrect site switching, prevents Yoast SEO from saving post meta 

= 1.2.4 - 20 Feb 2019 =
* Compat : PublishPress - publish button was hidden
* Change : Capitalize "Save as Pending Revision" checkbox caption
* Lang : Update .po file

= 1.2.3 - 19 Feb 2019 =
* FIXED : Scheduled revision publication failure, massive redundant email notifications (since 1.2)

= 1.2.2 - 19 Feb 2019 =
* Fixed : Temporarily disable scheduled revision publication emails, due to recently reported issue
* Compat : PHP / coding standards - removed needless byref variable assignments
* Fixed : PHP notices when viewing revision differences

= 1.2.1 - 14 Feb 2019 =
* Compat : Fatal error when another plugin hooks into 'user_has_cap' filter

= 1.2 - 13 Feb 2019 =
* Compat : PHP 7.2
* Compat : WordPress 5.0.3
* Fixed : Revision approval reset page template setting to default
* Team : Revisionary is now owned and developed by PublishPress. The original author (Kevin Behrens) is excited to join forces in building and supporting effective tools for publishing teams.

= 1.1.13 - 13 May 2015 =
* Fixed : Previewing a Page revision from Revisions Manager screen caused fatal error / white screen
* Fixed : When Previewing a revision, Publish Now link was not formatted properly on TwentyFifteen theme
* Fixed : Pending Revision counts, links were not displayed in Dashboard At a Glance if PP Collaborative Editing plugin is not active
* Compat : Jetpack Markdown - publishing a revision caused post content to be stripped
* Compat : various caching plugins - post cache was not cleared after publishing a revision

= 1.1.12 - 23 Dec 2013 =
* WP 3.8 - Fixed Revisionary > Settings styling
* Fixed : Email notifications were not sent on Pending Revision submission under some configurations
* Fixed : Email notifications were not sent upon Scheduled Revision publishing unless Press Permit / Role Scoper active and Scheduled Revision Monitors group populated
* Change : On network installations, email notifications to administrators will include super admins if constant RVY_NOTIFY_SUPER_ADMIN is defined
* Fixed : Network-wide Revisionary Options could not be modified
* Fixed : Revisions on Edit Posts screen were displayed with stored post title, ignoring modifications by previous filters (such as translations)
* Fixed : Administrator did not have "save as pending revision" option when post is currently scheduled for publishing
* Fixed : Revision Diff formatting (column alignment)
* Fixed : Revision preview from Revisions Manager screen not displayed correctly under some configurations
* Change : Revisions Manager screen marks a revision as "Current" only if it is published
* Change : Better consistency with standard Revisions Manager behavior: post-assigned Revisor role is sufficient to edit others' revisions, but post-assigned Contributor role is not
* Change : Better consistency with standard Revisions Manager behavior: prevent diff display of unreadable revisions
* Change : When comparing revisions, if only one of the revisions is past, force it to left
* Change : On Revisions Manager screen, add margins to Update Revision button
* Fixed : PHP Notices for non-static function calls
* Compat : Role Scoper - when Pending Revision Monitors group is used and notification is "by default", recipient checkboxes missing on Edit Post form and TinyMCE broken
* Compat : Duplicate Right Now links on dashboard if Role Scoper or Press Permit active

**1.1.11 - 18 Aug 2013**

= WP 3.6 Compatibility =
* WP 3.6 - Revisors could not submit revisions
* WP 3.6 - Don't modify native WP revision links
* WP 3.6 - In Publish metabox, re-caption Revisions as "Publication History" to distinguish from Pending Revisions (prevent this by defining constant RVY_PREVENT_PUBHIST_CAPTION)
* WP 3.6 - Post Title metabox was unformatted on Revisions Manager screen

= Email Notification =
* Fixed : Publishers to Notify metabox was displayed even if no selections available (when notification for both Publishers and Author is set to Always or Never)
* Fixed : PHP warning in Publishers to Notify metabox when a user has a very long name
* Change : If Press Permit or Role Scoper are active but Monitors group does not contain any users who can publish the post, notifications go to all WP Administrators and Editors who have sufficient site-wide capabilities (prevent this by defining constant RVY_FORCE_MONITOR_GROUPS)
* Change : On Revisionary Settings screen, expand caption to clarify email notification behavior

= General =
* Fixed : Revisors could not select desired publish date on Edit Post screen, even if Scheduled Revisions enabled
* Fixed : "save as pending" checkbox caused poor layout of adjacent UI in Publish metabox
* Perf : Eliminate some redundant queries on back-end for non-Administrators (especially with Press Permit or Role Scoper active)
* Compat : Edit Flow - don't offer to revise EF Metadata

= 1.1.10 - 29 May 2013 =
* SECURITY FIX : Revisions could be viewed by any registered user
* Feature : Option to prevent Revisors from viewing other user's drafts and regular pending posts (imposes edit_others_drafts cap requirement)
* Fixed : Other users' revisions were viewable in Revisions Manager even if option to prevent is enabled
* Fixed : "Publishers to Notify" metabox not displayed under some configurations
* Fixed : "Publishers to Notify" metabox was displayed with checkboxes even if Revisionary settings are for both editors and author to always receive notification
* Fixed : Email Notification for Pending Revision was not sent under some configurations
* Fixed : Monitor Groups (with Press Permit or Role Scoper activated) did not regulate email notifications
* Fixed : Users who cannot approve a revision received email notification under some configurations
* Fixed : PHP warnings for deprecated WP function calls
* Fixed : PHP warnings when "previewing" current revision
* Fixed : Invalid notifications were sent on revision submission error
* Fixed : JS warning on Edit Post form
* Compat : Press Permit Core
* Compat : Press Permit - revision previews could not be viewed by revisor (also requires PP Collaborative Editing 2.0.14-beta)
* Compat : CForms (and possibly other plugins) - tinyMCE buttons were suppressed

= 1.1.9 - 18 Jan 2012 =
* Compat : Press Permit - PP roles were not applied under some configurations
* Compat : Role Scoper - RS roles were not applied under some configurations (related fixes in RS 1.3.52)
* Fixed: PHP Warning for mysql_get_server_info()

= 1.1.8 - 20 Dec 2011 =
* Compat : Role Scoper - duplicate Pending counts in Dashboard Right Now
* API : new filter - rvy_hidden_meta_boxes
* API : new action: - rvy-revisions_sidebar
* API : new action - rvy-revisions_meta_boxes
* API : new action - revision_approved
* API : new action - post_revision_update

= 1.1.7 - 11 Nov 2011 =
* Compat : WP 3.3 - Revision Editor displayed redundantly, didn't work
* Compat : Press Permit integration
* Feature : By default, Revisor role does not enable editing other users' revisions (option to re-enable)
* Fixed : If Visual Editor is disabled, html entities not displayed or updated correctly in Revisions Manager
* Fixed : About Revisionary screen (linked from help menu) failed to display
* Fixed : Revision previews used wrong template under some configurations
* Fixed : Various PHP Notices

= 1.1.6 - 7 Sep 2011 =
* Fixed : Quick Edit was not disabled for Page Revisions, usage resulted in invalid revision data
* Fixed : Revisionary Options were not available when plugin activatated per-site on a Multisite installation
* Fixed : For Multisite installation, Revisionary Options on Sites menu caused a fatal error
* Change : For Multisite installation, Revisionary Options Blog/Site captions changed to Site/Network
* Fixed : Revised Post Title was not displayed in Revisions Manager
* Fixed : Various PHP Notices

= 1.1.5 - 29 June 2011 =
* Fixed : Markup error in Revisions Manager for Administrators / Editors, especially noticeable in WP 3.2
* Fixed : "save as pending revision" checkbox in Publish metabox caused formatting error with IE9
* Fixed : Previews did not display post thumbnail or other meta data
* Fixed : Previews could not be displayed for past revisions
* Compat : WP 3.2 - revision previews did not work
* Compat : WP 3.2 - preview link not displayed for Pending Revisions in edit.php listing
* Compat : Builder theme - previews of page revisions could not be displayed
* Compat : Events Calendar Pro - filtering fails when WP database prefix is non-default
* Change : Better styling for revision approval link displayed above preview
* Change : Remove Asynchronous Email option
* Change : Change all require and include statements to absolute path to work around oddball servers that can't handle relative paths
* Change : jQuery syntax change for forward compatibility

= 1.1.4 - 5 Apr 2011 =
* Fixed : Role Options, Role Defaults menu items were not available on 3.1 multisite
* Fixed : Pending / Scheduled Revisions could not be previewed by Revisors
* Fixed : "Submit Revision" button caption changed to "Update" or "Schedule" following publish date selection
* Fixed : PHP Warning on post creation / update
* Change : Hide Preview button from Revisors when editing for pending revision submission

= 1.1.3 - 3 Dec 2010 =
* Fixed : Autosave error message displayed while a revisor edits a published post prior to submitting a pending revision
* Fixed : Email notifications failed on some servers if Asynchronous option enabled
* Compat : Role Scoper - With RS 1.3 to 1.3.12, if another plugin (Events Manager) triggers a secondary edit_posts cap check when a Revisor attempts to edit another user's unpublished post, a pending revision is generated instead of just updating the unpublished post

= 1.1.2 - 29 Nov 2010 =
* Compat : Role Scoper - Post-assigned Revisor role was not honored to update another users' revision with RS 1.3+
* Fixed : While in Revisions Manager, invalid "Revisions" submenu link was displayed in Settings menu

= 1.1.1 - 5 Nov 2010 =
* Fixed : Fatal Error if theme displays post edit link on front end
* Fixed : Did not observe capability definitions for custom post types (assumed capability_type = post_type)
* Compat : Event Calendar Pro - revisions of sp_events were not included in Edit Posts listing due to postmeta clause applied by ECP

= 1.1 - 2 Nov 2010 =
* Fixed : Revision Approval notices were not sent if "always send" option enabled
* Feature : "save as pending revision" option when logged user has full editing capabilities in Edit Post/Page form

= 1.1.RC3 - 29 Oct 2010 =
* Fixed : Revision preview link returned 404 (since 1.1.RC)
* Fixed : Revision Approval emails were not sent reliably with "Asynchronous Email" option enabled (since 1.0)
* Fixed : Custom taxonomy selection UI was not hidden when submitting a revision
* Fixed : In Quick Edit form, Published option sometimes displayed inappropriately

= 1.1.RC.2 - 11 Oct 2010 =
* Fixed : Listed revisions in Revision Editor were not linked for viewing / editing (since 1.1.RC)

= 1.1.RC - 8 Oct 2010 =
* Feature : Support Custom Post Types
* Change : Better internal support for custom statuses
* Fixed : On Options page, links to "Pending Revision Monitors" and "Scheduled Revision Monitors" were reversed
* Fixed : Revision Edit link from Edit Posts/Pages listing led to uneditable revision display
* Change : Raise minimum WP version to 3.0

= 1.0.7 - 21 June 2010 =
* Fixed : Revisionary prevented the normal scheduling of drafts for first-time publishing

= 1.0.6 - 18 June 2010 =
* Compat : CForms conflict broke TinyMCE edit form in Revisions Manager 

= 1.0.5 - 7 May 2010 =
* Compat : WP 3.0 Multisite menu items had invalid link

= 1.0.4 - 6 May 2010 =
* Fixed : Pending Revision Approval email used invalid permalink if permalink structure changed since original post storage
* Fixed : Schedule Revision Publication email used invalid permalink if permalink structure changed since original post storage

= 1.0.3 - 6 May 2010 =
* Compat : WP 3.0 elimination of page.php, edit-pages.php, page-new.php broke many aspects of page filtering
* Fixed : Trash link did not work for revisions in Edit Posts/Pages listing
* Change : Administrators and Editors now retain Quick Edit link for non-revisions in Edit Pages, Edit Posts listing
* Fixed : "Publishers to Notify" metabox was included even if no eligible recipients are designated

= 1.0.2 - 11 Mar 2010 =
* Fixed : Email notification caused error if Role Scoper was not activated
* Fixed : Database error message (nuisance) in non-MU installations (SELECT meta_key, meta_value FROM WHERE site_id...)
* Fixed : Publish Now link on Scheduled Revision preview did not work
* Fixed : With WP > 2.9, newly published revisions also remained listed as a Pending or Scheduled revision
* Fixed : With WP > 2.9, revision date selection UI showed "undefined" caption next to new date selection
* Fixed : Link for viewing Scheduled Revisions was captioned as "Pending Revisions" (since 1.0.1) 
* Compat : WMPL plugin

= 1.0.1 - 6 Feb 2010 =
* Fixed : 	Submitting a Pending Revision to a published Post failed with Fatal Error
* Fixed : 	PHP short tag caused Parse Error on servers which were not configured to support it
* Compat :  Support TinyMCE Advanced and WP Super Edit for custom editor buttons on Revision Management form
* Feature : Revision preview bar can be styled via CSS file
* Lang 	 : 	Fixed several string formatting issues for better translation support
* Change : 	Use https link for Revisionary css and js files if ssl is being used / forced for the current uri

= 1.0 - 30 Dec 2009 =
* Feature : Use Blog Title and Admin Email as from address in revision notices, instead of "WordPress <wordpress@>"
* Fixed : Revision Approval / Publication Notices used p=ID link instead of normal post permalink
* Compat : Display workaround instructions for FolioPress conflict with visual revision display

**1.0.RC1 - 12 Dec 2009**
Initial release.  Feature Changes and Bug Fixes are vs. Pending Revisions function in Role Scoper 1.0.8

= General: =
* Feature : Scheduled Revisions - submitter can specify a desired publication date for a revision
* Feature : Any user with the delete_published_ and edit_published capabilities for a post/page can administer its revisions (must include those caps in RS Editor definitions and assign that role)
* Feature : Scheduled Publishing and Email notification is processed asynchronously

= Revisions Manager: =
* Feature : Dedicated Revisions Manager provides more meaningful captions, classified by Past / Pending / Scheduled
* Feature : RS Revision Manager form displays visually via TinyMCE, supports editing of content, title and date
* Feature : Revisions Manager supports individual or bulk deletion
* Feature : Users can view their own Pending and Scheduled Revisions
* Feature : Users can delete their own Pending Revisions until approval

= Preview: =
* Feature : Preview a Pending Revision, with top link to publish / schedule it
* Feature : Preview a Scheduled Revision, with top link fo publish it now
* Feature : Preview a Past Revision, with top link for restore it

= WP Admin: =
* Feature : Pending and Scheduled revisions are included in Edit Posts / Pages list for all qualified users
* Feature : Delete, View links on revisions in Edit Posts / Pages list redirect to RS Revisions Manager
* Feature : Add pending posts and pages total to Dashboard Right Now list (includes both new post submissions and Pending Revisions)
* Feature : Metaboxes in Edit Post/Page form for Pending / Scheduled Revisions
* Fixed : Multiple Pending Revions created by autosave
* Fixed : Users cannot preview their changes before submitting a Pending Revision on a published post/page
* Fixed : Pending Post Revisions were not visible to Administrator in Edit Posts list
* Fixed : Both Pending Page Revisions and Pending Post Revisions were visible to Administator in Edit Pages list
* Fixed : Pending Revisions were not included in list for restoration
* Fixed : Bulk Deletion attempt failed when pending / scheduled revisions were included in selection 

= Notification: =
* Feature : Optional email (to editors or post author) on Pending Revision submission
* Feature : Optional email (to editors, post author, or revisor) on Pending Revision approval
* Feature : Optional email (to editors, post author, or revisor) on Scheduled Revision publication
* Feature : If Role Scoper is active, Editors notification group can be customized via User Group

== Upgrade Notice ==

= 1.2.3 =
Important Fix: Scheduled Revision publication failure with runaway email notifications (since 1.2)

= 1.1.10 =
<strong>SECURITY FIX:</strong> Revisions could be viewed by any registered user

= 1.1.5 =
Fixes: Markeup Err in Revisions Manager; Revision Previews (WP 3.2, Display of Post Thumbnail & other metadata, Past Revisions, Page Revisions in Builder theme, Approval link styling); IE9 formatting err in publish metabox; Events Calendar Pro conflict
