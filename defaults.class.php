<?php
/*
 *	Lizzy - default and initial settings
 *
 *  Default Values
*/

class Defaults
{
    public function __construct()
    {
        $this->macrosPath               = SYSTEM_PATH.'macros/';
        $this->configPath               = CONFIG_PATH;
        $this->systemPath               = SYSTEM_PATH;
        $this->systemHttpPath           = '~/'.SYSTEM_PATH;

        $this->userCodePath             = 'code/';
        $this->userInitCodeFile         = $this->userCodePath.'user-init-code.php';
        $this->cachePath                = '.#cache/';
        $this->cacheFileName            = '.#page-cache.dat';
        $this->siteIdententation        = 4;


        // These are the settings that can be used in config/config.yaml:
        $this->configFileSettings      = [
            'userVariables'             => ['variables*.yaml', 	'Filename-pattern to identify files that should be loaded as ("transvar-)variables.' ],
            'userComputedVariablesFile' => ['user-var-defs.php', 'Filename of PHP-code that will generate ("transvar-)variables.' ],
            'pageTemplateFile'          => ['page_template.html', "Name of file that will be used as the template. Must be located in '{$this->userCodePath}''"],
            'sitemapFile'               => ['sitemap.txt', 'Name of file that defines the site structure. Build hierarchie simply by indenting.' ],
            'defaultLanguage'           => ['en', 'Default language as two-character code, e.g. "en"' ],
            'multiLanguageSupport'      => [false, '[true|false] whether support for multiple languages should be active.' ],
            'caching'                   => [false, '[true|false] whether caching should be active.' ],
            'pagesPath'                 => ['pages/', 'Name of folder in which all pages reside.' ],
            'stylesPath'                => ['css/', 'Name of folder in which style sheets reside' ],
            'logPath'                   => [false, 'Name of folder to which logging output should be sent. Or "false" for disabling logging.' ],
            'errorLogging'              => [false, 'Enable or disabling logging.' ],
            'dataPath'                  => ['data/', '(obsolete) Name of folder in which data is located by default.' ],
            'userCodePath'              => ['code/', 'Name of folder in which user-provided PHP-code must reside.' ],
            'usersFile'                 => ['users.yaml', 'Name of file (in $configPath) that defines user privileges and hashed passwords etc.' ],
            'autoAttrFile'              => [false, 'Name of file (in $configPath) which defines the automatic assignment of class-names to HTML-elements. Used to simplify deployment of CSS-Frameworks, such as Bootstrap.' ],
            'permitUserVarDefs'         => ['sandboxed', '[\'sandboxed\'|true|false] Only if true, "_code/user-var-defs.php" will be executed.' ],
            'permitUserCode'            => [false, "[true|false] Only if true, user-provided code can be executed. And only if located in '{$this->userCodePath}''" ],
            'pageSwitcher'              => [false, '[true|false] whether code should be added to support page switching (by arrow-keys or swipe gestures)' ],
            'slideShowSupport'          => [false, '[true|false] If true, provides support for slide-shows' ],
            'autoLoadJQuery'            => [false, '[true|false] whether jQuery should be loaded automatically (even if not initiated by one of the macros)' ],
            'loadJQuery'                => [false, '[true|false] synonym for "autoLoadJQuery"' ],
            'jQueryModule'              => ['JQUERY', 'One of [ JQUERY | JQUERY1 | JQUERY2 | JQUERY3 ], default is jQuery 3.x.' ],
            'quickview'                 => [false, '[true|false] enables automatic Quickview of images' ],
            'enableEditing'             => [false, '[true|false] enables online editing' ],
            'hideWhileEditing'          => [false, 'List of CSS-selectors which shall be hidden while online editing is active, e.g. [#menu, .logo]' ],
            'cssFramework'              => [false, 'Name of CSS-Framework to be invoked {Bootstrap/PureCSS/w3.css}' ],
            'supportLegacyBrowsers'     => [false, 'Determines whether jQuery 3 can be loaded, if false jQuery 1 is invoked' ],
            'forceBrowserCacheUpdate'   => [false, 'Forces the browser to reload css and js resources' ],
            'autoForceBrowserCache'     => [false, 'Determines whether the browser is forced to reload css and js resources' ],
            'sitemapFromFolders'        => [false, 'If true, the sitemap will be derieved from the folder structure under pages/, rather than the config/sitemap.yaml file.' ],
            'selflinkAvoid'             => [true, 'If true, the nav-link of the current page is replaced with a local page link.' ],
            'autoAdminOnLocalhost'      => [true, 'If true, on local host user has admin privileges without login.' ],
            'permitOneTimeAccessLink'   => [true, 'Activates one-time-access-link login mechanism.' ],
            'defaultAccessLinkValidyTime'=> [900,    'Default Time in seconds during whith an access-link is valid.' ],
            'webmasterEmail'            => [true, 'E-mail address of webmaster' ],
            'allowDebugInfo'            => [false, '[false|true|group] If true, debugging Info can be activated: log in as admin and invoke URL-cmd "?debug"' ],
            'showDebugInfo'             => [false, '[false|true|group] If true, debugging info is appended to the page (prerequisite: localhost or logged in as editor/admin)' ],
//            'logging_errors'            => [false, '[false|true] If true, errors are logged to file "'.ERROR_LOG.'"' ],
//            'logging_activities'        => [false, '[false|true]' ],
            'scssCompileWithLineNumbers'=> [false, '[false|true] If true, line numbers of original SCSS file are included in CSS file' ],
            'autoLoadClassBasedModules' => [true, '[false|true] If true, automatically loads modules that are invoked by applying classes, e.g. .editable' ],
            'enableDailyUserTask'       => [true, '[false|true] If true, looks for "code/user-daily-task.php" and executes it.' ],
            'collectBrowserSignatures'  => [false, '[false|true] If true, Lizzy records browser signatures of visitors.' ],
            'logClientAccesses'         => [false, '[false|true] If true, Lizzy records visits (IP-addresses and browser/os types).' ],
            ];

        // These settings are considered internal, so they shouldn't be altered by apps:
        $this->configFileSettings['supportedLanguages'][0] = $this->configFileSettings['defaultLanguage'][0];
        $this->configFileSettings['supportedLanguages'][1] = 'Comma-separated list of language-codes. E.g. "en, de, fr"';



        // shortcuts for modules to be loaded (upon request):
        // weight value controls the order of invokation. The higher the earlier.
        $this->jQueryWeight = 200;
        $this->loadModules['JQUERY']           = array('module' => 'third-party/jquery-3.2.1.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY3']          = array('module' => 'third-party/jquery-3.2.1.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY2']          = array('module' => 'third-party/jquery-2.2.4.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY1']          = array('module' => 'third-party/jquery-1.12.4.min.js', 'weight' => $this->jQueryWeight);

        $this->loadModules['JQUERYUI']         = array('module' => 'third-party/jquery-ui.min.js', 'weight' => 140);
        $this->loadModules['JQUERYUI_CSS']     = array('module' => 'third-party/jquery-ui.min.css', 'weight' => 140);

        $this->loadModules['AUXILIARY']        = array('module' => 'js/lizzylog.js', 'weight' => 130);

        $this->loadModules['EDITABLE']         = array('module' => 'js/editable.js', 'weight' => 120);
        $this->loadModules['EDITABLE_CSS']     = array('module' => 'css/editable.css', 'weight' => 120);

        $this->loadModules['ACCORDION']        = array('module' => 'js/accordion.js', 'weight' => 110);
        $this->loadModules['ACCORDION_CSS']    = array('module' => 'css/accordion.css', 'weight' => 110);

        $this->loadModules['DOODLE']           = array('module' => 'js/doodle.js', 'weight' => 100);
        $this->loadModules['DOODLE_CSS']       = array('module' => 'css/doodle.css', 'weight' => 100);

        $this->loadModules['QUICKVIEW']     	= array('module' => 'js/quickview.js', 'weight' => 90);
        $this->loadModules['QUICKVIEW_CSS']     = array('module' => 'css/quickview.css', 'weight' => 90);

        $this->loadModules['MAC_KEYS']          = array('module' => 'third-party/mac-keys/mac-keys.js', 'weight' => 80);

        $this->loadModules['HAMMERJS']          = array('module' => 'third-party/hammerjs/hammer2.0.8.min.js', 'weight' => 70);
        $this->loadModules['HAMMERJQ']          = array('module' => 'third-party/hammerjs/jquery.hammer.js', 'weight' => 70);
        $this->loadModules['JQUERYUI_TOUCH']    = array('module' => 'third-party/jquery.ui.touch-punch.min.js', 'weight' => 60);

        $this->loadModules['DATATABLES_CSS']    = array('module' => 'third-party/datatables/datatables.min.css', 'weight' => 50);
        $this->loadModules['DATATABLES']        = array('module' => 'third-party/datatables/datatables.min.js', 'weight' => 50);

        $this->loadModules['ZOOM_TARGET']       = array('module' => 'third-party/zoomooz/jquery.zoomooz.min.js', 'weight' => 45);
        $this->loadModules['TOUCH_DETECTOR']    = array('module' => 'js/touch_detector.js', 'weight' => 40);
        $this->loadModules['SLIDESHOW_SUPPORT'] = array('module' => 'js/slideshow_support.js', 'weight' => 32);
        $this->loadModules['SLIDESHOW_SUPPORT_CSS'] = array('module' => 'css/slideshow_support.css', 'weight' => 32);
        $this->loadModules['PAGE_SWITCHER']     = array('module' => 'js/page_switcher.js', 'weight' => 30);
        $this->loadModules['TETHER']            = array('module' => 'third-party/tether.js/tether.min.js', 'weight' => 20);


        $this->loadModules['W3CSS_CSS']         = array('module' => 'third-party/w3.css/w3.css', 'weight' => 10);
        $this->loadModules['W3CSS_ATTR']        = array('module' => '~/'.$this->configPath.'w3css-auto-attrs.yaml', 'weight' => 10);

        $this->loadModules['PURECSS_CSS']       = array('module' => 'third-party/pure-css/pure-min.css', 'weight' => 10);
        $this->loadModules['PURECSS_ATTR']      = array('module' => '~/'.$this->configPath.'purecss-auto-attrs.yaml', 'weight' => 10);

        $this->loadModules['BOOTSTRAP_CSS']     = array('module' => 'third-party/bootstrap4/css/bootstrap.min.css', 'weight' => 10);
        $this->loadModules['BOOTSTRAP']         = array('module' => 'third-party/bootstrap4/js/bootstrap.min.js', 'weight' => 10);
        $this->loadModules['BOOTSTRAP_ATTR']    = array('module' => '~/'.$this->configPath.'bootstrap-auto-attrs.yaml', 'weight' => 10);

        // modules that shall be loaded when corresponding classes are found anywhere in the page:
        $this->classBasedModules                = [ 'editable' => ['cssFiles' => 'EDITABLE_CSS', 'jqFiles' => 'EDITABLE', 'jq' => "$('.editable').editable();"],
                                                    'accordion' => ['cssFiles' => 'ACCORDION_CSS', 'jqFiles' => 'ACCORDION'],
                                                    'zoomTarget' => ['jsFiles' => 'ZOOM_TARGET'],
                                                  ];
        return $this;
    }
} // Defaults
