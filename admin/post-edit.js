jQuery(document).ready( function($) {
	$('#publish').show();
	
	$('#misc-publishing-actions div.num-revisions').contents().filter(function() {
     if ( typeof(this.nodeValue) != 'undefined' ){ console.debug(this.nodeValue);} return ( this.nodeType == 3 && ( typeof(this.nodeValue) != 'undefined' ) && this.nodeValue.indexOf("Revisions") != -1 ); }).wrap('<span class="rev-caption"></span>');
	 
	//$('#misc-publishing-actions span.rev-caption').html( rvyPostEdit.pubHistoryCaption + '&nbsp;' );
	
	if ( ! $('#timestampdiv a.now-timestamp').length ) {
	 	$('#timestampdiv a.cancel-timestamp').after('<a href="#timestamp_now" class="now-timestamp hide-if-no-js button-now">' + rvyPostEdit.nowCaption + '</a>');
	}
	$('#timestampdiv a.now-timestamp').click(function(){
		var nowDate = new Date();
		var month = nowDate.getMonth() + 1;
		if ( month.toString().length < 2 ) {
			month = '0' + month;
		}
		$('#mm').val(month);
		
		$('#jj').val(nowDate.getDate());
		$('#aa').val(nowDate.getFullYear());
		$('#hh').val(nowDate.getHours());

		var minutes = nowDate.getMinutes();
		if ( minutes.toString().length < 2 ) {
			minutes = '0' + minutes;
		}
		$('#mn').val(minutes);
	});

	// Apply "Schedule Revision" button caption even if post is private
	$('#timestampdiv a.save-timestamp').click( function() {
		if ( ! $('#timestampdiv a.save-timestamp').is(':visible') || ! $('#visibility-radio-private').attr('checked') || $('#publish').val() == postL10n.schedule ) {
			return;
		}
		
		var aa = $('#aa').val(), mm = $('#mm').val(), jj = $('#jj').val(), hh = $('#hh').val(), mn = $('#mn').val();
		var attemptedDate = new Date( aa, mm - 1, jj, hh, mn );
		var currentDate = new Date( $('#cur_aa').val(), $('#cur_mm').val() -1, $('#cur_jj').val(), $('#cur_hh').val(), $('#cur_mn').val() );

		// Confirm valid date
		if ( attemptedDate.getFullYear() == aa && (1 + attemptedDate.getMonth()) == mm && attemptedDate.getDate() == jj && attemptedDate.getMinutes() == mn ) {
			
			// If button caption should be "Schedule Revision," set it. Otherwise, no change
			if ( attemptedDate > currentDate && $('#original_post_status').val() != 'future' ) {
				$('#publish').val( postL10n.schedule );
			}
		}
	} );

});