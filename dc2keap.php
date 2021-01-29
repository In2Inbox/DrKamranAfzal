<?php

// require_once 'dctesting.php'; // ONLY unremark this for testing
require_once 'src/isdk.php';
require_once 'LogFileClass.php';
require_once 'OAuth/tokens.php';
require_once 'constants.inc';

class dc2keapObj {
	
	// Debug/logging/testing details
	private $token='';
	public $log=NULL;
	public $testing=FALSE;
	private $debug=FALSE;
	public $logging=TRUE;
	
	// Infusionsoft/Keap details
	//      Testing credentials
	private $appName='';
	private $appKey='';
	//      Live (client) credentials
	// private $appName='';
	// private $appKey='';
	public $keap=NULL;
	private $customFields=array();

	// Webhook details
	private $signature; // security signature from dr chrono
	private $event; // what type of event (patient create, appointment modify etc
	private $json; // the json body sent as a result
	public $obj; // the object converted json body
	private static $error; // the last error number
	private static $errMsg; // the last error message
	private static $messages=array // enumerate the error messages into an array
	(
		444=>'Invalid signature received.',
		445=>'Invalid event.',
		446=>'Expected JSON body but received none/empty.',
		447=>'Error creating Keap contact.',
		448=>'Error applying custom field (drchrono id)',
		449=>'Error creating Keap app object.',
		450=>'Keap generated exception while initializing application object.',
		451=>'Internal script exception while gathering custom fields.'
	);
	
	function __construct($json='') { // if $json included then it will be used for testing instead
		global $tokens, $keapTestAPIKey, $keapTestAppName, $patientTestDataFile, $DCSignature, $debugFile, $keapLiveAPIKey, $keapLiveAppName;
		if ($this->logging) {
			$this->log=new LogFileClass($debugFile);
			if ($this->testing) {
				$this->log->lfWriteLn('***************** BEGIN TEST LOGGING *****************');
			} else {
				$this->log->lfWriteLn('***************** BEGIN LIVE LOGGING *****************');
			}
		}
		if ($this->testing) {
			$this->appName = $keapTestAppName;
			$this->appKey = $keapTestAPIKey;
		} else {
			$this->appName = $keapLiveAppName;
			$this->appKey = $keapLiveAPIKey;
		}
		$this->token=$tokens->getAccessToken();
		$this->signature=$_SERVER['HTTP_X_DRCHRONO_SIGNATURE'];
		if ($this->signature!==$DCSignature) {
			$this->setError(444);
			return FALSE; // false if not signed
		}
		$this->event=$_SERVER['HTTP_X_DRCHRONO_EVENT'];
		if (($this->event!=='PATIENT_MODIFY') &&
			($this->event!=='PATIENT_CREATE') &&
			($this->event!=='APPOINTMENT_CREATE') &&
			($this->event!=='APPOINTMENT_DELETE') &&
			($this->event!=='APPOINTMENT_MODIFY')) {
			$this->setError(445);
			return FALSE; // false if none of the triggers are correct
		}
		$json='';
		if ($this->testing) $json=file_get_contents($patientTestDataFile);
		if (!$json=='') $this->json=$json; else $this->json=file_get_contents('PHP://input');
		if ($this->json=='') {
			$this->setError(446);
			return FALSE; // false if no json body
		}
		$this->obj=json_decode($this->json);
		if ($this->logging) $this->log->lfWriteLn('raw json received = '.$this->json);
		
		$this->token=$tokens->getAccessToken();
		try {
			$this->keap = new iSDK();
			$this->keap->cfgCon( $this->appName, $this->appKey );
		} catch (iSDKException $e) {
			$this->setError(450);
			if ($this->logging) $this->log->lfWriteLn('Error 450 while creating Keap object');
			throw new iSDKException('Keap Exception : '.$e->getTraceAsString());
		}
		if (!is_object($this->keap)) {
			$this->setError(449);
			if ($this->logging) $this->log->lfWriteLn('Error 449 while creating Keap object');
			return false;
		}
		if ($this->logging) $this->log->lfWriteLn('Data/Error checks ... Passed!');
		return TRUE;
	}
	
