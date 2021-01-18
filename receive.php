<?php

// ringcentral required validation for webhooks
$v = isset($_SERVER['HTTP_VALIDATION_TOKEN']) ? $_SERVER['HTTP_VALIDATION_TOKEN'] : '';
if (strlen($v)>0) {
	header("Validation-Token: {$v}");
}
// *********************************************

/**
 * IMPORTANT!  The following code MUST be at the top and at code root level
 * before namespacing or any other code.  While it is not necessary to keep
 * this code it is necessary that it is present whenever endpoint validation
 * is registered as this is how Infusionsoft confirms the endpoint as valid.
 */

/**$headers = array();
foreach ($_SERVER as $key => $value) {
	if (substr($key, 0, 5) <> 'HTTP_') {
		continue;
	}
	$header           = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
	$headers[$header] = $value;
}
$notification = json_decode(file_get_contents('php://input'));
header('X-Hook-Secret: ' . $headers['X-Hook-Secret'] . '');
**/
/**
 * End required code root
 */

file_put_contents('payload.dat', file_get_contents('PHP://input'));

$get=$_GET;
if ($get) {
	$file=fopen('GET.dat','w+');
	fwrite ($file,'***************** BEGIN GET *****************'.chr(10));
	foreach ($get as $key=>$g) {
		fwrite($file,$key.' = '.$g.chr(10));
	}
	fwrite($file, '****************** END GET ******************'.chr(10));
	fclose($file);
}

$post=$_POST;
if ($post) {
	$file=fopen('POST.dat','w+');
	fwrite ($file,'***************** BEGIN POST *****************'.chr(10));
	foreach ($post as $key=>$g) {
		fwrite($file,$key.' = '.$g.chr(10));
	}
	fwrite($file, '****************** END POST ******************'.chr(10));
	fclose($file);
}

$server=$_SERVER;
if ($server) {
	$file=fopen('SERVER.dat','w+');
	fwrite ($file,'***************** BEGIN SERVER *****************'.chr(10));
	foreach ($server as $key=>$g) {
		fwrite($file,$key.' = '.$g.chr(10));
	}
	fwrite($file, '****************** END SERVER ******************'.chr(10));
	fclose($file);
}

$header=getallheaders ();
if ($header) {
	$file=fopen('HEADER.dat','w+');
	fwrite ($file,'***************** BEGIN GET *****************'.chr(10));
	foreach ($header as $key=>$g) {
		fwrite($file,$key.' = '.$g.chr(10));
	}
	fwrite($file, '****************** END GET ******************'.chr(10));
	fclose($file);
}