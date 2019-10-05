<?php
/*
 *	Lizzy - default and initial settings
 *
 *  Default Values
*/

class Defaults
{

// User configurable Settings -> config/config.yaml:
private $userConfigurableSettingsAndDefaults      = [
    'admin_activityLogging'             => [true, 'If true, logs activities to file '.LOG_FILE.'.' ],
    'admin_allowDisplaynameForLogin'    => [false, 'If true, users may log in using their "DisplayName" rather than their "UserName".' ],
    'admin_autoAdminOnLocalhost'        => [false, 'If true, on local host user automatically has admin privileges without login.' ],
    'admin_enableAccessLink'            => [true, 'Activates one-time-access-link login mechanism.' ],
    'admin_defaultAccessLinkValidyTime' => [900,    'Default Time in seconds during whith an access-link is valid.' ],
    'admin_defaultGuestGroup'           => ['guest', 'Name of default group for self-registration.' ],
    'admin_defaultLoginValidityPeriod'  => [86400, 'Defines how long a user can access the page since the last login.' ],
    'admin_enableDailyUserTask'         => [false, 'If true, looks for "code/user-daily-task.php" and executes it.' ],
    'admin_enableEditing'               => [true, 'Enables online editing' ],
    'admin_enableSelfSignUp'            => [false, 'If true, visitors can create a guest account on their own.' ],
    'admin_useRequestRewrite'           => [true, 'If true, assumes web-server supports request-rewrite (i.e. .htaccess).' ],
    'admin_userAllowSelfAdmin'          => [false, 'If true, user can modify their account after they logged in' ],

    'custom_permitUserCode'             => [false, "Only if true, user-provided code can be executed. And only if located in '".USER_CODE_PATH."'" ],
    'custom_permitUserInitCode'         => [false, "Only if true, user-provided init-code can be executed. And only if located in '".USER_CODE_PATH."'" ],
    'custom_permitUserVarDefs'          => [false, 'Only if true, "_code/user-var-defs.php" will be executed.' ],
    'custom_wrapperTag'                 => [false, 	'The HTML tag in which MD-files are wrapped (default: section)' ],

    'debug_allowDebugInfo'              => [false, '[false|true|group] If true, debugging Info can be activated: log in as admin and invoke URL-cmd "?debug"' ],
    'debug_collectBrowserSignatures'    => [false, 'If true, Lizzy records browser signatures of visitors.' ],
    'debug_compileScssWithLineNumbers'  => [false, 'If true, original line numbers are added as comments to compiled CSS."' ],
    'debug_errorLogging'                => [false, 'Enable or disabling logging.' ],
    'debug_forceBrowserCacheUpdate'     => [false, 'If true, the browser is forced to ignore the cache and reload css and js resources on every time.' ],
    'debug_logClientAccesses'           => [false, 'If true, Lizzy records visits (IP-addresses and browser/os types).' ],
    'debug_showDebugInfo'               => [false, '[false|true|group] If true, debugging info is appended to the page (prerequisite: localhost or logged in as editor/admin)' ],
    'debug_showUndefinedVariables'      => [false, 'If true, all undefined static variables (i.e. obtained from yaml-files) are marked.' ],
    'debug_showVariablesUnreplaced'     => [false, 'If true, all static variables (i.e. obtained from yaml-files) are render as &#123;&#123; name }}.' ],
    'debug_suppressInsecureConnectWarning' => [false, 'If true, Lizzy suppresses insecure connection warnings (i.e. HTTP, not HTTPS).' ],
    'debug_monitorUnusedVariables'      => [false, '[false|true] If true, Lizzy keeps track of variable usage. Initialize tracking with url-arg "?reset"' ],

    'feature_autoConvertLinks'          => [false, 'If true, automatically converts links to HTML-link (i.e. &lt;a> tags).' ],
    'feature_autoLoadClassBasedModules' => [true, 'If true, automatically loads modules that are invoked by applying classes, e.g. .editable' ],
    'feature_autoLoadJQuery'            => [true, 'If true, jQuery will be loaded automatically (even if not initiated explicitly by macros)' ],
    'feature_enableAllowOrigin'         => [false, 'If true, Lizzy allows to produce a "allow origin" header' ],
    'feature_enableIFrameResizing'      => [false, 'If true, includes js code required by other pages to iFrame-embed this site' ],
    'feature_filterRequestString'       => [false, 'If true, permits only regular text in requests. Special characters will be discarded.' ],
    'feature_frontmatterCssLocalToSection' => [false, 'If true, all CSS rules in Frontmatter will be modified to apply only to the current section (i.e. md-file content).' ],
    'feature_jQueryModule'              => ['JQUERY', 'Specifies the jQuery Version to be loaded: one of [ JQUERY | JQUERY1 | JQUERY2 | JQUERY3 ], default is jQuery 3.x.' ],
    'feature_loadFontAwesome'           => [false, 'If true, loads Font-Awesome support' ],
    'feature_pageSwitcher'              => [false, 'If true, code will be added to support page switching (by arrow-keys or swipe gestures)' ],
    'feature_lateImgLoading'            => [false, 'If true, enables general use of lazy-loading of images' ],
    'feature_quickview'                 => [true, 'If true, enables automatic Quickview of images' ],
    'feature_ImgDefaultMaxDim'          => ['1600x1200', 'Defines the max dimensions ("WxH") to which Lizzy automatically converts images which it finds in the pages folders.' ],
    'feature_SrcsetDefaultStepSize'     => [300, 'Defines the step size when Lizzy creates srcsets for images.' ],
    'feature_preloadLoginForm'          => [false, 'If true, code for login popup is preloaded and opens without page load.' ],
    'feature_renderTxtFiles'            => [false, 'If true, all .txt files in the pages folder are rendered (in &lt;pre>-tags, i.e. as is). Otherwise they are ignored.' ],
    'feature_screenSizeBreakpoint'      => [480, '[px] Determines the point where Lizzy switches from small to large screen mode.' ],
    'feature_selflinkAvoid'             => [true, 'If true, the nav-link of the current page is replaced with a local page link (to improve accessibility).' ],
    'feature_sitemapFromFolders'        => [false, 'If true, the sitemap will be derived from the folder structure under pages/, rather than the config/sitemap.yaml file.' ],
    'feature_supportLegacyBrowsers'     => [false, 'If true, jQuery 1 is loaded in case of legacy browsers.' ],
    'feature_touchDeviceSupport'        => [true, 'If true, Lizzy supports swipe gestures etc. on touch devices.' ],

    'path_logPath'                      => [LOGS_PATH, '[true|Name] Name of folder to which logging output will be sent. Or "false" for disabling logging.' ],
    'path_pagesPath'                    => ['pages/', 'Name of folder in which all pages reside.' ],
    'path_stylesPath'                   => ['css/', 'Name of folder in which style sheets reside' ],
    'path_userCodePath'                 => [USER_CODE_PATH, 'Name of folder in which user-provided PHP-code must reside.' ],

    'site_compiledStylesFilename'       => ['__styles.css', 'Name of style sheet containing collection of compiled user style sheets' ],
    'site_dataPath'                     => [DATA_PATH, 'Path to data/ folder.' ],
    'site_defaultLocale'                => ['en_US', 'Default local, e.g. "en_US" or "de_CH"' ],
    'site_enableCaching'                => [false, 'If true, Lizzy\'s caching mechanism is activated. (not fully implemented yet)' ],
    'site_extractSelector'              => ['body main', '[selector] Lets an external js-app request an extract of the web-page' ],
    'site_enableRelLinks'               => [true, 'If true, injects "rel links" into header, e.g. "&lt;link rel=\'next\' title=\'Next\' href=\'...\'>"' ],
    'site_pageTemplateFile'             => ['page_template.html', "Name of file that will be used as the template. Must be located in '".CONFIG_PATH."'"],
    'site_robots'                       => [false, 'If true, Lizzy will add a meta-tag to inform search engines, not to index this site/page.' ],
    'site_sitemapFile'                  => ['sitemap.txt', 'Name of file that defines the site structure. Build hierarchy simply by indenting.' ],
    'site_supportedLanguages'           => ['en', 'Defines which languages will be supported: comma-separated list of language-codes. E.g. "en, de, fr"' ],
    'site_timeZone'                     => ['auto', 'Name of timezone, e.g. "UTC" or "CET". If auto, attempts to set it automatically.' ],

];


