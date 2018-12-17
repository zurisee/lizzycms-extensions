<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Page and its Components
 *
*/

define('MAX_ITERATION_DEPTH', 10);



class Page
{
    private $template = '';
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
    private $bodyTopInjections = '';
    private $bodyEndInjections = '';
    private $message = '';
    private $popup = false;
    private $pageSubstitution = false;
    private $override = false;   // if set, will replace the page content
    private $overlay = false;    // if set, will add an overlay while the original page gets fully rendered
    private $debugMsg = false;

    private $mdCompileOverride = false;
    private $mdCompileOverlay = false;
    private $overlayClosable = true;
    private $wrapperTag = 'section';

    private $assembledBodyEndInjections = '';
    private $assembledCss = '';
    private $assembledJs = '';
    private $assembledJq = '';

    private $pageElements = [
        'template', 'content', 'head', 'description', 'keywords',
        'cssFiles', 'css', 'jsFiles', 'js', 'jqFiles', 'jq',
        'bodyTopInjections', 'bodyEndInjections',
        'pageSubstitution', 'override','overlay','debugMsg', 'message', 'popup',
    ];




    public function __construct($lzy = false)
    {
        if ($lzy) {
            $this->lzy = $lzy;
            $this->trans = $lzy->trans;
            $this->config = $lzy->config;
        } else {
            $this->lzy = null;
            $this->trans = null;
            $this->config = false;
        }
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
            if (!in_array($key, $this->pageElements)) { // skip properties that are not page-elements
                continue;
            }

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
        $this->addToProperty('bodyTopInjections', $str, $replace);
    } // addBody



    //-----------------------------------------------------------------------
    public function addTemplate($str)
    {
        $this->addToProperty('template', $str, true);
    } // addContent



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
    public function addJQ($str, $replace = false)
    {
        //??? avoid adding 'lzy-editable' multiple times:
        if ((strpos($str, '.lzy-editable') !== false) && (strpos($this->jq, '.lzy-editable') !== false)) {
            return;
        }

        $this->addToProperty('jq', $str, $replace);
    } // addJQ



    //-----------------------------------------------------------------------
    public function addBodyEndInjections($str, $replace = false)
    {
        $this->addToProperty('bodyEndInjections', $str, $replace);
    } // addBodyEndInjections



    //-----------------------------------------------------------------------
    public function addMessage($str, $replace = false)
    {
        $this->addToProperty('message', $str, $replace);
    } // addMessage



    //-----------------------------------------------------------------------
    public function addPageSubstitution($str, $replace = false)
    {
        $this->addToProperty('pageSubstitution', $str, $replace);
    } // addMessage



    //-----------------------------------------------------------------------
    public function removeModule($module, $str)
    {
        $mod = $this->$module;
        $mod = str_replace($str, '', $mod);
        $mod = str_replace(',,', ',', $mod);
        $this->$module = $mod;
    } // removeModule





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
    public function registerPopupContent($id, $popupForm)
    {
        if (!$this->popup) {
            require_once SYSTEM_PATH.'popup.class.php';
            $this->popup = new PopupWidget($this);
            $this->popup->createPopupTemplate();
        }

        $this->popup->registerPopupContent($id, $popupForm);
    }



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
    public function addOverlay($str, $replace = false, $mdCompile = null, $closable = true)
    {
        $this->addToProperty('overlay', $str, $replace);
        if ($mdCompile !== null) {  // only override, if explicitly mentioned
            $this->mdCompileOverlay = $mdCompile;
        }
        $this->overlayClosable = $closable;
    } // addOverlay



    //-----------------------------------------------------------------------
    public function addDebugMsg($str, $replace = false)
    {
        $this->addToProperty('debugMsg', $str, $replace);
    } // addDebugMsg



