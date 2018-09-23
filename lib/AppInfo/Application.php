<?php
namespace OCA\Files_external_fulltextsearch\AppInfo;

use OCA\Files_External\Lib\Config\IBackendProvider;
use OCA\Files_External\Service\BackendService;
use OCP\AppFramework\App;

class Application extends App implements IBackendProvider {

	public function __construct(array $urlParams = array()) {
		parent::__construct('files_external_fulltextsearch', $urlParams);
	}

	public function getBackends() {
		$container = $this->getContainer();

		$backends = [
			$container->query('OCA\Files_external_fulltextsearch\Backend\FullTextSearch'),
		];

		return $backends;
	}

	public function register() {
		$container = $this->getContainer();
		$server = $container->getServer();

		$backendService = $server->query('OCA\\Files_External\\Service\\BackendService');
		$backendService->registerBackendProvider($this);
	}
}
