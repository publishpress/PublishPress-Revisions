<?php

function _rvy_mail_check_queue($new_msg = []) {
	if (!$queue = get_option('revisionary_mail_queue')) {
		$queue = [];
		$first_queue = true;
	}

	$new_msg_queued = false;

	if (!$sent_mail = get_option('revisionary_sent_mail')) {
		$sent_mail = [];
		$first_mail_log = true;
	}

	$current_time = time();

	// check sending limits
	$durations = ['minute' => 60, 'hour' => 3600, 'day' => 86400];
	$sent_counts = ['minute' => 0, 'hour' => 0, 'day' => 0];
	
	// by default, purge mail log entries older than 30 days
	$purge_time = apply_filters('revisionary_mail_log_duration', 86400 * 30);
	
	if ($purge_time < $durations['day'] * 2) {
		$purge_time = $durations['day'] * 2;
	}

	if ($use_queue) {
		$default_minute_limit = (defined('REVISIONARY_EMAIL_LIMIT_MINUTE')) ? REVISIONARY_EMAIL_LIMIT_MINUTE : 20;
		$default_hour_limit = (defined('REVISIONARY_EMAIL_LIMIT_HOUR')) ? REVISIONARY_EMAIL_LIMIT_HOUR : 100;
		$default_day_limit = (defined('REVISIONARY_EMAIL_LIMIT_DAY')) ? REVISIONARY_EMAIL_LIMIT_DAY : 1000;

		$send_limits = apply_filters(
			'revisionary_email_limits', 
			[
				'minute' => $default_minute_limit,
				'hour' => $default_hour_limit,
				'day' => $default_day_limit,
			]
		);
	}

	foreach($sent_mail as $k => $mail) {
		if (!isset($mail['time_gmt'])) {
			continue;
		}

		$elapsed = $current_time - $mail['time_gmt'];

		if ($use_queue) {
			foreach($durations as $limit_key => $duration) {
				if ($elapsed < $duration) {
					$sent_counts[$limit_key]++;
				}

				if ($new_msg && ($sent_counts[$limit_key] >= $send_limits[$limit_key])) {
					$new_msg_queued = true;
				}
			}
		}
		
		if ($elapsed > $purge_time) {
			unset($sent_mail[$k]);
			$purged = true;
		}
	}

	if ($new_msg_queued) {
		$queue = array_merge([$new_msg], $queue);
		update_option('revisionary_mail_queue', $queue);
	}

	if (!empty($purged)) {
		update_option('revisionary_sent_mail', $sent_mail);
	}

	if (!empty($first_mail_log) && $sent_mail) {
		global $wpdb;
		$wpdb->query("UPDATE $wpdb->options SET autoload = 'no' WHERE option_name = 'revisionary_sent_mail'");
	}

	if (!empty($first_queue) && $queue) {
		global $wpdb;
		$wpdb->query("UPDATE $wpdb->options SET autoload = 'no' WHERE option_name = 'revisionary_mail_queue'");
	}

	return (object) compact('queue', 'sent_mail', 'send_limits', 'sent_counts', 'new_msg_queued');
}

// called by WP-cron hook
function _rvy_send_queued_mail() {
	$queue_status = rvy_mail_check_queue();

	if (empty($queue_status->queue)) {
		return false;
	}

	$q = $queue_status->queue;

	while ($q) {
		foreach($queue_status->sent_counts as $limit_key => $count) {
			$queue_status->sent_counts[$limit_key]++;

			if ($count > $queue_status->send_limits[$limit_key]) {
				// A send limit has been reached
				break 2;
			}
		}

		$next_mail = array_pop($q);

		// update truncated queue immediately to prevent duplicate sending by another process
		update_option('revisionary_mail_queue', $q);

		// If queued notification is missing vital data, discard it
		if (empty($next_mail['address']) || empty($next_mail['title']) || empty($next_mail['message']) || empty($next_mail['time_gmt'])) {
			continue;
		}

		// If notification was queued more than a week ago, discard it
		if (time() - $next_mail['time_gmt'] > 3600 * 24 * 7 ) {
			continue;
		}

		if (defined('PRESSPERMIT_DEBUG')) {
			pp_errlog('*** Sending QUEUED mail: ');
			pp_errlog($next_mail['address'] . ', ' . $next_mail['title']);
			pp_errlog($next_mail['message']);
		}

		if (defined('RS_DEBUG')) {
			$success = wp_mail($next_mail['address'], $next_mail['title'], $next_mail['message']);
		} else {
			$success = @wp_mail($next_mail['address'], $next_mail['title'], $next_mail['message']);
		}

		if (!$success && defined('REVISIONARY_MAIL_RETRY')) {
			// message was not sent successfully, so put it back in the queue
			if ($q) {
				$q = array_merge([$next_mail], $q);
			} else {
				$q = [$next_mail];
			}
			update_option('revisionary_mail_queue', $q);
		} else {
			// log the sent mail
			$next_mail['time'] = strtotime(current_time( 'mysql' ));
			$next_mail['time_gmt'] = time();
			$next_mail['success'] = intval(boolval($success));

			if (!defined('RS_DEBUG') && !defined('REVISIONARY_LOG_EMAIL_MESSAGE')) {
				unset($next_mail['message']);
			}

			$queue_status->sent_mail[]= $next_mail;
			update_option('revisionary_sent_mail', $queue_status->sent_mail);
		}
	}
}
