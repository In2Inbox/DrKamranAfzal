<?php

require_once 'src/isdk.php';
$app=new iSDK();
$app->cfgCon('nr908', '59f28f974c07c0a2e2ec49014e1ee4d5');

$fields=$app->dsQuery('DataFormField', 1000, 0, array('Id'=>'%'),
				array('DataType', 'DefaultValue', 'FormId', 'GroupId', 'Id', 'Label',
					'ListRows', 'Name', 'Values'));

$json=json_encode($fields);
file_put_contents('fields.json', $json);