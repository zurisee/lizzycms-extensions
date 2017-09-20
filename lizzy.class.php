<?php
/*
 *	Lizzy - main class
 *
 *	Main Class *
*/

define('CONFIG_PATH',           'config/');
define('SYSTEM_PATH',           basename(dirname(__FILE__)).'/'); // _lizzy/
define('DEFAULT_CONFIG_FILE',   CONFIG_PATH.'config.yaml');
define('SUBSTITUTE_UNDEFINED',  1);
define('SUBSTITUTE_ALL',        2);

use Symfony\Component\Yaml\Yaml;
use voku\helper\HtmlDomParser;

require_once SYSTEM_PATH.'auxiliary.php';
require_once SYSTEM_PATH.'vendor/autoload.php';
require_once SYSTEM_PATH.'page.class.php';
require_once SYSTEM_PATH.'transvar.class.php';
require_once SYSTEM_PATH.'mymarkdown.class.php';
require_once SYSTEM_PATH.'scss.class.php';
require_once SYSTEM_PATH.'defaults.class.php';
require_once SYSTEM_PATH.'sitestructure.class.php';
require_once SYSTEM_PATH.'authentication.class.php';
require_once SYSTEM_PATH.'image-resizer.class.php';
require_once SYSTEM_PATH.'datastorage.class.php';
require_once SYSTEM_PATH.'sandbox.class.php';


$globalParams = array(
	'pathToRoot' => null,			// ../../
	'pagePath' => null,				// pages/xy/
    'logPath' => null,
);


class Lizzy
{
	private $currPage = false;
	private $configPath = CONFIG_PATH;
	private $systemPath = SYSTEM_PATH;
	private $autoAttrDef = [];
	private $httpSystemPath;
	private $pathToRoot;
	private $query;
	private $reqPagePath;
	private $appPath;
    private $trans;
	private $siteStructure;
	private $editingMode = false;
	private $timer = false;



	//....................................................
    public function __construct()
    {
		global $globalParams;
        ini_set('display_errors', '0');
        
		$this->checkInstallation();
		$this->init();
		$this->setupErrorHandling();

		if ($this->config->sitemapFile) {
			$this->currPage = $this->reqPagePath;
			$this->siteStructure = new SiteStructure($this->config, $this->reqPagePath);
            $this->currPage = $this->reqPagePath = $this->siteStructure->currPage;
            $this->pagePath = $this->config->pagesPath.$this->currPage;

			if (isset($this->siteStructure->currPageRec['showthis'])) {
				$this->pagePath = $this->config->pagesPath.$this->siteStructure->currPageRec['showthis'];
			} else {
				$this->pagePath = $this->config->pagesPath.$this->siteStructure->currPageRec['folder'];
			}
			$globalParams['pagePath'] = $this->pagePath;
			$this->pageRelativePath = $this->pathToRoot.$this->pagePath;

			$this->trans = new Transvar($this->config, array(SYSTEM_PATH.'config/sys_vars.yaml', CONFIG_PATH.'user_variables.yaml'), $this->siteStructure); // loads static variables
			$this->trans->addVariable('next_page', "<a href='~/{$this->siteStructure->nextPage}'>{{ nextPageLabel }}</a>");
			$this->trans->addVariable('prev_page', "<a href='~/{$this->siteStructure->prevPage}'>{{ prevPageLabel }}</a>");
		} else {
			$this->siteStructure = new SiteStructure($this->config, ''); //->list = false;
			$this->currPage = '';
			$this->trans = new Transvar($this->config, array(SYSTEM_PATH.'config/sys_vars.yaml', CONFIG_PATH.'user_variables.yaml'), $this->siteStructure); // loads static variables
		}
    } // __construct




	//....................................................
    public function render()
    {
		if ($this->timer) {
			startTimer();
		}

		$page = &$this->page;

		$this->handleInsecureConnection();

		$this->setTransvars1();

//		if ($html = getCache()) {
//            $html = $this->trans->render($html, $this->config->lang, SUBSTITUTE_ALL);
//            return $html;
//        }

        $this->loadFile();		// get content file

		$this->injectEditor();

		$this->injectPageSwitcher();

		$this->setTransvars2();

		$this->injectCssFramework();

		$this->runUserInitCode();

		$this->loadTemplate();

        $this->trans->doUserComputedVariables();

        $this->handleUrlArgs2();


        // now, compile the page from all its components:
        $html = $this->trans->render($page, $this->config->lang);


		$this->prepareImages($html);

        $html = resolveAllPaths($html, true);	// replace ~/, ~sys/, ~page/ with actual values

        $html = $this->executeAutoAttr($html);

        // writeCache()
        $html = $this->trans->render($html, $this->config->lang, SUBSTITUTE_ALL);

        if ($this->timer) {
			$this->debugMsg = readTimer();
		}

        return $html;
    } // render



