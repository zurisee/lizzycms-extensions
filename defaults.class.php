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
            'admin_allowDisplaynameForLogin'    => [false, 'If true, logs activities to file '.LOG_FILE.'.' ],
            'admin_autoAdminOnLocalhost'        => [false, 'If true, on local host user has admin privileges without login.' ],
            'admin_enableAccessLink'            => [true, 'Activates one-time-access-link login mechanism.' ],
            'admin_defaultAccessLinkValidyTime' => [900,    'Default Time in seconds during whith an access-link is valid.' ],
            'admin_defaultGuestGroup'           => ['guest', 'Name of default group for self-registration.' ],
            'admin_defaultLoginValidityPeriod'  => [86400, 'Defines how long a user can access the page since the last login.' ],
//            'admin_enableAutoCreateAccount'     => [false, '[false|true] If true, lets users create their accounts with admin intervention.' ],
            'admin_enableDailyUserTask'         => [true, '[false|true] If true, looks for "code/user-daily-task.php" and executes it.' ],
            'admin_enableEditing'               => [true, '[true|false] enables online editing' ],
            'admin_hideWhileEditing'            => [false, 'List of CSS-selectors which shall be hidden while online editing is active, e.g. [#menu, .logo]' ],
            'admin_logClientAccesses'           => [false, 'Activates logging of user accesses.' ],
            'admin_useRequestRewrite'           => [true, '[true|false] If true, assumes web-server supports request-rewrite (e.g. .htaccess).' ],
            'admin_userAllowSelfAdmin'          => [true, 'If true, user can modify their account after they logged in' ],
            'admin_webmasterEmail'              => [true, 'E-mail address of webmaster' ],

            'custom_permitUserCode'             => [false, "[true|false] Only if true, user-provided code can be executed. And only if located in '".USER_CODE_PATH."'" ],
            'custom_permitUserInitCode'         => [false, "[true|false] Only if true, user-provided init-code can be executed. And only if located in '".USER_CODE_PATH."'" ],
            'custom_permitUserVarDefs'          => ['sandboxed', '[\'sandboxed\'|true|false] Only if true, "_code/user-var-defs.php" will be executed.' ],
            'custom_wrapperTag'                 => [false, 	'The HTML tag in which MD-files are wrapped (default: section)' ],

            'debug_allowDebugInfo'              => [false, '[false|true|group] If true, debugging Info can be activated: log in as admin and invoke URL-cmd "?debug"' ],
            'debug_autoForceBrowserCache'       => [false, '[true|false] Determines whether the browser is forced to reload css and js resources when they have been modified.' ],
            'debug_collectBrowserSignatures'    => [false, '[false|true] If true, Lizzy records browser signatures of visitors.' ],
            'debug_compileScssWithLineNumbers'  => [false, '[false|true] If true, original line numbers are added as comments to compiled CSS."' ],
            'debug_errorLogging'                => [false, 'Enable or disabling logging.' ],
            'debug_forceBrowserCacheUpdate'     => [false, 'Forces the browser to reload css and js resources' ],
            'debug_logClientAccesses'           => [false, '[false|true] If true, Lizzy records visits (IP-addresses and browser/os types).' ],
            'debug_showDebugInfo'               => [false, '[false|true|group] If true, debugging info is appended to the page (prerequisite: localhost or logged in as editor/admin)' ],
            'debug_showUndefinedVariables'      => [false, '[false|true] If true, all undefined static variables (i.e. obtained from yaml-files) are marked.' ],
            'debug_showVariablesUnreplaced'     => [false, '[false|true] If true, all static variables (i.e. obtained from yaml-files) are render as &#123;&#123; name }}.' ],
            'debug_suppressInsecureConnectWarning' => [false, '[false|true] If true, doesn\'t warn when detecting an insecure connection (i.e. HTTP, not HTTPS).' ],
            'debug_monitorUnusedVariables'      => [false, '[false|true] If true, Lizzy keeps track of variable usage. Initialize tracking with url-arg "?reset"' ],

            //'feature_autoAttrFile'              => [false, 'Name of file (in $configPath) which defines the automatic assignment of class-names to HTML-elements. Used to simplify deployment of CSS-Frameworks, such as Bootstrap.' ],
            'feature_autoLoadClassBasedModules' => [true, '[false|true] If true, automatically loads modules that are invoked by applying classes, e.g. .editable' ],
            'feature_autoLoadJQuery'            => [true, '[true|false] whether jQuery should be loaded automatically (even if not initiated by one of the macros)' ],
            'feature_cssFramework'              => ['', 'Name of CSS-Framework to be invoked {PureCSS/w3.css}' ],
            'feature_enableAllowOrigin'         => [false, '[true|false] If true, Lizzy allows to produce a "allow origin" header' ],
            'feature_enableSelfSignUp'          => [false, '[true|false] If true, visitors can create a guest account on their own.' ],
            'feature_filterRequestString'       => [false, '[true|false] If true, permits only regular text in requests. Special characters and anything enclosed by them will be discarded.' ],
            'feature_jQueryModule'              => ['JQUERY', 'One of [ JQUERY | JQUERY1 | JQUERY2 | JQUERY3 ], default is jQuery 3.x.' ],
            'feature_loadFontAwesome'           => [false, '[true|false] loads Font-Awesome support' ],
            'feature_pageSwitcher'              => [false, '[true|false] whether code should be added to support page switching (by arrow-keys or swipe gestures)' ],
            'feature_lateImgLoading'            => [false, '[true|false] enables general use of lazy-loading of images' ],
            'feature_quickview'                 => [true, '[true|false] enables automatic Quickview of images' ],
            'feature_ImgDefaultMaxDim'          => ['1600x1200', 'Defines the max dimensions ("WxH") to which Lizzy automatically converts images which it finds in the pages folders.' ],
            'feature_SrcsetDefaultStepSize'     => [300, 'Defines the step size when Lizzy creates srcsets for images.' ],
            'feature_renderTxtFiles'            => [false, '[true|false] If true, all .txt files in the pages folder are rendered (in &lt;pre>-tags, i.e. as is). Otherwise they are ignored.' ],
            'feature_screenSizeBreakpoint'      => [480, '[px] Determines the point where Lizzy switches from small to large screen mode.' ],
            'feature_selflinkAvoid'             => [true, '[true|false] If true, the nav-link of the current page is replaced with a local page link (to improve accessibility).' ],
            'feature_sitemapFromFolders'        => [false, '[true|false] If true, the sitemap will be derieved from the folder structure under pages/, rather than the config/sitemap.yaml file.' ],
            'feature_slideShowSupport'          => [false, '[true|false] If true, provides support for slide-shows' ],
            'feature_supportLegacyBrowsers'     => [false, '[true|false] Determines whether jQuery 3 can be loaded, if false jQuery 1 is invoked' ],
            'feature_touchDeviceSupport'        => [true, '[true|false] Determines whether to support swipe gestures etc. on touch devices.' ],

            'path_logPath'                      => [LOGS_PATH, '[true|Name] Name of folder to which logging output should be sent. Or "false" for disabling logging.' ],
            'path_pagesPath'                    => ['pages/', 'Name of folder in which all pages reside.' ],
            'path_stylesPath'                   => ['css/', 'Name of folder in which style sheets reside' ],
            'path_userCodePath'                 => ['code/', 'Name of folder in which user-provided PHP-code must reside.' ],

            'site_compiledStylesFilename'       => ['__styles.css', 'Name of style sheet containing collection of compiled user style sheets' ],
            'site_dataPath'                     => ['data/', 'Path to data/ folder (default: "~/data/").' ],
            'site_defaultLanguage'              => ['en', 'Default language as two-character code, e.g. "en"' ],
            'site_defaultLocale'                => ['en_US', 'Default local, e.g. "en_US" or "de_CH"' ],
            'site_enableCaching'                => [false, '[true|false] If true, Lizzy\'s caching mechanism is activated.' ],
            'site_extractSelector'              => ['body main', '[selector] Lets an external js-app request an extract of the web-page' ],
            'site_multiLanguageSupport'         => [false, '[true|false] whether support for multiple languages should be active.' ],
            'site_pageTemplateFile'             => ['page_template.html', "Name of file that will be used as the template. Must be located in '".USER_CODE_PATH."'"],
            'site_robots'                       => [false, '[true|false] If true, Lizzy will add a meta-tag to inform search engines, not to index this site/page.' ],
            'site_sitemapFile'                  => ['sitemap.txt', 'Name of file that defines the site structure. Build hierarchy simply by indenting.' ],
            'site_timeZone'                     => [false, 'Name of timezone, e.g. "UTC" or "CET". If false, attempts to set it automatically.' ],
            'site_supportedLanguages'           => ['', 'Comma-separated list of language-codes. E.g. "en, de, fr"' ],

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
        $this->loadModules['JQUERY']                = array('module' => 'third-party/jquery/jquery-3.3.1.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY3']               = array('module' => 'third-party/jquery/jquery-3.3.1.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY2']               = array('module' => 'third-party/jquery/jquery-2.2.4.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY1']               = array('module' => 'third-party/jquery/jquery-1.12.4.min.js', 'weight' => $this->jQueryWeight);

        $this->loadModules['JQUERYUI']              = array('module' => 'third-party/jqueryui/jquery-ui.min.js, '.
                                                            'third-party/jqueryui/jquery-ui.min.css', 'weight' => 140);
        $this->loadModules['JQUERYUI_CSS']          = array('module' => 'third-party/jqueryui/jquery-ui.min.css', 'weight' => 140);

        $this->loadModules['MOMENT']                = array('module' => 'third-party/moment/moment.min.js', 'weight' => $this->jQueryWeight + 9);

        $this->loadModules['NORMALIZE_CSS']         = array('module' => 'css/normalize.min.css', 'weight' => 150);

        $this->loadModules['FONTAWESOME_CSS']       = array('module' => 'https://use.fontawesome.com/releases/v5.3.1/css/all.css', 'weight' => 135);

        $this->loadModules['AUXILIARY']             = array('module' => 'js/auxiliary.js', 'weight' => 130);

        $this->loadModules['TABBABLE']              = array('module' => 'third-party/tabbable/jquery.tabbable.min.js', 'weight' => 126);
        $this->loadModules['NAV']                   = array('module' => 'js/nav.js', 'weight' => 125);
