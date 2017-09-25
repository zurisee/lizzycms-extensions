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
        // These are the settings that can be used in config/config.yaml:
        $this->configFileSettings      = [
            'userVariables'             => ['variables*.yaml', 	'Filename-pattern to identify files that should be loaded as ("transvar-)variables.' ],
            'userComputedVariablesFile' => ['user-var-defs.php', 'Filename of PHP-code that will generate ("transvar-)variables.' ],
            'pageTemplateFile'          => ['page_template.html', 'Name of file that will be used as the template. Must be located in $userCodePath'],
            'sitemapFile'               => ['sitemap.txt', 'Name of file that defines the site structure. Build hierarchie simply by indenting.' ],
            'defaultLanguage'           => ['de', 'Default language as two-character code, e.g. "en"' ],
            'multiLanguageSupport'      => [false, '[true|false] whether support for multiple languages should be active.' ],
            'caching'                   => [false, '[true|false] whether caching should be active.' ],
            'pagesPath'                 => ['pages/', 'Name of folder in which all pages reside.' ],
            'stylesPath'                => ['css/', 'Name of folder in which style sheets reside' ],
            'logPath'                   => [false, 'Name of folder to which logging output should be sent. Or "false" for disabling logging.' ],
            'errorLogging'              => [false, 'Name of the file to which error logging output should be sent. Or "false" for disabling logging.' ],
            'dataPath'                  => ['data/', '(obsolete) Name of folder in which data is located by default.' ],
            'userCodePath'              => ['code/', 'Name of folder in which user-provided PHP-code must reside.' ],
            'usersFile'                 => ['users.yaml', 'Name of file (in $configPath) that defines user privileges and hashed passwords etc.' ],
            'autoAttrFile'              => [false, 'Name of file (in $configPath) which defines the automatic assignment of class-names to HTML-elements. Used to simplify deployment of CSS-Frameworks, such as Bootstrap.' ],
            'permitUserVarDefs'         => ['sandboxed', '[\'sandboxed\'|true|false] Only if true, "_code/user-var-defs.php" will be executed.' ],
            'permitUserCode'            => [false, '[true|false] Only if true, user-provided code can be executed. And only if located in $userCodePath' ],
            'pageSwitcher'              => [false, '[true|false] whether code should be added to support page switching (by arrow-keys or swipe gestures)' ],
            'autoLoadJQuery'            => [false, '[true|false] whether jQuery should be loaded automatically (even if not initiated by one of the macros)' ],
            'loadJQuery'                => [false, '[true|false] synonym for "autoLoadJQuery"' ],
            'jQueryModule'              => ['JQUERY', 'One of [ JQUERY | JQUERY1 | JQUERY2 | JQUERY3 ], default is jQuery 3.x.' ],
            'quickview'                 => [false, '[true|false] enables automatic Quickview of images' ],
            'enableEditing'             => [false, '[true|false] enables online editing' ],
            'cssFramework'              => [false, 'Name of CSS-Framework to be invoked {Bootstrap/PureCSS/w3.css}' ],
            ];

        // These settings are considered internal, so they shouldn't be altered by apps:
        $this->configFileSettings['supportedLanguages'][0] = $this->configFileSettings['defaultLanguage'][0];
        $this->configFileSettings['supportedLanguages'][1] = 'Comma-separated list of language-codes. E.g. "en, de, fr"';

        $this->macrosPath               = SYSTEM_PATH.'macros/';
        $this->configPath               = CONFIG_PATH;
        $this->systemPath               = SYSTEM_PATH;
        $this->systemHttpPath           = '~/'.SYSTEM_PATH;

        $this->userCodePath             = 'code/';
        $this->userInitCodeFile         = $this->userCodePath.'user-init-code.php';
        $this->cachePath                = '.#cache/';
        $this->siteIdententation        = 4;


        // shortcuts for modules to be loaded (upon request):
        // weight value controls the order of invokation. The higher the earlier.
        $this->loadModules['JQUERY']           = array('module' => 'third-party/jquery-3.2.1.min.js', 'weight' => 19);
        $this->loadModules['JQUERY3']          = array('module' => 'third-party/jquery-3.2.1.min.js', 'weight' => 19);
        $this->loadModules['JQUERY2']          = array('module' => 'third-party/jquery-2.2.4.min.js', 'weight' => 19);
        $this->loadModules['JQUERY1']          = array('module' => 'third-party/jquery-1.12.4.min.js', 'weight' => 19);

        $this->loadModules['JQUERYUI']         = array('module' => 'third-party/jquery-ui.min.js', 'weight' => 18);
        $this->loadModules['JQUERYUI_CSS']     = array('module' => 'third-party/jquery-ui.min.css', 'weight' => 18);

        $this->loadModules['EDITABLE']         = array('module' => 'js/editable.js', 'weight' => 14);
        $this->loadModules['EDITABLE_CSS']     = array('module' => 'css/editable.css', 'weight' => 14);

        $this->loadModules['QUICKVIEW']     	= array('module' => 'js/quickview.js', 'weight' => 12);
        $this->loadModules['QUICKVIEW_CSS']     = array('module' => 'css/quickview.css', 'weight' => 12);

        $this->loadModules['HAMMERJS']          = array('module' => 'third-party/hammerjs/hammer2.0.8.min.js', 'weight' => 10);
        $this->loadModules['HAMMERJQ']          = array('module' => 'third-party/hammerjs/jquery.hammer.js', 'weight' => 10);
        $this->loadModules['JQUERYUI_TOUCH']    = array('module' => 'third-party/jquery.ui.touch-punch.min.js', 'weight' => 8);

        $this->loadModules['DATATABLES_CSS']    = array('module' => 'third-party/datatables/datatables.min.css', 'weight' => 6);
        $this->loadModules['DATATABLES']        = array('module' => 'third-party/datatables/datatables.min.js', 'weight' => 6);

        $this->loadModules['TOUCH_DETECTOR']    = array('module' => 'js/touch_detector.js', 'weight' => 5);
        $this->loadModules['PAGE_SWITCHER']     = array('module' => 'js/page_switcher.js', 'weight' => 4);

        return $this;
    }
} // Defaults