    //....................................................
    private function init()
    {
        $mdStr = false;

        $configFile = DEFAULT_CONFIG_FILE;
        if (file_exists($configFile)) {
            $this->configFile = $configFile;
        } else {
            die("Error: file not found: ".$configFile);
        }

        session_start();
        $this->sessionId = session_id();
        $this->getConfigValues(); // from $this->configFile
        $this->config->appBaseName = base_name(rtrim(trunkPath(__FILE__, 1), '/'));
        if ($this->config->sitemapFile) {
            $sitemapFile = $this->config->configPath . $this->config->sitemapFile;

            if (file_exists($sitemapFile)) {
                $this->config->sitemapFile = $sitemapFile;
            } else {
                $this->config->sitemapFile = false;
            }
        }

        $serverName = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : 'localhost';
        $remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '';
        $this->page = new Page($this->config);
        $this->localCall = (($serverName == 'localhost') || (strpos($remoteAddress, '192.') === 0) || ($remoteAddress == '::1'));

        $this->config->isLocalhost = $this->localCall;
        $this->analyzeHttpRequest();
        $this->httpSystemPath = $this->pathToRoot.SYSTEM_PATH;
        $this->auth = new Authentication($this->config->configPath.$this->config->usersFile);
        $this->loggedInUser = $this->auth->authenticate();

        $this->handleUrlArgs();

        if ($mdStr) {
            $this->renderMD($mdStr);
            exit;
        }

        $cliarg = getCliArg('compile');
        if (isset($_GET['compile']) || ($cliarg !== null)) {
            $this->renderMD();
        }

        $this->scss = new SCssCompiler($this->config->stylesPath.'scss/*.scss', $this->config->stylesPath, $this->localCall);
        $this->scss->compile(!$this->config->caching);

        $this->loadAutoAttrDefinition();
    } // init



    //....................................................
    private function setupErrorHandling()
    {
        if ($_SESSION['user'] == 'admin') {     // set displaying errors on screen:
            $old = ini_set('display_errors', '1');  // on
            error_reporting(E_ALL);

        } else {
            $old = ini_set('display_errors', '0');  // off
            error_reporting(0);
        }
        if ($old === false) {
        	die("Error setting up error handling... (no kidding)");
        }
        $errorLog = $this->config->errorLogging;
        if ($this->config->logPath && $errorLog) {
            $errorLogFile = $this->config->logPath . $errorLog;
            ini_set("log_errors", 1);
            ini_set("error_log", $errorLogFile);
            //error_log( "Error-logging started" );
        }
    } // setupErrorHandling



    //....................................................
    private function handleInsecureConnection()
    {
        if ($this->isRestrictedPage()) {
            if ($this->localCall || (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == 'on')) {
                $overridePage = $this->auth->authForm($this->auth->message);
                $this->page->merge($overridePage);
            } else {
                $this->page->set('override', "{{ Warning insecure connection }}");
            }
        }
    } // handleInsecureConnection



    //....................................................
    private function loadAutoAttrDefinition($file = false)
    {
        if (!$file) {
            if (!file_exists($this->config->autoAttrFile)) {
                return;
            }
            $file = $this->config->autoAttrFile;
        }
        $autoAttrDef = getYamlFile($file);
        if ($autoAttrDef) {
            $this->autoAttrDef = array_merge($this->autoAttrDef, $autoAttrDef);
        }
        return;
    } // loadAutoAttrDefinition



