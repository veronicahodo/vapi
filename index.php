<?php

/*

index.php

This is basically how you use VAPI. This can actually be dropped in
as the index for your API and you're ready to go.

*/

// Usually we put our DB values in here
require_once('config.php');

require_once('vcrud.php');

// connect to the database
$crud = new Vcrud($DB_USER, $DB_PASS, $DB_HOST, $DB_NAME);

// Set our default error message
$return = [
    'status' => 'error',
    'message' => 'system: unspecified error'
];



// ********************************* MAIN ***********************
if ($unit = $_REQUEST['unit'] ?? false) {
    // Do we even have the unit we're requesting?
    if (!file_exists(htmlspecialchars($unit) . '.php')) {
        $return['message'] = '[main] invalid unit';
    }

    require_once($unit . '.php');

    $unit = ucfirst($unit);
    $api = new $unit($crud);
    $return = $api->process();
} else {
    $return['message'] = 'main: no unit specified';
}

 $crud = null;

// Output whatever kind of response we built
header('Content-Type: application/json');
print(json_encode($return));