//        $this->loadModules['NAV']                   = array('module' => 'js/nav.js, css/_nav.css', 'weight' => 125);

        $this->loadModules['EDITABLE']              = array('module' => 'extensions/editable/js/editable.js,'.
                                                            'extensions/editable/css/editable.css', 'weight' => 120);

        $this->loadModules['PANELS']                = array('module' => 'js/panels.js, css/panels.css', 'weight' => 110);

        $this->loadModules['QUICKVIEW']     	    = array('module' => 'js/quickview.js, css/quickview.css', 'weight' => 92);

        $this->loadModules['POPUP']  = // POPUP is synonym for POPUPS
        $this->loadModules['POPUPS']                = array('module' => 'third-party/jquery-popupoverlay/jquery.popupoverlay.js,'.
                                                                        'css/popup.css', 'weight' => 85);
//                                                                        'js/popup.js, css/popup.css', 'weight' => 85);

        $this->loadModules['MAC_KEYS']              = array('module' => 'third-party/mac-keys/mac-keys.js', 'weight' => 80);

        $this->loadModules['HAMMERJS']              = array('module' => 'third-party/hammerjs/hammer2.0.8.min.js', 'weight' => 70);
        $this->loadModules['HAMMERJQ']              = array('module' => 'third-party/hammerjs/jquery.hammer.js', 'weight' => 70);
        $this->loadModules['PANZOOM']               = array('module' => 'third-party/panzoom/jquery.panzoom.min.js', 'weight' => 60);

        $this->loadModules['DATATABLES']            = array('module' => 'third-party/datatables/datatables.min.js,'.
                                                                        'third-party/datatables/datatables.min.css', 'weight' => 50);

        $this->loadModules['ZOOM_TARGET']           = array('module' => 'third-party/zoomooz/jquery.zoomooz.min.js', 'weight' => 45);
        $this->loadModules['TOUCH_DETECTOR']        = array('module' => 'js/touch_detector.js', 'weight' => 40);
        $this->loadModules['SLIDESHOW_SUPPORT']     = array('module' => 'js/slideshow_support.js, css/slideshow_support.css', 'weight' => 32);
        $this->loadModules['PAGE_SWITCHER']         = array('module' => 'js/page_switcher.js', 'weight' => 30);
        $this->loadModules['TETHER']                = array('module' => 'third-party/tether.js/tether.min.js', 'weight' => 20);


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
        $this->lang = $this->site_defaultLanguage;

        if ($this->path_logPath == '1/') {
            $this->path_logPath = LOGS_PATH;
        }

        if (!$this->site_supportedLanguages) {
            $this->site_supportedLanguages = $this->site_defaultLanguage;
        }
        if ($this->site_multiLanguageSupport) {
            $this->site_supportedLanguages = explode(',', str_replace(' ', '',$this->site_supportedLanguages));
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
