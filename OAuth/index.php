<?php
//echo 'line 2';die();
$callbackURL='https://apiroi.net/DrKamranAfzal/OAuth/index.php';
$encodedURL=urlencode($callbackURL);
$clientId='fzhAEZhTPOjoO3MpBONt0bPjvveQUwBb86UNW3bc';
$clientSecret='c3lxfc0I76S3Jzz1CEtFpoHgfIU3hWOGsXc24u0yXimR9ZLQruE7xCAYIOLY7FQKkrgl2tybG90IexyvC3UI2xlB4bpdOr2fboGLXCsHNrXAjoG1qD1WtinjSrN2xjg4';
$scope='user:read user:write calendar:read calendar:write patients:read patients:write patients:summary:read patients:summary:write billing:read billing:write clinical:read clinical:write labs:read labs:write messages:read messages:write settings:read settings:write tasks:read tasks:write';
$state='drchrono_apiroi';
$authURL='https://drchrono.com/o/authorize/?redirect_uri='.$encodedURL.'&response_type=code&client_id='.urlencode($clientId).'&scope='.urlencode($scope);
$urlEncodedURL=urlencode($authURL);
$accessTokenURL='https://drchrono.com/o/token/';

if (!isset($_GET['code'])) { // either first run or access token
	if (isset($_GET['access_token'])) {
		$access_token=$_GET['access_token'];$obj['access_token']=$access_token;
		$refresh_token=$_GET['refresh_token'];$obj['refresh_token']=$refresh_token;
		$expires_in=$_GET['expires_in'];$obj['expires_in']=$expires_in;
		$json=json_encode($obj);
		file_put_contents('.oauth', $json);
		// write the above to a file
	} else {
		header('Location: '.$authURL);
	}
} else {
	$authToken=$_GET['code'];
	$grantType='authorization_code';
	$redirectURI='https://apiroi.net/DrKamranAfzal/OAuth/index.php';
	$tokenURL=$accessTokenURL.'?code='.urlencode($authToken).'&grant_type='.$grantType.'&client_id='.urlencode($clientId).'&client_secret='.urlencode($clientSecret).'&redirect_uri='.urlencode($redirectURI);
	?>
	<html lang="en">
	<body>
	<script>
		function getTokens() {
			var xhttp = new XMLHttpRequest();
			xhttp.onreadystatechange = function() {
				if (this.readyState === 4 && this.status === 200) {
					alert(this.responseText);
				}
			};
			xhttp.open("POST", "<?php echo $tokenURL; ?>", true);
			xhttp.send();
		}
		
		getTokens();
		
	</script>
	</body>
	</html>
	<?php
	//echo $response;
	//header('Location: '.$tokenURL);
}

