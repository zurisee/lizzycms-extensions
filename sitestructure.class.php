<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *  SiteStructure
*/
use Symfony\Component\Yaml\Yaml;

class SiteStructure
{
	private $list = false;
	private $tree = false;
	public $currPage;
	public $prevPage = '';
	public $nextPage = '';
	public $currPageRec = false;
	public $config;

	//....................................................
	public function __construct($config, $currPage = false)
	{
        $this->config = $config;
		$this->sitemapFile = $config->sitemapFile;
		$this->currPage = $currPage;
		if (!file_exists($this->sitemapFile)) {
			$this->currPageRec = array('folder' => '', 'name' => '');
			$this->list = false;
			return;
		}
		$this->caching = $this->config->caching;
		$this->cachePath = $this->config->cachePath; //'.#cache/';
		$this->cacheFile = $this->cachePath.'_siteStructure.dat';
		$cacheDirty = false;
		if (!$this->readCache()) {
			$this->list = $this->getList();
			$this->tree = $this->getTree();
			$this->writeCache();
		}

		$currNr = $this->findSiteElem($this->currPage);
		if ($currNr !== false) {
			$this->list[$currNr]['isCurrPage'] = true;
			$this->currPageRec = &$this->list[$currNr];
			$this->markParents();
		}
		if ($currNr < (sizeof($this->list) - 1)) {
			$this->nextPage = $this->list[$currNr + 1]['folder'];
		} else {
			$this->nextPage = '';
		}
		if ($currNr > 0) {
			$this->prevPage = $this->list[$currNr - 1]['folder'];
		} else {
			$this->prevPage = '';
		}
	} // __construct



	//....................................................
	public function getSiteList() {
		return $this->list;
	} // getSiteList



	//....................................................
	public function getSiteTree() {
		return $this->tree;
	} // getSiteTree



	//....................................................
	private function getList()
	{
		$lines = file($this->sitemapFile);
		$list = array();
		$i = -1;
		$lastLevel = 0;
		foreach($lines as $line) {
			if (strpos($line, '__END__') !== false) {
				break;
			}
			$line = preg_replace('|//.*|', '', $line);
			$line = preg_replace('|#.*|', '', $line);
			$line = rtrim($line);
			if (preg_match('/^\s*$/', $line) || preg_match('/^\s*#/', $line)) {continue;}
			$i++;
			if (preg_match('/^(\s*)([^:\{]+)(.*)/', $line, $m)) {
				$indent = $m[1];
				$name = trim($m[2]);
				$rec = &$list[$i];
				$rec['name'] = $name;
				if (strlen($indent) == 0) {
					$level = 0;
				} elseif ($indent{0} == "\t") {
					$level = strlen($indent);
				} else {
					$level = floor(strlen($indent) / $this->config->siteIdententation);
				}
				$rec['level'] = $level;
				if (($level - $lastLevel) > 1) {
					die("Error in site.txt: indentation too large on line <br>\n<pre>$line</pre>");
				}
				$lastLevel = $level;
				$rec['folder'] = basename(translateToIdentifier($name, true), '.html').'/';
				if ($m[3] && !preg_match('/^\s*:\s*$/', $m[3])) {
					$json = preg_replace('/:?\s*(\{[^\}]*\})/', "$1", $m[3]);
					$args = convertYaml($json);
					if ($args) {
						foreach($args as $key => $value) {
							if ($key == 'folder') {
								$rec[strtolower($key)] = fixPath($value);
							} else {
								$rec[strtolower($key)] = $value;
							}
						}
					}
				}
				$rec['isCurrPage'] = false;
				$rec['inx'] = $i;
				$rec['urlExt'] = '';
				$rec['active'] = false;
				$rec['hide'] = (isset($rec['hide'])) ? $rec['hide'] : false;
			}
		}
		return $list;
	} // getList



	//....................................................
	private function getTree()
	{
		$this->lastLevel = 0;
		$i = 0;
		list($tree, $visChildren) = $this->walkTree($this->list, $i, '', 0, null);
		return $tree;
	} // getTree



