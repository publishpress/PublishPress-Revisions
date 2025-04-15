jQuery(document).ready(function($){function RvyRecaptionElement(btnSelector,btnCaption,btnIcon=''){if(rvyObjEdit.disableRecaption){return;}
if(document.querySelector(btnSelector)){document.querySelector(btnSelector).innerText=`${btnCaption}`;if(btnIcon){document.querySelector(btnSelector).innerHTML=`<span class="dashicons dashicons-${btnIcon}"></span><span class="rvy-caption">${btnCaption}</span>`;}}}
function RvySetPublishButtonCaption(caption,waitForSaveDraftButton,forceRegen,timeout){if('future'==rvyObjEdit.currentStatus){caption=rvyObjEdit.updateCaption;}
if(typeof waitForSaveDraftButton=='undefined'){waitForSaveDraftButton=false;}
if((!waitForSaveDraftButton||$('button.editor-post-switch-to-draft').filter(':visible').length||$('button.editor-post-save-draft').filter(':visible').length)&&$('button.editor-post-publish-button').length){RvyRecaptionElement('button.editor-post-publish-button',caption);}}
var RvyDetectPublishOptionsDivClosureInterval='';var RvyDetectPublishOptionsClosure='';var RvyDetectPublishOptionsDiv=function(){if($('div.components-modal__header').length){clearInterval(RvyDetectPublishOptionsDivInterval);if($('span.pp-recaption-button').first()){rvyObjEdit.overrideColor=$('span.pp-recaption-button').first().css('color');}
RvyDetectPublishOptionsClosure=function(){if(!$('div.components-modal__header').length){clearInterval(RvyDetectPublishOptionsDivClosureInterval);$('span.pp-recaption-button').hide();RvyInitInterval=setInterval(RvyInitializeBlockEditorModifications,50);RvyDetectPublishOptionsDivInterval=setInterval(RvyDetectPublishOptionsDiv,1000);}}
RvyDetectPublishOptionsDivClosureInterval=setInterval(RvyDetectPublishOptionsClosure,200);}}
var RvyDetectPublishOptionsDivInterval=setInterval(RvyDetectPublishOptionsDiv,1000);if(typeof rvyObjEdit.publish=='undefined'){rvyObjEdit.publishCaptionCurrent=rvyObjEdit.updateCaption;}else{rvyObjEdit.publishCaptionCurrent=rvyObjEdit.publish;}
var RvyInitializeBlockEditorModifications=function(){if(($('button.editor-post-publish-button').length||$('button.editor-post-publish-panel__toggle').length)&&($('button.editor-post-switch-to-draft').length||$('button.editor-post-save-draft').length)){clearInterval(RvyInitInterval);if($('button.editor-post-publish-panel__toggle').length){if(typeof rvyObjEdit.prePublish!='undefined'&&rvyObjEdit.prePublish){RvyRecaptionElement('button.editor-post-publish-panel__toggle',rvyObjEdit.prePublish);}
$(document).on('click','button.editor-post-publish-panel__toggle,span.pp-recaption-prepublish-button',function(){RvySetPublishButtonCaption('',false,true);});}else{if(typeof rvyObjEdit.publish=='undefined'){RvySetPublishButtonCaption(rvyObjEdit.updateCaption,false,true);}else{RvySetPublishButtonCaption(rvyObjEdit.publish,false,true);}}
$('select.editor-post-author__select').parent().hide();$('button.editor-post-trash').parent().show();$('button.editor-post-switch-to-draft').hide();$('div.components-notice-list').hide();}}
var RvyInitInterval=setInterval(RvyInitializeBlockEditorModifications,50);var RvyHideElements=function(){var ediv='div.edit-post-sidebar ';if($(ediv+'div.edit-post-post-visibility,'+ediv+'div.editor-post-link,'+ediv+'select.editor-post-author__select:visible,'+ediv+'div.components-base-control__field input[type="checkbox"]:visible,'+ediv+'button.editor-post-switch-to-draft,'+ediv+'button.editor-post-trash').length){$(ediv+'select.editor-post-author__select').parent().hide();$(ediv+'button.editor-post-trash').parent().show();$(ediv+'button.editor-post-switch-to-draft').hide();$(ediv+'div.editor-post-link').parent().hide();$(ediv+'div.components-notice-list').hide();if(!rvyObjEdit.scheduledRevisionsEnabled){$(ediv+'div.edit-post-post-schedule').hide();}
$(ediv+'#publishpress-notifications').hide();$('#icl_div').closest('div.edit-post-meta-boxes-area').hide();}
if('future'==rvyObjEdit.currentStatus){$('button.editor-post-publish-button').show();}else{if($('button.editor-post-publish-button').length&&($('button.editor-post-save-draft:visible').length||$('button.editor-post-saved-stated:visible').length)){$('button.editor-post-publish-button').hide();}}
if((typeof window.PPCustomStatuses=='undefined')||(typeof rvyObjEdit=='undefined')||(typeof rvyObjEdit['isStatusesPro']=='undefined')||(!rvyObjEdit.isStatusesPro)){if(($('button.editor-post-publish-button').length||$('button.editor-post-publish-panel__toggle').length)&&($('button.editor-post-save-draft').filter(':visible').length||$('.is-saved').filter(':visible').length)){$('button.editor-post-publish-button').hide();$('button.editor-post-publish-panel__toggle').hide();}else{if($('button.editor-post-publish-button').length){$('button.editor-post-publish-button').show();}else{$('button.editor-post-publish-panel__toggle').show();}}}
ediv=null;}
var RvyHideInterval=setInterval(RvyHideElements,50);var RvySubmissionUI=function(){selectedDateHTML=wp.data.select('core/editor').getEditedPostAttribute('date');var selectedDate=new Date(selectedDateHTML);var currentDate=new Date();RvyTimeSelection=selectedDate.getTime()-((currentDate.getTimezoneOffset()*60-rvyObjEdit.timezoneOffset)*1000);if(RvyTimeSelection-currentDate.getTime()>1000){var approveCaption=rvyObjEdit['scheduleCaption'];}else{var approveCaption=rvyObjEdit['approveCaption'];}
if(!$('div.editor-post-schedule__panel-dropdown:visible').length){$('.rvy-creation-ui').remove();}
if($('button.rvy-direct-approve:visible').length){$('button.rvy-direct-approve:visible span.rvy-caption').html(approveCaption);}else{$('button.revision-approve:visible span.rvy-caption').html(approveCaption);}
$('button.edit-post-post-visibility__toggle, div.editor-post-url__panel-dropdown, div.components-checkbox-control').closest("div.editor-post-panel__row").hide();if($('div.edit-post-post-schedule').length){var refSelector='div.edit-post-post-schedule';}else{var refSelector='div.edit-post-post-visibility';if(!$(refSelector).length){if($('div.editor-post-panel__section').length){refSelector='div.editor-post-panel__section';}else{refSelector='div.edit-post-post-status h2';}}}
if(rvyObjEdit.ajaxurl&&!$('div.edit-post-revision-status').length&&$(refSelector).length){if($('div.editor-post-panel__row-label').length){var labelOpen='<div class="editor-post-panel__row-label">';var labelClose='</div>';var statusWrapperClass='editor-post-panel__row-control';}else{var labelOpen='<span>';var labelClose='</span>';var statusWrapperClass='';}
var currentStatusCaption;if(typeof rvyObjEdit[rvyObjEdit.currentStatus+'StatusCaption']!=='undefined'){currentStatusCaption=rvyObjEdit[rvyObjEdit.currentStatus+'StatusCaption'];}else{currentStatusCaption='';}
if(refSelector=='div.editor-post-panel__section'){var rvyUI='';if(statusWrapperClass){statusWrapperClass+=' gutenberg-18-5';rvyUI+='<div class="'+statusWrapperClass+'">';}
rvyUI+='<div class="components-dropdown rvy-current-status">'
+currentStatusCaption
+'</div>';if(statusWrapperClass){rvyUI+='</div>';}
$('div.editor-post-panel__row-control div.editor-post-status').html(rvyUI);}else{var rvyUI='<div class="components-panel__row rvy-creation-ui edit-post-revision-status">'
+labelOpen+rvyObjEdit.statusLabel+labelClose;if(statusWrapperClass){rvyUI+='<div class="'+statusWrapperClass+'">';}
rvyUI+='<div class="components-dropdown rvy-current-status">'
+currentStatusCaption
+'</div>';if(statusWrapperClass){rvyUI+='</div>';}
rvyUI+='</div>';$(refSelector).before(rvyUI);}
if(rvyObjEdit[rvyObjEdit.currentStatus+'ActionURL']){var url=rvyObjEdit[rvyObjEdit.currentStatus+'ActionURL'];}else{var url='javascript:void(0)';}
if(rvyObjEdit[rvyObjEdit.currentStatus+'ActionCaption']){var approveButtonHTML='';var mainDashicon='';if(rvyObjEdit.canPublish&&('pending'!=rvyObjEdit.currentStatus)&&('future'!=rvyObjEdit.currentStatus)){if(!rvyObjEdit.isStatusesPro){approveButtonHTML='<a href="'+rvyObjEdit['pendingActionURL']+'" class="revision-approve">'
+'<button type="button" class="components-button revision-approve is-button is-primary ppr-purple-button rvy-direct-approve">'
+'<span class="dashicons dashicons-yes"></span>'
+'<span class="rvy-caption">'+rvyObjEdit['approveCaption']+'</span></button></a>';}
mainDashicon='dashicons-upload';}else{if('pending'==rvyObjEdit.currentStatus){mainDashicon='dashicons-yes';}else{mainDashicon='dashicons-upload';}}
var rvyPreviewLink='';if(rvyObjEdit[rvyObjEdit.currentStatus+'CompletedLinkCaption']){rvyPreviewLink='<br /><a href="'+rvyObjEdit[rvyObjEdit.currentStatus+'CompletedURL']+'" class="revision-approve revision-preview components-button is-secondary ppr-purple-button" target="pp_revisions_copy">'
+rvyObjEdit[rvyObjEdit.currentStatus+'CompletedLinkCaption']+'</a>';}
if(refSelector=='div.editor-post-panel__section'){divClass=' gutenberg-18-5';}else{divClass='';}
if(!$('div.rvy-creation-ui a.revision-approve').length){$(refSelector).after('<div class="rvy-creation-ui rvy-submission-div'+divClass+'"><a href="'+url+'" class="revision-approve">'
+'<button type="button" class="components-button revision-approve is-button is-primary ppr-purple-button">'
+'<span class="dashicons '+mainDashicon+'"></span>'
+'<span class="rvy-caption">'+rvyObjEdit[rvyObjEdit.currentStatus+'ActionCaption']+'</span></button></a>'
+approveButtonHTML
+'<div class="revision-submitting" style="display: none;">'
+'<span class="revision-approve revision-submitting">'
+rvyObjEdit[rvyObjEdit.currentStatus+'InProcessCaption']+'</span><span class="spinner ppr-submission-spinner" style=""></span></div>'
+'<div class="revision-approving" style="display: none;">'
+'<span class="revision-approve revision-submitting">'
+rvyObjEdit.approvingCaption+'</span><span class="spinner ppr-submission-spinner" style=""></span></div>'
+'<div class="revision-created" style="display: none; margin-top: 15px">'
+'<span class="revision-approve revision-created">'
+rvyObjEdit[rvyObjEdit.currentStatus+'CompletedCaption']+'</span> '
+rvyPreviewLink
+'</div>'
+'</div>');}
$('div.rvy-submission-div').trigger('loaded-ui');}
$('.edit-post-post-schedule__toggle').after('<button class="components-button is-tertiary post-schedule-footnote" disabled>'+rvyObjEdit.onApprovalCaption+'</button>');}
refSelector=null;url=null;approveButtonHTML=null;mainDashicon=null;rvyPreviewLink=null;$('button.post-schedule-footnote').toggle(!/\d/.test($('button.edit-post-post-schedule__toggle').html()));$('button.editor-post-trash').parent().css('text-align','right');}
var RvyUIInterval=setInterval(RvySubmissionUI,100);setInterval(function(){if(rvyObjEdit.deleteCaption&&$('button.editor-post-trash').length&&($('button.editor-post-trash').html()!=rvyObjEdit.deleteCaption)){$('button.editor-post-trash').html(rvyObjEdit.deleteCaption).closest('div').show();}},100);var redirectCheckSaveDoneInterval=false;function rvyDoSubmission(){rvySubmitCopy();}
function rvyDoApproval(){setTimeout(function(){var redirectCheckSaveDoneInterval=setInterval(function(){if(!wp.data.select('core/editor').isSavingPost()||$('div.edit-post-header button.is-saved').length){clearInterval(redirectCheckSaveDoneInterval);if(rvyRedirectURL!=''){setTimeout(function(){window.location.href=rvyRedirectURL;},5000);}}},100);},500);}
$(document).on('click','div.postbox-container,div.acf-postbox,.editor-post-schedule__dialog-toggle',function(){rvyCreationDisabled=true;$('button.revision-approve').prop('disabled','disabled');$('button.revision-schedule').prop('disabled','disabled');$('a.revision-approve').attr('title',rvyObjEdit.actionDisabledTitle);$('a.revision-schedule').attr('title',rvyObjEdit.scheduleDisabledTitle);});var intSaveWatch=setInterval(()=>{if(wp.data.select('core/editor').isSavingPost()){rvyCreationDisabled=false;$('button.revision-approve').prop('disabled',false);$('button.revision-schedule').prop('disabled',false);$('a.revision-approve').attr('title',rvyObjEdit.actionTitle);$('a.revision-schedule').attr('title',rvyObjEdit.scheduleTitle);}},5000);var rvyRedirectURL='';$(document).on('click','button.revision-approve',function(){var isApproval=$(this).hasClass('rvy-direct-approve');var isSubmission=(rvyObjEdit[rvyObjEdit.currentStatus+'ActionURL']=="")&&!isApproval;$('button.revision-approve').hide();if(isApproval){$('div.revision-approving').show().css('display','block');$('div.revision-approving span.ppr-submission-spinner').css('visibility','visible');if(wp.data.select('core/editor').isEditedPostDirty()){wp.data.dispatch('core/editor').savePost();}
rvyRedirectURL=$('div.rvy-creation-ui button.rvy-direct-approve').closest('a').attr('href');if(rvyRedirectURL==''){rvyRedirectURL=$('div.rvy-creation-ui button.revision-approve').closest('a').attr('href');}}else{rvyRedirectURL=$('div.rvy-creation-ui a').attr('href');}
if(isSubmission){$('div.revision-submitting').show();rvyDoSubmission();}else{rvyDoApproval();}
isApproval=null;isSubmission=null;return false;});function RvyGetRandomInt(max){return Math.floor(Math.random()*max);}
function rvySubmitCopy(){var revisionaryCreateDone=function(){if(wp.data.select('core/editor').isEditedPostDirty()){wp.data.dispatch('core/editor').savePost();}
$('.revision-approve').hide();$('div.revision-submitting').hide();$('.revision-created').show();rvyObjEdit.currentStatus='pending';$('.rvy-current-status').html(rvyObjEdit[rvyObjEdit.currentStatus+'StatusCaption']);$('a.revision-preview').attr('href',rvyObjEdit[rvyObjEdit.currentStatus+'CompletedURL']).show();$('a.revision-edit').attr('href',rvyObjEdit[rvyObjEdit.currentStatus+'CompletedEditURL']).show();if((typeof PPCustomStatuses!='undefined')&&(typeof PPCustomStatuses.statusRestProperty!='undefined')){var ret=new Object();ret[PPCustomStatuses.statusRestProperty]='pending-revision';dispatch('core/editor').editPost(ret);}}
$.ajax({url:rvyObjEdit.ajaxurl,data:{'rvy_ajax_field':rvyObjEdit[rvyObjEdit.currentStatus+'AjaxField'],'rvy_ajax_value':wp.data.select('core/editor').getCurrentPostId(),'nc':RvyGetRandomInt(99999999)},dataType:"html",success:revisionaryCreateDone,error:function(data,txtStatus){$('div.rvy-creation-ui').html(rvyObjEdit[rvyObjEdit.currentStatus+'ErrorCaption']);}});}
var RvyRecaptionSaveDraft=function(){if($('button.editor-post-save-draft:not(.rvy-recaption)').length){RvyRecaptionElement('button.editor-post-save-draft:not(.rvy-recaption)',rvyObjEdit.saveRevision);$('button.editor-post-save-draft:not(.rvy-recaption)').addClass('rvy-recaption').removeClass('is-tertiary').addClass('is-primary').addClass('ppr-purple-button');}
if(rvyObjEdit.viewURL&&($('.editor-preview-dropdown__toggle').length||$('div.block-editor-post-preview__dropdown').length)){var viewPreviewLink='<a href="'
+rvyObjEdit.viewURL
+'" target="pp_revisions_copy" role="menuitem" class="ppr-purple-button components-button is-primary editor-preview-dropdown__button-external">'
+rvyObjEdit.viewTitle
+'</a>';if(rvyObjEdit.viewTitleExtra&&!$('.rvy-revision-preview').length){if($('.editor-preview-dropdown__toggle').length){$('.editor-preview-dropdown__toggle').after('<button class="components-button rvy-revision-preview" type="button">'+viewPreviewLink+'</button>');}else{$('div.block-editor-post-preview__dropdown').after('<div><button class="components-button rvy-revision-preview" type="button">'+viewPreviewLink+'</button></div>');}
$('button.rvy-revision-preview a.ppr-purple-button').html(rvyObjEdit.viewTitleExtra);}
if(!$('div.components-menu-group div a.ppr-purple-button').length){if($('.editor-preview-dropdown__button-external svg').length){$('.editor-preview-dropdown__button-external:not(.ppr-purple-button)').closest('div.components-dropdown-menu__menu').after('<div class="components-menu-group"><div role="group">'+viewPreviewLink+'</div></div>');}}
if(rvyObjEdit.viewCaption){RvyRecaptionElement('.editor-preview-dropdown__toggle',rvyObjEdit.viewCaption);$('.editor-preview-dropdown__toggle:not(.ppr-purple-button)').removeClass('is-tertiary').addClass('is-secondary').addClass('ppr-purple-button');}}
if(rvyObjEdit.viewTitle){$('div.edit-post-header__settings a.rvy-post-preview').attr('title',rvyObjEdit.viewTitle);}
if(rvyObjEdit.revisionEdits&&$('div.edit-post-sidebar a.editor-post-last-revision__title:visible').length&&!$('div.edit-post-sidebar a.editor-post-last-revision__title.rvy-recaption').length){$('div.edit-post-sidebar a.editor-post-last-revision__title').html(rvyObjEdit.revisionEdits);$('div.edit-post-sidebar a.editor-post-last-revision__title').addClass('rvy-recaption');}
newPreviewItem=null;}
var RvyRecaptionSaveDraftInterval=setInterval(RvyRecaptionSaveDraft,100);});