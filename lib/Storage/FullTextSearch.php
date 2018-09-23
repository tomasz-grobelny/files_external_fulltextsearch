<?php
namespace OCA\Files_external_fulltextsearch\Storage;

use OC\Files\Filesystem;
use OC\Files\Storage\Common;
use Icewind\Streams\IteratorDirectory;
use OCA\FullTextSearch\Model\SearchRequest;
use OCA\FullTextSearch\Service\SearchService;
use OCP\Files\NotFoundException;
use OCA\Files_external_fulltextsearch\Db\FtsFolderMapper;
use OCA\Files_external_fulltextsearch\Db\FtsFolder;

class FullTextSearch extends Common {

	private $fixedNodes;
	/**
	 * @var FileInfo[]
	 */
	protected $statCache;

	private $searchService;

	private $mapper;

	public function __construct($params) {
		parent::__construct($params);
		$this->mapper = \OC::$server->query(FtsFolderMapper::class);
		$this->searchService = \OC::$server->query(SearchService::class);
		$this->fixedNodes = array(
			".noindex" => new FileInfo('.noindex', 'file', NULL),
			"by_tag" => new FileInfo('by_tag', 'dir', NULL),
			"by_extension" => new FileInfo('by_extension', 'dir', NULL),
			"custom" => new FileInfo('custom', 'dir', NULL)
		);
	}

	/**
	 * @return string
	 */
	public function getId() {
		return 'fts';
	}

	/**
	 * @param string $path
	 * @return string
	 */
	protected function buildPath($path) {
		return Filesystem::normalizePath('fulltextsearch/' . $path, true, false, true);
	}

	/**
	 * @param string $path
	 * @throws NotFoundException
	 */
	protected function getFileInfo($path) {
		if ($path == '') {
		    return new FileInfo('', 'dir', NULL);
		}
		$fullPath = $this->buildPath($path);
		$folderPath = substr($path, 0, strrpos($path, '/'));
		$this->getFolderContents($folderPath);
		if (!array_key_exists($fullPath, $this->statCache) || is_null($this->statCache[$fullPath])) {
			throw new NotFoundException('not found');
		}
		return $this->statCache[$fullPath];
	}

	protected function getFolderContents($path) {
		$path = $this->buildPath($path);
		$files = [];
		$user = \OC\Files\Filesystem::getOwner('/');
		if ($path == '/fulltextsearch') {
			$files = array_values($this->fixedNodes);
		} else if ($path == '/fulltextsearch/by_tag') {
			$request = SearchRequest::fromArray(
				[
					'providers' => 'all',
					'search'    => '*'
				]
			);
			$request->setOptions(['meta' => []]);
			$request->cleanSearch();
			$result = $this->searchService->search($user, $request);
			foreach ($result[0]->getAggregation('subtags') as $value => $count) {
				$tagArray = explode('_', $value, 2);
				if ( $tagArray[0] != 'tag' ) {
					continue;
				}
				foreach (explode('/', $tagArray[1]) as $tag) {
					$files[] = new FileInfo($tag, 'dir', NULL);
				}
			}
		} else if (strpos($path, '/fulltextsearch/by_tag/') === 0) {
			$tag = substr($path, strlen('/fulltextsearch/by_tag/'));
			$request = SearchRequest::fromArray(
				[
					'providers' => 'all',
					'search'    => '*'
				]
			);
			$request->addSubTag('tag', strtolower($tag));
			$request->setOptions(['meta' => []]);
			$request->cleanSearch();
			$result = $this->searchService->search($user, $request);
			foreach ($result[0]->getDocuments() as $document) {
				$title = $document->getTitle();
				$title = substr($title, strrpos($title, '/') + 1);
				$files[] = new FileInfo($title, 'file', $document->getTitle());
			}
		} else if ($path == '/fulltextsearch/custom') {
			foreach ($this->mapper->findByUser($user) as $folder) {
				$files[] = new FileInfo($folder->getName(), 'dir', NULL);
			}
		} else if (strpos($path, '/fulltextsearch/custom/') === 0) {
			$folderName = substr($path, strlen('/fulltextsearch/custom/'));
			$selectedFolder = NULL;
			foreach ($this->mapper->findByUser($user) as $folder) {
				if ($folder->getName() == $folderName) {
					$selectedFolder = $folder;
				}
			}
			if(!is_null($selectedFolder)) {
				$files[] = new FileInfo($selectedFolder->getDefinition(), 'dir', NULL);
			}
		}
		foreach ($files as $file) {
			$this->statCache[$path . '/' . $file->getName()] = $file;
		}
		return $files;
	}

