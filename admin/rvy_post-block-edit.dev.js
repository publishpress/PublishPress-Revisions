/**
* Block Editor Modifications for Revisionary (Non-restricted Editor)
*
* By Kevin Behrens
*
* Copyright 2019, PublishPress
*/

jQuery(document).ready( function($) {
	/**
	 *  Redirect back to edit.php if a save operation triggers scheduled revision creation
	 */
	$(document).on('click', 'button.editor-post-publish-button,button.editor-post-save-draft', function() {
		var redirectCheckSaveInterval = setInterval( function() {
			let saving = wp.data.select('core/editor').isSavingPost();

			if ( saving ) {
				clearInterval(redirectCheckSaveInterval);
			
				var redirectCheckSaveDoneInterval = setInterval( function() {
					let saving = wp.data.select('core/editor').isSavingPost();
		
					if ( ! saving ) {
						clearInterval(redirectCheckSaveDoneInterval);

						let goodsave = wp.data.select('core/editor').didPostSaveRequestSucceed();
						if ( goodsave ) {
							let savedScheduledRevision = wp.data.select('core/editor').getCurrentPostAttribute('new_scheduled_revision');
							if ( savedScheduledRevision ) {
								var redirectProp = 'redirectURLscheduled';

								if ( typeof rvyObjEdit[redirectProp] != undefined ) {
									var rurl = rvyObjEdit[redirectProp];
									var recipients = $('input[name="prev_cc_user[]"]:checked');
									
									if ( recipients.length ) {
										var cc = recipients.map(function() {
											return $(this).val();
										}).get().join(',');

										rurl = rurl + '&cc=' + cc;
									}

									$(location).attr("href", rurl);
								}
							}
						}
					}
				}, 50 );
			}
		}, 10 );
	});

	/**
	 *  If date is set to future, change Publish button caption to "Schedule Revision",
	 *  Then set a self-interval to refresh that status once the selected date is no longer future.
	 * 
	 *  If the selected date is already past, change Publish button back to "Update"
	 */
	var RvySelectedFutureDate = false;

	var RvyRefreshPublishButtonCaptionWrapper = function() { RvyRefreshPublishButtonCaption(); }

	var RvyRefreshPublishButtonCaption = function( forceRefresh ) {
		var selectedDate = new Date( $('#edit-post-post-schedule__toggle-1').html() );
		var tdiff = selectedDate.getTime() - Date.now();

		if ( tdiff > 1000 || ( typeof forceRefresh != 'undefined' ) ) {
			let node = document.querySelector('button.editor-post-publish-button');
			if (node) {
				node.innerText = `${rvyObjEdit.ScheduleCaption}`;
			}
			RvySelectedFutureDate = true;
			setTimeout(RvyRefreshPublishButtonCaptionWrapper, tdiff + 2000);
		} else {
			if ( tdiff <= 0 ) {
				if ( RvySelectedFutureDate ) { // If button isn't already recaptioned, don't mess with it or even query for it
					let node = document.querySelector('button.editor-post-publish-button');
					if (node) {
						node.innerText = `${rvyObjEdit.UpdateCaption}`;
					}
				}
			}
		}
	}
	
	/**
	 *  Detect date selection by display, then closure of popup dialog
	 */
	var RvyDetectPublishOptionsDivClosureInterval = false;
	var RvyRefreshPublishButtonCaptionInterval = false;
	var RvyDetectPublishOptionsDiv = function() {
		if ( $('div.edit-post-post-schedule__dialog').length ) {
			clearInterval( RvyDetectPublishOptionsDivInterval );
			RvyRefreshPublishButtonCaptionInterval = setInterval(RvyRefreshPublishButtonCaption, 500);

			var RvyDetectPublishOptionsClosure = function() {
				if ( ! $('div.edit-post-post-schedule__dialog').length ) {
					clearInterval(RvyDetectPublishOptionsDivClosureInterval);
					clearInterval(RvyRefreshPublishButtonCaptionWrapper);
					RvyDetectPublishOptionsDivInterval = setInterval(RvyDetectPublishOptionsDiv, 500);
					RvyRefreshPublishButtonCaption(true);
				}
			}
			RvyDetectPublishOptionsDivClosureInterval = setInterval(RvyDetectPublishOptionsClosure, 200);
		}
	}
	var RvyDetectPublishOptionsDivInterval = setInterval(RvyDetectPublishOptionsDiv, 500);
});
