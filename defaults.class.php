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

        $this->path_userCodePath        = 'code/';
        $this->userInitCodeFile         = $this->path_userCodePath.'user-init-code.php';
        $this->cachePath                = '.#cache/';
        $this->cacheFileName            = '.#page-cache.dat';
        $this->siteIdententation        = 4;


        // These are the settings that can be used in config/config.yaml:
        $this->configFileSettings      = [
            'admin_autoAdminOnLocalhost'        => [true, 'If true, on local host user has admin privileges without login.' ],
            'admin_enableAccessLink'            => [true, 'Activates one-time-access-link login mechanism.' ],
            'admin_defaultAccessLinkValidyTime' => [900,    'Default Time in seconds during whith an access-link is valid.' ],
            'admin_defaultGuestGroup'           => ['guest', 'Name of default group for self-registration.' ],
            'admin_enableAutoCreateAccount'     => [false, '[false|true] If true, lets users create their accounts with admin intervention.' ],
            'admin_enableDailyUserTask'         => [true, '[false|true] If true, looks for "code/user-daily-task.php" and executes it.' ],
            'admin_enableEditing'               => [true, '[true|false] enables online editing' ],
            'admin_hideWhileEditing'            => [false, 'List of CSS-selectors which shall be hidden while online editing is active, e.g. [#menu, .logo]' ],
            'admin_logClientAccesses'           => [false, 'Activates logging of user accesses.' ],
            'admin_usersFile'                   => ['users.yaml', 'Name of file (in $configPath) that defines user privileges and hashed passwords etc.' ],
            'admin_webmasterEmail'              => [true, 'E-mail address of webmaster' ],

            'class_panels_widget'               => ['lzy-panels-widget', 'Class-name for Lizzy\'s Panels widget that triggers auto-loading of corresponding modules' ],
            'class_editable'                    => ['lzy-editable', 'Class-name for "Editable Fields" that triggers auto-loading of corresponding modules' ],
            'class_zoomTarget'                  => ['zoomTarget', 'Class-name for "ZoomTarget Elements" that triggers auto-loading of corresponding modules' ],

            'custom_computedVariablesFile'      => ['user-var-defs.php', 'Filename of PHP-code that will generate ("transvar-)variables.' ],
            'custom_permitUserCode'             => [false, "[true|false] Only if true, user-provided code can be executed. And only if located in '{$this->path_userCodePath}''" ],
            'custom_permitUserVarDefs'          => ['sandboxed', '[\'sandboxed\'|true|false] Only if true, "_code/user-var-defs.php" will be executed.' ],
            'custom_variables'                  => ['variables*.yaml', 	'Filename-pattern to identify files that should be loaded as ("transvar-)variables.' ],

            'debug_allowDebugInfo'              => [false, '[false|true|group] If true, debugging Info can be activated: log in as admin and invoke URL-cmd "?debug"' ],
            'debug_collectBrowserSignatures'    => [false, '[false|true] If true, Lizzy records browser signatures of visitors.' ],
            'debug_errorLogging'                => [false, 'Enable or disabling logging.' ],
            'debug_forceBrowserCacheUpdate'     => [false, 'Forces the browser to reload css and js resources' ],
            'debug_logClientAccesses'           => [false, '[false|true] If true, Lizzy records visits (IP-addresses and browser/os types).' ],
            'debug_showDebugInfo'               => [false, '[false|true|group] If true, debugging info is appended to the page (prerequisite: localhost or logged in as editor/admin)' ],
            'debug_showUndefinedVariables'      => [false, '[false|true] If true, all undefined static variables (i.e. obtained from yaml-files) are marked.' ],
            'debug_showVariablesUnreplaced'     => [false, '[false|true] If true, all static variables (i.e. obtained from yaml-files) are render as &#123;&#123; name }}.' ],
            'debug_suppressInsecureConnectWarning' => [false, '[false|true] If true, doesn\'t warn when detecting an insecure connection (i.e. HTTP, not HTTPS).' ],
            'debug_monitorUnusedVariables'      => [false, '[false|true] If true, Lizzy keeps track of variable usage. Initialize tracking with url-arg "?reset"' ],

            //'feature_autoAttrFile'              => [false, 'Name of file (in $configPath) which defines the automatic assignment of class-names to HTML-elements. Used to simplify deployment of CSS-Frameworks, such as Bootstrap.' ],
            'feature_autoForceBrowserCache'     => [false, '[true|false] Determines whether the browser is forced to reload css and js resources when they have been modified.' ],
            'feature_cssFramework'              => ['w3.css', 'Name of CSS-Framework to be invoked {Bootstrap/PureCSS/w3.css}' ],
            'feature_enableSelfSignUp'          => [false, '[true|false] If true, visitors can create a guest account on their own.' ],
            'feature_autoLoadClassBasedModules' => [true, '[false|true] If true, automatically loads modules that are invoked by applying classes, e.g. .editable' ],
            'feature_autoLoadJQuery'            => [false, '[true|false] whether jQuery should be loaded automatically (even if not initiated by one of the macros)' ],
            'feature_jQueryModule'              => ['JQUERY', 'One of [ JQUERY | JQUERY1 | JQUERY2 | JQUERY3 ], default is jQuery 3.x.' ],
            'feature_loadJQuery'                => [false, '[true|false] synonym for "feature_autoLoadJQuery"' ],
            'feature_loadFontAwesome'           => [false, '[true|false] loads Font-Awesome support' ],
            'feature_pageSwitcher'              => [false, '[true|false] whether code should be added to support page switching (by arrow-keys or swipe gestures)' ],
            'feature_quickview'                 => [false, '[true|false] enables automatic Quickview of images' ],
            'feature_renderTxtFiles'            => [false, '[true|false] If true, all .txt files in the pages folder are rendered (in &lt;pre>-tags, i.e. as is). Otherwise they are ignored.' ],
            'feature_selflinkAvoid'             => [true, '[true|false] If true, the nav-link of the current page is replaced with a local page link (to improve accessibility).' ],
            'feature_sitemapFromFolders'        => [false, '[true|false] If true, the sitemap will be derieved from the folder structure under pages/, rather than the config/sitemap.yaml file.' ],
            'feature_slideShowSupport'          => [false, '[true|false] If true, provides support for slide-shows' ],
            'feature_supportLegacyBrowsers'     => [false, '[true|false] Determines whether jQuery 3 can be loaded, if false jQuery 1 is invoked' ],
            'feature_touchDeviceSupport'        => [true, '[true|false] Determines whether to support swipe gestures etc. on touch devices.' ],

            'path_logPath'                      => [false, '[true|Name] Name of folder to which logging output should be sent. Or "false" for disabling logging.' ],
            'path_pagesPath'                    => ['pages/', 'Name of folder in which all pages reside.' ],
            'path_stylesPath'                   => ['css/', 'Name of folder in which style sheets reside' ],
            'path_userCodePath'                 => ['code/', 'Name of folder in which user-provided PHP-code must reside.' ],

            'site_defaultLanguage'              => ['en', 'Default language as two-character code, e.g. "en"' ],
            'site_enableCaching'                => [false, '[true|false] whether caching should be active.' ],
            'site_multiLanguageSupport'         => [false, '[true|false] whether support for multiple languages should be active.' ],
            'site_pageTemplateFile'             => ['page_template.html', "Name of file that will be used as the template. Must be located in '{$this->path_userCodePath}''"],
            'site_sitemapFile'                  => ['sitemap.txt', 'Name of file that defines the site structure. Build hierarchie simply by indenting.' ],
            'site_supportedLanguages'           => ['', '' ],


            ];

        // These settings are considered internal, so they shouldn't be altered by apps:
        $this->configFileSettings['site_supportedLanguages'][0] = $this->configFileSettings['site_defaultLanguage'][0];
        $this->configFileSettings['site_supportedLanguages'][1] = 'Comma-separated list of language-codes. E.g. "en, de, fr"';



        // shortcuts for modules to be loaded (upon request):
        // weight value controls the order of invokation. The higher the earlier.
        $this->jQueryWeight = 200;
        $this->loadModules['JQUERY']                = array('module' => 'third-party/jquery-3.3.1.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY3']               = array('module' => 'third-party/jquery-3.3.1.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY2']               = array('module' => 'third-party/jquery-2.2.4.min.js', 'weight' => $this->jQueryWeight);
        $this->loadModules['JQUERY1']               = array('module' => 'third-party/jquery-1.12.4.min.js', 'weight' => $this->jQueryWeight);

        $this->loadModules['JQUERYUI']              = array('module' => 'third-party/jquery-ui.min.js', 'weight' => 140);
        $this->loadModules['JQUERYUI_CSS']          = array('module' => 'third-party/jquery-ui.min.css', 'weight' => 140);

        $this->loadModules['FONTAWESOME']           = array('module' => 'third-party/font-awesome/5.0.6/svg-with-js/js/fontawesome-all.min.js', 'weight' => 135);
        $this->loadModules['AUXILIARY']             = array('module' => 'js/auxiliary.js', 'weight' => 130);

        $this->loadModules['EDITABLE']              = array('module' => 'js/editable.js', 'weight' => 120);
        $this->loadModules['EDITABLE_CSS']          = array('module' => 'css/editable.css.php', 'weight' => 120);

        $this->loadModules['PANELS']                = array('module' => 'js/panels.js', 'weight' => 110);
        $this->loadModules['PANELS_CSS']            = array('module' => 'css/panels.css.php', 'weight' => 110);

        $this->loadModules['DOODLE']                = array('module' => 'js/doodle.js', 'weight' => 100);
        $this->loadModules['DOODLE_CSS']            = array('module' => 'css/doodle.css', 'weight' => 100);

        $this->loadModules['QUICKVIEW']     	    = array('module' => 'js/quickview.js', 'weight' => 90);
        $this->loadModules['QUICKVIEW_CSS']         = array('module' => 'css/quickview.css', 'weight' => 90);

        $this->loadModules['POPUPS']                = array('module' => 'js/popup.js', 'weight' => 85);

        $this->loadModules['MAC_KEYS']              = array('module' => 'third-party/mac-keys/mac-keys.js', 'weight' => 80);

        $this->loadModules['HAMMERJS']              = array('module' => 'third-party/hammerjs/hammer2.0.8.min.js', 'weight' => 70);
        $this->loadModules['HAMMERJQ']              = array('module' => 'third-party/hammerjs/jquery.hammer.js', 'weight' => 70);
        $this->loadModules['PANZOOM']               = array('module' => 'third-party/panzoom/jquery.panzoom.min.js', 'weight' => 60);

        $this->loadModules['DATATABLES_CSS']        = array('module' => 'third-party/datatables/datatables.min.css', 'weight' => 50);
        $this->loadModules['DATATABLES']            = array('module' => 'third-party/datatables/datatables.min.js', 'weight' => 50);

        $this->loadModules['ZOOM_TARGET']           = array('module' => 'third-party/zoomooz/jquery.zoomooz.min.js', 'weight' => 45);
        $this->loadModules['TOUCH_DETECTOR']        = array('module' => 'js/touch_detector.js', 'weight' => 40);
        $this->loadModules['SLIDESHOW_SUPPORT']     = array('module' => 'js/slideshow_support.js', 'weight' => 32);
        $this->loadModules['SLIDESHOW_SUPPORT_CSS'] = array('module' => 'css/slideshow_support.css', 'weight' => 32);
        $this->loadModules['PAGE_SWITCHER']         = array('module' => 'js/page_switcher.js', 'weight' => 30);
        $this->loadModules['TETHER']                = array('module' => 'third-party/tether.js/tether.min.js', 'weight' => 20);


        $this->loadModules['W3CSS_CSS']             = array('module' => 'third-party/w3.css/w3.css', 'weight' => 10);
        // $this->loadModules['W3CSS_ATTR']            = array('module' => '~/'.$this->configPath.'w3css-auto-attrs.yaml', 'weight' => 10);

        $this->loadModules['PURECSS_CSS']           = array('module' => 'third-party/pure-css/pure-min.css', 'weight' => 10);
        // $this->loadModules['PURECSS_ATTR']          = array('module' => '~/'.$this->configPath.'purecss-auto-attrs.yaml', 'weight' => 10);

        $this->loadModules['BOOTSTRAP_CSS']         = array('module' => 'third-party/bootstrap4/css/bootstrap.min.css', 'weight' => 10);
        $this->loadModules['BOOTSTRAP']             = array('module' => 'third-party/bootstrap4/js/bootstrap.min.js', 'weight' => 10);
        // $this->loadModules['BOOTSTRAP_ATTR']        = array('module' => '~/'.$this->configPath.'bootstrap-auto-attrs.yaml', 'weight' => 10);

        $this->loadModules['USER_ADMIN']            = array('module' => 'js/user_admin.js', 'weight' => 5);
        $this->loadModules['USER_ADMIN_CSS']        = array('module' => 'css/user_admin.css,~/css/user_admin.css', 'weight' => 5);



        // modules that shall be loaded when corresponding classes are found anywhere in the page:
        $this->classBasedModules = [
            'editable' => ['cssFiles' => 'EDITABLE_CSS', 'jqFiles' => 'EDITABLE'],
            'panels_widget' => ['cssFiles' => 'PANELS_CSS', 'jqFiles' => 'PANELS'],
            'zoomTarget' => ['jsFiles' => 'ZOOM_TARGET'],
        ];

        return $this;
    }
} // Defaults
