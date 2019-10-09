<?php
class RvyError {
	private $notices = array();

	function error_notice( $err ) {
		/*
		switch( $err ) {
			case 'old_php' :
				$this->add_notice('Sorry, PublishPress Revisions requires PHP 5.4 or higher. Please upgrade your server or deactivate PublishPress Revisions.');
				break;
			
			default :
		}
		*/
		
		return true;
	}

	function error_box( $msg ) {
		global $pagenow;
		
		if ( isset($pagenow) && ( 'update.php' != $pagenow ) ) {
			$this->add_notice( $msg );
		}
	}

	function add_notice( $body, $args = array() ) {
		if ( ! $this->notices ) {
			add_action( 'all_admin_notices', array( &$this, 'do_notices'), 5 );
		}

		$this->notices[]= (object) array_merge( compact( 'body' ), $args );
	}

	function do_notices() {
		foreach( $this->notices as $msg ) {
			$style = ( ! empty( $msg->style ) ) ? "style='$msg->style'" : "style='color:black'";
			$class = ( ! empty( $msg->class ) ) ? "class='$msg->class'" : '';
			echo "<div id='message' class='error fade' $style $class>" . $msg->body . '</div>';
		}
	}
} // end class