	protected function formatInfo($info) {
		return [
			'size' => $info->getSize(),
			'mtime' => $info->getMTime(),
			'type' => $info->getType(),
		];
	}

	public function rename($source, $target, $retry = true) {
		$user = \OC\Files\Filesystem::getOwner('/');
		$fullSource = $this->buildPath($source);
		$fullTarget = $this->buildPath($target);
		if (strpos($fullSource, '/fulltextsearch/custom/') === 0 && strpos($fullTarget, '/fulltextsearch/custom/') === 0) {
			$sourceName = substr($fullSource, strlen('/fulltextsearch/custom/'));
			$targetName = substr($fullTarget, strlen('/fulltextsearch/custom/'));
			$sourceFolder = $this->mapper->findByUserAndName($user, $sourceName);
			$targetFolder = $this->mapper->findByUserAndName($user, $targetName);
			if (is_null($sourceFolder) || !is_null($targetFolder)) {
				return false;
			}
			$sourceFolder->setName($targetName);
			$this->mapper->update($sourceFolder);
			return true;
		}
		return false;
	}

	public function stat($path) {
		try {
			$result = $this->formatInfo($this->getFileInfo($path));
		} catch (NotFoundException $e) {
			return false;
		}
		return $result;
	}

	public function unlink($path) {
		return false;
	}

	public function hasUpdated($path, $time) {
		return true;
	}

	public function fopen($path, $mode) {
		$fullPath = $this->buildPath($path);
		if (is_null($this->statCache[$fullPath])) {
			return false;
		}
		$fi = $this->statCache[$fullPath];
		$storage_view = Filesystem::getView();
		$a = Filesystem::resolvePath($storage_view->getAbsolutePath($fi->path));
		return $a[0]->fopen($a[1], $mode);
	}

	public function rmdir($path) {
		$path = $this->buildPath($path);
		$user = \OC\Files\Filesystem::getOwner('/');
		if (strpos($path, '/fulltextsearch/custom/') === 0) {
			$folderName = substr($path, strlen('/fulltextsearch/custom/'));
			$folder = $this->mapper->findByUserAndName($user, $folderName);
			if (is_null($folder)) {
				return false;
			}
			$this->mapper->delete($folder);
			unset($this->statCache[$path]);
			return true;
		}
		return false;
	}

	public function touch($path, $time = null) {
		return false;
	}

	public function opendir($path) {
		try {
			$files = $this->getFolderContents($path);
		} catch (NotFoundException $e) {
			return false;
		}
		$names = array_map(function ($info) {
			return $info->getName();
		}, $files);
		return IteratorDirectory::wrap($names);
	}

	public function filetype($path) {
		try {
			return $this->getFileInfo($path)->getType();
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function mkdir($path) {
		$path = $this->buildPath($path);
		if (strpos($path, '/fulltextsearch/custom/') === 0) {
			$folderName = substr($path, strlen('/fulltextsearch/custom/'));
			$user = \OC::$server->getUserSession()->getUser()->getUID();
			if (!is_null($this->mapper->findByUserAndName($user, $folderName))) {
				return false;
			}
			$definition = 'sample def';
			$ftsFolder = new FtsFolder();
			$ftsFolder->setUser($user);
			$ftsFolder->setName($folderName);
			$ftsFolder->setDefinition($definition);
			$this->mapper->insert($ftsFolder);
			return true;
		}
		return false;
	}

	public function file_exists($path) {
		try {
			$this->getFileInfo($path);
			return true;
		} catch (NotFoundException $e) {
			return false;
		}
	}

	public function isReadable($path) {
		return $this->file_exists($path);
	}

	public function isUpdatable($path) {
		$path = $this->buildPath($path);
		return (strpos($path, '/fulltextsearch/custom/') === 0);
	}


	public function isCreatable($path) {
		$path = $this->buildPath($path);
		return ($path == '/fulltextsearch/custom');
	}

	public function isDeletable($path) {
		$path = $this->buildPath($path);
		return (strpos($path, '/fulltextsearch/custom/') === 0);
	}
}
