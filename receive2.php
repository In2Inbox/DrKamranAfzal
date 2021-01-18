<?php
if (isset($_GET['msg'])) {
	$msg = $_GET['msg'];
	$value = hash_hmac( 'sha256', "$msg", 'drchrono_apiroi' );
	echo json_encode( array("secret_token" => "$value") );
	die();
}

file_put_contents('payload2.dat', file_get_contents('PHP://input'));

$get=$_GET;
if ($get) {
	$file=fopen('GET2.dat','w+');
	fwrite ($file,'***************** BEGIN GET *****************'.chr(10));
	foreach ($get as $key=>$g) {
		fwrite($file,$key.' = '.$g.chr(10));
	}
	fwrite($file, '****************** END GET ******************'.chr(10));
	fclose($file);
}

$post=$_POST;
if ($post) {
	$file=fopen('POST2.dat','w+');
	fwrite ($file,'***************** BEGIN POST *****************'.chr(10));
	foreach ($post as $key=>$g) {
		fwrite($file,$key.' = '.$g.chr(10));
	}
	fwrite($file, '****************** END POST ******************'.chr(10));
	fclose($file);
}

$server=$_SERVER;
if ($server) {
	$file=fopen('SERVER2.dat','w+');
	fwrite ($file,'***************** BEGIN SERVER *****************'.chr(10));
	foreach ($server as $key=>$g) {
		fwrite($file,$key.' = '.$g.chr(10));
	}
	fwrite($file, '****************** END SERVER ******************'.chr(10));
	fclose($file);
}

$header=getallheaders ();
if ($header) {
	$file=fopen('HEADER2.dat','w+');
	fwrite ($file,'***************** BEGIN GET *****************'.chr(10));
	foreach ($header as $key=>$g) {
		fwrite($file,$key.' = '.$g.chr(10));
	}
	fwrite($file, '****************** END GET ******************'.chr(10));
	fclose($file);
}