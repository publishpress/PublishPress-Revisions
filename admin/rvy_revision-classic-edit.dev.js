/**
* Classic Editor Modifications for Revisionary
*
* By Kevin Behrens
*
* Copyright 2021, PublishPress
*/
jQuery(document).ready( function($) {
	var RvySubmissionUI = function() {
		var refSelector = '#rvy_compare_button';

        if (!$(refSelector).length) {
            var refSelector = '#submitdiv div.misc-pub-section:last';
        }

        if (!$(refSelector).length) {
            var refSelector = '#submitdiv div.curtime';
        }

        if (rvyObjEdit[rvyObjEdit.currentStatus + 'StatusCaption'] && (rvyObjEdit[rvyObjEdit.currentStatus + 'StatusCaption'] != $('#post-status-display').html())) {
            $('#post-status-display').html(rvyObjEdit[rvyObjEdit.currentStatus + 'StatusCaption']);
        }

		if (rvyObjEdit.ajaxurl && !$('div.rvy-creation-ui').length && $(refSelector).length) {
			if (rvyObjEdit[rvyObjEdit.currentStatus + 'ActionURL']) {
				var url = rvyObjEdit[rvyObjEdit.currentStatus + 'ActionURL'];
			} else {
				var url = 'javascript:void(0)';
            }
            
			if (rvyObjEdit[rvyObjEdit.currentStatus + 'ActionCaption']) {
                var approveButtonHTML = '';

                if (rvyObjEdit.canPublish && ('pending' != rvyObjEdit.currentStatus) && ('future' != rvyObjEdit.currentStatus)) {
					approveButtonHTML = '&nbsp;<a href="' + rvyObjEdit['pendingActionURL'] + '" class="button rvy-direct-approve">'
					+ rvyObjEdit['approveCaption'] + '</a>'
				}

                var rvyPreviewLink = '';

				if (rvyObjEdit[rvyObjEdit.currentStatus + 'CompletedLinkCaption']) {
                    rvyPreviewLink = '&nbsp; <a href="' + rvyObjEdit[rvyObjEdit.currentStatus + 'CompletedURL'] + '" class="revision-preview" target="_blank">' 
                    + rvyObjEdit[rvyObjEdit.currentStatus + 'CompletedLinkCaption'] + '</a>';
                }

				$(refSelector).after(
                    '<div class="rvy-creation-ui" style="flo-at:left; padding-left:10px; margin-bottom: 10px">'

                    + '<a href="' + url + '" class="button revision-approve">'
					+ rvyObjEdit[rvyObjEdit.currentStatus + 'ActionCaption'] + '</a>'
                
                    + approveButtonHTML

                    + '<div class="revision-created-wrapper" style="display: none; margin: 8px 0 0 2px">'
					+ '<span class="revision-approve revision-created" style="color:green">'
					+ rvyObjEdit[rvyObjEdit.currentStatus + 'CompletedCaption'] + '</span> '
                    + rvyPreviewLink
                    + '</div>'

                    + '</div>'
				);
            }
            
			$('.edit-post-post-schedule__toggle').after('<button class="components-button is-tertiary post-schedule-footnote" disabled>' + rvyObjEdit.onApprovalCaption + '</button>');

			if (rvyObjEdit[rvyObjEdit.currentStatus + 'DeletionURL']) {
				$('a.submitdelete').attr('href', rvyObjEdit[rvyObjEdit.currentStatus + 'DeletionURL']);
            }
            
            $('#publish').hide();
            $('#save-post').val(rvyObjEdit.updateCaption);

            if (rvyObjEdit.deleteCaption) {
                $('#submitdiv #submitpost #delete-action a.submitdelete').html(rvyObjEdit.deleteCaption).show();
            }
        }
	}
	var RvyUIInterval = setInterval(RvySubmissionUI, 100);

    $('a.save-timestamp').click(function() {
        $('#save-post').val(rvyObjEdit.updateCaption);
        $('a.revision-approve, a.rvy-direct-approve').attr('disabled', 'disabled');
    });

	$(document).on('click', 'a.save-timestamp, a.cancel-timestamp', function() {
        wp.autosave.server.triggerSave();
	});

	function RvyGetRandomInt(max) {
		return Math.floor(Math.random() * max);
    }
    
    $(document).on('click', 'div.postbox-container', function() {
		$('a.revision-approve').attr('disabled', 'disabled');
	});

	$(document).on('click', 'a.revision-approve', function() {
        if ($('a.revision-approve').attr('disabled')) {
			return false;
		}

        $('a.revision-approve').attr('disabled', 'disabled');

        if (wp.autosave.server.postChanged()) {
            wp.autosave.server.triggerSave();
            var approvalDelay = 250;
        } else {
            var approvalDelay = 1;
        }
        
        if (!rvyObjEdit[rvyObjEdit.currentStatus + 'ActionURL']) {
			var revisionaryCreateDone = function () {
                $('a.revision-approve').hide();
                $('.revision-created-wrapper, .revision-created').show();

				// @todo: abstract this for other workflows
				rvyObjEdit.currentStatus = 'pending';

				$('#post-status-display').html(rvyObjEdit[rvyObjEdit.currentStatus + 'StatusCaption']);
                $('a.revision-preview').attr('href', rvyObjEdit[rvyObjEdit.currentStatus + 'CompletedURL']).show();
			}

			var revisionaryCreateError = function (data, txtStatus) {
				$('div.rvy-creation-ui').html(rvyObjEdit[rvyObjEdit.currentStatus + 'ErrorCaption']);
			}

            var tmoSubmit = setInterval(function() {
                if (!wp.autosave.server.postChanged()) {
                    var data = {'rvy_ajax_field': rvyObjEdit[rvyObjEdit.currentStatus + 'AjaxField'], 'rvy_ajax_value': rvyObjEdit.postID, 'nc': RvyGetRandomInt(99999999)};

                    $.ajax({
                        url: rvyObjEdit.ajaxurl,
                        data: data,
                        dataType: "html",
                        success: revisionaryCreateDone,
                        error: revisionaryCreateError
                    });

                    clearInterval(tmoSubmit);
                }
            }, approvalDelay);
        } else {
            var tmoApproval = setInterval(function() {
                if (!wp.autosave.server.postChanged()) {
                    window.location.href = rvyObjEdit[rvyObjEdit.currentStatus + 'ActionURL'];

                    clearInterval(tmoApproval);
                }
            }, approvalDelay);

            return false;
        }
    });
    
    $(document).on('click', 'a.rvy-direct-approve', function() {
        if ($('a.rvy-direct-approve').attr('disabled')) {
			return false;
		}

        clearInterval(RvyUIInterval);

        $('a.rvy-direct-approve').attr('disabled', 'disabled');

        if (wp.autosave.server.postChanged()) {
            wp.autosave.server.triggerSave();
            var approvalDelay = 250;
        } else {
            var approvalDelay = 1;
        }
        
        var tmoDirectApproval = setInterval(function() {
            if (!wp.autosave.server.postChanged()) {
                window.location.href = rvyObjEdit['pendingActionURL'];

                clearInterval(tmoDirectApproval);
            }
        }, approvalDelay);

        return false;
    });

    $(document).on('click', '#post-body-content *, #content_ifr *, #wp-content-editor-container *, #tinymce *, #submitpost, span.revision-created', function() {
        $('.revision-created-wrapper, .revision-created').hide();

        if (!$('a.rvy-direct-approve').length) {
            $('a.revision-approve').html(rvyObjEdit[rvyObjEdit.currentStatus + 'ActionCaption']).show().removeAttr('disabled');
        }
    });
});
