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
		$('a.rvy-post-preview').remove();

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


	/************* RECAPTION PRE-PUBLISH AND PUBLISH BUTTONS ****************/
	rvyObjEdit.publishCaptionCurrent = rvyObjEdit.publish;
	
	// Initialization operations to perform once React loads the relevant elements
	var RvyInitializeBlockEditorModifications = function() {
		if ( ( $('button.editor-post-publish-button').length || $('button.editor-post-publish-panel__toggle').length ) && ( $('button.editor-post-switch-to-draft').length || $('button.editor-post-save-draft').length ) ) {
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
			$('button.editor-post-trash').parent().show();
			$('button.editor-post-switch-to-draft').hide();

			$('div.components-notice-list').hide();	// autosave notice
		}

		if ( ( $('button.editor-post-publish-button').length || $('button.editor-post-publish-panel__toggle').length ) ) {
			$('button.editor-post-publish-button').hide();
			$('button.editor-post-publish-panel__toggle').hide();
		}
	}
	var RvyInitInterval = setInterval(RvyInitializeBlockEditorModifications, 50);

	var RvyHideElements = function() {
		var ediv = 'div.edit-post-sidebar ';

		if ( $(ediv + 'div.edit-post-post-visibility,' + ediv + 'div.editor-post-link,' + ediv + 'select.editor-post-author__select:visible,' + ediv + 'div.components-base-control__field input[type="checkbox"]:visible,' + ediv + 'button.editor-post-switch-to-draft,' + ediv + 'button.editor-post-trash').length ) {
			$(ediv + 'select.editor-post-author__select').parent().hide();
			$(ediv + 'button.editor-post-trash').parent().show();
			$(ediv + 'button.editor-post-switch-to-draft').hide();
			$(ediv + 'div.editor-post-link').parent().hide();
			$(ediv + 'div.components-notice-list').hide();	// autosave notice

			if (!rvyObjEdit.scheduledRevisionsEnabled) {
				$(ediv + 'div.edit-post-post-schedule').hide();
			}

			$(ediv + '#publishpress-notifications').hide();

			$('#icl_div').closest('div.edit-post-meta-boxes-area').hide();
		}

		if ( $('button.editor-post-publish-button').length ) {
			$('button.editor-post-publish-button').hide();
		}

		if (rvyObjEdit.approvalURL && !RvyApprovalHidden && !$('button.revision-approve').length && $('div.edit-post-post-schedule').length) {
			$('div.edit-post-post-schedule').after(
				'<a href="' + rvyObjEdit.approvalURL + '" class="revision-approve" title="' + rvyObjEdit.approvalTitle + '"><button type="button" class="components-button revision-approve is-button is-default is-large">' + rvyObjEdit.approvalCaption + '</button></a>');
			
			if (rvyObjEdit.deletionURL) {
				$('button.editor-post-trash').wrap('<a href="' + rvyObjEdit.deletionURL + '"></a>');
			}
		}
	}
	var RvyHideInterval = setInterval(RvyHideElements, 50);

	var RvyRecaptionSaveDraft = function() {
		if ($('button.editor-post-save-draft:not(.rvy-recaption)').length) {
			RvyRecaptionElement('button.editor-post-save-draft:not(.rvy-recaption)', rvyObjEdit.saveRevision);
			$('button.editor-post-save-draft:not(.rvy-recaption)').addClass('rvy-recaption');
		}

		if (($('div.edit-post-header__settings a.editor-post-preview:visible').length || $('div.block-editor-post-preview__dropdown button.block-editor-post-preview__button-toggle:visible').length) && !$('a.rvy-post-preview').length) {
			
			if (rvyObjEdit.viewURL) {
				original = $('div.edit-post-header__settings a.editor-post-preview');
				$(original).after(original.clone().attr('href', rvyObjEdit.viewURL).attr('target', '_blank').removeClass('editor-post-preview').addClass('rvy-post-preview'));
				$(original).hide();

				if (rvyObjEdit.multiPreviewActive) {
					$('.rvy-post-preview').removeClass('components-button').css('height', 'inherit').css('text-decoration', 'none');
				}
			}

			if (rvyObjEdit.viewCaption) {
				RvyRecaptionElement('div.edit-post-header__settings a.rvy-post-preview', rvyObjEdit.viewCaption);
			}

			if (rvyObjEdit.viewTitle) {
				$('div.edit-post-header__settings a.rvy-post-preview').attr('title', rvyObjEdit.viewTitle);
			}
		} else {
			if (!rvyObjEdit.multiPreviewActive) {
				if (!$('a.editor-post-preview').next('a.rvy-post-preview').length) {
					$('a.rvy-post-preview').insertAfter($('a.editor-post-preview'));
				}

				if (rvyObjEdit.previewTitle && !$('a.editor-post-preview').attr('title')) {
					$('div.edit-post-header__settings a.editor-post-preview').attr('title', rvyObjEdit.previewTitle);
				}
			}
		}

		if (rvyObjEdit.revisionEdits && $('div.edit-post-sidebar a.editor-post-last-revision__title:visible').length && !$('div.edit-post-sidebar a.editor-post-last-revision__title.rvy-recaption').length) {
			$('div.edit-post-sidebar a.editor-post-last-revision__title').html(rvyObjEdit.revisionEdits);
			$('div.edit-post-sidebar a.editor-post-last-revision__title').addClass('rvy-recaption');
		}
	}
	var RvyRecaptionSaveDraftInterval = setInterval(RvyRecaptionSaveDraft, 100);

	var RvyApprovalHidden = false;

	$(document).on('click', 'div.edit-post-visual-editor *, div.editor-inserter *', function() {
		RvyApprovalHidden = true;
		$('a.revision-approve').hide();
		$('a.rvy-post-preview').hide();

		if (!rvyObjEdit.multiPreviewActive) {
			$('a.editor-post-preview').show();
		}

		//$('a.revision-save-to-approve').show();
	});

	$(document).on('click', 'button.editor-post-publish-button,button.editor-post-save-draft', function() {
		RvyApprovalHidden = false;
		//$('a.revision-save-to-approve').hide();
		$('a.revision-approve').show();
		$('a.rvy-post-preview').show();

		if (!rvyObjEdit.multiPreviewActive) {
			$('a.editor-post-preview').hide();
		}
	});
});
