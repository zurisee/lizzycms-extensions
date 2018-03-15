
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
define('UNDEFINED_VARS_FILE',   CACHE_PATH.'undefinedVariables.yaml');
define('FAILED_LOGIN_FILE',     CACHE_PATH.'_failed-logins.yaml');
define('HACK_MONITORING_FILE',  CACHE_PATH.'_hack_monitoring.yaml');
define('ONETIME_PASSCODE_FILE', CACHE_PATH.'_onetime-passcodes.yaml');
define('HACKING_THRESHOLD',     10);
define('HOUSEKEEPING_FILE',     CACHE_PATH.'_housekeeping.txt');

$files = ['config/user_variables.yaml', '_lizzy/config/*', '_lizzy/macros/transvars/*'];


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
//require_once SYSTEM_PATH.'user-account.class.php';
require_once SYSTEM_PATH.'user-account-form.class.php';


$globalParams = array(
	'pathToRoot' => null,			// ../../
	'pagePath' => null,				// pages/xy/
    'path_logPath' => null,
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

        $globalParams['feature_autoForceBrowserCache'] = $this->config->feature_autoForceBrowserCache;

        if ($this->config->site_sitemapFile || $this->config->feature_sitemapFromFolders) {
			$this->siteStructure = new SiteStructure($this->config, $this->reqPagePath);
            $this->currPage = $this->reqPagePath = $this->siteStructure->currPage;

			if (isset($this->siteStructure->currPageRec['showthis'])) {
				$this->pagePath = $this->siteStructure->currPageRec['showthis'];
			} else {
				$this->pagePath = $this->siteStructure->currPageRec['folder'];
			}
			$globalParams['pagePath'] = $this->pagePath;                    // excludes pages/
			$this->pathToPage = $this->config->path_pagesPath.$this->pagePath;   //  includes pages/
			$globalParams['pathToPage'] = $this->pathToPage;

			$this->pageRelativePath = $this->pathToRoot.$this->pagePath;

            $this->trans->loadStandardVariables($this->siteStructure);
            $this->trans->addVariable('next_page', "<a href='~/{$this->siteStructure->nextPage}'>{{ nextPageLabel }}</a>");
			$this->trans->addVariable('prev_page', "<a href='~/{$this->siteStructure->prevPage}'>{{ prevPageLabel }}</a>");

		} else {
			$this->siteStructure = new SiteStructure($this->config, ''); //->list = false;
			$this->currPage = '';
            $globalParams['pagePath'] = '';
            $this->pathToPage = $this->config->path_pagesPath;
            $globalParams['pathToPage'] = $this->pathToPage;
            $this->pageRelativePath = '';
            $this->pagePath = '';
        }
        $this->trans->addVariable('debug_class', '');
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

        // Future: enable caching of compiled MD pages:
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

        // Future: optionally enable Auto-Attribute mechanism
        //        $html = $this->executeAutoAttr($html);

        // Future: enable caching of compiled MD pages:
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
        // Config-param 'debug_forceBrowserCacheUpdate' forces this for every request
        // 'feature_autoForceBrowserCache' only forces reload when Lizzy detected changes in those files

        if (isset($_SESSION['lizzy']['reset']) && $_SESSION['lizzy']['reset']) {  // Lizzy has been reset, now force browser to update as well
            $forceUpdate = getVersionCode( true );
            unset($_SESSION['lizzy']['reset']);

        } elseif ($this->config->debug_forceBrowserCacheUpdate) {
            $forceUpdate = getVersionCode( true );

        } elseif ($this->config->feature_autoForceBrowserCache) {
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
        $this->checkInstallation0();

        $configFile = DEFAULT_CONFIG_FILE;
        if (file_exists($configFile)) {
            $this->configFile = $configFile;
        } else {
            die("Error: file not found: ".$configFile);
        }

        session_start();
        $this->sessionId = session_id();
        $this->getConfigValues(); // from $this->configFile


        register_shutdown_function('handleFatalPhpError');

        $this->config->appBaseName = base_name(rtrim(trunkPath(__FILE__, 1), '/'));
        if ($this->config->site_sitemapFile) {
            $sitemapFile = $this->config->configPath . $this->config->site_sitemapFile;

            if (file_exists($sitemapFile)) {
                $this->config->site_sitemapFile = $sitemapFile;
            } else {
                $this->config->site_sitemapFile = false;
            }
        }

        if ($this->config->site_multiLanguageSupport) {
            $this->config->site_supportedLanguages = explode(',', str_replace(' ', '',$this->config->site_supportedLanguages));
        }

        $GLOBALS['globalParams']['isAdmin'] = false;

        $this->page = new Page($this->config);
        $this->trans = new Transvar($this->config);
        $this->trans->readTransvarsFromFiles([ SYSTEM_PATH.'config/sys_vars.yaml', CONFIG_PATH.'user_variables.yaml' ]);

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

        $this->auth = new Authentication($this->config->configPath.$this->config->admin_usersFile, $this->config);

        $this->analyzeHttpRequest();
        $this->httpSystemPath = $this->pathToRoot.SYSTEM_PATH;


        $res = $this->auth->authenticate();
        if (is_array($res)) {   // array means user is not logged in yet but needs the one-time access-code form
            if ($res[2] == 'Overlay') {
                $this->page->addOverlay($res[1], false, false);
            } else {
                $this->page->addOverride($res[1], false, false);
            }
            $this->loggedInUser = false;

        } elseif ($res === null) {
            $this->renderLoginForm();
            $this->loggedInUser = false;

        } else {
            $this->loggedInUser = $res;
        }


        $res = $this->auth->adminActivities();
        if ($res) {
            if (isset($res[2]) && ($res[2] == 'Overlay')) {
                $this->page->addOverlay($res[1], false, false);
            } else {
                $this->page->addOverride($res[1], false, false);
            }
        }
        $GLOBALS['globalParams']['auth-message'] = $this->auth->message;

        $this->config->isPrivileged = false;
        if ($this->auth->isPrivileged()) {
            $this->config->isPrivileged = true;

        } elseif (file_exists(HOUSEKEEPING_FILE)) {  // suppress error msg output if not local host or admin or editor
            ini_set('display_errors', '0');
        }

        $this->handleUrlArgs();

        $this->saveEdition();  // if user chose to activate a previous edition of a page

        $cliarg = getCliArg('lzy-compile');
        if ($cliarg) {
            $this->renderMD();  // arg 'lzy-save' handled here if supplied together

        } else {
            $cliarg = getCliArg('lzy-save');
            if ($cliarg) {
                $this->saveSitemapFile($sitemapFile);
            }
        }

        $this->scss = new SCssCompiler($this->config->path_stylesPath.'scss/*.scss', $this->config->path_stylesPath, $this->localCall);
        $this->scss->compile( $this->config->debug_forceBrowserCacheUpdate );

        // Future: optionally enable Auto-Attribute mechanism
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
        if ($this->config->debug_errorLogging && !file_exists(ERROR_LOG_ARCHIVE)) {
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
        if ($this->config->debug_suppressInsecureConnectWarning) {
            mylog("Insecure-connection warning suppressed");
            return true;
        }
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
                $this->renderLoginForm();
                return false;
            }
            setStaticVariable('isRestrictedPage', $this->loggedInUser);
        } else {
            setStaticVariable('isRestrictedPage', false);
        }
        return true;
    } // checkAdmissionToCurrentPage




    //....................................................
    private function renderLoginForm()
    {
        $accForm = new UserAccountForm($this);
        $authPage = $accForm->renderLoginForm($this->auth->message, '{{ page requires login }}');
        $this->page->addCssFiles('USER_ADMIN_CSS' );
        $this->page->addjQFiles('USER_ADMIN' );
        $this->page->addOverride($authPage->get('override'), true, false);   // override page with login form
        $this->page->setOverrideMdCompile(false);
    }



    //....................................................
    private function loadAutoAttrDefinition($file = false)
    {
        if (!$file) {
            if (!file_exists($this->config->feature_autoAttrFile)) {
                return;
            }
            $file = $this->config->feature_autoAttrFile;
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
        $globalParams['pathToPage'] = $this->config->path_pagesPath.$pagePath;
        $_SESSION['lizzy']['pathToPage'] = $this->config->path_pagesPath.$pagePath;

        $globalParams['pathToRoot'] = $pathToRoot;  // path from requested folder to root (= ~/), e.g. ../
        $globalParams['host'] = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].'/';
        $this->pageUrl = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$requestedPath;
        $globalParams['pageUrl'] = $this->pageUrl;
        $requestedUrl = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$requestUri;
        $globalParams['requestedUrl'] = $requestedUrl;
        $globalParams['absAppRoot'] = $absAppRoot;  // path from FS root to base folder of app, e.g. /Volumes/...

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
        if ($this->config->feature_supportLegacyBrowsers) {
            $this->legacyBrowser = true;
            $globalParams['legacyBrowser'] = true;
            writeLog("Legacy-Browser Support activated.");

        } else {
            $overrideLegacy = getUrlArgStatic('legacy');
            if ($overrideLegacy === null) {
                $this->legacyBrowser = $isLegacyBrowser;
            } else {
                $this->legacyBrowser = $overrideLegacy;
            }
        }


        $this->reqPagePath = $pagePath;
        $globalParams['appRoot'] = $appRoot;  // path from docRoot to base folder of app, e.g. 'on/'
        $globalParams['pathToRoot'] = $pathToRoot;  // path from requested folder to root (= ~/), e.g. ../
        $globalParams['redirectedPath'] = $redirectedPath;  // the part that is optionally skippped by htaccess
        $globalParams['legacyBrowser'] = $this->legacyBrowser;
        $globalParams['localCall'] = $this->localCall;

        setStaticVariable('appRootUrl', $globalParams['host'].$appRoot);
        setStaticVariable('absAppRoot', $absAppRoot);

        if ($this->config->debug_logClientAccesses) {
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
        $this->config->lang = $this->config->site_defaultLanguage;

        $globalParams['path_logPath'] = $this->config->path_logPath;
    } // getConfigValues




    //....................................................
    private function loadTemplate()
    {
        $this->page->addBody($this->getTemplate(), true);
    } // loadTemplate




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

		if (!$this->config->admin_enableEditing || !$this->editingMode) {
			return;
		}
		require_once SYSTEM_PATH.'editor.class.php';
        require_once SYSTEM_PATH.'page-source.class.php';

        $this->config->editingMode =$this->editingMode;

        $ed = new ContentEditor($this->page);
		$ed->injectEditor($this->pagePath);
	} // injectEditor



	//....................................................
	private function injectPageSwitcher()
	{
        if ($this->config->feature_slideShowSupport) {
            require_once($this->config->systemPath."slideshow-support.php");

        } elseif ($this->config->feature_pageSwitcher) {
            require_once($this->config->systemPath."page_switcher.php");
        }
	} // injectPageSwitcher



    //....................................................
	private function setTransvars1()
	{
	    $userAcc = new UserAccountForm($this);
	    $login = $userAcc->renderLoginLink();
        $this->trans->addVariable('Log-in', $login);
        $this->trans->addVariable('user', $userAcc->getUsername(), false);


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

		if ($this->legacyBrowser) {
            $this->trans->addVariable('debug_class', ' legacy');
        }

		if ($this->config->site_multiLanguageSupport) {
            $supportedLanguages = $this->config->site_supportedLanguages;
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
        if ($this->config->admin_enableEditing && ($this->auth->checkGroupMembership('editors'))) {
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
        $type = (isset($page->feature_cssFramework)) ? $page->feature_cssFramework : false;
        $type = ($this->config->feature_cssFramework) ? $this->config->feature_cssFramework : $type;
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
                $this->config->feature_cssFramework = false;
                break;
        }
    } // injectCssFramework



	//....................................................
	private function runUserInitCode()
	{
	    if (!$this->config->custom_permitUserCode) {   // user-code enabled?
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
			$template = $this->config->site_pageTemplateFile;
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
		$file = $this->config->path_pagesPath.$file;
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

        $folder = $this->config->path_pagesPath.$folder;
		$this->handleMissingFolder($folder);

		$mdFiles = getDir($folder.'*.{md,txt}');

		// Case: no .md file available, but page has sub-pages -> show first sub-page instead
		if (!$mdFiles && isset($currRec[0])) {
			$folder = $currRec[0]['folder'];
			$this->siteStructure->currPageRec['folder'] = $folder;
			$mdFiles = getDir($this->config->path_pagesPath.$folder.'*.{md,txt}');
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
			if ($this->config->site_multiLanguageSupport) {
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
            } elseif ($mdStr && $this->config->feature_renderTxtFiles) {   // it's a TXT file, wrap it in <pre>
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
                $pagesPath = $this->config->path_pagesPath;
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
		if ($this->config->site_enableCaching) {
			$cache = $this->page->getEncoded(); //json_encode($this->page);
            $cacheFile = $this->pathToPage.$this->config->cacheFileName;

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
		if ($this->config->site_enableCaching) {
            $cacheFile = $this->pathToPage.$this->config->cacheFileName;
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

		$dir = getDirDeep($this->config->path_pagesPath, true);
		foreach ($dir as $folder) {
		    $filename = $folder.$this->config->cacheFileName;
		    if (file_exists($filename)) {
		        unlink($filename);
            }
        }
	} // clearCache





    //....................................................
    private function clearCaches($secondRun = false)
    {
        if (!$secondRun) {
            //            $this->clearCache();                            // clear page caches
            //            $this->siteStructure->clearCache();             // clear siteStructure cache
            if (isset($_SESSION['lizzy'])) {
                unset($_SESSION['lizzy']);                      // reset SESSION data
            }
            $_SESSION['lizzy']['reset'] = true;
            $this->userRec = false;

            if (file_exists(ERROR_LOG_ARCHIVE)) {   // clear error log
                unlink(ERROR_LOG_ARCHIVE);
            }
        } else {
            $this->clearCache();                            // clear page caches
            $this->siteStructure->clearCache();             // clear siteStructure cache
        }
    } // clearCaches



        //....................................................
	private function handleUrlArgs()
	{
        if (getUrlArg('reset')) {			            // reset (cache)
            $this->clearCaches();
        }


		if (getUrlArg('logout')) {	// logout
            $this->userRec = false;
            $this->auth->logout();
            reloadAgent(); // reload to get rid of url-arg ?logout
		}


        if ($nc = getStaticVariable('nc')) {		// nc
            $this->config->site_enableCaching = !$nc;
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
                $this->config->feature_pageSwitcher = false;
                $this->config->site_enableCaching = false;
                setStaticVariable('nc', true);
            }

            if (getUrlArg('purge')) {                        // empty recycleBins
                $this->purgeRecyleBins();
            }

            if (getUrlArg('lang', true) == 'none') {                        // empty recycleBins
                $this->config->debug_showVariablesUnreplaced = true;
                unset($_GET['lang']);
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
        if ($adminTask = getUrlArg('admin', true)) {                        // execute admin task
            require_once SYSTEM_PATH.'admintasks.class.php';
            $admTsk = new AdminTasks($this);
            $overridePage = $admTsk->execute($this, $adminTask);
            $this->page->merge($overridePage, 'override');
            $this->page->setOverrideMdCompile(false);
        }

        if (getUrlArg('reset')) {			            // reset (cache)
            $this->clearCaches(true);
            if ($this->config->debug_monitorUnusedVariables && $this->auth->isAdmin()) {
                $this->trans->reset($GLOBALS['files']);
            }
            reloadAgent();  //  reload to get rid of url-arg ?reset
        }

        // user wants to login in and is not already logged in:
		if (getUrlArg('login')) {                                               // login
		    if (getStaticVariable('user')) {    // already logged in -> logout first
                $this->userRec = false;
                setStaticVariable('user',false);
            }

            $this->renderLoginForm();
		}


        if (!$this->auth->checkGroupMembership('editors')) {  // only localhost or logged in as editor/admin group
            $this->trans->addVariable('toggle-edit-mode', "");
            return;
        }



        if (getUrlArg('unused')) {							        // unused
            $str = $this->trans->renderUnusedVariables();
            $str = "<h1>Unused Variables</h1>\n$str";
            $this->page->addOverlay($str);
        }


        if (getUrlArg('remove-unused')) {							// remove-unused
            $str = $this->trans->removeUnusedVariables();
            $str = "<h1>Removed Variables</h1>\n$str";
            $this->page->addOverlay($str);
        }


        if ($n = getUrlArg('printall', true)) {			// printall pages
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
			$str = $this->trans->renderAllTranslationObjects();
            $this->page->addOverlay($str, false, false);
            $this->page->addCssFiles('~sys/css/admin.css');
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
<a href='?unused'>?unused</a>		    show unused variables
<a href='?remove-unused'>?remove-unused</a>		    remove unused variables
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
        $mdStr = get_post_data('lzy_md', true);
        $mdStr = urldecode($mdStr);
        $doSave = getUrlArg('lzy-save');
		if ($doSave && ($filename = get_post_data('lzy_filename'))) {
			$permitted = $this->auth->checkGroupMembership('editors');
			if ($permitted) {
				if (preg_match("|^{$this->config->path_pagesPath}(.*)\.md$|", $filename)) {
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

		$out = $pg->get('content');
		if (getUrlArg('html')) {
			$out = "<pre>\n".htmlentities($out)."\n</pre>\n";
		}
		exit($out);
	} // renderMD



	//....................................................
	private function saveSitemapFile($filename)
	{
        $str = get_post_data('lzy-sitemap', true);
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

        } elseif ($this->config->site_multiLanguageSupport) {    // no preference in sitemap, use default if not overriden by url-arg
            $lang = getUrlArgStatic('lang', true);
            if (!in_array($lang, $this->config->site_supportedLanguages)) {
                $lang = $this->config->site_defaultLanguage;

            } elseif (!$lang) {   // no url-arg found
                if ($lang !== null) {   // special case: empty lang -> remove static value
                    setStaticVariable('lang', null);
                }
                $lang = $this->config->site_defaultLanguage;
            }

        } else {
            $lang = $this->config->site_defaultLanguage;
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
        $out = "<h1>Lizzy Config-Items and their Purpose:</h1>\n<dl class='lzy-config-viewer'>\n";
        $out .= "<p>Settings stored in file <code>{$this->configFile}</code>.</p>";
        $out2 = '';
        $ch = '';
        foreach ($configItems as $name => $rec) {
            $value = $this->config->$name;
            if (is_bool($value)) {
                $value = ($value) ? 'true' : 'false';
            } elseif (is_array($value)) {
                $value = var_r($value, false, true);
            } else {
                $value = (string)$value;
            }
            $val = $rec[0];
            if (is_bool($val)) {
                $default = ($val) ? 'true' : 'false';
                if ($val) {
                    $setVal = str_pad("#$name: false", 50)."# default=$default";
                } else {
                    $setVal = str_pad("#$name: true", 50)."# default=$default";
                }
            } else {
                $default = (string)$val;
                $setVal = str_pad("#$name: ''", 50)."# default=$default";
            }
            $text = (string)$rec[1];
            $diff = '';
            if ($default != $value) {
                $diff = ' class="lzy-config-viewer-hl"';
            }
            $out .= "<dt><strong>$name</strong>: ($default) <code$diff>$value</code></dt><dd>$text</dd>\n";

            if ($ch != substr($name, 0,2)) {
                $out2 .= "\n";
                $ch = substr($name, 0,2);
            }
            $out2 .= "$setVal\n";
        }
        $out .= "</dl>\n";

        if ($this->localCall) {
            $out .= "\n<hr />\n<h2>Template for config.yaml:</h2>\n<pre>$out2</pre>\n";
        }
        return $out;
    } // renderConfigHelp



    //....................................................
    private function sendAccessLinkMail()
    {
        if ($this->auth->mailIsPending) {
            require_once SYSTEM_PATH.'admintasks.class.php';
            $adm = new AdminTasks();

            $pM = $adm->getPendingMail();

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
	} // printall



    //....................................................
    private function getBrowser()
    {
        $ua = new UaDetector( $this->config->debug_collectBrowserSignatures );
        return [$ua->get(), $ua->isLegacyBrowser()];
    } // browserDetection





    //....................................................
    private function purgeRecyleBins()
    {
        $pageFolder = $this->config->path_pagesPath;
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
            $this->clearCaches();

        } elseif ($this->housekeeping) {
            $this->checkInstallation2();
            $this->clearCaches(true);
            if ($this->config->admin_enableDailyUserTask) {
                if (file_exists($this->config->path_userCodePath.'user-daily-task.php')) {
                    require( $this->config->path_userCodePath.'user-daily-task.php' );
                }
            }
            touch(HOUSEKEEPING_FILE);
            chmod(HOUSEKEEPING_FILE, 0770);
        }
    } // dailyHousekeeping



    //....................................................
    private function checkInstallation0()
    {
        if (!file_exists(DEFAULT_CONFIG_FILE)) {
            ob_end_flush();
            echo "<pre>";
            echo shell_exec('/bin/sh _lizzy/_install/install.sh');
            echo "</pre>";
            exit;
        }
    }



    //....................................................
    private function checkInstallation()
    {
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
        if ($this->config->admin_enableEditing) {
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


    public function postprocess($html)
    {
        $note = $this->trans->postprocess();
        if ($note) {
            $p = strpos($html, '</body>');
            $html = substr($html, 0, $p).createWarning($note).substr($html,$p);
        }
        return $html;
    }

} // class WebPage


