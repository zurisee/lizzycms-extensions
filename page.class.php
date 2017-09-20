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
    private $cssFiles = '';
    private $css = '';
    private $jsFiles = '';
    private $js = '';
    private $jqFiles = '';
    private $jq = '';
    private $autoAttrFiles = '';
    private $body_end_injections = '';
    private $message = '';
    private $pageSubstitution = false;
    private $override = false;   // if set, will replace the page content
    private $overlay = false;    // if set, will add an overlay while the original page gets fully rendered
    private $debugMsg = false;
    


    public function __construct($config = false)
    {
        $this->config = $config;
    }



    private function appendValue($key, $value)
    {
        if (isset($this->$key)) {
            if ($this->$key) {
                $this->$key .= $value;
            } else {
                $this->$key = $value;
            }
            return true;
        } else {
            $this->$key = $value;
            return false;
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



    public function merge($page)
    {
        foreach ($page as $key => $value) {
            if ($key == 'config') { continue; }
           $this->appendValue($key, $value);
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
        $this->addToProperty($this->body, $str, $replace);
    } // addBody



    //-----------------------------------------------------------------------
    public function addContent($str, $replace = false)
    {
        $this->addToProperty($this->content, $str, $replace);
    } // addContent



    //-----------------------------------------------------------------------
    public function addHead($str, $replace = false)
    {
        $this->addToProperty($this->head, $str, $replace);
    } // addHead



    //-----------------------------------------------------------------------
    public function addCssFiles($str, $replace = false)
    {
        $this->addToListProperty($this->cssFiles, $str, $replace);
    } // cssFiles



    //-----------------------------------------------------------------------
    public function addCss($str, $replace = false)
    {
        $this->addToProperty($this->css, $str, $replace);
    } // addCss



    //-----------------------------------------------------------------------
    public function addJsFiles($str, $replace = false, $persisent = false)
    {
        $this->addToListProperty($this->jsFiles, $str, $replace);
		if ($persisent) {
			$_SESSION["lizzyPersistentJsFiles"] .= $str;
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
        $this->addToProperty($this->js, $str, $replace);
    } // addJs



    //-----------------------------------------------------------------------
    public function addJQFiles($str, $replace = false)
    {
        $this->addToListProperty($this->jqFiles, $str, $replace);
    } // addJQFiles



    //-----------------------------------------------------------------------
    public function addJQ($str, $replace = false)
    {
        $this->addToProperty($this->jq, $str, $replace);
    } // addJQ



    //-----------------------------------------------------------------------
    public function addBody_end_injections($str, $replace = false)
    {
        $this->addToProperty($this->body_end_injections, $str, $replace);
    } // addBody_end_injections



    //-----------------------------------------------------------------------
    public function addMessage($str, $replace = false)
    {
        $this->addToProperty($this->message, $str, $replace);
    } // addMessage



    //-----------------------------------------------------------------------
    public function substitutePage($str)
    {
        $this->pageSubstitution = $str;
    } // substitutePage



    //-----------------------------------------------------------------------
    public function addOverride($str, $replace = false)
    {
        $this->addToProperty($this->override, $str, $replace);
    } // addOverride



    //-----------------------------------------------------------------------
    public function addOverlay($str, $replace = false)
    {
        $this->addToProperty($this->overlay, $str, $replace);
    } // addOverlay



    //-----------------------------------------------------------------------
    public function addDebugMsg($str, $replace = false)
    {
        $this->addToProperty($this->debugMsg, $str, $replace);
    } // addDebugMsg



    //-----------------------------------------------------------------------
    protected function addToProperty(&$property, $var, $replace = false)
    {
        if ($replace) {
            $property = $var;
        } else {
            $property .= $var;
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
            $o = compileMarkdownStr($o);
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
            $overlay = compileMarkdownStr($overlay);
            $this->addJq("\$('.overlay').click(function() { $(this).hide(); });");
            $this->addBody("<div class='overlay'>$overlay</div>\n");
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
            $this->addBody($pageSubstitution, true);
            return true;
        }
        return false;
    } // applayPageSubstitution




    //....................................................
    public function headInjections()
    {
        $headInjections = $this->get('head');
        $headInjections .= "\n".$this->getModules('css', $this->get('cssFiles'));
        if ($this->get('css')) {
            $headInjections .= "\t<style type='text/css'>\n".$this->get('css')."\n\t</style>\n";
        }
        $this->css = '';
        $this->cssFiles = '';
        $this->head = '';
        $headInjections = "\t<!-- head injections -->\n$headInjections\t<!-- /head injections -->\n";
        return $headInjections;
    } // headInjections



    //....................................................
    public function bodyEndInjections()
    {
        global $globalParams;
        $bodyEndInjections = '';

        $this->addJqFiles("~sys/js/touch_detector.js");
        if (($this->config->loadJQuery) || ($this->config->autoLoadJQuery)) {
            $this->addJqFiles($this->config->jQueryModule);
        }
        if ($this->get('jsFiles')) {
            $bodyEndInjections .= $this->getModules('js', $this->get('jsFiles'));
        }
        if ($this->get('jq') && !$this->get('jqFiles')) {
            $bodyEndInjections .= $this->getModules('js', $this->config->jQueryModule);
        }
        if ($this->get('jqFiles') || $this->get('jq')) {
            $bodyEndInjections .= $this->getModules('js', $this->get('jqFiles'));
        }
        if ($this->get('js')) {
            $bodyEndInjections .= "\t<script type='text/javascript'>\n".$this->get('js')."\n\t</script>\n";
        }
        if ($this->get('jq')) {
            $bodyEndInjections .= "\t<script type='text/javascript'>\n\t\t\$( document ).ready(function() {\n\t\t".$this->get('jq')."\n\t\t});\n\t</script>\n";
        }

        $pathToRoot = $globalParams['pathToRoot'];
        $appRootJs = "var appRoot = '$pathToRoot';";
        $sysPathJs = "var systemPath = '$pathToRoot{$this->config->systemPath}';";

        $bodyEndInjections = "\t<script type='text/javascript'>\n\t\t$appRootJs $sysPathJs\n\t</script>\n".$bodyEndInjections;
        if ($this->get('body_end_injections')) {
            $bodyEndInjections .= $this->get('body_end_injections');
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
        foreach($lines as $mod) {
            $mod = trim($mod);
            if (in_array($mod, array_keys($this->config->loadModules))) {
                if (empty($modules[$this->config->loadModules[$mod]['weight']])) {
                    if (($mod == 'JQUERY') || preg_match('/^JQUERY\d/', $mod)) {	// call for jQuery (but not jQueryUI etc)
                        $modules[$this->config->loadModules[$mod]['weight']] = $mod;
                    } else {
                        $modules[$this->config->loadModules[$mod]['weight']] = $sys.$this->config->loadModules[$mod]['module'];
                    }
                } else {
                    $prevMod = $modules[$this->config->loadModules[$mod]['weight']];
                    if (strcmp($mod, $prevMod) > 0) {
                        $modules[$this->config->loadModules[$mod]['weight']] = $mod;
                    }
                }
            } else {
                if (!$mod || strpos($modules[0], $mod) !== false) {
                    continue;
                }
                if ($type == 'js') {
                    if (strpos($mod, "<script") !== false) {
                        $modules[0] .= $mod;
                    } else {
                        $modules[0] .= "\t<script src='$mod' type='text/javascript'></script>\n";
                    }
                } else  {
                    if (strpos($mod, "<link") !== false) {
                        $modules[0] .= $mod;
                    } else {
                        $modules[0] .= "\t<link   href='$mod' type='text/css' rel='stylesheet'>\n";
                    }
                }
            }
        }
        if (isset($modules[9]) && (strpos($modules[9], 'JQUERY') === 0)) {
            if ($globalParams['legacyBrowser']) {
                $modules[9] = $sys . $this->config->loadModules['JQUERY1']['module'];
            } else {
                $modules[9] = $sys . $this->config->loadModules[$modules[9]]['module'];
            }
        }
        if (($type == 'js') && (sizeof($modules) > 1) && !isset($modules[9])) {	// automatically prepend jQuery if missing
            $modules[9] = $sys.$this->config->loadModules['JQUERY']['module'];
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
                    $out .= "\t<script src='$mod' type='text/javascript'></script>\n";
                }
            } else  {
                if (strpos($mod, "<link") !== false) {
                    $out .= "\t$mod\n";
                } else {
                    $out .= "\t<link   href='$mod' type='text/css' rel='stylesheet'>\n";
                }
            }
        }
        return $out;
    } // getModules

} // Page