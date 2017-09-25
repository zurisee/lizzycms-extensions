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
	private $macros = array();
	private $macroInfo = array();
	private $invocationCounter = array();
	private $sysVariables = ['content', 'head_injections'];
	public $page;

	//....................................................
	public function __construct($config, $file, $siteStructure)
	{
		$this->page = new Page;
		$this->siteStructure = $siteStructure;
		$this->config = $config;
		if (is_array($file)) {
			$a = $file;
			foreach ($a as $f) {
				$files = glob($f);
				foreach ($files as $ff) {
					if (substr(basename($ff),0,1) == '#') {
						continue;
					}
					$this->readFile($ff);
				}
			}
		} elseif (strpos($file, '*') !== false) {
			$files = glob($file);
			foreach ($files as $f) {
				if (substr(basename($f),0,1) == '#') {
					continue;
				}
				$this->readFile($f);
			}
		} else {
			$this->readFile($file);
		}

		$this->loadStandardVariables();

	} // __construct




	//....................................................
	public function render($page, $lang = false, $substitutionMode = false)
	{
	    if (is_string($page)) {
            $html = $page;
        } else {
            $this->page = $page;

            // handle pageReplacement?, override, overlay, message, debugMsg:
            $this->handlePageModifications();

            $html = $page->get('body');
            $html = $this->shieldSpecialVars($html);

            $this->addVariable('content', $page->get('content'));
        }

        $html = $this->adaptBraces($html, $substitutionMode);

		while (strpos($html, '{{') !== false) {
			$html = $this->translateMacros($html);
			$html = $this->translateVars($html, 'app', $substitutionMode);
		}

		$html = $this->translateSpecialVars($html);
        $html = $this->adaptBraces($html, SUBSTITUTE_UNDEFINED);
        $html = $this->translateVars($html, 'app', SUBSTITUTE_UNDEFINED);
        $this->handleLatePageSubstitution();

		return $html;
	} // render



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
            if (strpos('#^!&', $c1) !== false) {
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
                        $var = $this->translateMacros($var, $namespace);
                    }
                    $var = str_replace("\n", '', $var);    // remove newlines
                    if (preg_match('/^(\w+)\((.*)\)/', $var, $m)) {    // macro
                        $macro = $m[1];

                        $argStr = $m[2];
                        $this->macroArgs[$macro] = parseArgumentStr($argStr);
                        $this->macroInx = 0;

                        if (!isset($this->macros[$macro])) {     // macro already loaded
                            $this->loadMacro($macro);
                        }
                        if (isset($this->macros[$macro])) { // and try to execute it
                            $val = $this->macros[$macro]($this);
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
                    $str = substr($str, 0, $p1) . $val . substr($str, $p2 + 2);
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

            if (preg_match('/^ [\#^\!\&]? \s* [\w_]+ \( /x', $var)) { // skip macros()
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
                            $val = $this->doUserCode($var, $this->config->permitUserCode);
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





    //....................................................
    public function getArg($macroName, $name, $help = '', $default = null, $removeNl = true /*, $dynamic = false*/)
    {
        $inx = $this->macroInx++;
        $this->macroFieldnames[$macroName][$inx] = $name;
        if (preg_match('/^\d/', $name)) {
            $index = intval($name);
            if ($index < sizeof($this->macroArgs[$macroName])) {
                $out = array_values($this->macroArgs[$macroName])[$index];
                $this->macroHelp[$macroName][ array_keys($this->macroArgs[$macroName])[$index] ] = $help;
            }

        } else {
            if (isset($this->macroArgs[$macroName][$name])) {
                $out = $this->macroArgs[$macroName][$name];
                $this->macroHelp[$macroName][$name] = $help;

            } else {
                if (isset($this->macroArgs[$macroName][$inx])) {
                    $out = $this->macroArgs[$macroName][$inx];
                    $this->macroHelp[$macroName][$name] = $help;
                } elseif ($default !== null) {
                    $out = $default;
                } else {
                    $out = null;
                }
            }
        }
        if ($removeNl) {
            $out = str_replace('↵', '', $out);
        }
        return $out;
    } // getArg



    //....................................................
    private function getArgsArray($macroName, $removeNl = true)
    {
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
        $files = getDir($this->config->macrosPath.'*.php');
        foreach ($files as $file) {
            $f = basename($file);
            $moduleName = basename($file, '.php');
            if (!isset($modules[$f])) {
                $modules[$f] = 0;
            }
            require_once($file);
            $a = file($file);
            $a = preg_grep('/\$this->addMacro/', $a);
            $l = array_pop($a);
            $macName = basename($f, '.php');
            if (preg_match('/'.preg_quote('$this->addMacro($macroName, function (').'(.*)\)/', $l, $m)) {
                $this->macroInfo[] = "$macName({$m[1]})";
            }
            $transvarFile = $macrosPath."transvars/$macName.yaml";
            if (file_exists($transvarFile)) {
                $this->readFile($transvarFile);
            }
        }
        ksort($modules);
    } // loadAllMacros



    //....................................................
    private function loadMacro($macroName)
    {
        $sys = '~/'.$this->config->systemPath;
        $file = $this->config->macrosPath.$macroName.'.php';
        $page = &$this->page;
        if (file_exists($file)) {	// filename == macroname
            require_once($file);
            foreach($page as $key => $elem) {
                if ($elem && ($key != 'config')) {
                    $page->$key = $elem;
                }
            }
        } else {					// check if macro.php is in code/ folder?
            if ($this->config->permitUserCode) {
                $file = $this->config->userCodePath.$macroName.'.php';
                if (file_exists($file)) {
                    $this->doUserCode($file);
                    foreach($page as $key => $elem) {
                        if ($elem && ($key != 'config')) {
                            $page->$key = $elem;
                        }
                    }
                }
            } else {
                logError("Error: Macro '$macroName' not found");

//                fatalError("Error: Macro '$macroName' not found", 'File: '.__FILE__.' Line: '.__LINE__);
            }
        }
        $transvarFile = $this->config->macrosPath.'transvars/'.$macroName.'.yaml';
        if (file_exists($transvarFile)) {
            $this->readFile($transvarFile);
        }
    } // loadMacro



    //....................................................
    public function readFile($file)
    {
        if (!file_exists($file)) {
            fatalError("File not found: '$file'", 'File: '.__FILE__.' Line: '.__LINE__);
        }
        $this->transvars = array_merge($this->transvars, getYamlFile($file));
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
            if (!is_array($entry)) {
                $out = $entry;

            } elseif (isset($entry['dontCache']) && $entry['dontCache'] && ($substitutionMode != SUBSTITUTE_ALL)) {
                $out = "{||{ $key }||}";

            } elseif (isset($entry[$lang])) {
                $out = $entry[$lang];

            } elseif (isset($entry['_'])) {
                $out = $entry['_'];

            } elseif (isset($entry['*'])) {
                $out = $entry['*'];

            } elseif (isset($entry[$this->config->defaultLanguage])) {  // lang-rcs nor explicit default found -> use default-lang
                $out = $entry[$this->config->defaultLanguage];

            } else {    // this should only happen if a wrong value gets into $_SESSION
                fatalError("Error: transvar without propre value: '$key'", 'File: '.__FILE__.' Line: '.__LINE__);
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
        $this->addVariable('head_injections', $this->page->headInjections());
        $str = str_replace('@@head_injections@@', $this->getVariable('head_injections'), $str);
        $this->transvars['head_injections'] = '';

        $this->addVariable('body_end_injections', $this->page->bodyEndInjections());
        $str = str_replace('@@body_end_injections@@', $this->getVariable('body_end_injections'), $str);
        $this->transvars['body_end_injections'] = '';

        return $str;
    } // translateSpecialVars


    //....................................................
    public function doUserComputedVariables()
    {
        $code = $this->config->userCodePath.$this->config->userComputedVariablesFile;
        if (file_exists($code)) {
            $this->doUserCode( basename($code, '.php'), $this->config->permitUserVarDefs );
        }
    } // doUserComputedVariable


    //....................................................
    public function doUserCode($name, $execType = null)
    {
        $out = false;
        if ($execType === null) {
            $execType = $this->config->permitUserCode;
        }
        if ($execType) {
            $phpFile = $this->config->userCodePath.basename($name,'.php').'.php';
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
                    $sandbox = new MySandbox();
                    $vars['this'] = $this; // feed $trans into sandbox
                    return $sandbox->execute($phpFile, $this->config->configPath, $vars);
                }
            }
        }
        return $out;
    } // doUserCode




	//....................................................
	public function loadStandardVariables()
	{
		$this->addVariable('pagetitle', $this->siteStructure->currPageRec['name']);
		
		if (isset($this->siteStructure->currPageRec['inx'])) {
			$this->addVariable('numberofpages', $this->siteStructure->getNumberOfPages());
			$this->addVariable('pagenumber', $this->siteStructure->currPageRec['inx'] + 1);
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
//                fatalError("Error: transvar without propre value: '$key'", 'File: '.__FILE__.' Line: '.__LINE__);
			}
		}
		return $vars;
	} // readAll



	//....................................................
	public function printAll($lang = false)
	{
		$this->loadAllmacros();
		if ($lang) {
			$transvars = $this->readAll($lang);
		} else {
			$transvars = &$this->transvars;
		}
		uksort($transvars, "strnatcasecmp");

		$lines = explode(PHP_EOL, Yaml::dump($transvars));
		$out = '';
		foreach ($lines as $l) {
			$l = htmlentities($l);
			$l = str_replace("'", '', $l);
			$l = preg_replace('/^(\S[^:]+):/', "<strong>$1</strong>:", $l);
			$collect = preg_match('/^\s/', $l);
			$l = preg_replace("/^\s{2,}/", "<span class='space'>&nbsp;</span>", $l);
			$l = preg_replace("/^([^:]*):(.*)/", "<span class='tab'>$1:</span><span class='block'>$2</span>", $l);
			if ($collect) {
				$out = substr($out, 0, -7)."\n$l</div>\n";
				continue;
			}
			$out .= "<div>$l</div>\n";
		}
		$out1 = "<h1>Lizzy</h1>\n<h2>Transvars</h2>$out\n";

		$macros = $this->macroInfo;
		sort($macros);
		$out = '';
		foreach($macros as $m) {
			$m = preg_replace('/^(.*)\(/', "<strong>$1</strong>(", $m);
			$out .= "<div>$m</div>\n";
		}
		
		$out2 = "<h2>Macros</h2>$out\n";
		$out = <<<EOT
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8" />
	<title>Lizzy List</title>
	<style type="text/css">
		strong { color: maroon; }
		span { display: inline-block; vertical-align: top;}
	 	span.tab { width: 12em; }
		span.space { width: 10em; }
	 	span.block { width: calc(100% - 12.5em); }
		div { margin: 3px 0 6px 0; padding: 3px 6px; }
		div:nth-child(odd) { background: #eee; }
	</style>
</head>
<body>
$out1
$out2
</body>
</html>

EOT;
		exit( $out );
	} // printAll

} // Transvar