    //....................................................
    private function analyzeHttpRequest()
    {
    // appRoot:         path from docRoot to base folder of app, mostly = ''; appRoot == '~/'
    // pagePath:        forward-path from appRoot to requested folder, e.g. 'contact/ (-> excludes pages/)
    // pathToRoot:      upward-path from requested folder to appRoot, e.g. ../
    // redirectedPath:  if requests get redirected by .htaccess, this is the skipped folder(s), e.g. 'now_active/'

    // $globalParams:   -> pagePath, pathToRoot, redirectedPath
    // $_SESSION:       -> userAgent, pageName, currPagePath, lang

        global $globalParams, $pathToRoot;

        $requestUri     = (isset($_SERVER["REQUEST_URI"])) ? rawurldecode($_SERVER["REQUEST_URI"]) : '';
        $scriptPath     = dir_name($_SERVER['SCRIPT_NAME']);
        $appRoot        = fixPath(commonSubstr( $scriptPath, dir_name($requestUri), '/'));
        $redirectedPath = ($h = substr($scriptPath, strlen($appRoot))) ? $h : '';
        $requestedPath  = dir_name($requestUri);
        $requestedPagePath = dir_name(substr($requestUri, strlen($appRoot)));
        if ($requestedPagePath == '.') {
            $requestedPagePath = '';
        }
        $pagePath       = substr($requestedPath, strlen($appRoot));
        $pathToRoot = preg_replace('|\w+/|', '../', $requestedPagePath);
        if (!$pagePath) {
            $pagePath = './';
        }


        // get IP-address
        $ip = $_SERVER["HTTP_HOST"];
        if (stripos($ip, 'localhost') !== false) {  // case of localhost, not executed on host
            $ifconfig = shell_exec('ifconfig');
            $p = strpos($ifconfig, 'inet 192.168');
            $ip = substr($ifconfig, $p+5);
            if (preg_match('/([\d\.]+)/', $ip, $match)) {
                $ip = $match[1];
            }
        }
        $this->serverIP = $ip;


        // get info about browser
		if (function_exists('getallheaders')) {
	        $browser = new WhichBrowser\Parser(getallheaders());
			$_SESSION['userAgent'] = $this->userAgent = $browser->toString();
			$this->legacyBrowser = $browser->isBrowser('Internet Explorer', '<', '10') ||
									$browser->isBrowser('Android Browser') ||
									$browser->isOs('Windows', '<', '7') ||
									$browser->isBrowser('Safari', '<', '5.1');
		} else {
			$this->legacyBrowser = false;
			$_SESSION['userAgent'] = $this->userAgent = 'unknown';
		}


        $this->reqPagePath = $pagePath;
        //$globalParams['pagePath']   // forward-path from app-root to requested folder -> excludes pages/
        $globalParams['pathToRoot'] = $pathToRoot;  // path from requested folder to root (= ~/), e.g. ../
        $globalParams['redirectedPath'] = $redirectedPath;  // the part that is optionally skippped by htaccess
        $globalParams['legacyBrowser'] = $this->legacyBrowser;

        writeLog("UserAgent: [{$this->userAgent}]".(($this->legacyBrowser)?" (Legacy browser!)":''));
    } // analyzeHttpRequest



    //....................................................
    private function getConfigValues()
    {
        global $globalParams;
        $configValues = getYamlFile($this->configFile);

        $this->config = new Defaults;
        $overridableSettings = array_keys($this->config->configFileSettings);
        foreach ($overridableSettings as $key) {
            if (isset($configValues[$key])) {
                $val = $configValues[$key];
                if (stripos($key, 'Path') !== false) {
                    $val = preg_replace('|/\.\.+|', '', $val);
                    $val = fixPath(str_replace('/', '', $val));
                } elseif (stripos($key, 'File') !== false) {
                    $val = str_replace('/', '', $val);
                }
                $this->config->$key = $val;
            } else {
                $this->config->$key = $this->config->configFileSettings[$key][0];
            }
        }
        $this->config->pathToRoot = $this->pathToRoot;
        $this->config->lang = $this->config->defaultLanguage;

        $globalParams['logPath'] = $this->config->logPath;
    } // getConfigValues



    //....................................................
    private function loadTemplate()
    {
        $this->page->addBody($this->getTemplate(), true);
    } // loadTemplate



