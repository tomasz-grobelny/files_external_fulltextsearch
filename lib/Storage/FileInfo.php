<?php
namespace OCA\Files_external_fulltextsearch\Storage;

use OC\Files\Filesystem;

class FileInfo {
	public function __construct($filename, $filetype, $path) {
		$this->filename = $filename;
		$this->filetype = $filetype;
		$this->path = $path;
	}

	public $filename;
	public $filetype;
	public $path;

	public function getName() {
		return $this->filename;
	}

	public function getType() {
		return $this->filetype;
	}

	public function getSize() {
		if (is_null($this->path)) {
			return 0;
		}
		$storage_view = Filesystem::getView();
		$a = Filesystem::resolvePath($storage_view->getAbsolutePath($this->path));
		return $a[0]->stat($a[1])['size'];
	}

	public function getMTime() {
		if (is_null($this->path)) {
			return 0;
		}
		$storage_view = Filesystem::getView();
		$a = Filesystem::resolvePath($storage_view->getAbsolutePath($this->path));
		return $a[0]->stat($a[1])['mtime'];
	}
}
