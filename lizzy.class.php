
<?php
/*
 *	Lizzy - main class
 *
 *	Main Class *
*/

define('CONFIG_PATH',           'config/');
define('SYSTEM_PATH',           basename(dirname(__FILE__)).'/'); // _lizzy/
define('DEFAULT_CONFIG_FILE',   CONFIG_PATH.'config.yaml');
define('SUBSTITUTE_UNDEFINED',  1); // -> '{|{'     => delayed substitution within trans->render()
define('SUBSTITUTE_ALL',        2); // -> '{||{'    => variables translated after cache-retrieval

define('CACHE_PATH',            '.#cache/');
define('LOGS_PATH',             '.#logs/');
define('SYSTEM_RECYCLE_BIN_PATH','~/.#recycleBin/');
define('RECYCLE_BIN_PATH',      '~page/.#recycleBin/');

define('ERROR_LOG',             LOGS_PATH.'errlog.txt');
define('ERROR_LOG_ARCHIVE',     LOGS_PATH.'errlog_archive.txt');
define('VERSION_CODE_FILE',     LOGS_PATH.'version-code.txt');
define('BROWSER_SIGNATURES_FILE',     LOGS_PATH.'browser-signatures.txt');
define('UNKNOWN_BROWSER_SIGNATURES_FILE',     LOGS_PATH.'unknown-browser-signatures.txt');
define('LOGIN_LOG_FILENAME',    'logins.txt');
define('FAILED_LOGIN_FILE',     CACHE_PATH.'_failed-logins.yaml');
define('HACK_MONITORING_FILE',  CACHE_PATH.'_hack_monitoring.yaml');
define('ONETIME_PASSCODE_FILE', CACHE_PATH.'_onetime-passcodes.yaml');
define('HACKING_THRESHOLD',     10);
define('HOUSEKEEPING_FILE',     CACHE_PATH.'_housekeeping.txt');