    //....................................................
    private function executeAutoAttr($html)
    {
        $autoAttr = [];
        $autoAttrFiles = $this->page->get('autoAttrFiles');
        foreach (explode(',', $autoAttrFiles) as $file) {
            $this->loadAutoAttrDefinition($file);
        }
        if ($this->autoAttrDef) {
            $autoAttr = $this->autoAttrDef;
        }
        if (isset($this->page->autoAttr)) {
            $str = $this->page->get('autoAttr');
            $a = convertYaml($str);
            $autoAttr = array_merge($autoAttr, $a);
        }
        if (!$autoAttr) {
            return $html;
        }


        $dom = HtmlDomParser::str_get_html($html);

        foreach ($autoAttr as $pattern => $attr) {
            $elems = $dom->find($pattern);

            while (preg_match('/(.*\s)?((\S+)=(\S+))(.*)/', $attr, $m)) {
                $name = $m[3];
                $val = str_replace(["'", '"'], '', $m[4]);
                foreach ($elems as $e) {
                    $e->$name = $val;
                }
                $attr = $m[1].' '.$m[5];
            }

            $class = str_replace('.', ' ', $attr);
            $class = preg_replace('/\s+/', ' ', $class);
            $class = trim($class);

            foreach ($elems as $e) {
                $e->class = trim($e->class.' '.$class);
            }
        }
        $html = $this->tidyHTML5($dom->html() );
        return $html;
    } // executeAutoAttr



	//....................................................
	private function tidyHTML5($html)
    {
        $tidy = '/usr/local/Cellar/tidy-html5/5.4.0/bin/tidy';
        $tidyOptions = ' -q -config /usr/local/Cellar/tidy-html5/5.4.0/options.txt';
        if (!file_exists($tidy)) {
            die("Error: file not found: '$tidy'");
            return $html;
        }
        file_put_contents('tmp.html', $html);
        $html = shell_exec("$tidy $tidyOptions tmp.html" );
        return $html;
    } //



	//....................................................
	private function isRestrictedPage()
	{
		if (isset($this->siteStructure->currPageRec['restricted'])) {
			$lockProfile = $this->siteStructure->currPageRec['restricted'];
			return !$this->auth->checkAdmission($lockProfile);
		}
		return false;
	} // isRestrictedPage



	//....................................................
	private function injectEditor()
	{
		if (!$this->config->enableEditing || !$this->editingMode) {
			return;
		}
		require_once SYSTEM_PATH.'editor.class.php';	
		$ed = new ContentEditor($this->page);
		$ed->injectEditor();
	} // injectEditor



	//....................................................
	private function injectPageSwitcher()
	{
        if ($this->config->pageSwitcher) {
            require_once($this->config->systemPath."page_switcher.php");
        }
	} // injectPageSwitcher



    //....................................................
	private function setTransvars1()
	{
		if ($this->loggedInUser) {
			$this->trans->addVariable('user', $this->loggedInUser, false);			
			$this->trans->addVariable('Log-in', "<a href='?logout'>{{ Logged in as }} <strong>{$this->loggedInUser}</strong></a>");
		} else {
			$linkToThisPage = '~/'.$this->siteStructure->currPage;
			$this->trans->addVariable('Log-in', "<a href='$linkToThisPage?login' class='login-link'>{{ LoginLink }}</a>");
		}

        $this->trans->addVariable('appRoot', $this->pathToRoot);			// e.g. '../'
        $this->trans->addVariable('systemPath', $this->systemPath);		// -> file access path
        $this->trans->addVariable('lang', $this->config->lang);
		if  ($this->localCall || get_url_arg('debug', true)) {
			writeLog('starting debug mode');
        	$this->trans->addVariable('debug_class', ' debug');
		}
	} // setTransvars1



	//....................................................
	private function setTransvars2()
	{
		$page = &$this->page;
		if (isset($page->title)) {
			$this->trans->addVariable('page_title', $page->title, false);
		} else {
			$title = $this->trans->getVariable('page_title');
			$pageName = $this->siteStructure->currPageRec['name'];
			$title = preg_replace('/\$page_name/', $pageName, $title);
			$this->trans->addVariable('page_title', $title, false);
		}
		if ($this->siteStructure) {
            $page->pageName = $pageName = translateToIdentifier($this->siteStructure->currPageRec['name']);
            $this->trans->addVariable('page_name_class', 'page_'.$pageName);
		}
        $_SESSION['pageName'] = $pageName;
    }// setTransvars2



