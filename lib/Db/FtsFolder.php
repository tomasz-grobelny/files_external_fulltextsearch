<?php
namespace OCA\Files_external_fulltextsearch\Db;

use OCP\AppFramework\Db\Entity;

class FtsFolder extends Entity {
	protected $user;
	protected $name;
	protected $definition;
}
