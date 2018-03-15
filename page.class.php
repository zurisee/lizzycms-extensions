<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Page and its Components
 *
*/

class Page
{
    private $body = '';
    private $content = '';
    private $head = '';
    private $description = '';
    private $keywords = '';
    private $cssFiles = '';
    private $css = '';
    private $jsFiles = '';
    private $js = '';
    private $jqFiles = '';
    private $jq = '';
    private $autoAttrFiles = '';
    private $body_end_injections = '';
    private $message = '';
    private $popup = false;
    private $pageSubstitution = false;
    private $override = false;   // if set, will replace the page content
    private $mdCompileOverride = false;
    private $mdCompileOverlay = false;
    private $overlay = false;    // if set, will add an overlay while the original page gets fully rendered
    private $debugMsg = false;
    private $wrapperTag = 'section';
    private $jQloaded = false;



    public function __construct($config = false)
    {
        $this->config = $config;
    }



    public function set($varname, $val) {
        $this->$varname = $val;
    }



    public function get($var, $reset = false) {
        if (isset($this->$var)) {
            $val = $this->$var;
            if ($reset) {
                $this->$var = '';
            }
            return $val;
        } else {
            return '';
        }
    }



    private function appendValue($key, $value, $replace = false)
    {
        if ($replace) {
            $this->$key = $value;
            return true;
        }

        if (isset($this->$key)) {   // property already set
            if ($this->$key) {
                if ($value) {
                    if (strpos(',jqFiles,jsFiles,cssFiles,', ",$key,") !== false) {
                        $this->$key .= ',' . $value;
                    } elseif (strpos(',jq,js,css,', ",$key,") !== false) {
                        $this->$key .= "\t\t\t$value\n";
                    } else {
                        $this->$key .= $value;
                    }
                }
            } else {
                $this->$key = $value;
            }
            return true;

        } else {    // new property
            $this->$key = $value;
            return false;
        }
    }



    public function merge($page, $propertiesToReplace = '')
    {
        if (!(is_object($page) || is_array($page))) {
            return;
        }
        foreach ($page as $key => $value) {
            if ($key == 'config') { continue; }
            if (($key == 'wrapperTag') || (strpos($propertiesToReplace, $key) !== false)) {
                $this->appendValue($key, $value, true);
            } else {
                $this->appendValue($key, $value);
            }
        }
    }



    public function getEncoded()
    {
        $encoded = serialize($this);
        return $encoded;
    } // getEncoded



    //-----------------------------------------------------------------------
    public function addBody($str, $replace = false)
    {
        $p = strpos($this->body, '</body>');
        if ($p) {   // body already populated -> insert just before </body> end tag.
            $this->body = substr($this->body, 0, $p).$str.substr($this->body, $p);
        } else {
            $this->addToProperty('body', $str, $replace);
        }
    } // addBody



    //-----------------------------------------------------------------------
    public function addContent($str, $replace = false)
    {
        $this->addToProperty('content', $str, $replace);
    } // addContent



    //-----------------------------------------------------------------------
    public function addHead($str, $replace = false)
    {
        $this->addToProperty('head', $str, $replace);
    } // addHead



    //-----------------------------------------------------------------------
    public function addKeywords($str, $replace = false)
    {
        $this->addToListProperty($this->keywords, $str, $replace);
    } // addKeywords



    //-----------------------------------------------------------------------
    public function addDescription($str, $replace = false)
    {
        $this->addToListProperty($this->description, $str, $replace);
    } // addDescription



    //-----------------------------------------------------------------------
    public function addCssFiles($str, $replace = false)
    {
        $this->addToListProperty($this->cssFiles, $str, $replace);
    } // cssFiles



    //-----------------------------------------------------------------------
    public function addCss($str, $replace = false)
    {
        $this->addToProperty('css', $str, $replace);
    } // addCss



    //-----------------------------------------------------------------------
    public function addJsFiles($str, $replace = false, $persisent = false)
    {
        $this->addToListProperty($this->jsFiles, $str, $replace);
		if ($persisent) {
			$_SESSION['lizzy']["lizzyPersistentJsFiles"] .= $str;
		}
    } // addJsFiles