	//....................................................
	private function injectCssFramework()
    {
        $page = $this->page;
        $type = (isset($page->cssFramework)) ? $page->cssFramework : false;
        $type = ($this->config->cssFramework) ? $this->config->cssFramework : $type;
        $type = strtolower($type);

        switch ($type) {
            case 'bootstrap':
                $page->addCssFiles('~sys/third-party/bootstrap4/css/bootstrap.min.css');
                $page->addJqFiles(['~sys/third-party/tether.js/tether.min.js', '~sys/third-party/bootstrap4/js/bootstrap.min.js']);
                $page->addAutoAttrFiles('~/'.CONFIG_PATH.'/bootstrap-auto-attrs.yaml');
                break;

            case 'purecss':
                $page->addCssFiles('~sys/third-party/pure-css/pure-min.css');
                $page->addAutoAttrFiles('~/'.CONFIG_PATH.'/purecss-auto-attrs.yaml');
                break;

            case 'w3css':
            case 'w3.css':
                $page->addCssFiles('~sys/third-party/w3.css/w3.css');
                $page->addAutoAttrFiles('~/'.CONFIG_PATH.'/w3css-auto-attrs.yaml');
                break;

            default:
//                $out = '<!-- No Framework loaded -->';
                $this->config->cssFramework = false;
                break;
        }

    } // injectCssFramework



	//....................................................
	private function runUserInitCode()
	{
	    if (!$this->config->permitUserCode) {   // user-code enabled?
	        return;
        }

		if (file_exists($this->config->userInitCodeFile)) {
			require_once($this->config->userInitCodeFile);
		}
	} // runUserInitCode
	



	//....................................................
	private function getTemplate()
	{
		if (isset($this->page->template)) {
			$template = $this->page->template;
		} elseif (isset($this->siteStructure->currPageRec['template'])) {
			$template = $this->siteStructure->currPageRec['template'];
		} else {
			$template = $this->config->pageTemplateFile;
		}
		$tmplStr = getFile($this->config->configPath.$template);
		if ($tmplStr === false) {
			$this->page->addOverlay("Error: templage file not found: '$template'");
			return '';
		}
		return $tmplStr;
	} //getTemplate
	


	//....................................................
    private function loadHtmlFile($folder, $file)
	{
		$page = &$this->page;
		if (strpos($file, '~/') === 0) {
			$file = substr($file, 2);
		} else {
			$file = $folder.$file;
		}
		$file = $this->config->pagesPath.$file;
		if (file_exists($file)) {
			$html = file_get_contents($file);
			$page->addContent($this->extractHtmlBody($html), true);
		} else {
			$page->addOverride("Requested file not found: '$file'");
		}
		return $page;
	} // loadHtmlFile



	//....................................................
    private function loadFile($loadRaw = false)
	{
		$page = &$this->page;

		if (!$this->siteStructure->currPageRec) {
			$currRec['folder'] = '';
			$currRec['name'] = 'New Page';
		} else {
			$currRec = &$this->siteStructure->currPageRec;
		}
		if (isset($currRec['showthis']) && $currRec['showthis']) {
			$folder = fixPath($currRec['showthis']);
			$this->siteStructure->currPageRec['folder'] = $folder;
		} else {
			$folder = $currRec['folder'];
		}
		if (isset($currRec['file'])) {
			return $this->loadHtmlFile($folder, $currRec['file']);
		}
		if ($folder && !file_exists($this->config->pagesPath.$folder)) {
			$folder = $this->config->pagesPath.$folder;
			$mdFile = $folder.basename(substr($folder, 0, -1)).'.md';
			mkdir($folder, 0777, true);
			file_put_contents($mdFile, "#{$currRec['name']}\n");
		} else {
			$folder = $this->config->pagesPath.$folder;
		}
		$_SESSION['currPagePath'] = $folder;

		$mdFiles = getDir($folder.'*.md');
		
		// Case: no .md file available, but page has sub-pages -> show first sub-page instead
		if (!$mdFiles && isset($currRec[0])) {
			$folder = $currRec[0]['folder'];
			$this->siteStructure->currPageRec['folder'] = $folder;
			$mdFiles = getDir($this->config->pagesPath.$folder.'*.md');
		}
		
		if ($pg = $this->readCache($mdFiles)) {
			$this->page = $pg;
			return $pg;
		}

		$md = new MyMarkdown($this->trans);
		$md->html5 = true;
		$langPatt = '.'.$this->config->lang.'.';
		foreach($mdFiles as $f) {
			$newPage = new Page($this->config);
			if ($this->config->multiLanguageSupport) {
				if (preg_match('/\.\w\w\./', $f) && (strpos($f, $langPatt) === false)) {
					continue;
				}
			}
			$mdStr = getFile($f, true);
			$mdStr = $this->extractFrontmatter($mdStr, $newPage);
			$md->parse($mdStr, $newPage);
			
			$id = $cls = translateToIdentifier(base_name($f, false));
			
			$dataFilename = '';
			if ($this->editingMode) {
				$dataFilename = " data-filename='$f'";
			}
			if ($wrapperClass = $newPage->get('wrapping_class')) {
				$cls .= ' '.$wrapperClass;
			}
			$cls = trim($cls);
			$str = $newPage->get('content');
			$str = "\n\t\t    <section id='section_$id' class='section_$cls'$dataFilename>\n$str\t\t    </section>\n\n";
			$newPage->addContent($str, true);
			$this->page->merge($newPage);
		}
		$html = $page->get('content');
		if ((isset($this->siteStructure->currPageRec['backTickVariables'])) &&
			($this->siteStructure->currPageRec['backTickVariables'] == 'no')) {
			$html = str_replace('`', '&#96;', $html);
			$html = $this->extractHtmlBody($html);
		}
		$page->addContent($html, true);
		$this->writeCache();
        return $page;
	} // loadFile



