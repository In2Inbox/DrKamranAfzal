<?php
require_once 'constants.inc';

date_default_timezone_set("$timezone");

class oauthClass {

	private $clientId;
	private $clientSecret;
	public $tokens;
	private $abspath='C:\Users\JohnBorelli\PhpstormProjects\DrKamranAfzal\oauth.json';
	
	function __construct() {
		global $absPath, $clientSecret, $clientId;
		
		$this->abspath=$absPath;
		
		$json=file_get_contents($this->abspath);
		$this->tokens=json_decode($json);
		$this->clientId=$clientId;
		$this->clientSecret=$clientSecret;
		$this->manageRefresh();
	}
	
	function __destruct() {
	
	}

	function getAccessToken() {
		return $this->tokens->access_token;
	}
	
	function getRefreshToken() {
		return $this->tokens->refresh_token;
	}
	
	function getTimestamp() {
		return $this->tokens->expiry;
	}
	
	function manageRefresh() {
		$dt=new DateTime('now');
		$tsnow=date_timestamp_get($dt);
		$tsexpiry=$this->getTimestamp();
		if ($tsnow>=$tsexpiry-300) {
			$curl = curl_init();
			$rt=$this->tokens->refresh_token;
			$at=$this->tokens->access_token;
			curl_setopt_array($curl, array(
				CURLOPT_URL => "https://drchrono.com/o/token/?refresh_token=$rt&grant_type=refresh_token&client_id=$this->clientId&client_secret=$this->clientSecret",
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => 'POST',
				CURLOPT_HTTPHEADER => array(
					"Authorization: Bearer $at"
				)
			));
			
			$response = curl_exec($curl);
			$obj=json_decode($response);
			curl_close($curl);
			$this->tokens->access_token=$obj->access_token;
			$this->tokens->refresh_token=$obj->refresh_token;
			$this->tokens->expiry=$obj->expires_in + $this->getTimestamp();
			$json=json_encode($this->tokens);
			/**$file=fopen('../oauth.json', 'w');
			fputs($file, $json);
			fflush($file);
			fclose($file);**/
			file_put_contents("$this->abspath", $json,);
		}
	}
	
}

$tokens=new oauthClass();