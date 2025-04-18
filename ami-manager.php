<?php

use Morilog\Jalali\Jalalian;

require 'vendor/autoload.php'; // Add this line to include Composer's autoloader
require 'ami-connection.php'; // Connect AMI And String Proccesing

function parse_ami_event($data) {
    $event_info = [];

    // Split the data into lines
    $lines = explode("\n", $data);
    foreach ($lines as $line) {
        if (strpos($line, "CallerIDName") !== false) {
            $event_info['CallerIDName'] = trim(str_replace("CallerIDName: ", "", $line));
        }
        if (strpos($line, "CallerIDNum") !== false) {
            $event_info['CallerIDNum'] = trim(str_replace("CallerIDNum: ", "", $line));
        }
        if (strpos($line, "ConnectedLineName") !== false) {
            $event_info['ConnectedLineName'] = trim(str_replace("ConnectedLineName: ", "", $line));
            // echo $event_info['ConnectedLineName']; for debug
        }
        if (strpos($line, "ConnectedLineNum") !== false) {
            $event_info['ConnectedLineNum'] = trim(str_replace("ConnectedLineNum: ", "", $line));
        }
        if (strpos($line, "Uniqueid") !== false) {
            $event_info['Uniqueid'] = trim(str_replace("Uniqueid: ", "", $line));
        }
    }

    return !empty($event_info) ? $event_info : null;
}

function process_event($parsed_data, &$events, $db_host, $db_username, $db_password, $db_name, $tbl_name, $log_file) {
    $uniqueid = $parsed_data['Uniqueid'] ?? null;

    if ($uniqueid) {
        if (!isset($events[$uniqueid])) {
            // Initialize a new record for the unique call
            $events[$uniqueid] = [
                'CallerIDName' => null,
                'CallerIDNum' => null,
                'ConnectedLineName' => null,
                'ConnectedLineNum' => null,
                'Uniqueid' => $uniqueid,
            ];
        }

        // Remove "Dest" prefix from all relevant parsed data
        foreach (['CallerIDName', 'CallerIDNum', 'ConnectedLineName', 'ConnectedLineNum'] as $key) {
            if (isset($parsed_data[$key]) && strpos($parsed_data[$key], 'Dest') === 0) {
                $parsed_data[$key] = substr($parsed_data[$key], 4); // Remove "Dest"
            }
        }
        // Merge the parsed data into the existing record
        $events[$uniqueid] = array_merge($events[$uniqueid], array_filter($parsed_data));

        // Check if any column contains "<unknown>"
        if (array_filter($events[$uniqueid], function ($value) {
            return $value === '<unknown>';
        })) {
            file_put_contents($log_file, "Ignoring record due to '<unknown>' value.\n", FILE_APPEND);
            unset($events[$uniqueid]);
            return;
        }

        // Check if all fields are filled
        if (!in_array(null, $events[$uniqueid])) {
            // Save to database and remove from events
            save_event_to_db($events[$uniqueid], $db_host, $db_username, $db_password, $db_name, $tbl_name, $log_file);
            unset($events[$uniqueid]);
        }

    }
}


function notify_users($connectedLineName, $connectedLineNum, $callerIDName, $callerIDNum) {
    $url = 'node js notify address';
    
    $event_data = [
        'ConnectedLineName' => $connectedLineName,
        'ConnectedLineNum' => $connectedLineNum,
        'CallerIDName' => $callerIDName,
        'CallerIDNum' => $callerIDNum,
    ];
    
    $options = [
        'http' => [
            'header' => "Content-type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($event_data),
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ];
    
    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    // Add debug log
    file_put_contents('notify_log.txt', "Notify result: $result\nEvent data: " . print_r($event_data, true) . "\n", FILE_APPEND);
}

function save_event_to_db($event_data, $db_host, $db_username, $db_password, $db_name, $tbl_name, $log_file) {
    file_put_contents($log_file, print_r($event_data, true) . "\n", FILE_APPEND);

    $mysqli = new mysqli($db_host, $db_username, $db_password, $db_name);

    if ($mysqli->connect_error) {
        file_put_contents($log_file, "Connection failed: " . $mysqli->connect_error . "\n", FILE_APPEND);
        die("Connection failed: " . $mysqli->connect_error);
    }

    $callerIDNum = $event_data['CallerIDNum'];
    $connectedLineName = $event_data['ConnectedLineName'];
    $connectedLineNum = $event_data['ConnectedLineNum'];
    $uniqueid = $event_data['Uniqueid'];
    date_default_timezone_set('Asia/Tehran');
    $current_time = date("H:i:s");

    // Convert Gregorian date to Jalali date
    $jalali_date = Jalalian::fromDateTime(date("Y-m-d"))->format('Y-m-d');


    $type = (strlen($connectedLineNum) < 4) ? "خروجی" : "ورودی";

    if (strlen($callerIDNum) < 4 && strlen($connectedLineNum) < 4 ) {
	    $type = "داخلی";
    }

    if ($type === "خروجی" && strpos($callerIDNum, '9') === 0) {
         $callerIDNum = substr($callerIDNum, 1);
    }

    // Check if Uniqueid already exists
    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM $tbl_name WHERE Call_ID = ?");
    $stmt->bind_param("s", $uniqueid);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    if ($count > 0) {
        file_put_contents($log_file, "Record with Uniqueid $uniqueid already exists. Skipping insertion.\n", FILE_APPEND);
        return;
    }

    // Get the firstname from tblcontacts based on phonenumber
    if ($type === "ورودی") {
        $connectedLineName = get_caller_id_name($mysqli, $connectedLineNum);
        $callerIDName = $event_data['CallerIDName'];
    } else if ($type === "خروجی") {
        $callerIDName = get_caller_id_name($mysqli, $callerIDNum);
    } else {
    	$callerIDName = $event_data['CallerIDName'];
    }



    // Insert the new record
    $stmt = $mysqli->prepare("INSERT INTO $tbl_name (Call_ID, Type, S_Name, S_Number, D_Name, D_Number, Duration, Time, Date)
                              VALUES (?, ?, ?, ?, ?, ?, 'درج نشده', ?, ?)");
    $stmt->bind_param("ssssssss",
        $uniqueid,
        $type,
        $connectedLineName,
        $connectedLineNum,
        $callerIDName,
        $callerIDNum,
        $current_time,
        $jalali_date); // Use Jalali date here

    if ($stmt->execute()) {
        file_put_contents($log_file, "Log inserted successfully.\n", FILE_APPEND);
    } else {
        file_put_contents($log_file, "Error inserting log: " . $stmt->error . "\n", FILE_APPEND);
    }

    // Notify users 
    notify_users($callerIDName, $callerIDNum, $connectedLineName, $connectedLineNum);
}


function get_caller_id_name($mysqli, $number) {
        $stmt = $mysqli->prepare("SELECT firstname, lastname FROM tblcontacts WHERE phonenumber = ?");
        $stmt->bind_param("s", $number);
        $stmt->execute();
        $stmt->bind_result($firstname, $lastname);
        $stmt->fetch();
        $stmt->close();

        if ($firstname && $lastname) {
            return $firstname . ' ' . $lastname; 
        } else {
        $stmt = $mysqli->prepare("SELECT company FROM tblclients WHERE phonenumber = ?");
        $stmt->bind_param("s", $number);
        $stmt->execute(); $stmt->bind_result($company);
        $stmt->fetch(); $stmt->close();
        return $company ? $company : "ناشناس"; // Return the company name or "درج نشده" if not found
        }   
}


?>