	//....................................................
    private function prepareImages($html)
	{
        $resizer = new ImageResizer;
        $resizer->provideImages($html);
    } // prepareImages



	//....................................................
    private function extractFrontmatter($str, $page)
	{
		$lines = explode(PHP_EOL, $str);
		$yaml = '';
		if (!preg_match('/^---/', $lines[0])) {
			return $str;
		}
		$i = 1;
		$l = $lines[$i];
		while (($i < sizeof($lines)-1) && (!preg_match('/---/', $l))) {
			$yaml .= $l."\n";
			$i++;
			$l = $lines[$i];
		}
		if ($i == sizeof($lines)-1) {   // case '---' in first line, but no second instance
		    return $str;
        }

		if ($yaml) {
			$yaml = str_replace("\t", '    ', $yaml);
			try {
				$hdr = convertYaml($yaml);
			} catch(Exception $e) {
				die("Error in Yaml-Code: <pre>\n$yaml\n</pre>\n".$e->getMessage());
			}
			if ($hdr) {
				$page->merge( $hdr );
			}
		}
		$lines = array_slice($lines, $i+1);
		$str = implode("\n",  $lines);
		return $str;
	} // extractFrontmatter



	//....................................................
    private function extractHtmlBody($html)
    {
		if (((($p1 = strpos($html, "---")) !== false) && (($p1 == 0) || (substr($html,$p1-1,1) == "\n")))) {
			$p1 = strpos($html, "\n", $p1+3);
			if ($p2 = strpos($html, "\n---", $p1+3)) {
				$head = substr($html, $p1+1, $p2-$p1-1);
				$values = convertYaml($head);
				$this->page->merge($values);
				$html = substr($html, $p2+4);
			}
		}
		if (($p1=strpos($html, '<body')) !== false) {
			$p1 = strpos($html, '>', $p1);
			if (($p2=strpos($html, '</body')) !== false) {
				$html = trim(substr($html, $p1+1, $p2-$p1-1));
			}
		}
		return $html;
	} // extractHtmlBody



	//....................................................
    private function writeCache()
    {
		if ($this->page->get('overlay') || $this->page->get('override')) {
			return false;
		}
		if ($this->config->caching) {
			if (!file_exists($this->config->cachePath)) {
				mkdir($this->config->cachePath, 0770);
			}
			$cache = $this->page->getEncoded(); //json_encode($this->page);
			$cacheFile = $this->config->cachePath.$this->cacheFileName();
			
			file_put_contents($cacheFile, $cache);
		}
	} // writeCache



	//....................................................
    private function readCache($mdFiles)
    {
		$this->isCached = false;
		if ($this->page->get('overlay') || $this->page->get('override')) {
			return false;
		}
		if ($this->config->caching) {
			$cacheFile = $this->config->cachePath.$this->cacheFileName();
			if (!file_exists($cacheFile)) {
				return false;
			}
			$fTime = filemtime($cacheFile);
			$clean = true;
			foreach($mdFiles as $f) {
				if ($fTime < filemtime($f)) {
					$clean = false;
					break;
				}
			}
			if ($clean) {
				$pg = unserialize(file_get_contents($cacheFile));
				$this->isCached = true;
				return $pg;
			}
		}
		return false;
	} // readCache



