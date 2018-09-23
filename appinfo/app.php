<?php
if ((@include_once(dirname(__DIR__).'/vendor/autoload.php')) === false) {
	throw new \Exception('Cannot include autoload. Did you run install dependencies using composer?');
}

$app = new \OCA\Files_external_fulltextsearch\AppInfo\Application();
$app->register();