	//....................................................
	private function walkTree(&$list, &$i, $path, $lastLevel, $parent)
	{
		$j = 0;
		$tree = array();
		$path1 = '';
		$hasVisibleChildren = false;
		while ($i < sizeof($list)) {
			$level = $list[$i]['level'];
			if ($level > $lastLevel) {
				$lastRec = &$list[$i-1];
				list($subtree, $visChildren) = $this->walkTree($list, $i, $list[$i-1]['folder'], $lastLevel+1, ($i) ? ($i-1) : null);
				$lastRec['hasChildren'] = $visChildren;
				$tree[$j-1] = (isset($tree[$j-1])) ? array_merge($tree[$j-1], $subtree) : $subtree;
			} elseif ($level == $lastLevel) {
				$list[$i]['hasChildren'] = false;
				if (substr($list[$i]['folder'], 0, 2) != '~/') {
					$list[$i]['folder'] = $path.$list[$i]['folder'];
				} else {
					$list[$i]['folder'] = (strlen($list[$i]['folder']) > 2) ? substr($list[$i]['folder'], 2) : '';
				}
				$list[$i]['parent'] = $parent;
				if (!$list[$i]['hide']) {
					$hasVisibleChildren = true;
				}
				$tree[$j] = &$list[$i];
				$i++;
				$j++;
			} else {
				return array($tree, $hasVisibleChildren);
			}
		}
		return array($tree, $hasVisibleChildren);
	} // walkTree



	//....................................................
	private function markParents()
	{
		$this->currPageRec['active'] = true;
		$rec = &$this->currPageRec;
		while ($rec['parent']) {
			$rec = &$this->list[$rec['parent']];
			$rec['active'] = true;
		}
	} // markParents



    //....................................................
    public function render($inx, $page, $options)
    {
        if ($this->list == false) {     // it's a "one-pager", don't render any navigation
            return '';
        }

        $type = (isset($page->cssFramework)) ? $page->cssFramework : false;
        $type = ($this->config->cssFramework) ? $this->config->cssFramework : $type;
        $type = (isset($options['type'])) ? $options['type']: $type;
        $type = strtolower($type);
        if ($type) {
            $rendererFile = "nav-renderer-$type.php";
            if (file_exists($this->config->userCodePath."$rendererFile")) {
                require_once ($this->config->userCodePath."$rendererFile");
                return renderMenu($this, $page, $options);

            } elseif (file_exists(SYSTEM_PATH.$rendererFile)) {
                require_once (SYSTEM_PATH.$rendererFile);
                return renderMenu($this, $page, $options);

            } else {
                return $this->renderMenu($options);
            }
        } else {
            return $this->renderMenu($options);
        }
    } // render



    //....................................................
    public function renderMenu($options)
    {
        $class = (isset($options['class'])) ? $options['class']: '';
        $navClass = (isset($navClass) && ($navClass)) ? " class='$navClass'" : '';
        $ulClass = (isset($options['ulClass'])) ? $options['ulClass']: '';
        $liClass = (isset($options['liClass'])) ? $options['liClass']: '';
        $hasChildrenClass = (isset($options['hasChildrenClass'])) ? $options['hasChildrenClass']: '';
        $aClass = (isset($options['aClass'])) ? $options['aClass']: '';
        $showHidden = (isset($options['showHidden'])) ? $options['showHidden']: '';
        $title = (isset($options['title'])) ? $options['title']: '';

		$this->ulClass = $ulClass;
		$this->liClass = $liClass;
		$this->hasChildrenClass = ($hasChildrenClass) ? $hasChildrenClass : 'has-children';
		$this->aClass  = $aClass;
		$title = ($title) ? "<h1>$title</h1>" : '';
		$nav = $this->_renderMenu($navClass, false, '', $showHidden);
		$out = <<<EOT
	<nav$navClass>
		$title
$nav
	</nav>
EOT;
		return $out;
	} // render



