<?php

// this file MUST be the first require to allow values to be set prior to instantiating other objects

$_SERVER['HTTP_X_DRCHRONO_SIGNATURE']='drchrono_lionsoft';
$_SERVER['HTTP_X_DRCHRONO_EVENT']='PATIENT_CREATE';
$_SERVER['REQUEST_METHOD']='POST';
$json=file_get_contents('patient.create.json');
$obj=json_decode($json);