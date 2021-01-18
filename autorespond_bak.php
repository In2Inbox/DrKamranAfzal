<?php

/* Beginning of code required to verify webhook */
 if (isset($_GET['msg'])) {
	$msg = $_GET['msg'];
	$value = hash_hmac( 'sha256', "$msg", 'drchrono_lionsoft' );
	echo json_encode( array("secret_token" => "$value") );
	die();
}
/* end webhook verify code */

// Instantiate objects from classes
require_once 'dctesting.php'; // ONLY unremark when testing
require_once 'dc2keap.php';
require_once 'src/isdk.php';
$dc=new dc2keapObj($json);

// retrieve inputs from drchrono webhook
$event=$_SERVER['HTTP_X_DRCHRONO_EVENT'];
$method=$_SERVER['REQUEST_METHOD'];
if ($json==='') $json=file_get_contents('php://input');

/* check that method is a post action otherwise halt */
if ($method!=='POST') die();

/* Initialize all variables */
$dcId=0;

/**
 * Take all inputs and based on event type take appropriate action
 */
// setup for tagging
$tid=$dc->tagByNameExists($event);
if (!$tid) {
	$tid=$dc->createTag($event);
}

function addCon($first, $middle, $last, $nickname, $email, $phone1type, $phone1, $phone2type, $phone2,
                $phone3type, $phone3, $streetaddress1, $city, $state, $postalcode, $dob) {
	global $dc;
	
	$cid=$dc->keap->addWithDupCheck(array(
		'FirstName'=>$first,
		'MiddleName'=>$middle,
		'LastName'=>$last,
		'Nickname'=>$nickname,
		'Email'=>$email,
		'Phone1Type'=>$phone1type,
		'Phone2Type'=>$phone2type,
		'Phone3Type'=>$phone3type,
		'Phone1'=>$phone1,
		'Phone2'=>$phone2,
		'Phone3'=>$phone3,
		'StreetAddress1'=>$streetaddress1,
		'City'=>$city,
		'State'=>$state,
		'PostalCode'=>$postalcode,
		'Birthday'=>$dob
	), 'Email');
	$dc->keap->dsUpdate('Contact', $cid, array('_DrChronoId1'=>$dc->obj->object->id));
	return $cid;
}

