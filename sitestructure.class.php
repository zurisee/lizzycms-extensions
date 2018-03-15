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
        $this->nextPage = '';
        $this->prevPage = '';

        $this->config = $config;

		$this->site_sitemapFile = $config->site_sitemapFile;
		$this->currPage = $currPage;
		if (!$this->config->feature_sitemapFromFolders && !file_exists($this->site_sitemapFile)) {
			$this->currPageRec = array('folder' => '', 'name' => '');
			$this->list = false;
			return;
		}
		$this->site_enableCaching = $this->config->site_enableCaching;
		$this->cachePath = $this->config->cachePath; //'.#cache/';
		$this->cacheFile = $this->cachePath.'_siteStructure.dat';


		if (!$this->readCache()) {
            if ($config->feature_sitemapFromFolders) {
                $this->list = $this->getSitemapFromFolders();
            } else {
                $this->list = $this->getList();
            }
			$this->tree = $this->getTree();
            $this->propagateProperties();   // properties marked with ! -> propagate into branches, e.g. 'lang!'='fr'

			$this->writeCache();
		}

		$currNr = $this->findSiteElem($this->currPage);
		if ($currNr !== false) {
			$this->list[$currNr]['isCurrPage'] = true;
			$this->currPageRec = &$this->list[$currNr];
			$this->markParents();

		} else {    // requested page not found:
            $this->currPage = '_unknown/';
            $this->currPageRec = [
                'name' => 'Default',
                'level' => 0,
                'folder' => '_unknown/',
                'requestedFolder' => $currPage,
                'isCurrPage' => true,
                'inx' => '0',
                'urlExt' => '',
                'active' => false,
                'hide' => false,
                'hasChildren' => false,
                'parent' => null
            ];
            return;
        }

		if ($currNr < (sizeof($this->list) - 1)) {
			$this->nextPage = $this->list[$currNr + 1]['folder'];
		}
		if ($currNr > 0) {
			$this->prevPage = $this->list[$currNr - 1]['folder'];
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
    private function getSitemapFromFolders() {
        $list = array();
        $i = 0;
        return $this->_traverseDir($this->config->path_pagesPath,$i, 0, $list);
    } // getSitemapFromFolders




    //....................................................
    private function _traverseDir($path, &$i, $level, &$list)
    {
        if ($dh = opendir($path)) {
            while (($f = readdir($dh)) !== false) {
                $rec = [];
                $file = $path . $f;
                if (!is_dir($file) || (strpos('._#', $f[0]) !== false)) {
                    continue;
                }

                $name = preg_replace('/^\d\d_/', '', $f);
                $rec['name'] = str_replace('_', ' ', $name);
                $rec['level'] = $level;
                if (!$list) {
                    $rec['folder'] = '~/./';
                    $rec['showthis'] = $f . '/';
                } else {
                    $rec['folder'] = $f . '/';
                }
                $rec['isCurrPage'] = false;
                $rec['inx'] = $i;
                $rec['urlExt'] = '';
                $rec['active'] = false;
                $rec['hide'] = false;

                $i++;
                $list[] = $rec;
                $this->_traverseDir("$path$f/", $i, $level + 1, $list);
            };
        };
        return $list;
    } // _traverseDir



    //....................................................
	private function getList()
	{
		$lines = file($this->site_sitemapFile);
		$list = array();
		$i = -1;
		$lastLevel = 0;
		foreach($lines as $line) {
			if (strpos($line, '__END__') !== false) {
				break;
			}

            $rec = [];
			$line = str_replace(['http://', 'https://'], ['http:||', 'https:||'], $line);
			$line = preg_replace('|//.*|', '', $line);
			$line = preg_replace('|#.*|', '', $line);
            $line = str_replace(['http:||', 'https:||'], ['http://', 'https://'], $line);
			$line = rtrim($line);
			if (preg_match('/^\s*$/', $line) || preg_match('/^\s*#/', $line)) {continue;}
			$i++;
			if (preg_match('/^(\s*)([^:\{]+)(.*)/', $line, $m)) {
				$indent = $m[1];
				$name = trim($m[2]);
				$rec['name'] = $name;
				if (strlen($indent) == 0) {
                    $level = 0;
                } else {
				    // siteIdententation -> 4 blanks count as one tab
                    // convert every 4 blanks to a tab, then remove all remaining blanks => level
				    $indent = str_replace(str_repeat(' ', $this->config->siteIdententation), "\t", $indent);
				    $indent = str_replace(' ', '', $indent);
                    $level = strlen($indent);
                }
				if (($level - $lastLevel) > 1) {
                    writeLog("Error in sitemap.txt: indentation on line $line (level: $level / lastLevel: $lastLevel)", 'errlog');
                    $level = $lastLevel + 1;
				}
                $rec['level'] = $level;
				$lastLevel = $level;
				$rec['folder'] = basename(translateToIdentifier($name, true), '.html').'/';
				if ($m[3] && !preg_match('/^\s*:\s*$/', $m[3])) {
					$json = preg_replace('/:?\s*(\{[^\}]*\})/', "$1", $m[3]);
					$args = convertYaml($json, true, $this->site_sitemapFile);
					if ($args) {
						foreach($args as $key => $value) {
							if ($key == 'folder') {
								$folder = fixPath($value);
								$folder = ($folder == '~/') ? '~/./' : $folder;
								$rec[strtolower($key)] = $folder;
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

				// case: page only visible to privileged users:
				if (preg_match('/non\-?privileged/i',$rec['hide'])) {
				    if ($this->config->isPrivileged) {
                        $rec['hide!'] = false;
                    } else {
                        $rec['hide!'] = true;
                    }
                }

                // check time dependency:
				if (isset($rec['showfrom'])) {
				    $t = strtotime($rec['showfrom']);
                    $rec['hide!'] |= (time() < $t);
                }
				if (isset($rec['showtill'])) {
				    $t = strtotime($rec['showtill']);
                    $rec['hide!'] |= (time() > $t);
                }
                if (isset($rec['hidefrom'])) {
                    $t = strtotime($rec['hidefrom']);
                    $rec['hide!'] |= (time() > $t);
                }
                if (isset($rec['hidetill'])) {
                    $t = strtotime($rec['hidetill']);
                    $rec['hide!'] |= (time() < $t);
                }
                if (isset($rec['hide!'])) {
                    unset($rec['hide']);
                }

                $list[] = $rec;
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
	                    // $i -> list-elem counter
		$j = 0;         // $j -> counter within level
		$tree = array();
		$hasVisibleChildren = false;
		if ($path == './') {
		    $path = '';
        }

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
				if (!(isset($list[$i]['hide']) && $list[$i]['hide'] || isset($list[$i]['hide!']) && $list[$i]['hide!'])) {
					$hasVisibleChildren = true;
				}

				$tree[$j] = &$list[$i];
				$i++;
				$j++;
			} else {
				return array($tree, $hasVisibleChildren); // going up
			}
		}
		return array($tree, $hasVisibleChildren); // the end, all pages consumed
	} // walkTree



    private function propagateProperties()
    {
        $this->_propagateProperties($this->tree);
    } // propagateProperties




    private function _propagateProperties($subtree, $level = 0, $toPropagate = [])
    {
        $toPropagate1 = $toPropagate;
        if (isset($subtree['inx'])) {
            $r = &$this->list[$subtree['inx']];
            foreach ($toPropagate1 as $k => $v) {
                $r[$k] = $v;
            }

            if ($r !== null) {
                foreach ($r as $k => $v) {
                    if (strpos($k, '!') !== false) {    // item to propagate found
                        $k1 = str_replace('!', '', $k);
                        $toPropagate1[$k1] = $v;
                        $r[$k1] = $v;
                        unset($r[$k]);
                    }
                }
            }
        }

        foreach ($subtree as $key => $rec) {
                if (is_int($key)) {
                    $this->_propagateProperties($rec, $level + 1, $toPropagate1);
                }
            }
    } // _propagateProperties




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

        $type = (isset($page->feature_cssFramework)) ? $page->feature_cssFramework : false;
        $type = ($this->config->feature_cssFramework) ? $this->config->feature_cssFramework : $type;
        $type = (isset($options['type'])) ? $options['type']: $type;
        $type = strtolower($type);
        if ($type) {
            $rendererFile = "nav-renderer-$type.php";
            if (file_exists($this->config->path_userCodePath."$rendererFile")) {
                require_once ($this->config->path_userCodePath."$rendererFile");
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
		
		if ($mutliLang = $this->config->site_multiLanguageSupport) {
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
				if ($this->config->feature_selflinkAvoid) {
                    $path = '#main';
                }
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
	public function findSiteElem($str, $returnRec = false)
	{
	    if ($str == '/') {
            $str = './';
        } elseif ((strlen($str) > 0) && ($str{0} == '/')) {
	        $str = substr($str, 1);
        } elseif ((strlen($str) > 0) && (substr($str,0,2) == '~/')) {
            $str = substr($str, 2);
        }
        if (!$this->list) {
        	return false;
        }
        
		$list = $this->list;
		$found = false;
		foreach($list as $key => $elem) {
			if ($found || ($str == $elem['name']) || ($str == $elem['folder']) || ($str.'/' == $elem['folder'])) {
				$folder = $this->config->path_pagesPath.$elem['folder'];
				if (!$found && !file_exists($folder)) { // if folder doesen't exist, let it be created later in handleMissingFolder()
                    $found = true;
                    break;
				}
				if (isset($elem['showthis']) && $elem['showthis']) {	// no 'skip empty folder trick' in case of showthis
                    $found = true;
                    break;
				}
				
				$dir = getDir($this->config->path_pagesPath.$elem['folder'].'*');	// check whether folder is empty, if so, move to the next non-empty one
				$nFiles = sizeof(array_filter($dir, function($f) {
                    return ((substr($f, -3) == '.md') || (substr($f, -5) == '.link') || (substr($f, -5) == '.html'));
				}));
				if ($nFiles > 0) {
				    $found = true;
				    break;
				} else {
					$found = true;
				}
			} elseif (isset($elem['alias']) && ($str == $elem['alias'])) {
                $found = true;
                break;
			}
		}
		if ($returnRec && $found) {
		    return $list[$key];
        } elseif ($found) {
            return $key;
        } else {
		    return false;
        }
	} // findSiteElem



	
	private function writeCache()
	{
		if ($this->site_enableCaching) {
			if (!file_exists($this->cachePath)) {
				mkdir($this->cachePath, 0770);
			}
			$cache = serialize($this);
			file_put_contents($this->cacheFile, $cache);
		}
	} // writeCache



	
	private function readCache()
	{   // when cache can be used:
        // - config>site_enableCaching: true
        // - cached siteStructure exists
        // - cached siteStructure is newer than config/sitemap.txt
        $skipElements = ['currPage', 'config', 'site_sitemapFile', 'site_enableCaching'];
		if ($this->site_enableCaching) {
			if (!file_exists($this->cacheFile)) {
				return false;
			}
			$cacheTime = filemtime($this->cacheFile);
			if ($cacheTime > filemtime($this->site_sitemapFile)) {
				$site = unserialize(file_get_contents($this->cacheFile));
				foreach ($site as $key => $value) {
					if (!in_array($key, $skipElements)) {
						$this->$key = $value;
					}
				}
				return true;
			}
		}
		return false;
	} // readCache




    public function clearCache()
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    } // clearCache

	public function getNumberOfPages()
	{
		return sizeof( $this->list );
	} // getNumberOfPages

} // class SiteStructure