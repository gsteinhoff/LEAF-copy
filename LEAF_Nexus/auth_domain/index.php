<?php
/************************
    Authenticator
    Date: March 8, 2013

*/

include '../globals.php';
include '../sources/Login.php';
include '../db_mysql.php';
include '../config.php';

$config = new Orgchart\Config();
$db = new DB($config->dbHost, $config->dbUser, $config->dbPass, $config->dbName);

// Enforce HTTPS
if(isset($config->enforceHTTPS) && $config->enforceHTTPS == true) {
	if(!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
		header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
		exit();
	}
}

$login = new Orgchart\Login($db, $db);

if(isset($_SERVER['REMOTE_USER'])) {
	$protocol = 'http://';
	if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
		$protocol = 'https://';
	}
	$redirect = '';
	if(isset($_GET['r'])) {
        $redirect = $protocol . $_SERVER['HTTP_HOST'] . base64_decode($_GET['r']);
    }
    else {
        $redirect = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/../';
    }

    list($domain, $user) = explode("\\", $_SERVER['REMOTE_USER']);

    // see if user is valid
    $vars = array(':userName' => $user);
    $res = $db->prepared_query('SELECT * FROM employee
    										WHERE userName=:userName
												AND deleted=0', $vars);
    
    if(count($res) > 0) {
    	$_SESSION['userID'] = $user;

		header('Location: ' . $redirect);
    }
    else {
    	// try searching through national database
    	$globalDB = new DB(DIRECTORY_HOST, DIRECTORY_USER, DIRECTORY_PASS, DIRECTORY_DB);
    	$vars = array(':userName' => $user);
    	$res = $globalDB->prepared_query('SELECT * FROM employee
											LEFT JOIN employee_data USING (empUID)
											WHERE userName=:userName
    											AND indicatorID = 6
												AND deleted=0', $vars);
    	// add user to local DB
    	if(count($res) > 0) {
    		$vars = array(':firstName' => $res[0]['firstName'],
    				':lastName' => $res[0]['lastName'],
    				':middleName' => $res[0]['middleName'],
    				':userName' => $res[0]['userName'],
    				':phoFirstName' => $res[0]['phoneticFirstName'],
    				':phoLastName' => $res[0]['phoneticLastName'],
    				':domain' => $res[0]['domain'],
    				':lastUpdated' => time());
    		$db->prepared_query('INSERT INTO employee (firstName, lastName, middleName, userName, phoneticFirstName, phoneticLastName, domain, lastUpdated)
        							VALUES (:firstName, :lastName, :middleName, :userName, :phoFirstName, :phoLastName, :domain, :lastUpdated)
    								ON DUPLICATE KEY UPDATE deleted=0', $vars);
    		$empUID = $db->getLastInsertID();

    		if($empUID == 0) {
    			$vars = array(':userName' => $res[0]['userName']);
    			$empUID = $db->prepared_query('SELECT empUID FROM employee
                                                   WHERE userName=:userName', $vars)[0]['empUID'];
    		}

    		$vars = array(':empUID' => $empUID,
    				':indicatorID' => 6,
    				':data' => $res[0]['data'],
    				':author' => 'viaLogin',
    				':timestamp' => time()
    		);
    		$db->prepared_query('INSERT INTO employee_data (empUID, indicatorID, data, author, timestamp)
											VALUES (:empUID, :indicatorID, :data, :author, :timestamp)
    										ON DUPLICATE KEY UPDATE data=:data', $vars);
    			
    		// redirect as usual
    		$_SESSION['userID'] = $res[0]['userName'];

    		header('Location: ' . $redirect);
    	}
    	else {
    		echo 'Unable to log in: User not found in global database.';
    	}
    }
}
else {
    echo 'Unable to log in: Domain logon issue';
}