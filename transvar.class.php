<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Substitution-Variables and Macros()
 *
 * Usage: {{ }}
 *      or {{x }} where x:
 *          ^   = omit if var is not defined
 *          #   = comment out, skip altogether
 *          !   = force late processing
 *          &   = force md compilation after evaluation
*/

use Symfony\Component\Yaml\Yaml;


class Transvar
{
	private $transvars = array();
	private $usedVars = array();
	private $undefinedVars = array();
	private $macros = array();
	private $macroInfo = array();
	private $invocationCounter = array();
	private $sysVariables = ['content', 'head_injections'];
	public $page;

	//....................................................
	public function __construct($lzy)
	{
	    $this->lzy = $lzy;
		$this->page = new Page;
		$this->config = $lzy->config;

	} // __construct




	//....................................................
	public function render($page, $lang = false, $substitutionMode = false)
	{
        $this->shieldedStrings = [];
	    if (is_string($page)) {
            $html = $page;
        } else {
            $this->page = $page;

            // handle pageReplacement?, override, overlay, message, debugMsg:
            $this->handlePageModifications();

            $html = $page->get('body');
            $html = $this->shieldSpecialVars($html);

            $this->addVariable('content', $page->get('content'), false);
        }

        $html = $this->adaptBraces($html, $substitutionMode);

        $pp = findNextPattern($html, '{{');
        while ($pp !== false) {
            $html = $this->excludeShieldedStrings($html);
			$html = $this->translateMacros($html);
			$html = $this->translateVars($html, 'app', $substitutionMode);
            $pp = findNextPattern($html, '{{');
		}

		$html = $this->translateSpecialVars($html);
        $html = $this->adaptBraces($html, SUBSTITUTE_UNDEFINED);
        $html = $this->translateVars($html, 'app', SUBSTITUTE_UNDEFINED);
        $this->handleLatePageSubstitution();

        if (!isset($this->firstRun)) {  // avoid indefinite loop
            // in case a macro has raised a request for override etc, we need to re-run rendering routine:
            if ($this->page->get('override') ||
                $this->page->get('overlay') ||
                $this->page->get('pageSubstitution') ||
                $this->page->get('debugMsg') ||
                $this->page->get('message')) {
                $this->firstRun = true;
                return $this->render($page, $lang, $substitutionMode);
            }
        }
        $html = $this->replaceShieldedStrings($html);
		return $html;
	} // render




    private function excludeShieldedStrings($str) {
        $p1 = findNextPattern($str, '{[{[');
        $i  = sizeof($this->shieldedStrings);
        while ($p1) {
            $p2 = findNextPattern($str, ']}]}', $p1);
            if ($p2) {
                $this->shieldedStrings[$i] = substr($str, $p1+4, $p2-$p1-4);
                $str = substr($str, 0, $p1+4) . $i . substr($str, $p2);
                $p1 += strlen("{[{[$i]}]}");
                $p1 = findNextPattern($str, '{[{[', $p1);
                $i++;
            }
        }
        return $str;
    }



    private function replaceShieldedStrings($str) {
	    foreach ($this->shieldedStrings as $i => $s) {
	        $pat = "{[{[".$i."]}]}";
	        $str = str_replace($pat, $s, $str);
        }
        return $str;
    }