    //-----------------------------------------------------------------------
    protected function addToProperty($key, $var, $replace = false)
    {
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




    public function applyBodyTopInjection( $html )
    {
        if (!$this->bodyTopInjections) {
            return $html; // nothing to do
        }

        $p = strpos($html, '<body');
        if ($p) {
            $p = strpos($html, '>', $p);
            if (!$p) {  // syntax error, body tag not closed
                return $html;
            }
            $p++;
            $injectStr = "\n\t<!-- body top injections -->\n".$this->bodyTopInjections."\t<!-- /body top injections -->\n";
            $html = substr($html, 0, $p).$injectStr.substr($html, $p);
        }
        return $html;
    } // applyBodyTopInjection




    //....................................................
    public function applyOverride()
    {
        if ($o = $this->get('override', true)) {
            if ($this->mdCompileOverride) {
                $o = compileMarkdownStr($o);
                $this->mdCompileOverride = false;
            }
            $this->addContent($o, true);
            return true;
        }
        return false;
    } // applyOverride


    //....................................................
    public function setOverlayClosable($on = true)
    {
        $this->overlayClosable = $on;
    }



    //....................................................
    public function applyOverlay()
    {
        $overlay = $this->overlay;

        if ($overlay) {
            if ($this->mdCompileOverlay) {
                $overlay = compileMarkdownStr($overlay);
            }

            if ($this->overlayClosable) {
                $overlay = "<button id='close-overlay' class='close-overlay'>✕</button>\n".$overlay;
                // set ESC to close overlay:
                $this->addJq("\n$('body').keydown( function (e) { if (e.which == 27) { $('.overlay').hide(); } });\n".
                "$('#close-overlay').click(function() { $('.overlay').hide(); });");
            }
            $this->addBody("<div class='overlay'>$overlay</div>\n");
            $this->removeModule('jqFiles', 'PAGE_SWITCHER');
            $this->overlay = false;
            return true;
        }
        return false;
    } // applyOverlay




    //....................................................
    public function applyDebugMsg()
    {
        if ($debugMsg = $this->debugMsg) {
            $debugMsg = compileMarkdownStr($debugMsg);
            $debugMsg = createDebugOutput($debugMsg);
            $debugMsg = "<div id='log-placeholder'></div>\n".$debugMsg;
            $this->addBodyEndInjections($debugMsg);
            $this->debugMsg = false;
            return true;
        }
        return false;
    } // applyDebugMsg




    //....................................................
    public function applyMessage()
    {
        if ($msg = $this->message) {
            $msg = compileMarkdownStr($msg);
            $msg = createWarning($msg);
            $this->addBody($msg);
            $this->message = false;
            return true;
        }
        return false;
    } // applyMessage




    //....................................................
    public function autoInvokeClassBasedModules($content)
    {
        $modified = false;
        foreach ($this->config->classBasedModules as $class => $modules) {
            $varname = 'class_'.$class;
            if (isset($this->config->$varname) && ($class != $this->config->$varname)) {
                $class1 = $this->config->$varname;
            }
            if (preg_match("/class\=.*['\"\s] $class1 ['\"\s]/x", $content, $m)) {
                foreach ($modules as $module => $rsc) {
                    $modified = true;
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
                unset($this->config->classBasedModules[$class]); // avoid loading module multiple times
            }
        }
        return $modified;
    } // autoInvokeClassBasedModules




    //....................................................
    private function getHeadInjections()
    {
        $headInjections = $this->head;

        $keywords = $this->keywords;
        if ($keywords) {
            $keywords = "\t<meta name='keywords' content='$keywords' />\n";
        }

        $description = $this->description;
        if ($description) {
            $description = "\t<meta name='description' content='$description' />\n";
        }
        $headInjections .= $keywords.$description;

        if ($this->config->feature_loadFontAwesome) {   // Font-Awesome support needs to go into head:
            $this->addJQFiles('FONTAWESOME');
        }

        $headInjections .= $this->getModules('css', $this->cssFiles);

        if ($this->assembledCss) {
            $assembledCss = "\t\t".preg_replace("/\n/", "\n\t\t", $this->assembledCss);
            $headInjections .= "\t<style>\n{$assembledCss}\n\t</style>\n";
        }

        $headInjections = "\t<!-- head injections -->\n$headInjections\t<!-- /head injections -->";
        return $headInjections;
    } // getHeadInjections



    //....................................................
    public function prepareBodyEndInjections()
    // interatively collects snippets for js, jq, bodyTopInjections, bodyEndInjection (text)
    {
        $modified = false;

        if ($this->css) {
            $this->assembledCss .= $this->css;
            $this->css = '';
            $modified = true;
        }

        if ($this->js) {
            $this->assembledJs .= $this->js;
            $this->js = '';
            $modified = true;
        }

        if ($this->jq) {
            $this->assembledJq .= $this->jq;
            $this->jq = '';
            $modified = true;
        }

        return $modified;
    } // prepareBodyEndInjections




    public function getBodyEndInjections()
    {
        $bodyEndInjections = $this->bodyEndInjections;

        if ($this->jsFiles) {
            $bodyEndInjections .= $this->getModules('js', $this->jsFiles);
        }

        // jQuery needs to be loaded if any jq code is present:
        if ($this->jq && !$this->jqFiles) {
            $bodyEndInjections .= $this->getModules('js', $this->config->feature_jQueryModule);
        }
        if ($this->jqFiles) {
            $bodyEndInjections .= $this->getModules('js', $this->jqFiles);
        }

        if ($this->get('lightbox')) {
            $bodyEndInjections .= $this->lightbox;
        }

        $screenSizeBreakpoint = $this->config->feature_screenSizeBreakpoint;
        $pathToRoot = $this->lzy->pathToRoot;
        $rootJs  = "\t\tvar appRoot = '$pathToRoot';\n";
        $rootJs .= "\t\tvar systemPath = '$pathToRoot{$this->config->systemPath}';\n";
        $rootJs .= "\t\tvar screenSizeBreakpoint = $screenSizeBreakpoint;\n";
        $rootJs .= "\t\tvar pagePath = '{$this->lzy->pagePath}';\n";

        if (isset($this->config->editingMode) && $this->config->editingMode && $this->config->admin_hideWhileEditing) {  // for online-editing: add admin_hideWhileEditing
            $selectors = '';
            foreach (explode(',', $this->config->admin_hideWhileEditing) as $elem) {
                $elem = trim(str_replace(['"',"'"], '', $elem));
                $selectors .= "'".$elem."',";
            }
            $selectors = rtrim($selectors, ',');
            $rootJs .= "\n\t\tvar admin_hideWhileEditing = [$selectors];";
        }

        if (($this->config->debug_allowDebugInfo) &&
            (($this->config->debug_showDebugInfo)) || getUrlArgStatic('debug')) {
            if ($this->config->isPrivileged) {
                $bodyEndInjections .= $this->renderDebugInfo();
            }
        }

        if ($rootJs.$this->assembledJs) {
            $assembledJs = "\t\t".preg_replace("/\n/", "\n\t\t", $this->assembledJs);
            $bodyEndInjections = <<<EOT
    <script>
$rootJs$assembledJs
    </script>
$bodyEndInjections
EOT;
        }

        if ($this->assembledJq) {
            $assembledJq = "\t\t\t".preg_replace("/\n/", "\n\t\t\t", $this->assembledJq);
            $bodyEndInjections .= <<<EOT
    <script>
        $( document ).ready(function() {
$assembledJq
        });        
    </script>
EOT;
        }


        $bodyEndInjections = "<!-- body_end_injections -->\n$bodyEndInjections\n<!-- /body_end_injections -->";

        return $bodyEndInjections;
    } // getBodyEndInjections



    //....................................................
    private function getModules($type, $key)
    {
        global $globalParams;

        $jQloaded = false;

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
                        if ($jQloaded == false) {
                            $modules[$this->config->loadModules[$mod]['weight']] = $mod;
                            $jQloaded = true;
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
            if ($jQloaded == false) {
                if ($globalParams['legacyBrowser']) {
                    writeLog("Legacy-Browser -> jQuery1 loaded.");
                    $modules[$jQweight] = $sys . $this->config->loadModules['JQUERY1']['module'];
                } else {
                    $modules[$jQweight] = $sys . $this->config->loadModules['JQUERY']['module'];
                }
                $jQloaded = true;
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
    public function resolveVarsAndMacros()
    {
        return;
        // translate template
        $this->template = $this->trans->translate($this->template);

        // translate content
        $this->content = $this->trans->translate($this->content);

        // now, feed page elements gathered from macros back to main page object:
        $this->merge($this->trans->getPageObject());

        // translate bodyTopInjections
        $this->bodyTopInjections = $this->trans->translate($this->bodyTopInjections);

        // translate bodyEndInjections
        $this->bodyEndInjections = $this->trans->translate($this->bodyEndInjections);

    } // resolveVarsAndMacros





    //....................................................
    public function render($processShieldedElements = false)
    {
        $n = 0;
        $writeToCache = $this->config->cachingActive;
        if (!$processShieldedElements) {
            $processShieldedElements = !$writeToCache;
        }

        do {
            $modified = false;

            $modified |= $this->trans->supervisedTranslate($this, $this->template, $processShieldedElements);
            $modified |= $this->trans->supervisedTranslate($this, $this->content, $processShieldedElements);

            $modified |= $this->trans->supervisedTranslate($this, $this->assembledJs, $processShieldedElements);
            $modified |= $this->trans->supervisedTranslate($this, $this->assembledJq, $processShieldedElements);
            $modified |= $this->trans->supervisedTranslate($this, $this->bodyTopInjections, $processShieldedElements);
            $modified |= $this->trans->supervisedTranslate($this, $this->bodyEndInjections, $processShieldedElements);

            // pageSubstitution replaces everything, including template. I.e. no elements of original page shall remain
            if ($this->pageSubstitution) {
                return $this->pageSubstitution;
            }

            // inject html just after <body> tag:
            $modified |= $this->applyDebugMsg();
            $modified |= $this->applyMessage();

            // get and inject content, taking into account override and overlay:
            if ($this->override) {
                $this->applyOverride();
                $modified = true;
            } else {
                if ($this->overlay) {
                    $this->applyOverlay();
                    $modified = true;
                }
            }

            // check, whether we need to auto-invoke modules based on classes:
            if ($this->config->feature_autoLoadClassBasedModules) {
                $modified |= $this->autoInvokeClassBasedModules($this->content);
                $modified |= $this->autoInvokeClassBasedModules($this->template);
            }

            // get and inject body-end elements, compile them first:
            $modified |= $this->prepareBodyEndInjections();

            if ($n++ >= MAX_ITERATION_DEPTH) {
                fatalError("Max. iteration depth exeeded.<br>Most likely cause: a recursive invokation of a macro or variable.");
            }
        } while ($modified);

        if ($writeToCache) {
            $this->writeToCache();
        }
        $html = $this->assembleHtml();

        if ($this->trans->shieldedVariablePresent($html)) {
            $html = $this->render(true);
        }

        return $html;
    } // render




    private function assembleHtml()
    {
        $html = $this->template;

        $html = $this->applyBodyTopInjection($html, $this->bodyTopInjections);
        $html = $this->trans->adaptBraces($html);


        $html = $this->injectValue($html, 'head_injections', $this->getHeadInjections());
        $html = $this->injectValue($html, 'content', $this->content);
        $html = $this->injectValue($html, 'body_end_injections', $this->getBodyEndInjections());

        return $html;
    } // assembleHtml



    //....................................................
    private function injectValue( $html, $varName, $varValue)
    {
        return str_replace("@@$varName@@", $varValue, $html);
    } // injectValue



    //....................................................
    public function shieldVariable($str, $varName)
    {
        $str = preg_replace("/\{\{\^?\s*$varName\s*\}\}/", "@@$varName@@", $str);
        return $str;
    } // shieldVariable



    //....................................................
    public function renderDebugInfo()
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
        $debugInfo = str_replace('{', '&#123;', $debugInfo);
        return $debugInfo;
    } // renderDebugInfo



    //....................................................
    public function lateApplyMessag($html, $msg)
    {
        $msg = createWarning($msg);
        $p = strpos($html, '<body');
        if ($p) {
            $p = strpos($html, '>', $p);
            if (!$p) {  // syntax error, body tag not closed
                return $html;
            }
            $p++;
            $html = substr($html, 0, $p).$msg.substr($html, $p);
        }
        return $html;
    }



    //....................................................
    public function lateApplyDebugMsg($html, $msg)
    {
        if ((($p = strpos($html, '<div id="log">')) !== false) ||
            (($p = strpos($html, "<div id='log'>")) !== false)) {
            $p += strlen('<div id="log">');
            $before = substr($html, 0, $p);
            $after = substr($html, $p);
            $msg = "<p>$msg</p>";
            $html = $before . $msg . $after;
        } else {
            $p = strpos($html, '</body>');
            if ($p !== false) {
                $before = substr($html, 0, $p);
                $after = substr($html, $p);
                $html = $before . "<div id=\"log\"><p>$msg</p></div>" . $after;
            }
        }
        return $html;
    } // lateApplyDebugMsg



    private function writeToCache()
    {
        $pg2 = clone $this;
        foreach ($pg2 as $key => $value) {
            if (!in_array($key, $this->pageElements)) {
                unset( $pg2->$key );
            }
        }
        writeToCache($pg2);
    } // writeToCache


    public function readFromCache()
    {
        $pg = readFromCache();
        if (!$pg) {
            return false;
        }
        $this->merge($pg);
        return true;
    }
} // Page