    public function __construct($configFile)
    {
        $this->macrosPath               = MACROS_PATH;
        $this->extensionsPath           = EXTENSIONS_PATH;
        $this->configPath               = CONFIG_PATH;
        $this->systemPath               = SYSTEM_PATH;
        $this->systemHttpPath           = '~/'.SYSTEM_PATH;

        $this->userInitCodeFile         = USER_INIT_CODE_FILE;
        $this->cachePath                = CACHE_PATH;
        $this->cacheFileName            = CACHE_FILENAME;
        $this->cachingActive            = false;
        $this->siteIdententation        = MIN_SITEMAP_INDENTATION;


        // values not to be modified by config.yaml file:
        $this->admin_usersFile                   = 'users.yaml';
        $this->class_panels_widget               = 'lzy-panels-widget'; // 'Class-name for Lizzy\'s Panels widget that triggers auto-loading of corresponding modules' ],
        $this->class_editable                    = 'lzy-editable'; // 'Class-name for "Editable Fields" that triggers auto-loading of corresponding modules' ],
        $this->class_zoomTarget                  = 'zoomTarget'; // 'Class-name for "ZoomTarget Elements" that triggers auto-loading of corresponding modules' ],
        $this->custom_computedVariablesFile      = 'user-var-defs.php'; // 'Filename of PHP-code that will generate ("transvar-)variables.' ],
        $this->custom_variables                  = 'variables*.yaml'; // 	'Filename-pattern to identify files that should be loaded as ("transvar-)variables.' ],


        // shortcuts for modules to be loaded (upon request):
        // weight value controls the order of invocation. The higher the earlier.
        $this->jQueryWeight = 200;
        $this->loadModules['JQUERY']                = array('module' => 'third-party/jquery/jquery-3.4.1.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY3']               = array('module' => 'third-party/jquery/jquery-3.4.1.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY1']               = array('module' => 'third-party/jquery/jquery-1.12.4.min.js', 'weight' => $this->jQueryWeight);

        $this->loadModules['JQUERYUI']              = array('module' => 'third-party/jqueryui/jquery-ui.min.js, '.
                                                            'third-party/jqueryui/jquery-ui.min.css', 'weight' => 140);
        $this->loadModules['JQUERYUI_CSS']          = array('module' => 'third-party/jqueryui/jquery-ui.min.css', 'weight' => 140);

        $this->loadModules['MOMENT']                = array('module' => 'third-party/moment/moment.min.js', 'weight' => $this->jQueryWeight + 9);

        $this->loadModules['NORMALIZE_CSS']         = array('module' => 'css/normalize.min.css', 'weight' => 150);
        $this->loadModules['TOUCH_DETECTOR']        = array('module' => 'js/touch_detector.js', 'weight' => 149);

        $this->loadModules['FONTAWESOME_CSS']       = array('module' => 'https://use.fontawesome.com/releases/v5.3.1/css/all.css', 'weight' => 135);

        $this->loadModules['AUXILIARY']             = array('module' => 'js/auxiliary.js', 'weight' => 130);

        $this->loadModules['TABBABLE']              = array('module' => 'third-party/tabbable/jquery.tabbable.min.js', 'weight' => 126);
        $this->loadModules['NAV']                   = array('module' => 'js/nav.js', 'weight' => 125);

        $this->loadModules['EDITABLE']              = array('module' => 'extensions/editable/js/editable.js,'.
                                                            'extensions/editable/css/editable.css', 'weight' => 120);

        $this->loadModules['PANELS']                = array('module' => 'js/panels.js, css/panels.css', 'weight' => 110);

        $this->loadModules['QUICKVIEW']     	    = array('module' => 'js/quickview.js, css/quickview.css', 'weight' => 92);

        $this->loadModules['POPUP']  = // POPUP is synonym for POPUPS
        $this->loadModules['POPUPS']                = array('module' => 'third-party/jquery-popupoverlay/jquery.popupoverlay.js,'.
                                                                        'css/popup.css', 'weight' => 85);

        $this->loadModules['TOOLTIPS']              = array('module' => 'third-party/jquery-popupoverlay/jquery.popupoverlay.js,'.
                                                                        'js/tooltips.js, css/tooltips.css', 'weight' => 84);

        $this->loadModules['MAC_KEYS']              = array('module' => 'third-party/mac-keys/mac-keys.js', 'weight' => 80);

        $this->loadModules['HAMMERJS']              = array('module' => 'third-party/hammerjs/hammer2.0.8.min.js', 'weight' => 70);
        $this->loadModules['HAMMERJQ']              = array('module' => 'third-party/hammerjs/jquery.hammer.js', 'weight' => 70);
        $this->loadModules['PANZOOM']               = array('module' => 'third-party/panzoom/jquery.panzoom.min.js', 'weight' => 60);

        $this->loadModules['DATATABLES']            = array('module' => 'third-party/datatables/datatables.min.js,'.
                                                                        'third-party/datatables/datatables.min.css', 'weight' => 50);

        $this->loadModules['PAGED_POLYFILL']        = array('module' => 'third-party/paged.polyfill/paged.polyfill.js', 'weight' => 46);
        $this->loadModules['ZOOM_TARGET']           = array('module' => 'third-party/zoomooz/jquery.zoomooz.min.js', 'weight' => 45);
        $this->loadModules['PAGE_SWITCHER']         = array('module' => 'js/page_switcher.js', 'weight' => 30);
        $this->loadModules['TETHER']                = array('module' => 'third-party/tether.js/tether.min.js', 'weight' => 20);
        $this->loadModules['IFRAME_RESIZER']        = array('module' => 'third-party/iframe-resizer/iframeResizer.contentWindow.min.js', 'weight' => 19);
        $this->loadModules['USER_ADMIN']            = array('module' => 'js/user_admin.js, css/user_admin.css', 'weight' => 5);



        // elementes that shall be loaded when corresponding classes are found anywhere in the page:
        //   elements: can be any of cssFiles, css, js, jq etc.
        $this->classBasedModules = [
            'editable' => ['modules' => 'EDITABLE', 'jq' => "\$('.lzy-editable').editable();"],
            'panels_widget' => ['modules' => 'PANELS'],
            'zoomTarget' => ['jsFiles' => 'ZOOM_TARGET'],
        ];

        $this->getConfigValues($configFile);

        // userConfigurableSettingsAndDefaults will be needed if ?config arg was used, so keep it
        if (!getUrlArg('config')) {
            unset($this->userConfigurableSettingsAndDefaults);
        }
        return $this;
    } // __construct