    //-----------------------------------------------------------------------
    public function addAutoAttrFiles($str, $replace = false, $persisent = false)
    {
        $this->addToListProperty($this->autoAttrFiles, $str, $replace);
    } // addAutoAttrFiles



    //-----------------------------------------------------------------------
    public function addJs($str, $replace = false)
    {
        $this->addToProperty('js', $str, $replace);
    } // addJs



    //-----------------------------------------------------------------------
    public function addJQFiles($str, $replace = false)
    {
        $this->addToListProperty($this->jqFiles, $str, $replace);
    } // addJQFiles



    //-----------------------------------------------------------------------
    public function removeModule($module, $str)
    {
        $mod = $this->$module;
        $mod = str_replace($str, '', $mod);
        $mod = str_replace(',,', ',', $mod);
        $this->$module = $mod;
    } // removeModule



    //-----------------------------------------------------------------------
    public function addJQ($str, $replace = false)
    {
        //??? avoid adding 'lzy-editable' multiple times:
        if ((strpos($str, '.lzy-editable') !== false) && (strpos($this->jq, '.lzy-editable') !== false)) {
            return;
        }

        $this->addToProperty('jq', $str, $replace);
    } // addJQ



    //-----------------------------------------------------------------------
    public function addBody_end_injections($str, $replace = false)
    {
        $this->addToProperty('body_end_injections', $str, $replace);
    } // addBody_end_injections



    //-----------------------------------------------------------------------
    public function addMessage($str, $replace = false)
    {
        $this->addToProperty('message', $str, $replace);
    } // addMessage



    //-----------------------------------------------------------------------
    public function addPopup($inx, $args)
    {
        if (!$this->popup) {
            require_once SYSTEM_PATH.'popup.class.php';
            $this->popup = new PopupWidget($this);
            $this->popup->createPopupTemplate();
        }

        if (isset($args[0]) && ($args[0] == 'help')) {
            return $this->popup->renderHelp();
        }

        $this->popup->addPopup( $inx, $args );
        return "\t<!-- lzy-popup invoked -->\n";
    }


    //-----------------------------------------------------------------------
    public function substitutePage($str)
    {
        $this->pageSubstitution = $str;
    } // substitutePage



    //-----------------------------------------------------------------------
    public function setOverrideMdCompile($mdCompile)
    {
        $this->mdCompileOverride = $mdCompile;
    }



    //-----------------------------------------------------------------------
    public function addOverride($str, $replace = false, $mdCompile = true)
    {
        $this->addToProperty('override', $str, $replace);
        $this->mdCompileOverride = $mdCompile;
    } // addOverride



    //-----------------------------------------------------------------------
    public function setOverlayMdCompile($mdCompile)
    {
        $this->mdCompileOverlay = $mdCompile;
    }



    //-----------------------------------------------------------------------
    public function addOverlay($str, $replace = false, $mdCompile = null)
    {
        $this->addToProperty('overlay', $str, $replace);
        if ($mdCompile !== null) {
            $this->mdCompileOverlay = $mdCompile;
        }
    } // addOverlay



    //-----------------------------------------------------------------------
    public function addDebugMsg($str, $replace = false)
    {
        $this->addToProperty('debugMsg', $str, $replace);
    } // addDebugMsg



    //-----------------------------------------------------------------------
    protected function addToProperty($key, $var, $replace = false)
    {
        if (strpos(',jq,js,css,', ",$key,") !== false) {
            $var = "\t\t\t$var\n";
        }
        if ($replace) {
            $this->$key = $var;
        } else {
            $this->$key .= $var;
        }
    } // addToProperty



    //-----------------------------------------------------------------------
    protected function addToListProperty(&$property, $var, $replace = false)
    {
        if (is_array($var)) {
            if ($replace) {
                $property = '';
            }
            if ($property) {
                $property .= ','.implode(',', $var);
            } else {
                $property = implode(',', $var);
            }
        } else {
            if (!$property || $replace) {
                $property = $var;
            } else {
                if (strpos($property, $var) === false) {    // avoid duplication
                    $property .= ','.$var;
                }
            }
        }
    } // addToListProperty



    //....................................................
    public function applyOverride()
    {
        if ($o = $this->get('override', true)) {
            if ($this->mdCompileOverride) {
                $o = compileMarkdownStr($o);
            }
            $this->addContent($o, true);
            return true;
        }
        return false;
    } // applyOverride



