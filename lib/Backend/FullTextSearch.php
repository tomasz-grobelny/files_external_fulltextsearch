<?php
namespace OCA\Files_external_fulltextsearch\Backend;

use OCP\IL10N;
use OCA\Files_External\Lib\Auth\AuthMechanism;
use OCA\Files_External\Lib\Backend\Backend;

class FullTextSearch extends Backend {
	public function __construct(IL10N $l) {
		$this
			->setIdentifier('files_external_fulltextsearch')
			->setStorageClass('\OCA\Files_external_fulltextsearch\Storage\FullTextSearch')
			->setText($l->t('Search Folders'))
			->addAuthScheme(AuthMechanism::SCHEME_NULL);
	}
}
