/**
* Block Editor Modifications for Revisionary
*
* By Kevin Behrens
*
* Copyright 2021, PublishPress
*/
jQuery(document).ready(function ($) {
    function RvyRecaptionElement(btnSelector, btnCaption, btnIcon = '') {
        if (rvyObjEdit.disableRecaption) {
            return;
		}
		
		let node = document.querySelector(btnSelector);
		
        if (node) {
			document.querySelector(btnSelector).innerText = `${btnCaption}`;
			
            if (btnIcon) {
                document.querySelector(btnSelector).innerHTML = `<span class="dashicons dashicons-${btnIcon}"></span>${btnCaption}`;
            }
        }
	}
	
	// Update main publish ("Publish" / "Submit Pending") button width and span caption
    function RvySetPublishButtonCaption(caption, waitForSaveDraftButton, forceRegen, timeout) {
        if ('future' == rvyObjEdit.currentStatus) {
            caption = rvyObjEdit.updateCaption;
		}
		
        if (typeof waitForSaveDraftButton == 'undefined') {
            waitForSaveDraftButton = false;
		}
		
        if ( ( ! waitForSaveDraftButton || $('button.editor-post-switch-to-draft').filter(':visible').length || $('button.editor-post-save-draft').filter(':visible').length ) && $('button.editor-post-publish-button').length ) {  // indicates save operation (or return from Pre-Publish) is done
            RvyRecaptionElement('button.editor-post-publish-button', caption);
        }
	}
	
	// Force spans to be regenerated following modal settings window access
	var RvyDetectPublishOptionsDivClosureInterval = '';
    var RvyDetectPublishOptionsDiv = function () {
        if ($('div.components-modal__header').length) {
			clearInterval(RvyDetectPublishOptionsDivInterval);
			
            if ($('span.pp-recaption-button').first()) {
                rvyObjEdit.overrideColor = $('span.pp-recaption-button').first().css('color');
			}
			
            var RvyDetectPublishOptionsClosure = function () {
                if (!$('div.components-modal__header').length) {
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
    if (typeof rvyObjEdit.publish == 'undefined') {
        rvyObjEdit.publishCaptionCurrent = rvyObjEdit.updateCaption;
    } else {
        rvyObjEdit.publishCaptionCurrent = rvyObjEdit.publish;
	}
	
	// Initialization operations to perform once React loads the relevant elements
    var RvyInitializeBlockEditorModifications = function () {
        if (($('button.editor-post-publish-button').length || $('button.editor-post-publish-panel__toggle').length) && ($('button.editor-post-switch-to-draft').length || $('button.editor-post-save-draft').length)) {
			clearInterval(RvyInitInterval);
			
            if ($('button.editor-post-publish-panel__toggle').length) {
                if (typeof rvyObjEdit.prePublish != 'undefined' && rvyObjEdit.prePublish) {
                    RvyRecaptionElement('button.editor-post-publish-panel__toggle', rvyObjEdit.prePublish);
                }

				// Presence of pre-publish button means publish button is not loaded yet. Start looking for it once Pre-Publish button is clicked.
                $(document).on('click', 'button.editor-post-publish-panel__toggle,span.pp-recaption-prepublish-button', function () {
					RvySetPublishButtonCaption('', false, true); // nullstring: set caption to value queued in rvyObjEdit.publishCaptionCurrent
                });
            } else {
                if (typeof rvyObjEdit.publish == 'undefined') {
                    RvySetPublishButtonCaption(rvyObjEdit.updateCaption, false, true);
                } else {
                    RvySetPublishButtonCaption(rvyObjEdit.publish, false, true);
                }
			}
			
            $('select.editor-post-author__select').parent().hide();
            $('button.editor-post-trash').parent().show();
            $('button.editor-post-switch-to-draft').hide();

			$('div.components-notice-list').hide();	// autosave notice
        }
	}
	var RvyInitInterval = setInterval(RvyInitializeBlockEditorModifications, 50);
	
    var RvyHideElements = function () {
        var ediv = 'div.edit-post-sidebar ';
        if ($(ediv + 'div.edit-post-post-visibility,' + ediv + 'div.editor-post-link,' + ediv + 'select.editor-post-author__select:visible,' + ediv + 'div.components-base-control__field input[type="checkbox"]:visible,' + ediv + 'button.editor-post-switch-to-draft,' + ediv + 'button.editor-post-trash').length) {
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
        if ('future' == rvyObjEdit.currentStatus) {
            $('button.editor-post-publish-button').show();
		
        } else {
            if ($('button.editor-post-publish-button').length && ($('button.editor-post-save-draft:visible').length || $('button.editor-post-saved-stated:visible').length)) {
                $('button.editor-post-publish-button').hide();
            }
        }
        if (($('button.editor-post-publish-button').length || $('button.editor-post-publish-panel__toggle').length) 
        && ($('button.editor-post-save-draft').filter(':visible').length || $('.is-saved').filter(':visible').length)
        ) {
            $('button.editor-post-publish-button').hide();
            $('button.editor-post-publish-panel__toggle').hide();
        } else {
            if ($('button.editor-post-publish-button').length) {
                $('button.editor-post-publish-button').show();
            } else {
                $('button.editor-post-publish-panel__toggle').show();
            }
        }
	}
	var RvyHideInterval = setInterval(RvyHideElements, 50);
	
    var RvySubmissionUI = function () {
		// @todo: use .edit-post-post-visibility if edit-post-post-schedule not available
        if ($('div.edit-post-post-schedule').length) {
            var refSelector = 'div.edit-post-post-schedule';
        } else {
            var refSelector = 'div.edit-post-post-visibility';

            if (!$(refSelector).length) {
                refSelector = 'div.edit-post-post-status h2';
            }
        }

        if (rvyObjEdit.ajaxurl && !$('div.edit-post-revision-status').length && $(refSelector).length) {
			$(refSelector).before(
				'<div class="components-panel__row rvy-creation-ui edit-post-revision-status">'
                 + '<span>' + rvyObjEdit.statusLabel + '</span>'
                 + '<div class="components-dropdown rvy-current-status">'
                 + rvyObjEdit[rvyObjEdit.currentStatus + 'StatusCaption']
                 + '</div>'
				+ '</div>'
			);
			
            if (rvyObjEdit[rvyObjEdit.currentStatus + 'ActionURL']) {
                var url = rvyObjEdit[rvyObjEdit.currentStatus + 'ActionURL'];
            } else {
                var url = 'javascript:void(0)';
			}
			
            if (rvyObjEdit[rvyObjEdit.currentStatus + 'ActionCaption']) {
                var approveButtonHTML = '';
				var mainDashicon = '';
				
                if (rvyObjEdit.canPublish && ('pending' != rvyObjEdit.currentStatus) && ('future' != rvyObjEdit.currentStatus)) {
                    approveButtonHTML = '<a href="' + rvyObjEdit['pendingActionURL'] + '" class="revision-approve">'
                         + '<button type="button" class="components-button revision-approve is-button is-primary ppr-purple-button rvy-direct-approve">'
                         + '<span class="dashicons dashicons-yes"></span>'
						+ rvyObjEdit['approveCaption'] + '</button></a>';
						
                    mainDashicon = 'dashicons-upload';
                } else {
                    if ('pending' == rvyObjEdit.currentStatus) {
                        mainDashicon = 'dashicons-yes';
                    } else {
                        mainDashicon = 'dashicons-upload';
                    }
				}
				
				var rvyPreviewLink = '';
				
                if (rvyObjEdit[rvyObjEdit.currentStatus + 'CompletedLinkCaption']) {
                    rvyPreviewLink = '<br /><a href="' + rvyObjEdit[rvyObjEdit.currentStatus + 'CompletedURL'] + '" class="revision-approve revision-preview components-button is-secondary ppr-purple-button" target="pp_revisions_copy">'
                        + rvyObjEdit[rvyObjEdit.currentStatus + 'CompletedLinkCaption'] + '</a>';
				}
				
                $(refSelector).after('<div class="rvy-creation-ui rvy-submission-div"><a href="' + url + '" class="revision-approve">'
                    + '<button type="button" class="components-button revision-approve is-button is-primary ppr-purple-button">'
                    + '<span class="dashicons ' + mainDashicon + '"></span>'
                    + rvyObjEdit[rvyObjEdit.currentStatus + 'ActionCaption'] + '</button></a>'
                    + approveButtonHTML
                    + '<div class="revision-submitting" style="display: none;">'
                    + '<span class="revision-approve revision-submitting">'
                    + rvyObjEdit[rvyObjEdit.currentStatus + 'InProcessCaption'] + '</span><span class="spinner ppr-submission-spinner" style=""></span></div>'
                    + '<div class="revision-approving" style="display: none;">'
                    + '<span class="revision-approve revision-submitting">'
                    + rvyObjEdit.approvingCaption + '</span><span class="spinner ppr-submission-spinner" style=""></span></div>'
                    + '<div class="revision-created" style="display: none; margin-top: 15px">'
                    + '<span class="revision-approve revision-created">'
                    + rvyObjEdit[rvyObjEdit.currentStatus + 'CompletedCaption'] + '</span> '
                    + rvyPreviewLink
                    + '</div>'
					+ '</div>');

                $('div.rvy-submission-div').trigger('loaded-ui');
            }

			$('.edit-post-post-schedule__toggle').after('<button class="components-button is-tertiary post-schedule-footnote" disabled>' + rvyObjEdit.onApprovalCaption + '</button>');
			
            if (rvyObjEdit[rvyObjEdit.currentStatus + 'DeletionURL']) {
                $('button.editor-post-trash').wrap('<a href="' + rvyObjEdit[rvyObjEdit.currentStatus + 'DeletionURL'] + '" style="text-decoration:none"></a>');
            }
        }
        $('button.post-schedule-footnote').toggle(!/\d/.test($('button.edit-post-post-schedule__toggle').html()));

        $('button.editor-post-trash').parent().css('text-align', 'right');
	}
    var RvyUIInterval = setInterval(RvySubmissionUI, 100);
    setInterval(function () {
        if (rvyObjEdit.deleteCaption && $('button.editor-post-trash').length && ($('button.editor-post-trash').html() != rvyObjEdit.deleteCaption)) {
            $('button.editor-post-trash').html(rvyObjEdit.deleteCaption).closest('div').show();
        }
    }, 100);

    var redirectCheckSaveDoneInterval = false;

	function rvyDoSubmission() {
       rvySubmitCopy();
    }

	function rvyDoApproval() {
        setTimeout(
            function() {
                var redirectCheckSaveDoneInterval = setInterval(function () {
                    let saving = wp.data.select('core/editor').isSavingPost();
                    
                    if (!saving || $('div.edit-post-header button.is-saved').length) {
                        clearInterval(redirectCheckSaveDoneInterval);
                        approveCheckSaveInterval = false;

                        if (rvyRedirectURL != '') {
                            setTimeout(
                                function() {
                                    window.location.href = rvyRedirectURL;
                                },
                                5000
                            );
                        }
                    }
                }, 100);
            }, 500
        );
	}

	var rvyRedirectURL = '';

    $(document).on('click', 'button.revision-approve', function () {
        // If autosave approvals are ever enabled, we will need this
        var isApproval = $(this).hasClass('rvy-direct-approve');
		var isSubmission = (rvyObjEdit[rvyObjEdit.currentStatus + 'ActionURL'] == "") && !isApproval;
		
		$('button.revision-approve').hide();
		
		if (isApproval) {
            $('div.revision-approving').show().css('display', 'block');
            $('div.revision-approving span.ppr-submission-spinner').css('visibility', 'visible');

            if (wp.data.select('core/editor').isEditedPostDirty()) {
                wp.data.dispatch('core/editor').savePost();
            }
			rvyRedirectURL = $('div.rvy-creation-ui button.rvy-direct-approve').closest('a').attr('href');

			if (rvyRedirectURL == '') {
				rvyRedirectURL = $('div.rvy-creation-ui button.revision-approve').closest('a').attr('href');
			}
        } else {
            rvyRedirectURL = $('div.rvy-creation-ui a').attr('href');
        }

        if (isSubmission) {
            $('div.revision-submitting').show();
            rvyDoSubmission();
        } else {
            rvyDoApproval();
        }

		return false;
	});
	
	function RvyGetRandomInt(max) {
        return Math.floor(Math.random() * max);
    }

    function rvySubmitCopy() {
        var revisionaryCreateDone = function () {
            if (wp.data.select('core/editor').isEditedPostDirty()) {
                wp.data.dispatch('core/editor').savePost();
            }

            $('.revision-approve').hide();
            $('div.revision-submitting').hide();
            $('.revision-created').show();

			// @todo: abstract this for other workflows
            rvyObjEdit.currentStatus = 'pending';
            $('.rvy-current-status').html(rvyObjEdit[rvyObjEdit.currentStatus + 'StatusCaption']);
            $('a.revision-preview').attr('href', rvyObjEdit[rvyObjEdit.currentStatus + 'CompletedURL']).show();
            $('a.revision-edit').attr('href', rvyObjEdit[rvyObjEdit.currentStatus + 'CompletedEditURL']).show();
        }
		
		var revisionaryCreateError = function (data, txtStatus) {
            $('div.rvy-creation-ui').html(rvyObjEdit[rvyObjEdit.currentStatus + 'ErrorCaption']);
        }
		
		var data = {'rvy_ajax_field': rvyObjEdit[rvyObjEdit.currentStatus + 'AjaxField'], 'rvy_ajax_value': wp.data.select('core/editor').getCurrentPostId(), 'nc': RvyGetRandomInt(99999999)};
		
        $.ajax({
            url: rvyObjEdit.ajaxurl,
            data: data,
            dataType: "html",
            success: revisionaryCreateDone,
            error: revisionaryCreateError
        });
    }
    var RvyRecaptionSaveDraft = function () {
        if ($('button.editor-post-save-draft:not(.rvy-recaption)').length) {
			RvyRecaptionElement('button.editor-post-save-draft:not(.rvy-recaption)', rvyObjEdit.saveRevision);
			
            $('button.editor-post-save-draft:not(.rvy-recaption)').addClass('rvy-recaption').removeClass('is-tertiary').addClass('is-primary').addClass('ppr-purple-button');
		}
		
        if (rvyObjEdit.viewTitleExtra) {
            var newPreviewItem = '<div class="components-menu-group"><div role="group"><div class="edit-post-header-preview__grouping-external">'
                + '<a href="' + rvyObjEdit.viewURL + '" target="pp_revisions_copy" role="menuitem" class="ppr-purple-button components-button is-primary edit-post-header-preview__button-external">'
                + rvyObjEdit.viewTitle + '</a></div></div></div>';

            if (rvyObjEdit.viewTitleExtra && !$('div.rvy-revision-preview').length) {
                if (rvyObjEdit.viewTitleExtra) {
                    $('div.block-editor-post-preview__dropdown').after(newPreviewItem).addClass('rvy-revision-preview');
                }
            }
        }

        if (($('div.edit-post-header__settings a.editor-post-preview:visible').length || $('div.block-editor-post-preview__dropdown button.block-editor-post-preview__button-toggle:visible').length) && !$('a.rvy-post-preview').length) {
            if (rvyObjEdit.viewURL && $('.block-editor-post-preview__button-toggle').length) {
                if ($('div.edit-post-header-preview__grouping-external').length == 1) {
					var elemTemp = $('div.edit-post-header-preview__grouping-external a svg').clone();
                    
                    if (typeof elemTemp[0] != 'undefined') {
                        var svgElem = elemTemp[0].outerHTML;
					
					    $('div.edit-post-header-preview__grouping-external').after(newPreviewItem);
                    }
				}
				
                if (rvyObjEdit.viewCaption) {
                    RvyRecaptionElement('.block-editor-post-preview__button-toggle', rvyObjEdit.viewCaption);
                    $('button.block-editor-post-preview__button-toggle:not(.ppr-purple-button)').removeClass('is-tertiary').addClass('is-secondary').addClass('ppr-purple-button');
                }
            }
            if (rvyObjEdit.viewTitle) {
                $('div.edit-post-header__settings a.rvy-post-preview').attr('title', rvyObjEdit.viewTitle);
            }
			
        } else {
			if (!rvyObjEdit.multiPreviewActive) { // WP < 5.5
                if (!$('a.editor-post-preview').next('a.rvy-post-preview').length) {
                    original = $('div.edit-post-header__settings a.editor-post-preview');
                    $(original).after(original.clone().attr('href', rvyObjEdit.viewURL).attr('target', '_blank').removeClass('editor-post-preview').addClass('rvy-post-preview').css('margin', '0 10px 0 10px'));
					
					if (rvyObjEdit.viewCaption) {
                        RvyRecaptionElement('div.edit-post-header__settings a.rvy-post-preview', rvyObjEdit.viewCaption);
                    }
					
					if (rvyObjEdit.viewTitle) {
                        $('div.edit-post-header__settings a.rvy-post-preview').attr('title', rvyObjEdit.viewTitle);
                    }
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
});