	function __destruct() {
		$e=NULL;
		try {
			// MUST destroy memory contents to be HIPPA compliant
			$this->signature = $this->chrcpy( 0, strlen( $this->signature ) );
			$this->event = $this->chrcpy( 0, strlen( $this->event ) );
			$this->json = $this->chrcpy( 0, strlen( $this->json ) );
			$this->obj = $this->chrcpy( 0, sizeof( array($this->obj) ) );
			self::$messages = $this->chrcpy( 0, sizeof( array(self::$messages) ) );
			$this->keap = $this->chrcpy( 0, sizeof( array($this->keap) ) );
		} catch (Exception $e) {
			echo 'Exception '.$e->getCode().' on line '.$e->getLine().chr(10);
			echo 'TRACE = '.$e->getTraceAsString().chr(10);
			if ($this->logging) {
				$this->log->lfWriteLn( '__destruct error clearing data.' );
				$this->log->lfWriteLn($e->getTraceAsString());
			}
		}
		if ($this->logging) $this->log->lfWriteLn('END LOGGING **************************************');
	}
	
	/* custom field related functions */
	private function getHeaderId($cfLabel) {
		$hid=$this->keap->dsQuery('DataFormGroup', 1, 0, array('Name'=>$cfLabel),
		array('Id','Name','TabId'));
		return $hid[0]['Id'];
	}
	
	private function cfExists($cfLabel) {
		$cfs=$this->customFields;
		foreach ($cfs as $cf) {
			if ($cf['Label']===$cfLabel) return TRUE;
		}
		return FALSE;
	}
	
	private function createCF($cfLabel) {
		$hid=$this->getHeaderId('Custom Fields');
		$result=$this->keap->addCustomField('Contact', $cfLabel, 15, $hid);
		return $result;
	}
	
	private function getCFDBName($cfLabel) {
		$exists=false;
		$cfs=$this->customFields;
		foreach ($cfs as $cf) {
			if ($cf['Label']===$cfLabel) return $cf['Name'];
		}
		return FALSE;
	}
	
	private function getCFByLabel($label) {
		$cfs=$this->customFields;
		foreach ($cfs as $cf) {
			if ($cf['Label']===$label) return $cf;
		}
		return FALSE;
	}
	
	private function getCFields() {
		try {
			$fields = $this->keap->dsQuery(
				'DataFormField', 1000, 0, array('Id' => '%'),
				array('Id', 'DataType', 'DefaultValue', 'FormId', 'GroupId', 'Label', 'ListRows',
					'Name', 'Values') );
		} catch (Exception $e) {
			self::setError(451);
			$this->log->lfWriteLn('Internal script exception while gathering custom fields.');
			return FALSE;
		} finally {
			$this->customFields=$fields;
			return TRUE;
		}
	}
	
	function customFields() {
		return $this->customFields;
	}
	
	/* class/object related functions */
	function getSignature()
	{
		// return signature value
		return $this->signature;
	
	}
	
	function getEvent() {
		// return event value
		return $this->event;
	}
	
	function getJSON() {
		// return json value
		return $this->json;
	}
	
	function getObj() {
		// return obj value (json converted to object)
		return $this->obj;
	}
	
	private function chrcpy($val, $mul) {  // $val can be either an ascii int or a string character
		// copy a single character ($val by chr or int), $mul times/long
		$chr=NULL;
		if (is_integer($val)) $chr=chr($val);
		if (is_string($val)) $chr=$val[0];
		$st='';
		for ($x=1;$x<=$mul;$x++) {
			$st.=$chr;
		}
		return $st;
	}
	
	/* error related functions */
	private static function setError($errNum) {
		// a shorthand to set the error params
		self::$error=$errNum;
		self::$errMsg=self::$messages[$errNum];
	}
	
	function getLastError() {
		// get the last error number
		return self::$error;
	}
	
	function getLastErrorMessage() {
		// get the last error message
		return self::$errMsg;
	}
	