    //....................................................
    public function applyOverlay()
    {
        $overlay = $this->get('overlay', true);

        if ($overlay) {
            if ($this->mdCompileOverlay) {
                $overlay = compileMarkdownStr($overlay);
            }
            $this->addBody("<div class='overlay'>$overlay</div>\n");
            $this->set('overlay', '');
            $this->removeModule('jqFiles', 'PAGE_SWITCHER');
            return true;
        }
        return false;
    } // applyOverlay




    //....................................................
    public function applyDebugMsg()
    {
        if ($debugMsg = $this->get('debugMsg', true)) {
            $debugMsg = compileMarkdownStr($debugMsg);
            $debugMsg = createDebugOutput($debugMsg);
            $this->addBody($debugMsg);
            return true;
        }
        return false;
    } // applyDebugMsg



    //....................................................
    public function applyMessage()
    {
        if ($msg = $this->get('message', true)) {
            $msg = compileMarkdownStr($msg);
            $msg = createWarning($msg);
            $this->addBody($msg);
            return true;
        }
        return false;
    } // applyMessage




    //....................................................
    public function applyPageSubstitution()
    {
        $pageSubstitution = $this->get('pageSubstitution', true);
        if ($pageSubstitution) {
            $this->substitutePage('');
            return true;
        }
        return false;
    } // applayPageSubstitution




    //....................................................
    public function autoInvokeClassBasedModules($content)
    {
        foreach ($this->config->classBasedModules as $class => $modules) {
            $varname = 'class_'.$class;
            $urlArg = '';
            if (isset($this->config->$varname) && ($class != $this->config->$varname)) {
                $class0 = $class;
                $class = $this->config->$varname;
                $urlArg = "?$class0=$class";
            }
            if (preg_match("/class\=.*['\"\s] $class ['\"\s]/x", $content, $m)) {
                foreach ($modules as $module => $rsc) {
                    $rsc .= $urlArg;
                    if ($module == 'cssFiles') {
                        $this->addCssFiles($rsc);
                    } elseif ($module == 'css') {
                        $this->addCss($rsc);

                    } elseif ($module == 'jqFiles') {
                        $this->addJQFiles($rsc);
                    } elseif ($module == 'jq') {
                        $this->addJq($rsc);

                    } elseif ($module == 'jsFiles') {
                        $this->addJsFiles($rsc);
                    } elseif ($module == 'jq') {
                        $this->addJq($rsc);
                    }
                }
            }
        }
    } // autoInvokeClassBasedModules




    //....................................................
    public function headInjections()
    {
        $headInjections = $this->get('head');

        $keywords = $this->get('keywords');
        if ($keywords) {
            $keywords = "\t<meta name='keywords' content='$keywords'>\n";
        }

        $description = $this->get('description');
        if ($description) {
            $description = "\t<meta name='description' content='$description'>\n";
        }
        $headInjections .= $keywords.$description;

        if ($this->config->feature_loadFontAwesome) {   // Font-Awesome support needs to go into head:
            $headInjections .= "\t<script defer src='~sys/{$this->config->loadModules['FONTAWESOME']['module']}'></script>\n";
        }

        $headInjections .= $this->getModules('css', $this->get('cssFiles'));
        if ($this->get('css')) {
            $headInjections .= "\t<style>\n".$this->get('css')."\n\t</style>\n";
        }
        $this->css = '';
        $this->cssFiles = '';
        $this->head = '';
        $headInjections = "\t<!-- head injections -->\n$headInjections\t<!-- /head injections -->";
        return $headInjections;
    } // headInjections



