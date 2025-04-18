// ami-connection.php - Handles AMI connection and event handling
<?php
require 'config.php'; // AMI Conf Import

$socket = fsockopen($ami_host, $ami_port);
fputs($socket, "Action: Login\r\nUsername: $ami_username\r\nSecret: $ami_password\r\n\r\n");

$events = []; // Array to store partial data for ongoing calls
$log_file = 'logs.txt'; // Log file to save logs

while (!feof($socket)) {
    $data = fgets($socket, 128);
    if (empty($data)) continue;

    // Collect full event data until a blank line is found
    $event_data .= $data;

    // Check if we have the complete event (indicated by a blank line)
    if ($data == "\r\n") {
        // Save the complete event data to the log file
        file_put_contents($log_file, $event_data . "\n", FILE_APPEND);

        // Check if this is a "DialBegin" event
        if (strpos($event_data, "Event: DialBegin") !== false) {
            $parsed_data = parse_ami_event($event_data);
            if ($parsed_data) {
                process_event($parsed_data, $events, $db_host, $db_username, $db_password, $db_name, $tbl_name, $log_file);
            }
        }

        // Clear the event data buffer for the next event
        $event_data = '';
    }
}
?>