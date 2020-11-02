<?php
if( basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME']) )
	die();

class RevisionaryEditRevisionUI {
	function __construct () {
		$this->add_js();
	}
	
	function add_js() {
		?>
<script type="text/javascript">
/* <![CDATA[ */
jQuery(document).ready( function($) {
	if (typeof(postL10n) != 'undefined') {
		postL10n.update = "<?php _e('Update Revision', 'revisionary' )?>";
		postL10n.saveDraft = "<?php _e('Update Revision', 'revisionary' )?>";
	} else {
		setInterval(
			function() {
				if ($('#publish').val() != "<?php _e('Update Revision', 'revisionary' )?>") {
					$('#publish').val("<?php _e('Update Revision', 'revisionary' )?>");
				}
			}
			, 200
		);
	}
});
/* ]]> */
</script>
    <?php
	}
}