	//....................................................
    private function clearCache()
    {
		$dir = glob($this->config->cachePath.'*');
		foreach($dir as $file) {
			unlink($file);
		}
	} // clearCache



	//....................................................
    private function cacheFileName()
    {
		$currRec = &$this->siteStructure->currPageRec;
		return substr(str_replace('/', '_', $currRec['folder']), 0, -1).'.dat';
	} // cacheFileName




	//....................................................
	private function handleUrlArgs()
	{
		if (isset($_GET['lang'])) {				// lang
			$lang = get_url_arg('lang');
			$this->config->lang = $lang;
			$_SESSION['lang'] = $lang;
		} elseif (isset($_SESSION['lang'])) {
		    if (!$_SESSION['lang']) {
                $_SESSION['lang'] = $this->config->defaultLanguage;
            }
			$this->config->lang = $_SESSION['lang'];
		}



		if (isset($_GET['reset'])) {			// reset (cache)
			$this->clearCache();
			$_SESSION['user'] = false;
			$_SESSION['timer'] = false;
			$_SESSION['editingMode'] = false;
			$_SESSION['nc'] = false;
		}



		if (isset($_GET['logout'])) {			// logout
			$this->userRec = false;
			$_SESSION['user'] = false;
		}



		if (isset($_GET['nc'])) {				// nc
			$nc = get_url_arg('nc', true);
			$this->config->caching = !$nc;
			$_SESSION['nc'] = $nc;
		} elseif (isset($_SESSION['nc']) && $_SESSION['nc']) {
			$this->config->caching = false;
		} else {
			$this->config->caching = !$this->localCall;	// disable caching on localhost, unless nc arg provided
		}



		if (isset($_GET['convert']) && ($this->localCall)) {	// convert (pw to hash)
			$password = get_url_arg('convert', true);
			die(password_hash($password, PASSWORD_DEFAULT));
		}



		$editingMode = getUrlArgStatic('edit', 'editingMode');		// edit
		if ($editingMode) {
		
			$permitted = $this->auth->checkRole('editor');
			if ($permitted || $this->localCall){
				$this->editingMode = true;
				$this->config->pageSwitcher = false;
				$_SESSION['editingMode'] = $editingMode;
				$this->config->caching = false;
				$_SESSION['nc'] = true;
			} else {
				$this->page->addMessage('{{ need to login to edit }}');
				$_SESSION['editingMode'] = false;
                $this->editingMode = false;
			}
		}



		$this->timer = getUrlArgStatic('timer');				// timer
	} // handleUrlArgs



	//....................................................
	private function handleUrlArgs2()
	{
		if (isset($_GET['printall'])) {							// printall
			die( $this->printall() );
		}


		
		if (isset($_GET['login']) && !$_SESSION['user']) {
			if ($this->localCall || (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == 'on')) {
				$overridePage = $this->auth->authForm($this->auth->message);
				$this->page->merge($overridePage);
			} else {
				$this->page->addOverride("{{ Warning insecure connection }}");
			}
		}



		if ($this->localCall && (isset($_GET['info']) || isset($_GET['list']))) {	// info
			$this->trans->printAll();
			exit;
		}


		
		if ($this->localCall && (isset($_GET['config']))) {	// config
			$str = $this->renderConfigHelp();
            $this->page->addOverlay($str);
		}



		if ($this->localCall && isset($_GET['help'])) {		// help
			$overlay = <<<EOT
<h1>Lizzy Help</h1>
<pre>
Available URL-commands:

<a href='?help'>?help</a>		this message
<a href='?list'>?list</a>		list of transvars and macros()
<a href='?config'>?config</a>		list configuration-items in the config-file
<a href='?edit'>?edit</a>		start editing mode
<a href='?convert=pw'>?convert=pw</a>	convert password to hash
<a href='?login'>?login</a>		login
<a href='?logout'>?logout</a>		logout
<a href='?reset'>?reset</a>		clear cache
<a href='?nc'>?nc</a>		supress caching (?nc=false to enable caching again)
<a href='?lang=xy'>?lang=xy</a>	switch to given language (e.g. '?lang=en')
<a href='?timer'>?timer</a>		switch timer on or off
<a href='?printall'>?printall</a>	show all pages in one
<a href='?touch'>?touch</a>		emulate touch mode

post('md')=> MD-source		returns compiled markdown
</pre>
EOT;
			$this->page->addOverlay($overlay);
		}

		if ($_SESSION['user'] || $this->localCall) {
			if ($_SESSION['editingMode']) {
				$this->trans->addVariable('toggle-edit-mode', "<a href='?edit=false'>{{ turn edit mode off }}</a> | ");
			} else {
				$this->trans->addVariable('toggle-edit-mode', "<a href='?edit'>{{ turn edit mode on }}</a> | ");
			}
		}


		
		if (isset($_GET['touch'])) {			// touch
			$this->trans->addVariable('debug_class', ' touch small-screen');
		}



//		if (isset($_GET['auto'])) {			// auto
//			$cwd = getcwd();
//			$cmd = $cwd.'/_lizzy/third-party/watch-folder.sh';
//			shell_exec("/Users/sto/bin/cmd_k $cmd");
//		}
		
	} // handleUrlArgs2