	//....................................................
	private function _renderMenu($navClass, $tree, $indent, $showHidden = false)
	{
		$navClass = ($navClass) ? " class='$navClass'": '';
		$indent = str_replace('\t', "\t", $indent);
		if ($indent == '') {
			$indent = "\t";
		}
		if (!$tree) {
			$tree = $this->tree;
		}
		$nav = '';
		$_nav = '';
		
		if ($mutliLang = $this->config->multiLanguageSupport) {
			$currLang = $this->config->lang;
		}		

		$ulClass = ($this->ulClass) ? " class='{$this->ulClass}'" : '';
		$out = "$nav$indent<ul$ulClass>\n";
		$aClass = ($this->aClass) ? " class='{$this->aClass}'" : '';
		foreach($tree as $n => $elem) {
			if (!is_int($n)) { continue; }
			$currClass = '';
			if ($mutliLang && isset($elem[$currLang])) {
				$name = $elem[$currLang];
			} else {
				$name = $elem['name'];
			}
			if (isset($elem['goto'])) {
				$targInx = $this->findSiteElem($elem['goto']);
				$targ = $this->list[$targInx];
				$name = $targ['name'];
				$path = $targ['folder'];
			} else {
				$path = (isset($elem['folder'])) ? $elem['folder'] : '';
			}
			if ($path == '') {
				$path = '~/';
			} elseif (substr($path, 0, 2) != '~/') {
				$path = '~/'.$path;
			}
			$liClass = $this->liClass;
			if ($elem['isCurrPage']) {
				$liClass .= ' curr active';
				$path = '#main';
			} elseif ($elem['active']) {
				$liClass .= ' active';
			}
			if (isset($elem['target'])) {
				$target = " target='{$elem['target']}'";
			} else {
				$target = '';
			}
			if ((!$elem['hide']) || $showHidden) {
				if (isset($elem[0])) {	// does it have children?
					if ($elem['hasChildren']) {
						$liClass .= ' '.$this->hasChildrenClass; //' has-children';
					}
					if (($elem['isCurrPage']) || ($elem['active'])) {
						$liClass .= ' open';
					}
					$liClass = trim($liClass);
					$liClass = ($liClass) ? " class='$liClass'" : '';
					$out .= "$indent\t<li$liClass><a href='$path'$aClass$target><span style='width: calc(100% - 1em);'>$name</span></a>\n";
					$out .= $this->_renderMenu('', $elem, "$indent\t\t", $showHidden);
					$out .= "$indent\t</li>\n";
				} else {
					$liClass = trim($liClass);
					$liClass = ($liClass) ? " class='{$liClass}'" : '';
					$out .= "$indent\t<li$liClass><a href='$path'$aClass$target>$name</a></li>\n";
				}
			}
		}
		
		$out .= "$indent</ul>\n$_nav";
		return $out;
	} // _renderMenu



	//....................................................
	public function findSiteElem($str)
//	private function findSiteElem($str)
	{
		$list = $this->list;
		$found = false;
		foreach($list as $key => $elem) {
			if ($found || ($str == $elem['name']) || ($str == $elem['folder']) || ($str.'/' == $elem['folder'])) {
				$folder = $this->config->pagesPath.$elem['folder'];
				if (!$found && !file_exists($folder)) {
					preparePath($folder);
					$md = "# {$elem['name']}\n\n";
					$file = $folder.translateToFilename($elem['name'], 'md');
					file_put_contents($file, $md);
				}
				if (isset($elem['showthis']) && $elem['showthis']) {	// no 'skip empty folder trick' in case of showthis
					return $key;
				}
				
				$dir = getDir($this->config->pagesPath.$elem['folder'].'*');	// check whether folder is empty, if so, move to the next non-empty one
				$nFiles = sizeof(array_filter($dir, function($f) {
					return ((substr($f, -3) == '.md') || (substr($f, -5) == '.link') || (substr($f, -5) == '.html'));
				}));
				if ($nFiles > 0) {
					return $key;
				} else {
					$found = true;
				}
			} elseif (isset($elem['alias']) && ($str == $elem['alias'])) {
				return $key;
			}
		}
		return $found;
	} // findSiteElem



	
	private function writeCache()
	{
		if ($this->caching) {
			if (!file_exists($this->cachePath)) {
				mkdir($this->cachePath, 0770);
			}
			$cache = serialize($this);
			file_put_contents($this->cacheFile, $cache);
		}
	} // writeCache



	
	private function readCache()
	{
		if ($this->caching) {
			if (!file_exists($this->cacheFile)) {
				return false;
			}
			$cacheTime = filemtime($this->cacheFile);
			if ($cacheTime > filemtime($this->sitemapFile)) {
				$site = unserialize(file_get_contents($this->cacheFile));
				foreach ($site as $key => $value) {
					if (($key != 'currPage') && ($key != 'currPageRec')) {
						$this->$key = $value;
					}
				}
				return true;
			}
		}
		return false;
	} // readCache



	public function getNumberOfPages()
	{
		return sizeof( $this->list );
	} // getNumberOfPages

} // class SiteStructure