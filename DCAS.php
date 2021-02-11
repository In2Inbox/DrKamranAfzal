<?php

$get=$_GET;
$json=json_encode($get);
//file_put_contents('json.json', $json);

/* Beginning of code required to verify webhook */
 if (isset($_GET['msg'])) {
	$msg = $_GET['msg'];
	$value = hash_hmac( 'sha256', "$msg", 'drchrono_lionsoft' );
	echo json_encode( array("secret_token" => "$value") );
	die();
}
/* end webhook verify code */

require_once 'dctesting.inc'; // ONLY unremark when testing
require_once 'dc2keap.php';
require_once 'src/isdk.php';
require_once 'constants.inc';
require_once 'LogFileClass.php';

// Instantiate objects from classes
$dcId=0;
$dc=new dc2keapObj();
$jsonLog=new LogFileClass('json.log');

// retrieve inputs from drchrono webhook
$event=$_SERVER['HTTP_X_DRCHRONO_EVENT'];
$method=$_SERVER['REQUEST_METHOD'];
if ($json==='') $json=$dc->getJSON();

/* active log of json inputs */
$jsonLog->lfWriteLn($json);
if ($dc->logging) file_put_contents('json.json', $json);

/* check that method is a post action otherwise halt */
if ($method!=='POST') die();

/* Check that no other excluding factors exist */
// $obj=$dc->getObj();
if (isset($obj->appt_is_break)) {
	if ($obj->appt_is_break) {
		if ($dc->logging) $dc->log->lfWriteLn('Appointment object is a BREAK.  End processing.');
		die();
	}
}

if (isset($obj->patient)) {
	if (is_nan($obj->patient)) {
		if ($dc->logging) $dc->log->lfWriteLn('Patient ID not present.  Cannot continue.');
		die();
	}
}

if (isset($obj->office)) {
	if (is_nan($obj->patient)) {
		if ($dc->logging) $dc->log->lfWriteLn('Office ID not present.  Cannot continue.');
		die();
	}
}

if (isset($obj->doctor)) {
	if (is_nan($obj->doctor)) {
		if ($dc->logging) $dc->log->lfWriteLn('Doctor ID not present.  Cannot continue.');
		die();
	}
}

if (isset($obj->profile)) {
	if (is_nan($obj->profile)) {
		if ($dc->logging) $dc->log->lfWriteLn('Profile ID not present.  Cannot continue.');
		die();
	}
}

/**
 * Take all inputs and, based on event type, take appropriate action
 */
// setup for tagging the event
$tid=$dc->tagByNameExists($event);
if (!$tid) {
	$tid=$dc->createTag($event);
}

// for manually calling the Keap contact add method
function addCon($first, $middle, $last, $nickname, $email, $phone1type, $phone1, $phone2type, $phone2,
                $phone3type, $phone3, $streetaddress1, $city, $state, $postalcode, $dob,
				$conObj) {
	global $dc;
	$check=$dc->getContactByDCId($conObj->id);
	if (!$check) {
		$cid=$dc->keap->dsAdd('Contact',
			array(
				'FirstName' => $first,
				'MiddleName' => $middle,
				'LastName' => $last,
				'Nickname' => $nickname,
				'Email' => $email,
				'Phone1Type' => $phone1type,
				'Phone2Type' => $phone2type,
				'Phone3Type' => $phone3type,
				'Phone1' => $phone1,
				'Phone2' => $phone2,
				'Phone3' => $phone3,
				'StreetAddress1' => $streetaddress1,
				'City' => $city,
				'State' => $state,
				'PostalCode' => $postalcode,
				'Birthday' => $dob
			));
	} else $cid=$check[0]['Id'];
	if ($dc->logging) $dc->log->lfWriteLn('addCon() (dsAdd) result = '.$cid);
	$dc->keap->dsUpdate('Contact', $cid, array('_DrChronoId'=>$conObj->id));
	$dc->keap->optIn($email);
	return $cid;
}