	function getDCPatientById($id) {
		$curl = curl_init();
		
		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://app.drchrono.com/api/patients/$id",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
				"Authorization: Bearer $this->token"
			),
		));
		
		$response = curl_exec($curl);
		$obj=json_decode($response);
		curl_close($curl);
		return $obj;
	}
	
	/* Contact related functions */
	function keapContactAdd($id) {
		$con=$this->getContactByDCId($id);
		if (count($con)>0) $cid=$con[0]['Id']; else $cid=false;
		if ($cid) { // contact exists so update
			$this->keap->dsUpdate('Contact', $cid, array(
				'FirstName' => $this->obj->object->first_name,
				'LastName' => $this->obj->object->last_name,
				'Email' => $this->obj->object->email,
				'Phone1Type' => 'Home',
				'Phone2Type' => 'Mobile',
				'Phone3Type' => 'Work',
				'Phone1' => $this->obj->object->home_phone,
				'Phone2' => $this->obj->object->cell_phone,
				'Phone3' => $this->obj->object->office_phone,
				'StreetAddress1' => $this->obj->object->address,
				'City' => $this->obj->object->city,
				'State' => $this->obj->object->state,
				'PostalCode' => $this->obj->object->zip_code,
				'Birthday' => $this->obj->object->date_of_birth,
				'MiddleName' => $this->obj->object->middle_name,
				'Nickname' => $this->obj->object->nick_name
			));
		} else { // contact does NOT exist so create new
			$cid=$this->keap->dsAdd('Contact', array(
				'FirstName' => $this->obj->object->first_name,
				'LastName' => $this->obj->object->last_name,
				'Email' => $this->obj->object->email,
				'Phone1Type' => 'Home',
				'Phone2Type' => 'Mobile',
				'Phone3Type' => 'Work',
				'Phone1' => $this->obj->object->home_phone,
				'Phone2' => $this->obj->object->cell_phone,
				'Phone3' => $this->obj->object->office_phone,
				'StreetAddress1' => $this->obj->object->address,
				'City' => $this->obj->object->city,
				'State' => $this->obj->object->state,
				'PostalCode' => $this->obj->object->zip_code,
				'Birthday' => $this->obj->object->date_of_birth,
				'MiddleName' => $this->obj->object->middle_name,
				'Nickname' => $this->obj->object->nick_name
			));
		}
		$this->keap->optIn($this->obj->object->email);
		if ($this->logging) $this->log->lfWriteLn('Create/Update contact result = '.$cid);
		if (!is_integer($cid)) {
			$this->setError(447);
			if ($this->logging) $this->log->lfWriteLn('Error 447 creating/updating contact');
			return false;
		}
		$cf=$this->keap->dsUpdate('Contact', $cid, array('_DrChronoId1'=>$id));
		if (!is_integer($cf)) {
			$this->setError(448);
			if ($this->logging) $this->log->lfWriteLn('Error 448 updating dr chrono id');
			return false;
		}
		return $cid;
	}
	
	// cid=IS contact id, date/time/location of appointment
	function addAppointmentFields($cid, $date, $time, $location) {
		// to be used when rec=appointment record
	}
	
	function getContactByDCId($id) {
		return $this->keap->dsQuery('Contact', 1, 0, array('_DrChronoId1'=>$id),
									array('Id', 'FirstName', 'MiddleName', 'LastName', 'Email',
										'Nickname', 'Phone1Type', 'Phone1', 'Phone2Type', 'Phone2',
										'Phone3Type', 'Phone3', 'StreetAddress1', 'City', 'State',
										'PostalCode', 'Birthday', '_DrChronoId1'));
	}
	
	/* tag related functions */
	function tagByNameExists($name) {
		$tag = $this->keap->dsQuery('ContactGroup', 1, 0, array('GroupName'=>$name),
		array('Id', 'GroupName', 'GroupDescription', 'GroupCategoryId'));
		if (is_array($tag)) {
			if (count($tag)==1) {
				return $tag[0]['Id'];
			} else {
				return FALSE;
			}
		} else {
			return FALSE;
		}
	}
	
	function getTagByName($name) {
		$tag = $this->keap->dsQuery('ContactGroup', 1, 0, array('GroupName'=>$name),
			array('Id', 'GroupName', 'GroupDescription', 'GroupCategoryId'));
		if (is_array($tag)) {
			if (count($tag)==1) {
				return $tag[0];
			} else {
				return FALSE;
			}
		} else {
			return FALSE;
		}
	}
	
	function createTag($name) {
		return $this->keap->dsAdd('ContactGroup', array('GroupName'=>$name,
			'GroupDescription'=>'Created by drchrono integration', 'GroupCategoryId'=>0));
	}

	function getOfficeById($officeId) {
		$curl = curl_init();
		
		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://app.drchrono.com/api/offices/$officeId",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'GET',
			CURLOPT_HTTPHEADER => array(
				"Authorization: Bearer $this->token"
			),
		));
		
		$response = curl_exec($curl);
		curl_close($curl);
		$this->log->lfWriteLn('office json = '.$response);
		return json_decode($response);
	}
	
}

$dc2k=new dc2keapObj();
/*$con=$dc2k->getDCPatientById('90999991');*/
//$con=$dc2k->getOfficeById('300585');
//var_export($con);