    //....................................................
    public function bodyEndInjections()
    {
        global $globalParams;
        $bodyEndInjections = '';

        if ($this->config->feature_touchDeviceSupport) {
            $this->addJqFiles("TOUCH_DETECTOR,AUXILIARY,MAC_KEYS");
        } else {
            $this->addJqFiles("AUXILIARY,MAC_KEYS");
        }

        if (($this->config->feature_loadJQuery) || ($this->config->feature_autoLoadJQuery)) {
            $this->addJqFiles($this->config->feature_jQueryModule);
        }
        if ($this->get('jsFiles')) {
            $bodyEndInjections .= $this->getModules('js', $this->get('jsFiles'));
        }
        if ($this->get('jq') && !$this->get('jqFiles')) {
            $bodyEndInjections .= $this->getModules('js', $this->config->feature_jQueryModule);
        }
        if ($this->get('jqFiles') || $this->get('jq')) {
            $bodyEndInjections .= $this->getModules('js', $this->get('jqFiles'));
        }
        $js = $this->get('js');
        if ($js) {
            $js = "\t\t$js\n";
        }
        if ($this->get('jq')) {
            $bodyEndInjections .= "\t<script>\n\t\t\$( document ).ready(function() {\n".$this->get('jq')."\t\t});\n\t</script>\n";
        }

        if ($this->get('lightbox')) {
            $bodyEndInjections .= $this->get('lightbox');
        }

        $pathToRoot = $globalParams['pathToRoot'];
        $appRootJs = "var appRoot = '$pathToRoot';";
        $sysPathJs = "var systemPath = '$pathToRoot{$this->config->systemPath}';";

        if (isset($this->config->editingMode) && $this->config->editingMode && $this->config->admin_hideWhileEditing) {  // for online-editing: add admin_hideWhileEditing
            $selectors = '';
            foreach (explode(',', $this->config->admin_hideWhileEditing) as $elem) {
                $elem = trim(str_replace(['"',"'"], '', $elem));
                $selectors .= "'".$elem."',";
            }
            $selectors = rtrim($selectors, ',');
            $sysPathJs .= "\n\t\tvar admin_hideWhileEditing = [$selectors];";
        }

        $bodyEndInjections = "\t<script>\n\t\t$appRootJs $sysPathJs\n$js\t</script>\n".$bodyEndInjections;
        if ($tmp = $this->get('body_end_injections')) {
            $bodyEndInjections .= $tmp;
            $this->set('body_end_injections', '');
        }


        if (($this->config->debug_allowDebugInfo) &&
            (($this->config->debug_showDebugInfo)) || getUrlArgStatic('debug')) {
            if ($this->config->isPrivileged) {
                $bodyEndInjections .= $this->renderDebugInfo();
            }
        }

        $bodyEndInjections = "<!-- body_end_injections -->\n$bodyEndInjections\n<!-- /body_end_injections -->";

        $this->js = '';
        $this->jsFiles = '';
        $this->jq = '';
        $this->jqFiles = '';

        return $bodyEndInjections;
    } // bodyEndInjections



