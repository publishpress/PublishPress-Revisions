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
                            let savedAsRevision = wp.data.select('core/editor').getCurrentPostAttribute('save_as_revision');

                            if ( savedAsRevision ) {
                                var redirectProp = 'redirectURLpending';
                            } else {
                                let savedScheduledRevision = wp.data.select('core/editor').getCurrentPostAttribute('new_scheduled_revision');
                                
                                if ( savedScheduledRevision ) {
                                    var redirectProp = 'redirectURLscheduled';
                                }
                            }
                            
                            if (typeof redirectProp != 'undefined') {     
								if ( typeof rvyObjEdit[redirectProp] != 'undefined' ) {
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

	var RvyRefreshPublishButtonCaption = function() {
		var selectedDate = new Date( $('button.edit-post-post-schedule__toggle').html() );
		var tdiff = selectedDate.getTime() - Date.now();

		let postStatus = wp.data.select('core/editor').getCurrentPostAttribute('status');

		var publishedStatuses = Object.keys(rvyObjEdit.publishedStatuses).map(function (key) { return rvyObjEdit.publishedStatuses[key]; });
		var isPublished = publishedStatuses.indexOf(postStatus) >= 0;

		if ((tdiff > 1000) && isPublished) {
			let node = document.querySelector('button.editor-post-publish-button');
			if (node && !$('input.rvy_save_as_revision:checked').length) {
				node.innerText = `${rvyObjEdit.ScheduleCaption}`;
				$('input.rvy_save_as_revision').closest('label').attr('title', rvyObjEdit.revisionTitleFuture);
			}
			RvySelectedFutureDate = true;
			setTimeout(RvyRefreshPublishButtonCaptionWrapper, tdiff + 2000);
		} else {
			if ( tdiff <= 0 ) {
				if ( RvySelectedFutureDate ) { // If button isn't already recaptioned, don't mess with it or even query for it
					let node = document.querySelector('button.editor-post-publish-button');
					if (node) {
						node.innerText = `${rvyObjEdit.UpdateCaption}`;
						$('input.rvy_save_as_revision').closest('label').attr('title', rvyObjEdit.revisionTitle);
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
					RvyRefreshPublishButtonCaption();
				}
			}
			RvyDetectPublishOptionsDivClosureInterval = setInterval(RvyDetectPublishOptionsClosure, 200);
		}
	}

	if (rvyObjEdit.ScheduleCaption) {
    	var RvyDetectPublishOptionsDivInterval = setInterval(RvyDetectPublishOptionsDiv, 500);
	}
	
	// @todo: Don't show Pending Revision checkbox when post is not publish, private or a custom privacy status
	// @todo: Fix formatting of Pending Revision checkbox when Pre-Publish check is enabled
    var RvySaveAsRevision = function() {
		let postStatus = wp.data.select('core/editor').getCurrentPostAttribute('status');

		var revisableStatuses = Object.keys(rvyObjEdit.revisableStatuses).map(function (key) { return rvyObjEdit.revisableStatuses[key]; });

		if (revisableStatuses.indexOf(postStatus) >= 0) {
			if ($('button.editor-post-publish-panel__toggle:visible').length && !$('div.editor-post-publish-panel:visible').length) {
				$('#rvy_save_as_revision:visible').parent('label').hide();
			} else {
				if (rvyObjEdit.revision && !$('#rvy_save_as_revision:visible').length) {
					$('#rvy_save_as_revision').parent('label').remove();

					var attribs = rvyObjEdit.defaultPending ? ' checked="checked"' : '';
					
					if (rvyObjEdit.defaultPending) {
						RvyRecaptionElement('button.editor-post-publish-button', rvyObjEdit.SaveCaption);
					}

					$('button.editor-post-publish-button').last().after('<label style="-webkit-touch-callout: none;-webkit-user-select: none;-moz-user-select: none;-ms-user-select: none;user-select: none;" title="' + rvyObjEdit.revisionTitle + '"><input type="checkbox" class="rvy_save_as_revision" id="rvy_save_as_revision"' + attribs + '>' + rvyObjEdit.revision + '&nbsp;</label>');
					$('.editor-post-publish-panel__header-cancel-button').css('margin-bottom', '20px');  /* Pre-publish cancel button alignment when revision submission is enabled for unpublished posts */
				}

				$('#rvy_save_as_revision').parent('label').last().show();
			}
		} else {
			$('#rvy_save_as_revision').parent('label').remove();
		}
	}
    var RvyRecaptionSaveDraftInterval = setInterval(RvySaveAsRevision, 100);
	
	function RvyRecaptionElement(btnSelector, btnCaption) {
		let node = document.querySelector(btnSelector);

		if (node) {
			document.querySelector(btnSelector).innerText = `${btnCaption}`;
		}
	}

    $(document).on('click', '#rvy_save_as_revision', function(){
        var data = {'rvy_ajax_field': 'save_as_revision', 'rvy_ajax_value': $(this).prop('checked'), 'post_id': wp.data.select('core/editor').getCurrentPostId()};
        $.ajax({url: rvyObjEdit.ajaxurl, data: data, dataType: "html", success: function(){}, error: function(){}});

        if($(this).prop('checked')) {
            RvyRecaptionElement('button.editor-post-publish-button', rvyObjEdit.SaveCaption);
        } else {
            RvyRecaptionElement('button.editor-post-publish-button', rvyObjEdit.UpdateCaption);
        }
	});
	
	if (rvyObjEdit.scheduledRevisionsURL || rvyObjEdit.pendingRevisionsURL) {
		var RvyPendingRevPanel = function() {
			var ediv = 'div.edit-post-sidebar ';

			if (rvyObjEdit.scheduledRevisionsURL && !$(ediv + 'div.edit-post-last-revision__panel a.rvy-scheduled-revisions').length ) {
				var sclone = $('div.edit-post-last-revision__panel a:first').clone().addClass('rvy-scheduled-revisions').attr('href', rvyObjEdit.scheduledRevisionsURL).html(rvyObjEdit.scheduledRevisionsCaption);
				$(ediv + 'div.edit-post-last-revision__panel a:last').after(sclone);
			}

			if (rvyObjEdit.pendingRevisionsURL && !$(ediv + 'div.edit-post-last-revision__panel a.rvy-pending-revisions').length ) {
				var pclone = $('div.edit-post-last-revision__panel a:first').clone().addClass('rvy-pending-revisions').attr('href', rvyObjEdit.pendingRevisionsURL).html(rvyObjEdit.pendingRevisionsCaption);
				$(ediv + 'div.edit-post-last-revision__panel a:last').after(pclone);
			}

			$('div.edit-post-last-revision__panel').css('height', 'inherit');
		}
		var RvyPendingRevPanelInt = setInterval(RvyPendingRevPanel, 200);
	}
});
