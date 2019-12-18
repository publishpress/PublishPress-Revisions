=== Plugin Name ===
Contributors: publishpress, kevinB, stevejburge, andergmartins
Tags: revision, access, permissions, cms, user, groups, members, admin, pages, posts, page, Post
Requires at least: 4.9.7
Tested up to: 5.2.4
Requires PHP: 5.6.20
Stable Tag: 2.0.7
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Maintain published content with teamwork and precision using the Revisions model to submit, moderate and schedule changes.

== Description ==

WordPress Revisions are a powerful way to track where your site has been. But what about where it's going?

To moderate or schedule changes to published posts, just install PublishPress Revisions and let the teamwork begin.  There is no need to learn or configure complex new screens. Users gain new abilities through the familiar UI of the Edit Posts screen and Gutenberg or Classic Editor. 

= New Editorial Capabilities, Smoothly Integrated =
* "Contributors" can submit revisions to their own published posts.
* "Revisors" can submit revisions to posts and pages published by others.
* "Authors", "Editors" and "Administrators" can approve revisions or schedule their own revisions.
* To schedule changes to a published post, just set the desired future date before hitting Update.

= Features =
* Schedule or request changes to published posts and pages using the Gutenberg (or Classic) Editor
* Revision fields include Content, Title, Excerpt, Categories, Tags, Custom Taxonomy Terms, Page Parent or Page Template
* Display changes using the familiar Compare Revisions interface
* Front-end preview display of Pending / Scheduled Revisions with Compare, Approve, and Edit buttons.
* Make further changes to Pending Revisions and Scheduled Revisions with Gutenberg or Classic Editor
* New WordPress role, "Revisor" is a moderated Editor.
* Works with site-wide WordPress Roles, or in conjunction with <a href="https://publishpress.com/presspermit/">PressPermit Pro</a> for page-specific or category-specific permissions.

= Additional Features in the Pro Version =
* Advanced Custom Fields integration
* BeaverBuilder integration
* The Events Calendar compatibility
* WooCommerce compatibility
* WPML integration

For more details about both the free and pro version, see our <a href="https://publishpress.com/revisionary/">Plugin Overview</a> and <a href="https://publishpress.com/documentation/revisionary-start/">Plugin Documentation</a>.

= Support =
* PublishPress Revisions is professionally supported by both the original author (Kevin Behrens) and the experienced <a href="https://publishpress.com">PublishPress</a> team.

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
* Fixed : Scheduled Revisions - If "Update Publish Date" enabled, 404 Not Found redirect after manually publishing a scheduled revision if the post type uses post date in permalink structure
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