function appointmentFields($dcid, $date, $time, $location) {
	global $dc, $appointmentDateFieldName, $appointmentTimeFieldName, $appointmentLocationFieldName;
	$cid=$dc->getContactByDCId($dcid)[0]['Id'];
	return $dc->keap->dsUpdate('Contact', $cid, array($appointmentDateFieldName=>$date,
		$appointmentTimeFieldName=>$time, $appointmentLocationFieldName=>$location));
}

function demographicFields($chartid) {
	global $dc;
	$cid=$dc->getContactByDCId($dcid);
	return $dc->keap->dsUpdate('Contact', $cid, array($drchronoPatientIdFieldName=>$chartid));
}

$obj=$dc->getObj()->object;

// handle whatever event trigger has been sent for
if ($dc->logging) $dc->log->lfWriteLn('Event reported = '.$event);
if ($event==='PATIENT_CREATE') {
	$cid=$dc->keapContactAdd($obj->id);
} elseif ($event==='PATIENT_MODIFY') {
	$con=$dc->getDCPatientById($obj->id);
	$cid=$dc->keapContactAdd($obj->id);
} elseif (!$obj->appt_is_break) {
	$con=$dc->getDCPatientById($obj->patient);
	$cid=addCon($con->first_name, $con->middle_name, $con->last_name, $con->nick_name,
	$con->email, 'Home', $con->home_phone, 'Mobile', $con->cell_phone,
	'Work', $con->office_phone, $con->address, $con->city, $con->state,
		$con->zip_code, $con->date_of_birth, $con);
}

// Apply event tag
$dc->keap->grpAssign($cid, $tid);
//if ($dc->logging) $dc->log->lfWriteLn('Line 112 values = '.$cid.' (contact) and '.$tid.' (tag)');
/*
 * at this point all contact create/update concerns have been met.
 * focus on tags and fields for appointments
 */
if (($event==='APPOINTMENT_CREATE') ||
	($event==='APPOINTMENT_MODIFY') ||
	($event==='APPOINTMENT_DELETE')) {
	if (($event!=='APPOINTMENT_DELETE') && (!$obj->appt_is_break)) {
		$ofc = $dc->getOfficeById( $obj->office );
		$office = json_decode( $ofc );
		// set appointment fields
		$da = explode( 'T', $obj->scheduled_time );
		$date = $da[0];
		$time = $da[1];
		appointmentFields( $obj->patient, $date, $time, $ofc->name );
		if ($dc->logging) {
			$dc->log->lfWriteLn( 'office json = ' . $ofc );
			$dc->log->lfWriteLn( 'Appointment Fields :' );
			$dc->log->lfWriteLn( '     Patient = ' . $obj->patient );
			$dc->log->lfWriteLn( '     Date = ' . $date );
			$dc->log->lfWriteLn( '     Time = ' . $time );
			$dc->log->lfWriteLn( '     Office/Location = ' . $ofc->name );
		}
	}
	$tid = $dc->tagByNameExists($obj->status);
	if (!$tid) $tid=$dc->createTag($obj->status);
	if ($tid) $dc->keap->grpAssign($cid, $tid);
}

// if appointment is a break (not an appointment) no need to go further
if (isset($obj->appt_is_break)) {
	if (!$obj->appt_is_break) die();
}

// apply ancillary tags here
if (isset($obj->disable_sms_messages)) { // $obj? / $con
	if ($dc->logging) $dc->log->lfWriteLn('SMS field is set = '.$con->disable_sms_messages);
	if ($con->disable_sms_messages) {
		$tid = $dc->tagByNameExists( 'DrChrono Disable SMS Messaging' );
		if (!$tid) $tid=$dc->createTag('DrChrono Disable SMS Messaging');
	} else {
		$tid = $dc->tagByNameExists( 'DrChrono Enable SMS Messaging' );
		if (!$tid) $tid=$dc->createTag('DrChrono Enable SMS Messaging');
	}
	if ($tid) {
		if ($dc->logging) $dc->log->lfWriteLn('SMS messaging related tag applied.');
		$dc->keap->grpAssign( $cid, $tid );
	}
}