	//....................................................
	private function translateMacros($str, $namespace = 'app')
	{
        list($p1, $p2) = strPosMatching($str);
		while (($p1 !== false)) {
			$commmented = false;
			$optional = false;
            $dontCache = false;
            $compileMd = false;
			$var = trim(substr($str, $p1+2, $p2-$p1-2));
			if (!$var) {
			    $str = substr($str,0,$p1).substr($str, $p2+2);
                list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
                continue;
            }
            // handle macro-modifiers, e.g. {{# }}
			$c1 = $var{0};
            if (strpos('#^!&', $c1) !== false) {    // modifier? #, ^, !, &
				$var = trim(substr($var, 1));
				if ($c1 == '#') {
					$commmented = true;
				} elseif ($c1 == '!') {
					$dontCache = true;
				} elseif ($c1 == '&') {
                    $compileMd = true;
				} else {
					$optional = true;
				}
			}

            if ($dontCache) {   // don't cache -> shield now and translate after read-cache
                $str = substr($str, 0, $p1) . "{||{ $var }||}" . substr($str, $p2 + 2);

            } else {
                if (!$commmented) {
                    if (strpos($var, '{{') !== false) {     // nested transvar/macros
                        $var = $this->translateMacros($var, $namespace);
                    }

                    $var = str_replace("\n", '', $var);    // remove newlines
                    if (preg_match('/^([\w\-]+)\((.*)\)/', $var, $m)) {    // macro
                        $macro = str_replace(['-','_'], '', $m[1]);

                        $argStr = $m[2];
                        $this->macroArgs[$macro] = parseArgumentStr($argStr);
                        $this->macroInx = 0;

                        if (!isset($this->macros[$macro])) {     // macro already loaded
                            $this->loadMacro($macro);
                        }

                        if (isset($this->macros[$macro])) { // and try to execute it

                            $this->optionAddNoComment = false;
                            $val = $this->macros[$macro]($this);                    // execute the macro

                            if (($this->config->isLocalhost || $this->config->isPrivileged) && !$this->optionAddNoComment) {
                                $val = "\n\n<!-- Lizzy Macro: $macro() -->\n$val\n<!-- /$macro() -->\n\n\n";   // mark its output
                            }

                            if (trim($argStr) == 'help') {
                                if ($val2 = $this->getMacroHelp($macro)) {
                                    $val = $val2;
                                }
                            }
                            if ($compileMd) {
                                $val = compileMarkdownStr($val);
                            }

                        } elseif ($optional) {              // was marked as optional '{{^', so just skip it
                            $val = '';

                        } else {                            // macro not defined, raise error
                            $msg ="Error: undefined macro: '$macro()'";
                            logError($msg);
                            $val = '';
                        }

                    } else {                                        // variable
                        list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1 + 1);
                        continue;
                    }
                }
                if ($commmented) {
                    $str = substr($str, 0, $p1) . substr($str, $p2 + 2);
                } elseif (!$optional && ($val === false)) {
                    $str = substr($str, 0, $p1) . $var . substr($str, $p2 + 2);
                } else {
                    $before = substr($str, 0, $p1);
                    $after = substr($str, $p2 + 2);
                    // remove spurious <p></p>:
                    if ((substr($before, -3) == '<p>') && (substr($after, 0,4) == '</p>')) {
                        $before = substr($before, 0, -3);
                        $after = substr($after, 4);
                    }
                    $str = $before . $val . $after;
                }
            }
            list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
		}
		return $str;
	} // translateMacros



	//....................................................
	public function translateVars($str, $namespace = 'app', $substitutionMode = false)
	{

        list($p1, $p2) = strPosMatching($str);
		while (($p1 !== false)) {
			$commmented = false;
			$optional = false;
            $dontCache = false;
            $compileMd = false;
			$var = trim(substr($str, $p1+2, $p2-$p1-2));
            if (!$var) {
                $str = substr($str,0,$p1).substr($str, $p2+2);
                list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
                continue;
            }

            if (preg_match('/^ [\#^\!\&]? \s* [\w\-]+ \( /x', $var)) { // skip macros()
                list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
                continue;
            }

            // handle var-modifiers, e.g. {{^ }}
            $c1 = $var{0};
			if (strpos('#^!&',$c1) !== false) {
				$var = trim(substr($var, 1));
				if ($c1 == '#') {
					$commmented = true;
				} elseif ($c1 == '!') {
					$dontCache = true;
                } elseif ($c1 == '&') {
                    $compileMd = true;
                } else {
					$optional = true;
				}
			}
            if ($dontCache) {
                $str = substr($str, 0, $p1) . "{||{ $var }||}" . substr($str, $p2 + 2);

            } else {
                if (!$commmented) {
                    if (strpos($var, '{{') !== false) {
                        $var = $this->translateVars($var, $namespace);
                    }
                    $var = str_replace("\n", '', $var);    // remove newlines
                    if (preg_match('/^(\w+)\((.*)\)/', $var, $m)) {    // macro
                        list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1 + 1);
                        continue;

                    } else {                                        // variable
                        $val = $this->getVariable($var, '', $namespace, $substitutionMode);
                        if ($val === false) {
                            $val = $this->doUserCode($var, $this->config->custom_permitUserCode);
                            if (is_array($val)) {
                                fatalError($val[1]);
                            }
                        }
                        if ($compileMd) {
                            $val = compileMarkdownStr($val);
                        }
                    }
                }
                
                if ($commmented) {
                    $str = substr($str, 0, $p1) . substr($str, $p2 + 2);

                } elseif ($dontCache) {
                    $str = substr($str, 0, $p1) . "{||{ $var }||}" . substr($str, $p2 + 2);

                } elseif (!$optional && ($val === false)) {
                    if ($substitutionMode == SUBSTITUTE_UNDEFINED) {     // undefined are substituted only in the last round
                        $str = substr($str, 0, $p1) . $var . substr($str, $p2 + 2); // replace with itself (minus {{}})
                    } else {
                        $str = substr($str, 0, $p1) . "{|{ $var }|}" . substr($str, $p2 + 2);
                        $p1 += 4;
                    }

                } else {
                    $str = substr($str, 0, $p1) . $val . substr($str, $p2 + 2);   // replace with value
                }
            }
			list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
		}
		return $str;
	} // translateVars





    public function setMacroInfo($macroName, $info)
    {
        $this->macroInfo[] = [$macroName, $info];
    }


    //....................................................
    public function getArg($macroName, $name, $help = '', $default = null, $removeNl = true /*, $dynamic = false*/)
    {
        $inx = $this->macroInx++;
        $this->macroFieldnames[$macroName][$inx] = $name;
        if (preg_match('/^\d/', $name)) {
            $index = intval($name);
            if ($index < sizeof($this->macroArgs[$macroName])) {
                $out = array_values($this->macroArgs[$macroName])[$index];
            }

        } else {
            if (isset($this->macroArgs[$macroName][$name])) {
                $out = $this->macroArgs[$macroName][$name];

            } else {
                if (isset($this->macroArgs[$macroName][$inx])) {
                    $out = $this->macroArgs[$macroName][$inx];
                } elseif ($default !== null) {
                    $out = $default;
                } else {
                    $out = null;
                }
                $this->macroArgs[$macroName][$name] = $out; // prepare named option as well
            }
        }
        if ($removeNl) {
            $out = str_replace('↵', '', $out);
        }

        $this->macroHelp[$macroName][$name] = $help;
        return $out;
    } // getArg



    //....................................................
    private function getArgsArray($macroName, $removeNl = true, $argNames = false)
    {
    	//??? unfinished!
    	// -> removeNl in case of $argNames provided
    	
        $this->macroHelp[$macroName] = [];
        if ($argNames && is_array($argNames)) {
            $an = [];
            foreach ($argNames as $i => $argName) {
                if (isset($this->macroArgs[$macroName][$argName])) {
                    $an[$argName] = $this->macroArgs[$macroName][$argName];
                } elseif (isset($this->macroArgs[$macroName][$i])) {
                    $an[$argName] = $this->macroArgs[$macroName][$i];
                } else {
                    $an[$argName] = '';
                }
            }
            return $an;
        }
        if (!$this->macroArgs[$macroName]) {
            return [];
        }
        if ($removeNl) {
            $a = [];
            foreach ($this->macroArgs[$macroName] as $key => $value) {
                $key = trim(str_replace(['↵',"'"], '', $key));
                $a[$key] = $value;
            }
            return $a;
        } else {
            return $this->macroArgs[$macroName];
        }
    } // getArgsArray



    //....................................................
    private function getMacroHelp($macroName)
    {
        $argsHelp = $this->macroHelp[$macroName];
        if (!$argsHelp) {       // don't show anything if there are no arguments listed
            return '';
        }
        $out = '';
        foreach ($argsHelp as $name => $text) {
            $out .= "\t<dt>$name:</dt>\n\t\t<dd>$text</dd>\n";
        }
        $out = "<h2>Options for macro <em>$macroName()</em></h2>\n<dl>\n$out</dl>\n";
        return $out;
    } // getMacroHelp




    //....................................................
    public function getInvocationCounter($macroName)
    {
        if (!isset($this->invocationCounter[$macroName])) {
            $this->invocationCounter[$macroName] = 0;
        }
        $this->invocationCounter[$macroName]++;
        return $this->invocationCounter[$macroName];
    } // getInvocationCounter



    //....................................................
    private function adaptBraces($str, $substitutionMode = false)
    {
        if ($substitutionMode & SUBSTITUTE_UNDEFINED) {
            $str = str_replace(['{|{','}|}'], ['{{', '}}'], $str);
        }
        if ($substitutionMode & SUBSTITUTE_ALL) {
            $str = str_replace(['{||{','}||}'], ['{{', '}}'], $str);
        }

        return $str;
    } // adaptBraces



    //....................................................
    private function handlePageModifications()
    {
        if ($this->page->applyPageSubstitution()) {
            return;
        }
        $this->page->applyOverlay();
        $this->page->applyOverride();
        $this->page->applyMessage();
        $this->page->applyDebugMsg();
    } // handlePageModifications




    //....................................................
    private function handleLatePageSubstitution()
    {
        if ($str = $this->page->get('pageSubstitution')) {
            exit($str);
        }
    } // handleLatePageSubstitution




    //....................................................
    public function loadAllMacros() {
        $page = $this->page = new Page;
        $sys = '~/'.$this->config->systemPath;
        $macrosPath = $this->config->macrosPath;
        $macros = [];
        $files = getDir($this->config->macrosPath.'*.php');
        foreach ($files as $file) {
            $moduleName = basename($file, '.php');

            $info = '';
            $lines = file($file);
            $l = array_filter($lines, function($v, $k) {
                return (strpos($v, '@info') !== false);
            }, ARRAY_FILTER_USE_BOTH);
            if ($l) {
                $info = preg_replace('/^[^\:]*\s*:\s*/', '', array_pop($l));
            }

            $macros[$moduleName] = $info;
        }
        ksort($macros);
        $this->macroInfo = $macros;
    } // loadAllMacros



    //....................................................
    private function loadMacro($macroName)
    {
        $sys = '~/'.$this->config->systemPath;
        $file = $this->config->macrosPath.$macroName.'.php';
        $page = &$this->page;
        if (file_exists($file)) {	// filename == macroname
            require_once($file);

            // -> vars to be loaded by macros!!
//            // load associated vars
//            $transvarFile = $this->config->macrosPath.'transvars/'.$macroName.'.yaml';
//            if (file_exists($transvarFile)) {
//                $this->readTransvarsFromFile($transvarFile);
//            }

//            foreach($page as $key => $elem) {
//                if ($elem && ($key != 'config')) {
//                    $page->$key = $elem;
//                }
//            }
//$page = $page;

        } else {
            $file = $this->config->extensionsPath."$macroName/code/".$macroName.'.php';
            if (file_exists($file)) {    // filename == macroname
                require_once($file);
//                foreach($page as $key => $elem) {
//                    if ($elem && ($key != 'config')) {
//                        $page->$key = $elem;
//                    }
//                }
            } else {
                // check user-code: if macro.php is in code/ folder:
                if ($this->config->custom_permitUserCode) {
                    $file = $this->config->path_userCodePath . $macroName . '.php';
                    if (file_exists($file)) {
                        $this->doUserCode($file);
//                        foreach ($page as $key => $elem) {
//                            if ($elem && ($key != 'config')) {
//                                $page->$key = $elem;
//                            }
//                        }
                    }
                } else {
                    logError("Error: Macro '$macroName' not found");
                }
            }
        }
//        $transvarFile = $this->config->macrosPath.'transvars/'.$macroName.'.yaml';
//        if (file_exists($transvarFile)) {
//            $this->readTransvarsFromFile($transvarFile);
//        }
    } // loadMacro




    /**
     * @param $file
     */
    public function readTransvarsFromFiles($file, $markSource = false)
    {
        if (is_array($file)) {
            $a = $file;
            foreach ($a as $f) {
                $files = glob($f);
                foreach ($files as $ff) {
                    if (substr(basename($ff), 0, 1) == '#') {
                        continue;
                    }
                    $this->readTransvarsFromFile($ff, $markSource);
                }
            }
        } elseif (strpos($file, '*') !== false) {
            $files = glob($file);
            foreach ($files as $f) {
                if (substr(basename($f), 0, 1) == '#') {
                    continue;
                }
                $this->readTransvarsFromFile($f, $markSource);
            }
        } else {
            $this->readTransvarsFromFile($file, $markSource);
        }
    }




    //....................................................
    public function readTransvarsFromFile($file, $markSource = false)
    {
        if ($file[0] == '~') {
            $file = resolvePath($file);
        }
        if (!file_exists($file)) {
            fatalError("File not found: '$file'", 'File: '.__FILE__.' Line: '.__LINE__);
        }
        $newVars = getYamlFile($file);
        if (is_array($newVars)) {
            $markSource = true;
            if ($this->config->debug_showVariablesUnreplaced) { // for debugging
                array_walk($newVars, function(&$value, &$key, $file) {
                    if ($key != 'page_title') {
                        $value = "<span title='$file'>&#123;&#123; $key }}</span>";
                    }
                }, $file);
                $markSource = false; // no need to also mark source
            }
            if ($markSource) {
                foreach ($newVars as $key => $rec) {
                    if (!is_array($rec)) {
                        $v = $rec;
                        unset($newVars[$key]);
                        $newVars[$key]['_'] = $v;
                    }
                    $newVars[$key]['src'] = $file;
                };
            }
            $this->transvars = array_merge($this->transvars, $newVars);
        }
    } // readFile



    //....................................................
    public function addVariable($key, $value, $lang = '')
    {
        if ($lang === false) {	// delete first
            $this->transvars[$key] = '';
        }
        if ($lang) {
            if (!isset($this->transvars[$key][$lang])) {
                $this->transvars[$key][$lang] = $value;
            } else {
                $this->transvars[$key][$lang] .= $value;
            }

        } else {
            if (!isset($this->transvars[$key])) {
                $this->transvars[$key] = $value;
            } else {
                $this->transvars[$key] .= $value;
            }
        }
    } // addVariable



    //....................................................
    public function clearVariable($key)
    {
        if (isset($this->transvars[$key])) {
            $this->transvars[$key] = '';
        }
    } // clearVariable



    //....................................................
    public function addMacro($macroName, $func)
    {
        $this->macros[$macroName] = $func;
    } // addMacro



    //....................................................
    public function getVariable($key, $lang = '', $namespace = '', $substitutionMode = false)
    {
        $lang = ($lang) ? $lang : $this->config->lang;
        $out = false;

        if (isset($this->transvars[$key])) {
            $entry = $this->transvars[$key];

            if (($key{0} != '_') && (!in_array($key, $this->sysVariables))) {
                $this->usedVars[$namespace.'@:'.$key] = $entry;
            }
            if ($this->config->debug_monitorUnusedVariables && is_array($entry)) {
                if (isset($entry['uu'])) {
                    unset($this->transvars[$key]['uu']);
                }
            }
            if (!is_array($entry)) {
                $out = $entry;

            } elseif (isset($entry['dontCache']) && $entry['dontCache'] && ($substitutionMode != SUBSTITUTE_ALL)) {
                $out = "{||{ $key }||}";

            } elseif (isset($entry[$lang])) {
                $out = $entry[$lang];

            } elseif (isset($entry[$lang]) && ($entry[$lang] === null)) {
                fatalError("Error: transvar with empty value: '$key'", 'File: '.__FILE__.' Line: '.__LINE__);

            } elseif (isset($entry['_'])) {
                $out = $entry['_'];

            } elseif (isset($entry['*'])) {
                $out = $entry['*'];

            } elseif (isset($entry[$this->config->site_defaultLanguage])) {  // lang-rcs nor explicit default found -> use default-lang
                $out = $entry[$this->config->site_defaultLanguage];

            } else {    // this should only happen if a wrong value gets into $_SESSION
                fatalError("Error: transvar without propre value: '$key'", 'File: '.__FILE__.' Line: '.__LINE__);
            }
        } elseif ($this->config->debug_showUndefinedVariables) {
            if (!in_array($key, ['PageSource Load previous edition', 'PageSource Load next edition', 'PageSource cancel', 'PageSource activate edition', 'Page-History:'])) {
                $out = "<span class='mark-undefined-variable'>&#123;&#123; $key }}</span>";
                $this->undefinedVars[] = $key;
            }

        } else {
            if ((strlen($key) > 0) && ($key{0} != '_') && (!in_array($key, $this->sysVariables))) {
                $this->usedVars['_@:'.$key] = '';
            }
        }
        return $out;
    } // getVariable



    //....................................................
    public function exportUsedVariables()
    {
        ksort($this->usedVars);
        return convertToYaml($this->usedVars);
    } // exportUsedVariables



    //....................................................
    private function shieldSpecialVars($str)
    {
        $str = preg_replace('/\{\{\^?\s*head_injections\s*\}\}/', '@@head_injections@@', $str);
        $str = preg_replace('/\{\{\^?\s*body_end_injections\s*\}\}/', '@@body_end_injections@@', $str);
        return $str;
    } // shieldSpecialVars



    //....................................................
    private function translateSpecialVars($str)
    {
        if ($this->config->feature_autoLoadClassBasedModules) {
            $this->page->autoInvokeClassBasedModules($str);
        }
        $str = str_replace('@@head_injections@@', $this->page->headInjections(), $str);
        $str = str_replace('@@body_end_injections@@', $this->page->bodyEndInjections(), $str);

        return $str;
    } // translateSpecialVars


    //....................................................
    public function doUserComputedVariables()
    {
        $code = $this->config->path_userCodePath.$this->config->custom_computedVariablesFile;
        if (file_exists($code)) {
            $this->doUserCode( basename($code, '.php'), $this->config->custom_permitUserVarDefs );
        }
    } // doUserComputedVariable


    //....................................................
    public function doUserCode($name, $execType = null, $breakOnError = false)
    {
        $out = false;
        if ($execType === null) {
            $execType = $this->config->custom_permitUserCode;
        }
        if ($execType) {
            $phpFile = $this->config->path_userCodePath.basename($name,'.php').'.php';
            if (file_exists($phpFile)) {
                $page = &$this->page;
                if ($execType == 'true') {
                    $res =  require($phpFile);
                    if (is_array($res)) {
                        foreach ($res as $key => $value) {
                            $this->addVariable($key, $value);
                        }
                    } elseif (is_string($res)) {
                        $out = $res;
                    }
                } else {
                    fatalError("PHP-Sandbox feature currently not supported.");

                    // To re-enable, run
                    //  composer install corveda/php-sandbox
                    $sandbox = new MySandbox();
                    $vars['this'] = $this; // feed $trans into sandbox
                    return $sandbox->execute($phpFile, $this->config->configPath, $vars);
                }
            } elseif ($breakOnError) {
                return [false, "Requested file '$phpFile' not found."];
            }
        } elseif ($breakOnError) {
            return [false, "User-Code not enabled in config/config.yaml (option 'custom_permitUserCode')"];
        }
        return $out;
    } // doUserCode




	//....................................................
	public function loadStandardVariables($siteStructure)
	{
	    $this->siteStructure = $siteStructure;
		$this->addVariable('pagetitle', $siteStructure->currPageRec['name']);
		
		if (isset($this->siteStructure->currPageRec['inx'])) {
			$this->addVariable('numberofpages', $siteStructure->getNumberOfPages());
			$this->addVariable('pagenumber', $siteStructure->currPageRec['inx'] + 1);
		} else {
			$this->addVariable('numberofpages', '');
			$this->addVariable('pagenumber', '');
		}
	} // loadStandardVariables



	//....................................................
	public function readAll($lang = false)
	{
		$lang = ($lang) ? $lang : $this->lang;
		$vars = array();
		foreach ($this->transvars as $key => $entry) {
			if (!is_array($entry)) {
				$vars[$key] = $entry;
			} elseif (isset($entry[$lang])) {
				$vars[$key] = $entry[$lang];
			} elseif (isset($entry['_'])) {
				$vars[$key] = $entry['_'];
			} else {
                logError("Error: transvar without propre value: '$key'");
			}
		}
		return $vars;
	} // readAll



	//....................................................
	public function renderAllTranslationObjects($lang = false)
	{
		$this->loadAllMacros();
		if ($lang) {
			$transvars = $this->readAll($lang);
		} else {
			$transvars = &$this->transvars;
		}

		$out = $this->renderAllVariables($transvars);

		$out .= $this->renderAllMacros();

        return $out;

	}



    private function renderAllVariables($transvars)
    {
        uksort($transvars, "strnatcasecmp");
        $str = "\n\t<div class='lzy-list-transvars'>\n\t\t<h1>Transvars:</h1>\n";
        foreach ($transvars as $key => $rec) {
            if (isset($rec['uu'])) {
                $inactive = ' unused';
                unset($rec['uu']);
            } else {
                $inactive = '';
            }
            if (isset($rec['src'])) {
                $src = " <span class='lzy-list-src'>({$rec['src']})</span>";
                unset($rec['src']);
            } else {
                $src = '';
            }
            $str .= <<<EOT

        <div class='lzy-list-entry$inactive'>
            <div class='lzy-list-line'><span class='lzy-list-var'>$key:</span>$src</div>
EOT;
            if (is_array($rec)) {
                foreach ($rec as $lang => $text) {
                    $text = htmlentities($text);
                    $str .= <<<EOT

            <div class='lzy-list-line'>
                <span class='lzy-list-attr-name'>$lang</span>:
                <span class='lzy-list-attr-value'>$text</span>
            </div>

EOT;
                }
            } else {
                $rec = htmlentities($rec);
                $str .= <<<EOT

            <div class='lzy-list-line'>
                <span class='lzy-list-scalar-value'>$rec</span>
            </div>

EOT;

            }
            $str .= <<<EOT
        </div>

EOT;
        }
        $str .= "\t</div>\n";
        return $str;
    }




    private function renderAllMacros()
    {
        $macros = $this->macroInfo;
        $out = '';
        foreach($macros as $name => $info) {
            $out .= "<div><span class='lzy-macro-name'>$name()</span>: <span class='lzy-macro-info'>$info</span></div>\n";
        }

        $out = "<h2>Macros</h2>$out\n";
        return $out;
    }



    public function reset( $files )
    {
        $this->processFiles($files, 'resetFile');
    }




    /**
     * @param $file
     */
    private function resetFile($file)
    {
        copy($file, $file.'.0');
        $lines = file($file);
        $out = '';
        foreach ($lines as $i => $l) {
            $l1 = isset($lines[$i+1]) ? $lines[$i+1] : '';
            if (preg_match('/^[^\#]*:\s*$/', $l) && (strpos($l1, 'uu: true') === false)) {
                $out .= "$l    uu: true\n";
            } else {
                $out .= $l;
            }
        }
        file_put_contents($file, $out);
    }




    public function postprocess()
    {
        $this->updateUndefinedVarsFile();
        return $this->processFiles($GLOBALS['files'], 'updateTransvarFile');
    }




    public function removeUnusedVariables()
    {
        return $this->processFiles($GLOBALS['files'], 'removeUnusedVariableFromFile');
    }




    private function removeUnusedVariableFromFile($filename)
    {
        $lines = file($filename);
        $out = '';
        $outInactive = '';
        $var = false;
        $end = false;
        $modified = false;
        $note = '';
        $rec = '';
        $unused = false;
        foreach ($lines as $line) {
            if (strpos($line, '__END__') === 0) {
                $end = true;
            }

            if (preg_match('/^([^\#]*):\s*$/m', $line, $m)) {   // beginning of var
                if ($unused) {      // append previous rec:
                    $note .= "<p>$var</p>\n";
                    $outInactive .= $rec;
                } else {
                    $out .= $rec;
                }
                $var = $m[1];
                $rec = '';
                $unused = false;
            }
            if ((isset($line{0}) && ($line{0} != '/')) && (!$end && strpos($line, 'uu: true') !== false)) {
                if (isset($this->transvars[$var]['uu'])) {
                    $unused = true;
                }
            }
            $rec .= $line;
        }

        if ($outInactive) {
            $out .= "\n\n__END__\n#############################################\n# Unused Variables:\n\n".$outInactive;
            $note = "<h2>File updated: $filename</h2>\n".$note;
            file_put_contents($filename . ".1", $out);
        }
        return $note;
    }



    public function renderUnusedVariables()
    {
        return $this->processFiles($GLOBALS['files'], 'renderUnusedInFile');
    }



    private function renderUnusedInFile($filename)
    {
        return "<p>renderUnusedInFile() not implemented yet [$filename]</p>";
    }



    private function updateTransvarFile($filename)
    {
        $lines = file($filename);
        $out = '';
        $var = false;
        $end = false;
        $modified = false;
        $note = '';
        foreach ($lines as $line) {
            if (strpos($line, '__END__') === 0) {
                $end = true;
            }
            if (preg_match('/^([^\#]*):\s*$/m', $line, $m)) {
                $var = $m[1];
            }
            if ((isset($line{0}) && ($line{0} != '/')) && (!$end && strpos($line, 'uu: true') !== false)) {
                if (isset($this->transvars[$var]['uu'])) {
                    $out .= $line;
                } else {
                    $note .= "used var: $var<br>\n";
                    $modified = true;
                }
            } else {
                $out .= $line;
            }
        }

        if ($modified) {
            $note = "<h2>$filename</h2>\n".$note;
            file_put_contents($filename, $out);
        }
        return $note;
    }
    
    
    
    private function processFiles($filenames, $fun)
    {
        $note = '';
        foreach($filenames as $file) {
            if (substr($file, -1) == '*') {
                $files2 = getDir($file);
                foreach ($files2 as $f) {
                    if (fileExt($f) == 'yaml') {
                        $note .= $this->$fun($f);
                    }
                }
            } else {
                $note .= $this->$fun($file);
            }
        }
        return $note;
    }

    private function updateUndefinedVarsFile()
    {
        if (!$this->undefinedVars) {
            return;
        }
        $undefinedVars = getYamlFile(UNDEFINED_VARS_FILE);
        $rec = [];
        foreach ($this->config->site_supportedLanguages as $l) {
            $rec[$l] = '';
        }

        foreach ($this->undefinedVars as $key) {
            $undefinedVars[$key] = $rec;
        }
        ksort($undefinedVars);
        writeToYamlFile(UNDEFINED_VARS_FILE, $undefinedVars);
    }


} // Transvar