// handle whatever event trigger has been sent for
if ($event==='PATIENT_CREATE') {
	$cid=$dc->keapContactAdd();
	$dc->keap->grpAssign($cid, $tid);
} elseif ($event==='PATIENT_MODIFY') {
	$obj=$dc->getObj();
	$con=$dc->getContactByDCId($obj->object->id);
	if (!empty($con)) { // patient exists already... update
		$cid=$dc->keapContactAdd();
		$dc->keap->grpAssign($cid, $tid);
	} else { // patient is new (doesn't exist in IS/Keap yet)
		$cid=$dc->keapContactAdd();
		$tid=$dc->tagByNameExists('PATIENT_CREATE');
		$dc->keap->grpAssign($cid, $tid);
	}
} elseif ($event==='APPOINTMENT_CREATE') {
	$obj=$dc->getObj();
	// does contact exist in keap?
	$rec=$dc->getContactByDCId($obj->object->patient);
	// yes, exists, update contact
	if (!empty($rec)) {
		$cid=addCon($con[0]['FirstName'], $con[0]['MiddleName'], $con[0]['LastName'], $con[0]['Nickname'], $con[0]['Email'],
			'Home', $con[0]['Phone1'], 'Mobile', $con[0]['Phone2'], 'Work',
			'office_phone', $con[0]['Phone3'], $con[0]['City'], $con[0]['State'], $con[0]['Postalcode'], $con[0]['Birthday']);
		$dc->keap->dsUpdate('Contact', $cid, array('_DrChronoId1'=>$con[0]['_DrChronoId1']));
		$tid=$dc->tagByNameExists('PATIENT_MODIFY');
		$dc->keap->grpAssign($cid, $tid);
	// no, does not exist, create a new contact
	} else {
		$con = $dc->getDCPatientById( $obj->object->patient );
		$cid=addCon($con->first_name, $con->middle_name, $con->last_name, $con->nick_name, $con->email,
			'Home', $con->home_phone, 'Mobile', $con->cell_phone, 'Work',
			$con->office_phone, $con->address, $con->city, $con->state, $con->zip_code, $con->date_of_birth);
		$dc->keap->dsUpdate('Contact', $cid, array('_DrChronoId1'=>$con->id));
		$tid=$dc->tagByNameExists('PATIENT_CREATE');
		$dc->keap->grpAssign($cid, $tid);
	}
	if (isset($con->disable_sms_messages)) {
		if ($con->disable_sms_messages) {
			$tid = $dc->tagByNameExists( 'DrChrono Disable SMS Messaging' );
		} else {
			$tid = $dc->tagByNameExists( 'DrChrono Enable SMS Messaging' );
		}
		$dc->keap->grpAssign($cid, $tid);
	}
} elseif ($event==='APPOINTMENT_MODIFY') {
	$obj=$dc->getObj();
	// does contact exist in keap?
	$rec=$dc->getContactByDCId($obj->patient);
	// yes, exists, update contact
	if ($rec) {
		$cid=addCon($con[0]['FirstName'], $con[0]['MiddleName'], $con[0]['LastName'], $con[0]['Nickname'], $con[0]['Email'],
			'Home', $con[0]['Phone1'], 'Mobile', $con[0]['Phone2'], 'Work',
			'office_phone', $con[0]['Phone3'], $con[0]['City'], $con[0]['State'], $con[0]['Postalcode'], $con[0]['Birthday']);
		$dc->keap->dsUpdate('Contact', $cid, array('_DrChronoId1'=>$con[0]['_DrChronoId1']));
		$dc->keap->optIn($con->email);
		$tid=$dc->tagByNameExists('PATIENT_MODIFY');
		$dc->keap->grpAssign($cid, $tid);
		// no, does not exist, create a new contact
	} else {
		$con = $dc->getDCPatientById( $obj->patient );
		$cid=addCon($con[0]['FirstName'], $con[0]['MiddleName'], $con[0]['LastName'], $con[0]['Nickname'], $con[0]['Email'],
			'Home', $con[0]['Phone1'], 'Mobile', $con[0]['Phone2'], 'Work',
			'office_phone', $con[0]['Phone3'], $con[0]['City'], $con[0]['State'], $con[0]['Postalcode'], $con[0]['Birthday']);
		$dc->keap->dsUpdate('Contact', $cid, array('_DrChronoId1'=>$con[0]['_DrChronoId1']));
		$dc->keap->optIn($con->email);
		$tid=$dc->tagByNameExists('PATIENT_CREATE');
		$dc->keap->grpAssign($cid, $tid);
	}
	if ($obj->disable_sms_messages) {
		$tid=$dc->tagByNameExists('Disable SMS Messaging');
	} else {
		$tid=$dc->tagByNameExists('Enable SMS Messaging');
	}
	$dc->keap->grpAssign($cid, $tid);
} elseif ($event==='APPOINTMENT_DELETE') {
	$obj=$dc->getObj();
	// does contact exist in keap?
	$rec=$dc->getContactByDCId($obj->patient);
	// yes, exists, update contact
	if ($rec) {
		$cid=addCon($con[0]['FirstName'], $con[0]['MiddleName'], $con[0]['LastName'], $con[0]['Nickname'], $con[0]['Email'],
			'Home', $con[0]['Phone1'], 'Mobile', $con[0]['Phone2'], 'Work',
			'office_phone', $con[0]['Phone3'], $con[0]['City'], $con[0]['State'], $con[0]['Postalcode'], $con[0]['Birthday']);
		$dc->keap->dsUpdate('Contact', $cid, array('_DrChronoId1'=>$con[0]['_DrChronoId1']));
		$dc->keap->optIn($con->email);
		$tid=$dc->tagByNameExists('PATIENT_MODIFY');
		$dc->keap->grpAssign($cid, $tid);
		// no, does not exist, create a new contact
	} else {
		$con = $dc->getDCPatientById( $obj->patient );
		$cid=addCon($con[0]['FirstName'], $con[0]['MiddleName'], $con[0]['LastName'], $con[0]['Nickname'], $con[0]['Email'],
			'Home', $con[0]['Phone1'], 'Mobile', $con[0]['Phone2'], 'Work',
			'office_phone', $con[0]['Phone3'], $con[0]['City'], $con[0]['State'], $con[0]['Postalcode'], $con[0]['Birthday']);
		$dc->keap->dsUpdate('Contact', $cid, array('_DrChronoId1'=>$con[0]['_DrChronoId1']));
		$dc->keap->optIn($con->email);
		$tid=$dc->tagByNameExists('PATIENT_CREATE');
		$dc->keap->grpAssign($cid, $tid);
	}
	if ($obj->disable_sms_messages) {
		$tid=$dc->tagByNameExists('Disable SMS Messaging');
	} else {
		$tid=$dc->tagByNameExists('Enable SMS Messaging');
	}
	$dc->keap->grpAssign($cid, $tid);
} else {
	$result=false;
}