    //....................................................
    private function getModules($type, $key)
    {
        global $globalParams;

        // makes sure that explicit version of JQUERY gets precedence over unspecific one
        $key = str_replace(',', "\n", $key);
        $lines = explode("\n", $key);
        $modules = array(0 => '');
        $sys = '~/'.SYSTEM_PATH; //$this->config->systemHttpPath;
        $out = '';
        $jQweight = $this->config->jQueryWeight;
        foreach($lines as $mod) {
            $mod = trim($mod);
            $urlArg = '';
            if (preg_match('/(.*)(\?.*)/', $mod, $m)) {
                $mod = $m[1];
                $urlArg = $m[2];
            }
            if (in_array($mod, array_keys($this->config->loadModules))) {
                if (empty($modules[$this->config->loadModules[$mod]['weight']])) {
                    if (($mod == 'JQUERY') || preg_match('/^JQUERY\d/', $mod)) {	// call for jQuery (but not jQueryUI etc)
                        if ($this->jQloaded == false) {
                            $modules[$this->config->loadModules[$mod]['weight']] = $mod;
                            $this->jQloaded = true;
                        }
                    } else {
                        $name = $sys.$this->config->loadModules[$mod]['module'];
                        if (strpos($name, ',') !== false) {     // case multiple files sep by comma:
                            foreach (explode(',', $name) as $i => $item) {
                                $modules[$this->config->loadModules[$mod]['weight']+$i] = $item . $urlArg;
                            }
                        } else {
                            $modules[$this->config->loadModules[$mod]['weight']] = $name . $urlArg;
                        }
                    }
                } else {
                    $prevMod = $modules[$this->config->loadModules[$mod]['weight']];
                    if (strcmp($mod, $prevMod) > 0) {
                        $modules[$this->config->loadModules[$mod]['weight']] = $mod.$urlArg;
                    }
                }
            } else {
                if (!$mod || strpos($modules[0], $mod) !== false) {
                    continue;
                }
                if ($type == 'js') {
                    if (strpos($mod, "<script") !== false) {
                        $modules[0] .= $mod.$urlArg;
                    } else {
                        $modules[0] .= "<script src='$mod'></script>\n";
                    }
                } else  {
                    if (strpos($mod, "<link") !== false) {
                        $modules[0] .= $mod.$urlArg;
                    } else {
                        $modules[0] .= "<link   href='$mod' rel='stylesheet'>\n";
                    }
                }
            }
        }


        if (isset($modules[$jQweight]) && (strpos($modules[$jQweight], 'JQUERY') === 0)) {
            if ($globalParams['legacyBrowser']) {
                writeLog("Legacy-Browser -> jQuery1 loaded.");
                $modules[$jQweight] = $sys . $this->config->loadModules['JQUERY1']['module'];
            } else {
                $modules[$jQweight] = $sys . $this->config->loadModules[$modules[$jQweight]]['module'];
            }
        }


        if (($type == 'js') && (sizeof($modules) > 1) && !isset($modules[$jQweight])) {	// automatically prepend jQuery if missing
            if ($this->jQloaded == false) {
                if ($globalParams['legacyBrowser']) {
                    writeLog("Legacy-Browser -> jQuery1 loaded.");
                    $modules[$jQweight] = $sys . $this->config->loadModules['JQUERY1']['module'];
                } else {
                    $modules[$jQweight] = $sys . $this->config->loadModules['JQUERY']['module'];
                }
                $this->jQloaded = true;
            }
        }
        ksort($modules);
        while (isset($modules[0]) && !$modules[0]) {
            array_shift($modules);
        }
        while ($mod = array_pop($modules)) {
            if ($type == 'js') {
                if (strpos($mod, "<script") !== false) {
                    $out .= "\t$mod\n";
                } else {
                    $out .= "\t<script src='$mod'></script>\n";
                }
            } else  {
                if (strpos($mod, "<link") !== false) {
                    $out .= "\t$mod\n";
                } else {
                    $out .= "\t<link   href='$mod' rel='stylesheet'>\n";
                }
            }
        }
        return $out;
    } // getModules




    //....................................................
    private function renderDebugInfo()
    {
        global $globalParams;
        $debugInfo = var_r($_SESSION, '$_SESSION');
        $globalParams['whoami'] = trim(shell_exec('whoami')).':'.trim(shell_exec('groups'));
        $debugInfo .= var_r($globalParams, '$globalParams');


        if (file_exists(ERROR_LOG)) {
            $errLog = file_get_contents(ERROR_LOG);
            $errLog = substr($errLog, -1000);
            $debugInfo .= "\n<p><strong>Error Log:</strong></p><div class='log scrollToBottom'>$errLog</div>\n";
        }

        if (file_exists(LOGS_PATH . LOGIN_LOG_FILENAME)) {
            $failedLogins = file_get_contents(LOGS_PATH . LOGIN_LOG_FILENAME);
            $failedLogins = substr($failedLogins, -1000);
            $debugInfo .= "\n<p><strong>Log-ins:</strong></p><div class='log scrollToBottom'>$failedLogins</div>\n";
        }

        if (file_exists(LOGS_PATH . 'log.txt')) {
            $accessLog = file_get_contents(LOGS_PATH . 'log.txt');
            $accessLog = substr($accessLog, -1000);
            $debugInfo .= "\n<p><strong>Access Log:</strong></p><div class='log scrollToBottom'>$accessLog</div>\n";
        }

        if (strpos($debugInfo, 'scrollToBottom') !== false) {
            $this->addJQ('$(".scrollToBottom").scrollTop($(".scrollToBottom")[0].scrollHeight);');
        }

        $debugInfo .= "<div id='log'></div>";
        $debugInfo = "\n<div id='debugInfo'><p><strong>DebugInfo:</strong></p>$debugInfo</div>\n";
        return $debugInfo;
    } // renderDebugInfo

} // Page