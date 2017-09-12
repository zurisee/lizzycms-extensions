<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Replacement-Variables and Macros()
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
	public function render($page, $lang = false)
	{
		$this->page = $page;

		// handle pageReplacement?, override, overlay, message, debugMsg:
        $this->handlePageModifications();

        $html = $page->get('body');
		$html = $this->shieldSpecialVars($html);

        $this->addVariable('content', $page->get('content'));

		while (strpos($html, '{{') !== false) {
			$html = $this->translateMacros($html);
			$html = $this->translateVars($html);
		}

		$html = $this->translateSpecialVars($html);
        $html = $this->translateVars($html);
		return $html;
	} // render



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
//                    $page->$key = $elem."\n"; // ??? why was this???
                    $page->$key = $elem;
                }
            }
        } else {					// search files containing multiple macros
            if ($this->config->permitUserCode) {
                $file = $this->config->userCodePath.$macroName.'.php';
                if (file_exists($file)) {
                    require_once($file);
                    foreach($page as $key => $elem) {
                        if ($elem && ($key != 'config')) {
                            $page->$key = $elem;
                        }
                    }
                }
            } else {
                die("Error: Macro '$macroName' not found");
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
            die("File not found: '$file'");
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
    public function getVariable($key, $lang = '', $namespace = '')
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
            } elseif (isset($entry[$lang])) {
                $out = $entry[$lang];
            } elseif (isset($entry['_'])) {
                $out = $entry['_'];
            } else {    // this should only happen if a wrong value gets into $_SESSION
                //$out = $entry[$this->config->defaultLanguage];
                die("Error: transvar without propre value: '$key' \n(".basename(__FILE__).':'.__LINE__.")");
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
	private function translateMacros($str, $namespace = 'app')
	{
		list($p1, $p2) = strPosMatching($str);
		while (($p1 !== false)) {
			$commmented = false;
			$optional = false;
			$var = trim(substr($str, $p1+2, $p2-$p1-2));
			if (!$var) {
			    $str = substr($str,0,$p1).substr($str, $p2+2);
                list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
                continue;
            }
			$c1 = $var{0};
			if (($c1 == '#') || ($c1 == '^')) {
				$var = trim(substr($var, 1));
				if ($c1 == '#') {
					$commmented = true;
				} else {
					$optional = true;
				}
			}
			if (!$commmented) {
				if (strpos($var, '{{') !== false) {
					$var = $this->translateMacros($var, $namespace);
				}
				$var = str_replace("\n", '', $var);	// remove newlines
				if (preg_match('/^(\w+)\((.*)\)/', $var, $m)) {	// macro
					$macro = $m[1];

                    $argStr = $m[2];
                    $this->macroArgs[$macro] = parseArgumentStr( $argStr );
                    $this->macroInx = 0;

					if (isset($this->macros[$macro])) {
						$val = $this->macros[$macro]();
					} else {
						$this->loadMacro($macro);
						if (isset($this->macros[$macro])) {
							$val = $this->macros[$macro]();
						} else {
						$this->loadMacro($macro);
							die("Error: undefined macro: '$macro()'");
						}
					}
					$val = $this->translateMacros($val, $macro);

				} else {										// variable
                    list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
                    continue;
				}
			}
			if ($commmented) {
				$str = substr($str, 0, $p1).substr($str, $p2+2);
			} elseif (!$optional && ($val === false)) {
				$str = substr($str, 0, $p1).$var.substr($str, $p2+2);
			} else {
				$str = substr($str, 0, $p1).$val.substr($str, $p2+2);
			}
            list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
		}
		return $str;
	} // translateMacros



	//....................................................
	private function getArg($macroName, $name, $help = '', $default = null, $removeNl = true /*, $dynamic = false*/)
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
	public function translateVars($str, $namespace = 'app')
	{
		list($p1, $p2) = strPosMatching($str);
		while (($p1 !== false)) {
			$commmented = false;
			$optional = false;
			$var = trim(substr($str, $p1+2, $p2-$p1-2));
            if (!$var) {
                $str = substr($str,0,$p1).substr($str, $p2+2);
                list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
                continue;
            }
            $c1 = $var{0};
			if (($c1 == '#') || ($c1 == '^')) {
				$var = trim(substr($var, 1));
				if ($c1 == '#') {
					$commmented = true;
				} else {
					$optional = true;
				}
			}
			if (!$commmented) {
				if (strpos($var, '{{') !== false) {
					$var = $this->translateVars($var, $namespace);
				}
				$var = str_replace("\n", '', $var);	// remove newlines
				if (preg_match('/^(\w+)\((.*)\)/', $var, $m)) {	// macro
                    list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
                    continue;
				} else {										// variable
					$val = $this->getVariable($var, '', $namespace);
					if ($val === false) {
						$val = $this->doUserCode($var, $this->config->permitUserCode);
					}
				}
			}
			if ($commmented) {
				$str = substr($str, 0, $p1).substr($str, $p2+2);
			} elseif (!$optional && ($val === false)) {
				$str = substr($str, 0, $p1).$var.substr($str, $p2+2);
			} else {
				$str = substr($str, 0, $p1).$val.substr($str, $p2+2);
			}
			list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
		}
		return $str;
	} // translateVars



    //....................................................
    public function doUserComputedVariables()
    {
        $code = $this->config->userCodePath.$this->config->userComputedVariablesFile;
        if (file_exists($code)) {
            $this->doUserCode( basename($code, '.php'), $this->config->permitUserVarDefs );
        }
    } // doUserComputedVariable


    //....................................................
    public function doUserCode($name, $execType)
    {
        $out = false;
        if ($execType) {
            $phpFile = $this->config->userCodePath.$name.'.php';
            if (file_exists($phpFile)) {
                $page = &$this->page;
                if ($execType === 'sandboxed') {
                    $out =  $this->runInSandbox($name, $phpFile);

                } elseif ($this->config->permitUserCode) {
                    $res =  require($phpFile);
                    if (is_array($res)) {
                        foreach ($res as $key => $value) {
                            $this->addVariable($key, $value);
                        }
                    }
                }
            }
        }
        return $out;
    } // doUserCode



	private function runInSandbox($name, $phpFile)
	{
		// See: https://docs.phpsandbox.org/2.0/classes/PHPSandbox.PHPSandbox.html#source-view
		$phpCode = file_get_contents($phpFile);
		$phpCode = str_replace(['<?php','?>'], '', $phpCode);

		$sandbox_allowed_functions = getYamlFile($this->config->configPath.'sandbox_allowed_functions.yaml');
		$sandbox = new PHPSandbox\PHPSandbox;
		if ($sandbox_allowed_functions) {
			$sandbox->whitelistFunc($sandbox_allowed_functions);
		}
		
		$sandbox_available_variables = getYamlFile($this->config->configPath.'sandbox_available_variables.yaml');
		$sandbox = new PHPSandbox\PHPSandbox;
		if ($sandbox_available_variables) {
			$vars = array();
			foreach ($sandbox_available_variables as $varName) {
				if (isset($$varName)) {
					$vars[$varName] = $$varName;
				}
			}
			$sandbox->defineVars($vars);
        }

		try {
			$res = $sandbox->execute($phpCode);
			if (is_array($res)) {
			    foreach ($res as $key => $value) {
                    $this->addVariable($key, $value);
                }
            }
		} catch(Exception $e) {
			die("Error while executing user code in sanbox: <br>".$e->getMessage());
		}
		return true;
	} // runInSandbox



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
				die("Error: transvar without propre value: '$key' \n(".basename(__FILE__).':'.__LINE__.")");
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