use Symfony\Component\Yaml\Yaml;
//use voku\helper\HtmlDomParser;

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
require_once SYSTEM_PATH.'uadetector.class.php';

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
	private $reqPagePath;
    private $trans;
	private $siteStructure;
	private $editingMode = false;
	private $timer = false;



	//....................................................
    public function __construct()
    {
		global $globalParams;

        $this->dailyHousekeeping();
		$this->init();
		$this->setupErrorHandling();

        $globalParams['autoForceBrowserCache'] = $this->config->autoForceBrowserCache;

        if ($this->config->sitemapFile) {
			$this->siteStructure = new SiteStructure($this->config, $this->reqPagePath);
            $this->currPage = $this->reqPagePath = $this->siteStructure->currPage;

			if (isset($this->siteStructure->currPageRec['showthis'])) {
				$this->pagePath = $this->siteStructure->currPageRec['showthis'];
			} else {
				$this->pagePath = $this->siteStructure->currPageRec['folder'];
			}
			$globalParams['pagePath'] = $this->pagePath;                    // excludes pages/
			$this->pathToPage = $this->config->pagesPath.$this->pagePath;   //  includes pages/
			$globalParams['pathToPage'] = $this->pathToPage;

			$this->pageRelativePath = $this->pathToRoot.$this->pagePath;

			$this->trans = new Transvar($this->config, array(SYSTEM_PATH.'config/sys_vars.yaml', CONFIG_PATH.'user_variables.yaml'), $this->siteStructure); // loads static variables
			$this->trans->addVariable('next_page', "<a href='~/{$this->siteStructure->nextPage}'>{{ nextPageLabel }}</a>");
			$this->trans->addVariable('prev_page', "<a href='~/{$this->siteStructure->prevPage}'>{{ prevPageLabel }}</a>");

		} else {
			$this->siteStructure = new SiteStructure($this->config, ''); //->list = false;
			$this->currPage = '';
			$this->trans = new Transvar($this->config, array(SYSTEM_PATH.'config/sys_vars.yaml', CONFIG_PATH.'user_variables.yaml'), $this->siteStructure); // loads static variables
            $globalParams['pagePath'] = '';
            $this->pathToPage = $this->config->pagesPath;
            $globalParams['pathToPage'] = $this->pathToPage;
            $this->pageRelativePath = '';
            $this->pagePath = '';
        }
        $this->dailyHousekeeping(2);
    } // __construct




	//....................................................
    public function render()
    {
		if ($this->timer) {
			startTimer();
		}

		$this->selectLanguage();

		$page = &$this->page;

		$accessGranted = $this->checkAdmissionToCurrentPage();   // override page with login-form if required

        $this->setTransvars1();

        if ($accessGranted) {

//		if ($html = getCache()) {
//            $html = $this->trans->render($html, $this->config->lang, SUBSTITUTE_ALL);
//            return $html;
//        }

            $this->loadFile();        // get content file
        }
        $this->injectPageSwitcher();

        $this->warnOnErrors();

        $this->setTransvars2();

        $this->injectCssFramework();

        if ($accessGranted) {
            $this->runUserInitCode();
        }
        $this->loadTemplate();

        if ($accessGranted) {
            $this->injectEditor();

            $this->trans->doUserComputedVariables();
        }

        $this->handleUrlArgs2();

        $this->sendAccessLinkMail();

        // now, compile the page from all its components:
        $html = $this->trans->render($page, $this->config->lang);


		$this->prepareImages($html);

		$html = $this->applyForcedBrowserCacheUpdate($html);

        $html = resolveAllPaths($html, true);	// replace ~/, ~sys/, ~page/ with actual values

//        $html = $this->executeAutoAttr($html);

//        if ($accessGranted) {
//             writeCache();
//        }

        $html = $this->trans->render($html, $this->config->lang, SUBSTITUTE_ALL);

        if ($this->timer) {
			$this->debugMsg = readTimer();
		}

        return $html;
    } // render




    //....................................................
    private function applyForcedBrowserCacheUpdate($html)
    {
        // forceUpdate adds some url-arg to css and js files to force browsers to reload them
        // Config-param 'forceBrowserCacheUpdate' forces this for every request
        // 'autoForceBrowserCache' only forces reload when Lizzy detected changes in those files

        if (isset($_SESSION['lizzy']['reset']) && $_SESSION['lizzy']['reset']) {  // Lizzy has been reset, now force browser to update as well
            $forceUpdate = getVersionCode( true );
            unset($_SESSION['lizzy']['reset']);

        } elseif ($this->config->forceBrowserCacheUpdate) {
            $forceUpdate = getVersionCode( true );

        } elseif ($this->config->autoForceBrowserCache) {
            $forceUpdate = getVersionCode();
        } else {
            return $html;
        }
        if ($forceUpdate) {
            $html = preg_replace('/(\<link\s+href=(["])[^"]+)"/m', "$1$forceUpdate\"", $html);
            $html = preg_replace("/(\<link\s+href=(['])[^']+)'/m", "$1$forceUpdate'", $html);

            $html = preg_replace('/(\<script\s+src=(["])[^"]+)"/m', "$1$forceUpdate\"", $html);
            $html = preg_replace("/(\<script\s+src=(['])[^']+)'/m", "$1$forceUpdate'", $html);
        }
        return $html;
    } // applyForcedBrowserCacheUpdate




    //....................................................
    private function init()
    {
        $configFile = DEFAULT_CONFIG_FILE;
        if (file_exists($configFile)) {
            $this->configFile = $configFile;
        } else {
            fatalError("Error: file not found: ".$configFile, 'File: '.__FILE__.' Line: '.__LINE__);

        }

        session_start();
        $this->sessionId = session_id();
        $this->getConfigValues(); // from $this->configFile

        register_shutdown_function('handleFatalPhpError');

        $this->config->appBaseName = base_name(rtrim(trunkPath(__FILE__, 1), '/'));
        if ($this->config->sitemapFile) {
            $sitemapFile = $this->config->configPath . $this->config->sitemapFile;

            if (file_exists($sitemapFile)) {
                $this->config->sitemapFile = $sitemapFile;
            } else {
                $this->config->sitemapFile = false;
            }
        }

        if ($this->config->multiLanguageSupport) {
            $this->config->supportedLanguages = explode(',', str_replace(' ', '',$this->config->supportedLanguages));
        }

        $this->page = new Page($this->config);

        if (isLocalCall()) {
            if (($lc = getUrlArgStatic('localcall')) !== null) {
                $this->localCall = $lc;
            } else {
                $this->localCall = true;
            }
        } else {
            $this->localCall = false;
            setStaticVariable('localcall', false);
        }

        $this->config->isLocalhost = $this->localCall;

        $this->auth = new Authentication($this->config->configPath.$this->config->usersFile, $this->config);

        $this->analyzeHttpRequest();
        $this->httpSystemPath = $this->pathToRoot.SYSTEM_PATH;


        $res = $this->auth->authenticate();
        if (is_array($res)) {   // array means user is not logged in yet but needs the one-time access-code form
            $this->page->addOverlay($res[1]);
            $this->loggedInUser = false;
        } else {
            $this->loggedInUser = $res;
        }

        $this->config->isPrivileged = false;
        if ($this->auth->isPrivileged()) {
            $this->config->isPrivileged = true;

        } elseif (file_exists(HOUSEKEEPING_FILE)) {  // suppress error msg output if not local host or admin or editor
            ini_set('display_errors', '0');
        }

        $this->handleUrlArgs();

        $this->saveEdition();  // if user chose to activate a previous edition of a page

        $cliarg = getCliArg('compile');
        if ($cliarg) {
            $this->renderMD();  // arg 'save' handled here if supplied together

        } else {
            $cliarg = getCliArg('save');
            if ($cliarg) {
                $this->saveSitemapFile($sitemapFile);
            }
        }

        $this->scss = new SCssCompiler($this->config->stylesPath.'scss/*.scss', $this->config->stylesPath, $this->localCall);
        $this->scss->compile( $this->config->forceBrowserCacheUpdate );

//        $this->loadAutoAttrDefinition();

    } // init



    //....................................................
    private function setupErrorHandling()
    {
        global $globalParams;
        $globalParams['errorLogFile'] = '';
        if ($this->auth->checkGroupMembership('editors') || $this->localCall) {     // set displaying errors on screen:
            $old = ini_set('display_errors', '1');  // on
            error_reporting(E_ALL);

        } elseif (file_exists(HOUSEKEEPING_FILE)) {
            $old = ini_set('display_errors', '0');  // off
            error_reporting(0);
        }
        if ($old === false) {
            fatalError("Error setting up error handling... (no kidding)", 'File: '.__FILE__.' Line: '.__LINE__);
        }
//??? not working:
        if ($this->config->errorLogging && !file_exists(ERROR_LOG_ARCHIVE)) {
            $errorLogPath = dirname(ERROR_LOG_ARCHIVE).'/';
            $errorLogFile = ERROR_LOG_ARCHIVE;

            // make error log folder:
            preparePath($errorLogPath);
            if (!is_writable($errorLogPath)) {
                die("Error: no write permission to create error log folder '$errorLogPath'");
            }

            // make error archtive file and check
            touch($errorLogFile);
            if (!file_exists($errorLogFile) || !is_writable($errorLogPath)) {
                die("Error: unable to create error log file '$errorLogPath' - probably access rights are not ");
            }

            // make error log file, check and delete immediately
            touch(ERROR_LOG);
            if (!file_exists(ERROR_LOG) || !is_writable(ERROR_LOG)) {
                die("Error: unable to create error log file '".ERROR_LOG."' - probably access rights are not ");
            }
            unlink(ERROR_LOG);

            ini_set("log_errors", 1);
            ini_set("error_log", $errorLogFile);
            //error_log( "Error-logging started" );

            $globalParams['errorLogFile'] = ERROR_LOG;
        }
    } // setupErrorHandling




    //....................................................
    private function checkInsecureConnection()
    {
        global $globalParams;
        if (!$this->localCall && !(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == 'on')) {
            $url = str_replace('http://', 'https://', $globalParams['pageUrl']);
            $this->page->addMessage("{{ Warning insecure connection }}<br />{{ Please switch to }}: <a href='$url'>$url</a>");
            //??? reloadAgent($url);
            return false;
        }
        return true;
    } // checkInsecureConnection



    //....................................................
    private function checkAdmissionToCurrentPage()
    {
        if ($reqGroups = $this->isRestrictedPage()) {     // handle case of restricted page
            $this->checkInsecureConnection();
            if (!$this->auth->checkGroupMembership( $reqGroups )) {
                $authPage = $this->auth->renderForm('{{ page requires login }}');
                $this->page->addOverride($authPage->get('override'), true);   // override page with login form
                $this->page->addJQ($authPage->get('jq'), true);   // override page with login form
                $this->page->addCss($authPage->get('css'), true);   // override page with login form
                return false;
            }
            setStaticVariable('isRestrictedPage', $this->loggedInUser);
        } else {
            setStaticVariable('isRestrictedPage', false);
        }
        return true;
    } // checkAdmissionToCurrentPage



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
    // pathToPage:      filesystem forward-path from appRoot to requested folder, e.g. 'pages/contact/ (-> includes pages/)
    // pathToRoot:      upward-path from requested folder to appRoot, e.g. ../
    // redirectedPath:  if requests get redirected by .htaccess, this is the skipped folder(s), e.g. 'now_active/'

    // $globalParams:   -> pagePath, pathToRoot, redirectedPath
    // $_SESSION:       -> userAgent, pageName, currPagePath, lang

        global $globalParams, $pathToRoot;

        $requestUri     = (isset($_SERVER["REQUEST_URI"])) ? rawurldecode($_SERVER["REQUEST_URI"]) : '';
        $absAppRoot     = dir_name($_SERVER['SCRIPT_FILENAME']);
        $scriptPath     = dir_name($_SERVER['SCRIPT_NAME']);
        $appRoot        = fixPath(commonSubstr( $scriptPath, dir_name($requestUri), '/'));
        $redirectedPath = ($h = substr($scriptPath, strlen($appRoot))) ? $h : '';
        $requestedPath  = dir_name($requestUri);
        $ru = preg_replace('/\?.*/', '', $requestUri); // remove opt. '?arg'
        $requestedPagePath = dir_name(substr($ru, strlen($appRoot)));
        if ($requestedPagePath == '.') {
            $requestedPagePath = '';
        }
        $pagePath       = substr($requestedPath, strlen($appRoot));
        $pathToRoot = str_repeat('../', sizeof(explode('/', $requestedPagePath)) - 1);
        $globalParams['pagePath'] = $pagePath;
        $_SESSION['lizzy']['pagePath'] = $pagePath;
        $globalParams['pathToPage'] = $this->config->pagesPath.$pagePath;
        $_SESSION['lizzy']['pathToPage'] = $this->config->pagesPath.$pagePath;

        $globalParams['pathToRoot'] = $pathToRoot;  // path from requested folder to root (= ~/), e.g. ../
        $globalParams['host'] = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].'/';
        $this->pageUrl = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$requestedPath;
        $globalParams['pageUrl'] = $this->pageUrl;

        $pagePath = $this->auth->validateOnetimeAccessCode($pagePath);

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
        list($ua, $isLegacyBrowser) = $this->getBrowser();
        $globalParams['userAgent'] = $ua;
        $_SESSION['lizzy']['userAgent'] = $ua;
        $globalParams['legacyBrowser'] = ($isLegacyBrowser) ? 'yes' : 'no';
        $globalParams['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];

        // check whether to support legacy browsers -> load jQ version 1
        if ($this->config->supportLegacyBrowsers) {
            $this->legacyBrowser = true;

        } else {
            $overrideLegacy = getUrlArgStatic('legacy');
            if ($overrideLegacy === null) {
                $this->legacyBrowser = $isLegacyBrowser;
            } else {
                $this->legacyBrowser = $overrideLegacy;
            }
        }


        $this->reqPagePath = $pagePath;
        //$globalParams['pagePath']   // forward-path from app-root to requested folder -> excludes pages/
        $globalParams['absAppRoot'] = $absAppRoot;  // path from FS root to base folder of app, e.g. /Volumes/...
        $globalParams['appRoot'] = $appRoot;  // path from docRoot to base folder of app, e.g. 'on/'
        $globalParams['pathToRoot'] = $pathToRoot;  // path from requested folder to root (= ~/), e.g. ../
        $globalParams['redirectedPath'] = $redirectedPath;  // the part that is optionally skippped by htaccess
        $globalParams['legacyBrowser'] = $this->legacyBrowser;
        $globalParams['localCall'] = $this->localCall;

        setStaticVariable('appRootUrl', $globalParams['host'].$appRoot);
        setStaticVariable('absAppRoot', $absAppRoot);

        if ($this->config->logClientAccesses) {
            writeLog('[' . getClientIP(true) . "] $ua" . (($this->legacyBrowser) ? " (Legacy browser!)" : ''));
        }
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


/*
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
*/


//	//....................................................
//	private function tidyHTML5($html)
//    {
//        $tidy = '/usr/local/Cellar/tidy-html5/5.4.0/bin/tidy';
//        $tidyOptions = ' -q -config /usr/local/Cellar/tidy-html5/5.4.0/options.txt';
//        if (!file_exists($tidy)) {
//            fatalError("Error: file not found: '$tidy'", 'File: '.__FILE__.' Line: '.__LINE__);
//            return $html;
//        }
//        file_put_contents('tmp.html', $html);
//        $html = shell_exec("$tidy $tidyOptions tmp.html" );
//        return $html;
//    } //



	//....................................................
	private function isRestrictedPage()
	{
		if (isset($this->siteStructure->currPageRec['restricted'])) {
			$lockProfile = $this->siteStructure->currPageRec['restricted'];
			return $lockProfile;
		}
		return false;
	} // isRestrictedPage



	//....................................................
	private function saveEdition()
	{
        $admission = $this->auth->checkGroupMembership('editors');
        if (!$admission) {
            return;
        }

        $edSave = getUrlArg('ed-save', true);
        if ($edSave !== null) {
            require_once SYSTEM_PATH . 'page-source.class.php';
            PageSource::saveEdition();  // if user chose to activate a previous edition of a page
        }
    } // saveEdition



	//....................................................
	private function injectEditor()
	{
        $admission = $this->auth->checkGroupMembership('editors');
        if (!$admission) {
            return;
        }

		if (!$this->config->enableEditing || !$this->editingMode) {
			return;
		}
		require_once SYSTEM_PATH.'editor.class.php';
        require_once SYSTEM_PATH.'page-source.class.php';
        $ed = new ContentEditor($this->page);
		$ed->injectEditor($this->pagePath);
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
		    if (isset($_SESSION['lizzy']['userDisplayName'])) {
		        $username = $_SESSION['lizzy']['userDisplayName'];
            } else {
		        $username = $this->loggedInUser;
            }
			$this->trans->addVariable('user', $username, false);
			$this->trans->addVariable('Log-in', "<a href='?logout'>{{ Logged in as }} <strong>$username</strong></a>");
		} else {
			$linkToThisPage = '~/'.$this->siteStructure->currPage;
			$this->trans->addVariable('Log-in', "<a href='$linkToThisPage?login' class='login-link'>{{ LoginLink }}</a>");
		}

        $this->trans->addVariable('pageUrl', $this->pageUrl);
        $this->trans->addVariable('appRoot', $this->pathToRoot);			// e.g. '../'
        $this->trans->addVariable('systemPath', $this->systemPath);		// -> file access path
        $this->trans->addVariable('lang', $this->config->lang);


		if  ($this->localCall || getUrlArgStatic('debug')) {
            if  (!$this->localCall) {   // log only on non-local host
                writeLog('starting debug mode');
            }
        	$this->trans->addVariable('debug_class', ' debug');
		}


		if ($this->config->multiLanguageSupport) {
            $supportedLanguages = $this->config->supportedLanguages;
            $out = '';
            foreach ($supportedLanguages as $lang) {
                if ($lang == $this->config->lang) {
                    $out .= "<span class='active-lang $lang'>{{ select $lang }}</span>";
                } else {
                    $out .= "<span class='$lang'><a href='?lang=$lang'>{{ select $lang }}</a></span>";
                }
            }
            $out = "<div class='lang-selection'>$out</div>";
            $this->trans->addVariable('lang-selection', $out);
        } else {
            $this->trans->addVariable('lang-selection', '');
        }
	} // setTransvars1



	//....................................................
	private function setTransvars2()
	{
        global $globalParams;
        $page = &$this->page;
		if (isset($page->title)) {                                  // page_title
			$this->trans->addVariable('page_title', $page->title, false);
		} else {
			$title = $this->trans->getVariable('page_title');
			$pageName = $this->siteStructure->currPageRec['name'];
			$title = preg_replace('/\$page_name/', $pageName, $title);
			$this->trans->addVariable('page_title', $title, false);
		}

		if ($this->siteStructure) {                                 // page_name_class
            $page->pageName = $pageName = translateToIdentifier($this->siteStructure->currPageRec['name']);
            $pagePathClass = ' path_'.str_replace('/', '--', $this->pagePath);
            $pagePathClass = rtrim($pagePathClass, '--');
            $this->trans->addVariable('page_name_class', 'page_'.$pageName.$pagePathClass);
		}
        setStaticVariable('pageName', $pageName);

    }// setTransvars2




	//....................................................
	private function warnOnErrors()
    {
        global $globalParams;
        if ($this->config->enableEditing && ($this->auth->checkGroupMembership('editors'))) {
            if ($globalParams['errorLogFile'] && file_exists($globalParams['errorLogFile'])) {
                $logFileName = $globalParams['errorLogFile'];
                $logMsg = file_get_contents($logFileName);
                $logArchiveFileName = str_replace('.txt', '', $logFileName)."_archive.txt";
                file_put_contents($logArchiveFileName, $logMsg, FILE_APPEND);
                unlink($logFileName);
                $logMsg = shieldMD($logMsg);
                $this->page->addMessage("Attention: Errors occured, see error-log file!\n$logMsg");
            }
        }
    } // warnOnErrors



	//....................................................
	private function injectCssFramework()
    {
        $page = $this->page;
        $type = (isset($page->cssFramework)) ? $page->cssFramework : false;
        $type = ($this->config->cssFramework) ? $this->config->cssFramework : $type;
        $type = strtolower($type);

        switch ($type) {
            case 'bootstrap':
                $page->addCssFiles('BOOTSTRAP_CSS');
                $page->addJqFiles(['TETHER', 'BOOTSTRAP']);
                $page->addAutoAttrFiles('BOOTSTRAP_ATTR');
                break;

            case 'purecss':
                $page->addCssFiles('PURECSS_CSS');
                $page->addAutoAttrFiles('PURECSS_ATTR');
                break;

            case 'w3css':
            case 'w3.css':
                $page->addCssFiles('W3CSS_CSS');
                $page->addAutoAttrFiles('W3CSS_ATTR');
                break;

            default:
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
        global $globalParams;
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

        $folder = $this->config->pagesPath.$folder;
		$this->handleMissingFolder($folder);

		$mdFiles = getDir($folder.'*.{md,txt}');

		// Case: no .md file available, but page has sub-pages -> show first sub-page instead
		if (!$mdFiles && isset($currRec[0])) {
			$folder = $currRec[0]['folder'];
			$this->siteStructure->currPageRec['folder'] = $folder;
			$mdFiles = getDir($this->config->pagesPath.$folder.'*.{md,txt}');
		}
		
		if ($pg = $this->readCache($mdFiles)) {
			$this->page = $pg;
			return $pg;
		}

        $handleEditions = false;
        if (getUrlArg('ed', true) && $this->auth->checkGroupMembership('editors')) {
            require_once SYSTEM_PATH.'page-source.class.php';
            $handleEditions = true;
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
            $globalParams['lastLoadedFile'] = $f;
			$ext = fileExt($f);

            if ($handleEditions) {
                $mdStr = PageSource::getFileOfRequestedEdition($f);
            } else {
                $mdStr = getFile($f, true);
            }
			$mdStr = $this->extractFrontmatter($mdStr, $newPage);

            if ($ext == 'md') {             // it's an MD file, convert it
                $md->parse($mdStr, $newPage);
            } elseif ($mdStr) {                                        // it's a TXT file, wrap it in <pre>
                $newPage->addContent("<pre>$mdStr\n</pre>\n");
            } else {
                continue;
            }
			
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
			$wrapperTag = $newPage->get('wrapperTag');
			$str = "\n\t\t    <$wrapperTag id='section_$id' class='section_$cls'$dataFilename>\n$str\t\t    </$wrapperTag>\n\n";
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
    private function handleMissingFolder($folder)
    // if a folder is missing when rendering a page, Lizzy tries to guess whether the folder may have been moved from another location
	{
	    if ($this->loggedInUser || $this->localCall) {

            if (!file_exists($folder)) {
                $f = basename($folder);
                $folders = getDirDeep('pages/', true, true);
                $pagesPath = $this->config->pagesPath;
                if (isset($folders[$f])) { // folder exists somewhere else, moved?
                    if (($mf=getUrlArg('mvfolder')) == 'true') {
                        $oldPath = $folders[$f];
                        rename($oldPath, $folder);

                    } elseif ($mf == 'false') { // create new folder
                        $mdFile = $folder . basename(substr($folder, 0, -1)) . '.md';
                        mkdir($folder, 0777, true);
                        $name = $this->siteStructure->currPageRec['name'];
                        file_put_contents($mdFile, "# $name\n");

                    } else {        // ask admin whether to move folder
                        $out = <<<EOT

::: style:'border: 1px solid red; background: #800;  padding: 2em;'

# Page moved?
The requested page folder "$folder/" does not exist.

However it appears to exist in a different location within the sitemap. Has it been moved?

Previously: {{tab(7em)}} ``$pagesPath$f/``  
New: {{tab}} ``$folder``

{{ vgap }}

Would you like to move the folder?

[yes](?mvfolder=true) {{ space }} [no, prepare a new one](?mvfolder=false)

:::

EOT;
                        $this->page->addOverride($out);
                    }
                } else {
                    $mdFile = $folder . basename(substr($folder, 0, -1)) . '.md';
                    mkdir($folder, 0777, true);
                    $name = $this->siteStructure->currPageRec['name'];
                    file_put_contents($mdFile, "# $name\n");
                }
            }

        }
    } // handleMissingFolder



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
		if (!preg_match('/^---/', $lines[0]) || (sizeof($lines) < 2)) {
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
                fatalError("Error in Yaml-Code: <pre>\n$yaml\n</pre>\n".$e->getMessage(), 'File: '.__FILE__.' Line: '.__LINE__);
			}
			if ($hdr && is_array($hdr)) {
				$page->merge( $hdr );
			} else {
                fatalError("Error in Yaml-Code: <pre>\n$yaml\n</pre>\n", 'File: '.__FILE__.' Line: '.__LINE__);
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
        if (getUrlArg('reset')) {			            // reset (cache)
            $this->clearCache();
            unset($_SESSION['lizzy']);                      // reset SESSION data
            $_SESSION['lizzy']['reset'] = true;
            $this->userRec = false;

            if (file_exists(ERROR_LOG_ARCHIVE)) {   // clear error log
                unlink(ERROR_LOG_ARCHIVE);
            }
            reloadAgent();
        }


		if (getUrlArg('logout')) {	// logout
            $this->userRec = false;
            $this->auth->logout();
            reloadAgent(); // reload to get rid of url-arg ?logout
		}


        if ($nc = getStaticVariable('nc')) {		// nc
            $this->config->caching = !$nc;
        }

        $this->timer = getUrlArgStatic('timer', true);				// timer


        //====================== the following is restricted to editors and admins:
        if ($editingPermitted = $this->auth->checkGroupMembership('editors')) {
            if (isset($_GET['convert'])) {                                  // convert (pw to hash)
                $password = getUrlArg('convert', true);
                exit( password_hash($password, PASSWORD_DEFAULT) );
            }

            $editingMode = getUrlArgStatic('edit', false, 'editingMode');// edit
            if ($editingMode) {
                $this->editingMode = true;
                $this->config->pageSwitcher = false;
                $this->config->caching = false;
                setStaticVariable('nc', true);
            }

            if (getUrlArg('purge')) {                        // empty recycleBins
                $this->purgeRecyleBins();
            }

        } else {                    // no privileged permission: reset modes:
            if (getUrlArg('edit')) {
                $this->page->addMessage('{{ need to login to edit }}');
            }
            setStaticVariable('editingMode', false);
            $this->editingMode = false;
		}

	} // handleUrlArgs



	//....................................................
	private function handleUrlArgs2()
	{
        // user wants to login in and is not already logged in:
		if (getUrlArg('login')) {                                               // login
		    if (getStaticVariable('user')) {    // already logged in -> logout first
                $this->userRec = false;
                setStaticVariable('user',false);
            }

            $this->checkInsecureConnection();
            $overridePage = $this->auth->renderForm();
            $this->page->merge($overridePage);
            $this->page->addOverride($overridePage->get('override'), true);
		}


        if (!$this->auth->checkGroupMembership('editors')) {  // only localhost or logged in as editor/admin group
            return;
        }



        if ($n = getUrlArg('printall', true)) {							// printall
            exit( $this->printall($n) );
        }


        if (getUrlArg('log')) {    // log
            if (file_exists(ERROR_LOG_ARCHIVE)) {
                $str = file_get_contents(ERROR_LOG_ARCHIVE);
            } else {
                $str = "Currently no error log available.";
            }
            $str = "<pre>$str</pre>";
            $this->page->addOverlay($str);
        }



        if (getUrlArg('info')) {    // info
            $str = $this->renderDebugInfo();
            $this->page->addOverlay($str);
		}



        if (getUrlArg('list')) {    // list
			$this->trans->printAll();
			exit;
		}



        if (getUrlArg('config')) {                              // config
			$str = $this->renderConfigHelp();
            $this->page->addOverlay($str);
		}



        if (getUrlArg('help')) {                              // help
			$overlay = <<<EOT
<h1>Lizzy Help</h1>
<pre>
Available URL-commands:

<a href='?help'>?help</a>		    this message
<a href='?list'>?list</a>		    list of transvars and macros()
<a href='?config'>?config</a>		    list configuration-items in the config-file
<a href='?localcall'>?localcall=false</a>    to test behavior as if on non-local host
<a href='?edit'>?edit</a>		    start editing mode *)
<a href='?convert=pw'>?convert=</a>	    convert password to hash
<a href='?login'>?login</a>		    login
<a href='?logout'>?logout</a>		    logout
<a href='?reset'>?reset</a>		    clear cache, session-variables and error-log
<a href='?purge'>?purge</a>		    empty and delete all recycle bins (i.e. copies of modified pages)
<a href='?nc'>?nc</a>		    supress caching (?nc=false to enable caching again)  *)
<a href='?lang=xy'>?lang=</a>	            switch to given language (e.g. '?lang=en')  *)
<a href='?timer'>?timer</a>		    switch timer on or off  *)
<a href='?printall'>?printall</a>	    show all pages in one
<a href='?touch'>?touch</a>		    emulate touch mode  *)
<a href='?debug'>?debug</a>		    adds 'debug' class to page on non-local host *)
<a href='?info'>?info</a>		    list debug-info
<a href='?log'>?log</a>		    displays log files in overlay

