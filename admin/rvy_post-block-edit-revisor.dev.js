/**
* Block Editor Modifications for Revisionary (Revision Submitter)
*
* By Kevin Behrens
*
* Copyright 2019, PublishPress
*/
jQuery(document).ready( function($) {
	/***** Redirect back to edit.php if user won't be able to futher edit after changing post status *******/
	$(document).on('click', 'button.editor-post-publish-button,button.editor-post-save-draft', function() {
		var RvyRedirectCheckSaveInterval = setInterval( function() {
			let saving = wp.data.select('core/editor').isSavingPost();
			if ( saving ) {
				clearInterval(RvyRedirectCheckSaveInterval);
			
				var RvyRedirectCheckSaveDoneInterval = setInterval( function() {
					let saving = wp.data.select('core/editor').isSavingPost();
		
					if ( ! saving ) {
						clearInterval(RvyRedirectCheckSaveDoneInterval);

						let goodsave = wp.data.select('core/editor').didPostSaveRequestSucceed();
						if ( goodsave ) {
							var redirectProp = 'redirectURL';
							
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
				}, 50 );
			}
		}, 10 );
	});
	/*******************************************************************************************************/

	function RvyRecaptionElement(btnSelector, btnCaption) {
		let node = document.querySelector(btnSelector);

		if (node) {
			document.querySelector(btnSelector).innerText = `${btnCaption}`;
		}
	}

	// Update main publish ("Publish" / "Submit Pending") button width and span caption
	function RvySetPublishButtonCaption(caption,waitForSaveDraftButton,forceRegen,timeout) {
		if ( caption == '' && ( typeof rvyObjEdit['publishCaptionCurrent'] != 'undefined' )  ) {
			caption = rvyObjEdit.publishCaptionCurrent;
		} else {
			rvyObjEdit.publishCaptionCurrent = caption;
		}

		if ( typeof waitForSaveDraftButton == 'undefined' ) {
			waitForSaveDraftButton = false;
		}

		if ( ( ! waitForSaveDraftButton || $('button.editor-post-switch-to-draft').filter(':visible').length || $('button.editor-post-save-draft').filter(':visible').length ) && $('button.editor-post-publish-button').length ) {  // indicates save operation (or return from Pre-Publish) is done
			RvyRecaptionElement('button.editor-post-publish-button', caption);
		} else {
			if ( ( typeof timeout == 'undefined' ) || parseInt(timeout) < 50 ) { timeout = 15000;}
			var RecaptionInterval = setInterval(RvyWaitForRecaption, 100);
			var RecaptionTimeout = setTimeout( function(){ clearInterval(RvyRecaptionInterval);}, parseInt(timeout) ); 

			function WaitForRecaption(timeout) {
				if ( ! waitForSaveDraftButton || $('button.editor-post-switch-to-draft').filter(':visible').length || $('button.editor-post-save-draft').filter(':visible').length ) { // indicates save operation (or return from Pre-Publish) is done
					if ( $('button.editor-post-publish-button').length ) {			  // indicates Pre-Publish is disabled
						clearInterval(RvyRecaptionInterval);
						clearTimeout(RvyRecaptionTimeout);
						RvyRecaptionElement('button.editor-post-publish-button', caption);
					} else {
						if ( waitForSaveDraftButton ) {  // case of execution following publish click with Pre-Publish active
							clearInterval(RvyRecaptionInterval);
							clearTimeout(RvyRecaptionTimeout);
						}
					}
				}
			}
		}
	}

	// Force spans to be regenerated following modal settings window access
	var RvyDetectPublishOptionsDivClosureInterval='';
	var RvyDetectPublishOptionsDiv = function() {
		if ( $('div.components-modal__header').length ) {
			clearInterval( RvyDetectPublishOptionsDivInterval );

			if ( $('span.pp-recaption-button').first() ) {
				rvyObjEdit.overrideColor = $('span.pp-recaption-button').first().css('color');
			}

			var RvyDetectPublishOptionsClosure = function() {
				if ( ! $('div.components-modal__header').length ) {
					clearInterval(RvyDetectPublishOptionsDivClosureInterval);
					
					$('span.pp-recaption-button').hide(); //.addClass('force-regen');
					RvyInitInterval = setInterval(RvyInitializeBlockEditorModifications, 50);
					RvyDetectPublishOptionsDivInterval = setInterval(RvyDetectPublishOptionsDiv, 1000);
				}
			}
			RvyDetectPublishOptionsDivClosureInterval = setInterval(RvyDetectPublishOptionsClosure, 200);
		}
	}
	var RvyDetectPublishOptionsDivInterval = setInterval(RvyDetectPublishOptionsDiv, 1000);
	/*****************************************************************************************************************/


	/************* RECAPTION PRE-PUBLISH AND PUBLISH BUTTONS ****************/
	rvyObjEdit.publishCaptionCurrent = rvyObjEdit.publish;
	
	// Initialization operations to perform once React loads the relevant elements
	var RvyInitializeBlockEditorModifications = function() {
		if ( ( $('button.editor-post-publish-button').length || $('button.editor-post-publish-panel__toggle').length ) && ( $('button.editor-post-switch-to-draft').length || $('button.editor-post-save-draft').length || $('div.publishpress-extended-post-status select:visible').length ) ) {
			clearInterval(RvyInitInterval);
			
			if ( $('button.editor-post-publish-panel__toggle').length ) {
				if ( typeof rvyObjEdit.prePublish != 'undefined' && rvyObjEdit.prePublish ) {
					RvyRecaptionElement('button.editor-post-publish-panel__toggle', rvyObjEdit.prePublish);
				}

				// Presence of pre-publish button means publish button is not loaded yet. Start looking for it once Pre-Publish button is clicked.
				$(document).on('click', 'button.editor-post-publish-panel__toggle,span.pp-recaption-prepublish-button', function() {
					RvySetPublishButtonCaption('', false, true); // nullstring: set caption to value queued in rvyObjEdit.publishCaptionCurrent 
				});
			} else {
				RvySetPublishButtonCaption(rvyObjEdit.publish, false, true);
			}
			
			$('select.editor-post-author__select').parent().hide();
			//$('div.edit-post-last-revision__panel').hide();
			$('div.edit-post-post-visibility').hide();
			$('button.editor-post-trash').hide();
			$('button.editor-post-switch-to-draft').hide();

			$('div.components-notice-list').hide();	// autosave notice
			$('div.edit-post-post-status div.components-base-control__field input[type="checkbox"]').hide().next('label').hide(); // stick to top
			if (rvyObjEdit.userRevision) {
				if ($('button.editor-post-publish-button:visible').length && !$('div.have-revision-notice').length) {
					// @todo: Gutenberg does not allow manually inserted html to contain links
					var html = '<div class="components-notice is-warning" style="padding:0 0 0 8px"><div class="components-notice__content">' 
					+ rvyObjEdit.revisionExistsCaption;
					/*
					+  '<a class="components-button is-link">'
					+ rvyObjEdit.editRevisionCaption
					+ '</a>'
					*/

					if (rvyObjEdit.editRevisionURL) {
						html += ' <strong>' + rvyObjEdit.editRevisionURL + '</strong>';
					}

					html += '</div></div> ';

					$('div.edit-post-header-toolbar__left').after('<div class="have-revision-notice">' + html + '</div>');
				}
				
				/*
				// @todo: Gutenberg does not allow this to be displayed to Revisors
				wp.data.dispatch('core/notices').createInfoNotice(
					'info', // Can be one of: success, info, warning, error.
					rvyObjEdit.revisionExistsCaption,
					{
						id: 'rvyRevisionExistsNotice',
						isDismissible: false,
		
						// Any actions the user can perform.
						actions: [
							{
								url: rvyObjEdit.editRevisionURL,
								label: rvyObjEdit.editRevisionCaption,
							},
				 			//{
				 			//	label: ' or ',
							//},
				 			//{
				 			//	url: another_url,
				 			//	label: __('Another action'),
							//},
				  		]
		  			}
				);
				*/
			}
		}
	}
	var RvyInitInterval = setInterval(RvyInitializeBlockEditorModifications, 50);


	var RvyHideElements = function() {
		var ediv = 'div.edit-post-sidebar ';

		//if ( $(ediv + 'div.edit-post-post-visibility,' + ediv + 'div.edit-post-last-revision__panel,' + ediv + 'div.editor-post-link,' + ediv + 'select.editor-post-author__select:visible,' + ediv + 'div.components-base-control__field input[type="checkbox"]:visible,' + ediv + 'button.editor-post-switch-to-draft,' + ediv + 'button.editor-post-trash').length ) {
		if ( $(ediv + 'div.edit-post-post-visibility,' + ediv + 'div.editor-post-link,' + ediv + 'select.editor-post-author__select:visible,' + ediv + 'div.components-base-control__field input[type="checkbox"]:visible,' + ediv + 'button.editor-post-switch-to-draft,' + ediv + 'button.editor-post-trash').length ) {
			$(ediv + 'select.editor-post-author__select').parent().hide();
			//$(ediv + 'div.edit-post-last-revision__panel').hide();
			$(ediv + 'div.edit-post-post-visibility').hide();
			$(ediv + 'button.editor-post-trash').hide();
			$(ediv + 'button.editor-post-switch-to-draft').hide();
			$(ediv + 'div.components-notice-list').hide();	// autosave notice
			$(ediv + 'div.edit-post-post-status div.components-base-control__field input[type="checkbox"]').each(function(e){$('label[for="' + $(this).attr('id') + '"]').hide();}).hide().next('label').hide();
		}
		
	}
	var RvyHideInterval = setInterval(RvyHideElements, 50);


	/*
	// If Publish button is clicked, current post status will be set to [user's next/max status progression]
	// So set Publish button caption to "Save As %s" to show that no further progression is needed / offered.
	$(document).on('click', 'button.editor-post-publish-button', function() {
		// Wait for Save Draft button to reappear; this will have no effect on Publish Button if Pre-Publish is enabled (but will update rvyObjEdit property for next button refresh)
		setTimeout( function() {
			RvySetPublishButtonCaption(rvyObjEdit.saveAs, true);
		}, 50 );
	});
	*/
	/**************************************************************************************************************************************/
});
