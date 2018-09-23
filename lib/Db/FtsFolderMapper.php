<?php
namespace OCA\Files_external_fulltextsearch\Db;

use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

class FtsFolderMapper extends Mapper {

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'fts_folders');
	}

	public function find($id) {
		$sql = 'SELECT * FROM `*PREFIX*fts_folders` WHERE `id` = ?';
		return $this->findEntity($sql, [$id]);
	}

	public function findByUser($user) {
		$sql = 'SELECT * FROM `*PREFIX*fts_folders` WHERE `user` = ?';
		return $this->findEntities($sql, [$user]);
	}

	public function findByUserAndName($user, $name) {
		$sql = 'SELECT * FROM `*PREFIX*fts_folders` WHERE `user` = ? and `name` = ?';
		$folders = $this->findEntities($sql, [$user, $name]);
		if (count($folders) == 0) {
			return NULL;
		} else if (count($folders) > 1) {
			throw new \Exception('Multiple folders!');
		}
		return $folders[0];
	}
}