*) these options are persistent, they keep their value for further page requests. 
Unset individually as ?xy=false or globally as ?reset

</pre>
EOT;
			$this->page->addOverlay($overlay);
		}



        if (getStaticVariable('editingMode')) {
            $this->trans->addVariable('toggle-edit-mode', "<a href='?edit=false'>{{ turn edit mode off }}</a> | ");
        } else {
            $this->trans->addVariable('toggle-edit-mode', "<a href='?edit'>{{ turn edit mode on }}</a> | ");
        }


		
		if (getUrlArgStatic('touch')) {			                                // touch
			$this->trans->addVariable('debug_class', ' touch small-screen');
		}

	} // handleUrlArgs2



	//....................................................
	private function renderMD()
	{
        $mdStr = get_post_data('md', true);

        $doSave = getUrlArg('save');
		if ($doSave && ($filename = get_post_data('filename'))) {
			$permitted = $this->auth->checkGroupMembership('editors');
			if ($permitted) {
				if (preg_match("|^{$this->config->pagesPath}(.*)\.md$|", $filename)) {
                    require_once SYSTEM_PATH.'page-source.class.php';
                    PageSource::storeFile($filename, $mdStr);

				} else {
                    fatalError("illegal file name: '$filename'", 'File: '.__FILE__.' Line: '.__LINE__);
				}
			} else {
				die("Sorry, you have no permission to modify files on the server.");
			}
		}
		$md = new MyMarkdown();
		$pg = new Page;
		$mdStr = $this->extractFrontmatter($mdStr, $pg);
		$md->parse($mdStr, $pg);
		$out = $pg->get('body');
		if (getUrlArg('html')) {
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
	private function saveSitemapFile($filename)
	{
        $str = get_post_data('sitemap', true);
        $permitted = $this->auth->checkGroupMembership('editors');
        if ($permitted) {
            require_once SYSTEM_PATH.'page-source.class.php';
            PageSource::storeFile($filename, $str, SYSTEM_RECYCLE_BIN_PATH);

        } else {
            fatalError("Sorry, you have no permission to modify files on the server.", 'File: '.__FILE__.' Line: '.__LINE__);
        }
    } // saveSitemapFile




	//....................................................
	private function selectLanguage()
	{
	    global $globalParams;
        // check sitemap for language setting
        if (isset($this->siteStructure->currPageRec['lang'])) {
            $lang = $this->siteStructure->currPageRec['lang'];

            if (($l = getUrlArgStatic('lang', true)) !== null) { // override if explicitly supplied
                if ($l) {
                    $lang = $l;
                    setStaticVariable('lang', $lang);
                } else {
                    setStaticVariable('lang', null);
                }
            }

        } else {    // no preference in sitemap, use default if not overriden by url-arg
            $lang = getUrlArgStatic('lang', true);
            if (!$lang) {   // no url-arg found
                if ($lang !== null) {   // special case: empty lang -> remove static value
                    setStaticVariable('lang', null);
                }
                $lang = $this->config->defaultLanguage;
            }

        }
        $this->config->lang = $lang;
        $globalParams['lang'] = $lang;
        return $lang;
    } // selectLanguage



	//....................................................
	private function renderConfigHelp()
	{
        $configItems = $this->config->configFileSettings;
        ksort($configItems);
        $out = "<h1>Lizzy Config-Items and their Purpose:</h1>\n<dl class='lizzy-config-viewer'>\n";
        $out .= "<p>Settings stored in file <code>{$this->configFile}</code>.</p>";
        $out2 = '';
        foreach ($configItems as $name => $rec) {
            $value = $this->config->$name;
            if (is_bool($value)) {
                $value = ($value) ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = var_r($value, false, true);
            } else {
                $value = (string)$value;
            }
            if (is_bool($rec[0])) {
                $default = ($rec[0]) ? 'true' : 'false';
                $setVal = ($rec[0]) ? "false\t# default=$default" : "true\t# default=$default";
            } else {
                $default = $setVal = (string)$rec[0];
            }
            $text = (string)$rec[1];
            $diff = '';
            if ($default != $value) {
                $diff = ' class="lizzy-config-viewer-hl"';
            }
            $out .= "<dt><strong>$name</strong>: ($default) <code$diff>$value</code></dt><dd>$text</dd>\n";
            
            $out2 .= "#$name: $setVal\n";
        }
        $out .= "</dl>\n";
        
        $out .= "\n\n<pre>$out2</pre>\n";
        return $out;
    } // renderConfigHelp



    //....................................................
    private function sendAccessLinkMail()
    {
        if ($pM = $this->auth->getPendingMail()) {

            $headers = "From: {$pM['from']}\r\n" .
                'X-Mailer: PHP/' . phpversion();
            $subject = $this->trans->translateVars( $pM['subject'] );
            $message = $this->trans->translateVars( $pM['message'] );

            if ($this->localCall) {
                $this->page->addOverlay("<pre class='debug-mail'><div>Subject: $subject</div>\n<div>$message</div></pre>");
            } else {
                if (!mail($pM['to'], $subject, $message, $headers)) {
                    fatalError("Error: unable to send e-mail", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                }
            }
        }
    } // sendAccessLinkMail




	//....................................................
	private function printall($maxN = true)
	{
die('Not implemented yet');
		if (!($title = $this->trans->getVariable('print_all_title'))) {
			if (!($title = $this->trans->getVariable('page_title'))) {
				$title = '';
			}
		}
		if (intval($maxN)) {
            $maxN = intval($maxN);
        } else {
		    $maxN = 4; //999;
        }
		$pages = '';
		foreach($this->siteStructure->getSiteList() as $i => $rec) {
			$url = resolvePath('~/'.$rec['folder'], false, 'https');
			if (!$url || ($url == 'home/')) {
				$url = './';
			}
			$pages .= "\t<iframe src='$url?debug=false&localcall=false'></iframe>\n";
			$pages .= "\t<div style='page-break-after: always;'></div>\n\n";
            if ($i >= ($maxN-1)) {
                break;
            }
		}

		$html = <<<EOT
<!DOCTYPE html>
<html lang="en">
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
    private function getBrowser()
    {
        $ua = new UaDetector( $this->config->collectBrowserSignatures );
        return [$ua->get(), $ua->isLegacyBrowser()];
    } // browserDetection





    //....................................................
    private function purgeRecyleBins()
    {
        $pageFolder = $this->config->pagesPath;
        $recycleBinFolderName = basename(RECYCLE_BIN_PATH);

        // purge in page folders:
        $pageFolders = getDirDeep($pageFolder, true, false, true);
        foreach ($pageFolders as $item) {
            if (basename($item) === $recycleBinFolderName) {
                array_map('unlink', glob("$item/*.*"));
                rmdir($item);
            }
        }

        // purge global recycle bin:
        array_map('unlink', glob("$recycleBinFolderName/*.*"));
        rmdir($recycleBinFolderName);

    } // purgeRecyleBins




    //....................................................
    private function dailyHousekeeping($run = 1)
    {
        if ($run == 1) {
            if (file_exists(HOUSEKEEPING_FILE)) {
                $fileTime = intval(filemtime(HOUSEKEEPING_FILE) / 86400);
                $today = intval(time() / 86400);
                if (($fileTime) == $today) {    // update once per day
                    $this->housekeeping = false;
                    return;
                }
            }
            if (!file_exists(CACHE_PATH)) {
                mkdir(CACHE_PATH, 0777);
            }
            touch(HOUSEKEEPING_FILE);
            chmod(HOUSEKEEPING_FILE, 0770);

            writeLog("Daily housekeeping run.", 'log.txt');

            $this->checkInstallation();

            $this->housekeeping = true;

        } elseif ($this->housekeeping) {
            $this->checkInstallation2();
            if ($this->config->enableDailyUserTask) {
                if (file_exists($this->config->userCodePath.'user-daily-task.php')) {
                    require( $this->config->userCodePath.'user-daily-task.php' );
                }
            }
        }
    } // dailyHousekeeping



    //....................................................
    private function checkInstallation()
    {
        if (!file_exists(DEFAULT_CONFIG_FILE)) {
            ob_end_flush();
            echo "<pre>";
            echo shell_exec('/bin/sh _lizzy/_install/install.sh');
            echo "</pre>";
            exit;
        }

        $writableFolders = ['data/', '.#cache/', '.#logs/'];
        $readOnlyFolders = ['_lizzy/','code/','config/','css/','pages/'];
        $out = '';
        foreach ($writableFolders as $folder) {
            if (!file_exists($folder)) {
                mkdir($folder, 0777);
            }
            if (!is_writable( $folder )) {
                $out .= "<p>folder not writable: '$folder'</p>\n";
            }
            foreach( getDir($folder.'*') as $file) {
                if (!is_writable( $file )) {
                    $out .= "<p>folder not writable: '$file'</p>\n";
                }
            }
        }

        foreach ($readOnlyFolders as $folder) {
            if (!file_exists($folder)) {
                mkdir($folder, 0700);
            }
            if (!is_readable( $folder )) {
                $out .= "<p>folder not readable: '$folder'</p>\n";
            }

        }
        if ($out) {
            exit( $out );
        }
        return;

        print( trim(shell_exec('whoami')).':'.trim(shell_exec('groups'))."<br>\n");

        $all = array_merge($writableFolders, $readOnlyFolders);
        foreach ($all as $folder) {
            $rec = posix_getpwuid(fileowner($folder));
            $name = $rec['name'];
            $rec = posix_getgrgid(filegroup($filename));
            $group = $rec['name'];
            print("$folder: $name:$group<br>\n");
        }
        exit;
    } // checkInstallation



    //....................................................
    private function checkInstallation2()
    {
        $out = '';
        if ($this->config->enableEditing) {
            if (!is_writable( 'pages' )) {
                $out .= "<p>folder not writable: 'pages/'</p>\n";
            }
            foreach(getDirDeep('pages/*') as $file) {
                if (!is_writable( $file )) {
                    $out .= "<p>file or folder not writable: '$file'</p>\n";
                }
            }
        }
        if ($out) {
            exit( $out );
        }
    } // checkInstallation2

} // class WebPage