	//....................................................
	private function renderMD()
	{
		if (isset($_POST['md'])) {
            $mdStr = $_POST['md'];
//		} elseif (!$mdStr) { //??? what was that for...?
        } else {
			$mdStr = '';
		}
		$doSave = (isset($_GET['save']));
		if ($doSave && ($filename = get_post_data('filename'))) {
			$permitted = $this->auth->checkRole('editor');
			if ($permitted || $this->localCall) {
				if (preg_match("|^{$this->config->pagesPath}(.*)\.md$|", $filename)) {
					$this->storeFile($filename, $mdStr);
				} else {
					die("illegal file name: '$filename'");
				}
			} else {
				die("Sorry, you have not permission to modify files on the server.");
			}
		}
		$md = new MyMarkdown();
//		$md->html5 = true;
//		$options = '';
		$pg = new Page;
		$mdStr = $this->extractFrontmatter($mdStr, $pg);
		$md->parse($mdStr, $pg);
		$out = $pg->get('body');
		if (isset($_GET['html'])) {
			$out = "<pre>\n".htmlentities($out)."\n</pre>\n";
		}
		if ($mdStr) {
			$out = <<<EOT
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8" />
	<title>Markdown</title>
</head>
<body>
$out
</body>
</html>

EOT;
		}
		exit($out);
	} // renderMD



	//....................................................
	private function renderConfigHelp()
	{
        $configItems = $this->config->configFileSettings;
        $out = "<h1>Lizzy Config-Items and their Purpose:</h1>\n<dl>\n";
        foreach ($configItems as $name => $rec) {
            $out .= "<dt>'<strong>$name</strong>':</dt><dd> {$rec[1]}</dd>\n";
        }
        $out .= "</dl>\n";
        return $out;
    } // renderConfigHelp



	//....................................................
	private function storeFile($filename, $content)
	{
		if (file_exists($filename)) {
			preparePath($this->config->recycleBin);
			$recycleFile = $this->config->recycleBin.str_replace('/', '_', $filename). ' ['.date('Y-m-d,H.i.s').']';
			rename($filename, $recycleFile);
		}
		file_put_contents($filename, $content);
	} // storeFile



	//....................................................
	private function printall()
	{
		if (!($title = $this->trans->getVariable('print_all_title'))) {
			if (!($title = $this->trans->getVariable('page_title'))) {
				$title = '';
			}
		}
		$pages = '';
		foreach($this->siteStructure->getSiteList() as $i => $rec) {
			$url = $rec['folder'];
			if (!$url) {
				$url = './';
			}
			$pages .= "\t<iframe src='$url'></iframe>\n";
			$pages .= "\t<div style='page-break-after: always;'></div>\n\n";
		}

		$html = <<<EOT
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8" />
	<title>$title</title>
	<link href="css/printall.css" rel="stylesheet" type="text/css">
</head>
<body>

$pages

</body>
</html>

EOT;
		return $html;
	} // printall



    //....................................................
    private function checkInstallation()
    {
        if (file_exists(DEFAULT_CONFIG_FILE)) {
            return;
        }
        echo "<pre>";
        echo shell_exec('/bin/sh _lizzy/_install/install.sh');
        echo "</pre>";
        exit;
    } // checkInstallation
	
} // class WebPage