    //....................................................
    private function getConfigValues($configFile)
    {
        $configValues = getYamlFile($configFile);

        $overridableSettings = array_keys($this->userConfigurableSettingsAndDefaults);
        foreach ($overridableSettings as $key) {
            if (isset($configValues[$key])) {
                $val = $configValues[$key];
                if (stripos($key, 'Path') !== false) {
                    if ($key !== 'site_dataPath') { // site_dataPath is the only exception that is allowed to use ../
                        $val = preg_replace('|/\.\.+|', '', $val);  // disallow ../
                        $val = fixPath(str_replace('/', '', $val));
                    }
                } elseif (stripos($key, 'File') !== false) {
                    $val = str_replace('/', '', $val);
                }
                $this->$key = $val;
            } else {
                $this->$key = $this->userConfigurableSettingsAndDefaults[$key][0];
            }
        }

        // fix some values:

        if ($this->path_logPath == '1/') {
            $this->path_logPath = LOGS_PATH;
        }

        if (!$this->site_supportedLanguages) {
            fatalError('Error: no value(s) defined for config item "site_supportedLanguages".');
        }
        $this->site_multiLanguageSupport = true;
        $this->site_supportedLanguages = explode(',', str_replace(' ', '',$this->site_supportedLanguages));
        $n = ($this->site_supportedLanguages[0]) ? sizeof($this->site_supportedLanguages) : 0;
        if ($n === 1) {
            $this->site_multiLanguageSupport = false;
        }
        $this->site_defaultLanguage = $this->site_supportedLanguages[0];
        $this->lang = $this->site_defaultLanguage;


        if ($this->site_timeZone === 'auto') {
            $this->site_timeZone = false;
        }


        if ($this->site_sitemapFile) {
            $sitemapFile = $this->configPath . $this->site_sitemapFile;

            if (file_exists($sitemapFile)) {
                $this->site_sitemapFile = $sitemapFile;
            } else {
                $this->site_sitemapFile = false;
            }
        }

        if (isLocalCall()) {
            if (($lc = getUrlArgStatic('localcall')) !== null) {
                $localCall = $lc;
            } else {
                $localCall = true;
            }
        } else {
            $localCall = false;
            setStaticVariable('localcall', false);
        }

        $this->isLocalhost = $this->localCall = $localCall;

    } // getConfigValues


    public function getConfigInfo()
    {
        return $this->userConfigurableSettingsAndDefaults;
    }

} // Defaults
