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
	postL10n.update = "<?php _e('Update Revision', 'revisionary' )?>";
	postL10n.saveDraft = "<?php _e('Update Revision', 'revisionary' )?>";
});
/* ]]> */
</script>
    <?php
	}
}
