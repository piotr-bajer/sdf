<?php
/**
 * message handler
 */

require_once WP_PLUGIN_DIR . '/sdf/types.php';

function sdf_message_handler($type, $message) {
	// type = (ERROR | SUCCESS | LOG)
	// all messages are written to sdf.log
	// rotated every six months
	// success and error messages are passed as an object to the waiting js
	// the data structure is json, and should be very simple.
	// data.type = error | success
	// data.message = message

	switch($type) {
		case \SDF\MessageTypes::ERROR: $type = 'error'; break;
		case \SDF\MessageTypes::SUCCESS: $type = 'success'; break;
		case \SDF\MessageTypes::LOG: $type = 'log'; break;
	}

	$logmessage = sprintf('%s - %s - %s%s',
			date('D, d M Y H:i:s'), $type, $message, PHP_EOL);

	file_put_contents(WP_PLUGIN_DIR . '/sdf/sdf.log', $logmessage, FILE_APPEND);

	// send data to the requestor
	if($type != 'log') {
		ob_clean();
		$data = array(
			'type' => $type,
			'message' => $message
		);
		echo json_encode($data);
		ob_flush();
		die();
	}
}

function sdf_clean_log() {
	$file = WP_PLUGIN_DIR . '/sdf/sdf.log';
	$handle = fopen($file, 'r+');
	$linecount = 0;

	while(!feof($handle)) {
		$line = fgets($handle);
		$linecount++;
	}
	
	ftruncate($handle, 0);
	rewind($handle);
	fwrite($handle, time() . ' - Cron run. '
			. $linecount . ' lines cleared.' . PHP_EOL);
	
	fclose($handle);
}

function sdf_add_biannual($schedules) {
	$schedules['biannual'] = array(
		'interval' => 10000000,
		'display' => __('Every Six Months')
	);
	return $schedules;
}

add_filter('cron_schedules', 'sdf_add_biannual');
add_action('sdf_biannual_hook', 'sdf_clean_log'); ?>
