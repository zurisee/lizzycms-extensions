<?php

define('LOG_PATH', '.#logs/');
define('MAX_URL_ARG_SIZE', 255);

use Symfony\Component\Yaml\Yaml;
use cebe\markdown\MarkdownExtra;


//--------------------------------------------------------------
function parseArgumentStr($str)
{
    $options = [];
    $str0 = $str;

    // for compatibility with Yaml, the argument list may come enclosed in { }
    if (preg_match('/^\s* (\{? \s*)  (.*)  \} \s* $/x', $str, $m)) {
        $str = $m[2];
    }

    $assoc = false;
    while ($str || $assoc) {
        $str = trim($str, '↵ ');
        $c = (isset($str[0])) ? $str[0] : '';

        // grab next value, can be bare or enclosed in ' or "
        if ($c == '"') {    // -> "
            $p = findNextPattern($str, '"', 1);
            if ($p) {
                $val = substr($str, 1, $p - 1);
                $val = str_replace('\\"', '"', $val);
                $str = trim(substr($str, $p + 1));
                $str = preg_replace('/^\s*↵\s*$/', '', $str);
            } else {
                die("Error in key-value string: '$str0'");
            }

        } elseif ($c == "'") {    // -> '
            $p = findNextPattern($str, "'", 1);
            if ($p) {
                $val = substr($str, 1, $p - 1);
                $val = str_replace("\\'", "'", $val);
                $str = trim(substr($str, $p + 1));
                $str = preg_replace('/^\s*↵\s*$/', '', $str);
            } else {
                die("Error in key-value string: '$str0'");
            }

        } else {    // -> bare value
            $rest = strpbrk($str, ':,');
            if ($rest) {
                $val = substr($str, 0, strpos($str, $rest));
            } else {
                $val = $str;
            }
            $str = $rest;
        }

        // now, check whether it's a single value or a key:value pair
        if ($str && ($str[0] == ':')) {         // -> key:value pair
            $str = substr($str, 1);
            $assoc = true;
            $key = $val;

        } elseif (!$str || ($str[0] == ',')) {  // -> single value
            if ($assoc) {
                $options[$key] = $val;
            } else {
                $options[] = $val;
            }
            $assoc = false;
            $str = substr($str, 1);

        } else {    // anything else is an error
            die("Error in argument string: '$str0'");
        }
    }

    return $options;
} // parseArgumentStr





//--------------------------------------------------------------
function parseCsv($str, $delim = false, $enclos = false) {

    if (!$delim) {
        $delim = (substr_count($str, ',') > substr_count($str, ';')) ? ',' : ';';
        $delim = (substr_count($str, $delim) > substr_count($str, "\t")) ? $delim : "\t";
    }
    if (!$enclos) {
        $enclos = (substr_count($str, '"') > substr_count($str, "'")) ? '"': "'";
    }

    $lines = explode(PHP_EOL, $str);
    $array = array();
    foreach ($lines as $line) {
        if (!$line) { continue; }
        $array[] = str_getcsv($line, $delim, $enclos);
    }
    return $array;
} // parseCsv



//------------------------------------------------------------
function csv_to_array($str, $delim = ',') {
    $str = trim($str);
    if (preg_match('/^(\{.*\})[\s,]*$/', $str, $m)) {   // {}
        $str = preg_replace('/,*$/', '', $str);
        $a = array($str, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '');
        return $a;
    }
    $a = str_getcsv($str, $delim, "'");
    for ($i=0; $i<sizeof($a); $i++) {
        $a[$i] = trim($a[$i]);
        if (preg_match('/^"(.*)"$/', $a[$i], $m)) {
            $a[$i] = $m[1];
        }
    }
    return $a;
} // csv_to_array




//--------------------------------------------------------------
function convertYaml($str)
{
	$data = null;
	if ($str) {
	    if (preg_match('/^[\'"] [^\'"]+ [\'"]/x', $str)) {
	        // handle case of argument-list where yaml fails:
	        $data = csv_to_array($str); //???

        } else {
            $str = str_replace("\t", '    ', $str);
            try {
                $data = Yaml::parse($str);
            } catch(Exception $e) {
                die("Error in Yaml-Code: <pre>\n$str\n</pre>\n".$e->getMessage());
            }
        }
	}
	return $data;
} // convertYaml



//--------------------------------------------------------------
function getYamlFile($filename)
{
	$yaml = getFile($filename, true);
	$data = convertYaml($yaml);
	return $data;
} // getYamlFile



//--------------------------------------------------------------
function convertToYaml($data, $level = 3)
{
	return Yaml::dump($data, $level);
} // convertToYaml



//--------------------------------------------------------------
function writeToYamlFile($file, $data, $level = 3)
{
	$yaml = Yaml::dump($data, $level);
	file_put_contents($file, $yaml);
} // writeToYamlFile



//--------------------------------------------------------------
function findFile($pat)
{
	$pat = rtrim($pat, "\n");
	$dir = array_filter(glob($pat), 'isNotShielded');
	return (isset($dir[0])) ? $dir[0] : false;
} // findFile



//--------------------------------------------------------------
function getFile($pat, $removeComments = false)
{
    global $globalParams;
	$pat = str_replace('~/', '', $pat);
	if (strpos($pat, '~page/') === 0) {
	    $pat = str_replace('~page/', $globalParams['pagePath'], $pat);
    }
	if ($fname = findFile($pat)) {
		if (is_file($fname) && is_readable($fname)) {
			$file = file_get_contents($fname);
		} else {
			die("Error trying to read file '$fname'");
		}
		if (($p = strpos($file, "\n__END__")) !== false) {	// must be at beginning of line
			$file = substr($file, 0, $p+1);
		}
		if ($removeComments === true) {
			$file = removeCStyleComments($file);
		} elseif ($removeComments) {
			$file = removeHashTypeComments($file);
		}
		if (strpos($removeComments, 'emptyLines')) {
			$file = removeEmptyLines($file);
		}
		return $file;
	} else {
		return false;
	}
} // getFile



//--------------------------------------------------------------
function fileExists($file)
{
    global $globalParams;
    $file = str_replace('~/', '', $file);
    if (strpos($file, '~page/') === 0) {
        $file = str_replace('~page/', $globalParams['pagePath'], $file);
    }
    return file_exists($file);

} // fileExists



//--------------------------------------------------------------
function removeEmptyLines($str)
{
	$lines = explode(PHP_EOL, $str);
	foreach ($lines as $i => $l) {
		if (!$l) {
			unset($lines[$i]);
		}
	}
	return implode("\n", $lines);
} // removeEmptyLines



//--------------------------------------------------------------
function removeHashTypeComments($str)
{
	$lines = explode(PHP_EOL, $str);
	foreach ($lines as $i => $l) {
		if (isset($l{0}) && ($l{0} == '#')) {
			unset($lines[$i]);
		}
	}
	return implode("\n", $lines);
} //



//--------------------------------------------------------------
function removeCStyleComments($str)
{
	$p = 0;
	while (($p = strpos($str, '/*', $p)) !== false) {		// /* */ style comments
		if ($p && ($str{$p-1} == '\\')) {					// avoid shielded /*
			$str = substr($str, 0, $p-1).substr($str,$p);
			$p += 2;
			continue;
		}
		$p2 = strpos($str, "*/", $p);
		$str = substr($str, 0, $p).substr($str, $p2+2);
	}

	$p = 0;
	while (($p = strpos($str, '//', $p)) !== false) {		// // style comments
		if ($p && ($str{$p-1} == ':')) {			// avoid http://
			$p += 2;
			continue;
		}
		if ($p && ($str{$p-1} == '\\')) {					// avoid shielded //
			$str = substr($str, 0, $p-1).substr($str,$p);
			$p += 2;
			continue;
		}
		$p2 = strpos($str, "\n", $p);
		if ($p2 === false) {
			return substr($str, 0, $p);
		}
		if ((!$p || ($str{$p-1} == "\n")) && ($str{$p2})) {
			$p2++;
		}
		$str = substr($str, 0, $p).substr($str, $p2);
	}
	return $str;
} // removeCStyleComments



//--------------------------------------------------------------
function getDir($pat)
{
	if (!file_exists(dirname($pat))) {
		$pat = linkedFilepath($pat);
	}
	$files = array_filter(glob($pat), 'isNotShielded');
	$path = dirname($pat).'/';
	$fPat = basename($pat);
	$linkFiles = array_filter(glob($path.'*.link'), 'isNotShielded');
	foreach ($linkFiles as $f) {
		$linkedFiles = explode(PHP_EOL, getFile($f, 'hashTypeComments+emptyLines'));
		foreach($linkedFiles as $f) {
			if (strpos($f, '~/') === 0) {
				$pat1 = substr($f, 2);
			} else {
				$pat1 = $path.$f;
			}
			$lf = glob($pat1.$fPat);
			$files = array_merge($files, $lf);
		}
	}
	return $files;
} // getDir



//--------------------------------------------------------------
function getDirDeep($path)
{
    $path = rtrim($path, '/').'/';
    $dir = glob($path.'*');
    foreach($dir as $inx => $entry) {
        if (is_dir($entry)) {
            $dir[$inx] = rtrim($entry, '/').'/';
            $dir = array_merge($dir, getDirDeep($entry));
        }
    }
    return array_filter($dir, 'isNotShielded');
} // getDirDeep



//--------------------------------------------------------------
function linkedFilepath($path, $relativeToCurrPage = false)
// checks a path whether it contains folder-link somewhere on the way, e.g. /a/b/c, where b/ doesn't exists but /a/b.link
{
    global $globalParams;
    if (!$path) {
        return '';
    }
    if ($path[strlen($path)-1] == '/') {
        $path .= 'DUMMYFILE';
    }
	$file = basename($path);
    if ($file == 'DUMMYFILE') { $file = ''; }
	$folder = dirname($path);
	$elems = explode('/', $folder);
	$path = '';
	foreach ($elems as $elem) {
		if (file_exists($path.$elem.'.link')) {
			$link = chop(file_get_contents($path.$elem.'.link'), "\n");
            $path = preg_replace('|~/|', '', $link);
            $path = preg_replace('|~sys/|', SYSTEM_PATH, $path);
            $path = preg_replace(['|~page/|', '|\^/|'], $globalParams['pathToRoot'].$globalParams['pagePath'], $path);
		} else {
			$path .= $elem.'/';
		}
	}
	$path = str_replace('//', '/', $path);
	if (substr($path, 0, 2) == './') {
	    $path = substr($path, 2);
    }
	return $path.$file;
} // linkedFilepath



//--------------------------------------------------------------
function fileExt($file)
{
	return pathinfo($file, PATHINFO_EXTENSION);
} // getDir



//--------------------------------------------------------------
function isNotShielded($str)
{	// first char of file or dirname must not be '#'
	return (($str{0} != '#') && (strpos($str,'/#') === false));
} // isNotShielded



//------------------------------------------------------------
function base_name($file, $incl_ext = true, $incl_args = false) {
	if (!$incl_args && ($pos = strpos($file, '?'))) {
		$file = substr($file, 0, $pos);
	}
	if (preg_match('/\&\#\d+\;/',  $file)) {
		$file = htmlspecialchars_decode($file);
		$file = preg_replace('/\&\#\d+\;/', '', $file);
	}
	if (!$incl_args && ($pos = strpos($file, '#'))) {
		$file = substr($file, 0, $pos);
	}
	if (substr($file,-1) == '/') {
		return '';
	}
	$file = basename($file);
	if (!$incl_ext) {
		$file = preg_replace("/(\.\w*)$/U", '', $file);
	}
	return $file;
} //base_name



//------------------------------------------------------------
function dir_name($path)
{
    if (!$path) {
        return '';
    }
    $path = preg_replace('/[\#\?].*/', '', $path);
    if (strpos($path, '.') !== false) {
        return dirname($path).'/';
    } else {
        return rtrim($path, '/').'/';
    }
} // dir_name



//------------------------------------------------------------
function correctPath($path)
{
	if ($path) {
		$path = rtrim($path, '/').'/';
	}
    return $path;
} // correctPath



//------------------------------------------------------------
function fixPath($path)
{
	if ($path) {
		$path = rtrim($path, '/').'/';
	}
    return $path;
} // fixPath



//------------------------------------------------------------
function commonSubstr($str1, $str2, $delim = '')
{
    $res = '';
    if ($delim) {
        $a1 = explode($delim, $str1);
        $a2 = explode($delim, $str2);

    } else {
        $a1 = str_split($str1);
        $a2 = str_split($str2);
    }

    foreach($a1 as $i => $p) {
        if (!isset($a2[$i]) || ($p != $a2[$i])) {
            break;
        }
        $res .= $p.$delim;
    }
    return $res;
} // commonSubstr




//------------------------------------------------------------
function makePathDefaultToPage($path)
{
	if (!$path) {
		return '';
	}
	$path = rtrim($path, '/').'/';
	
	if ((($ch1=$path[0]) != '/') && ($ch1 != '~') && ($ch1 != '.') && ($ch1 != '_')) {	//default to path local to page ???always ok?
		$path = '~page/'.$path;
	}
	return $path;
} // makePathDefaultToPage




//------------------------------------------------------------
function resolvePath($path, $relativeToCurrPage = false, $httpAccess = false)
{
	global $globalParams;
	
	if (!$path) {
		return '';
	}
	
	if ($relativeToCurrPage) {		// for HTTP Requests
        $path = makePathRelativeToPage($path);

	} else {
        if ((($ch1=$path[0]) != '/') && ($ch1 != '~') && ($ch1 != '.')/* && ($ch1 != '_')*/) {	//default to path local to page ???always ok?
            $path = '~/'.$path;
        }
    }

    if ($httpAccess) {  // http access:
        $path = preg_replace('|~/|', $globalParams['pathToRoot'], $path);
        $path = preg_replace('|~sys/|', $globalParams['pathToRoot'].SYSTEM_PATH, $path);
        $path = preg_replace(['|~page/|', '|\^/|'], $globalParams['pathToRoot'].$globalParams['pagePath'], $path);
        $filepath = str_replace($globalParams['pathToRoot'], '', $path);
        $path = $globalParams['pathToRoot'].linkedFilepath($filepath, true);

    } else {            // file access:
        $path = preg_replace('|~/|', '', $path);
        $path = preg_replace('|~sys/|', SYSTEM_PATH, $path);
        $path = preg_replace(['|~page/|', '|\^/|'], $globalParams['pagePath'], $path);
        $path = linkedFilepath($path, false);
    }
    return $path;
} // resolvePath



//------------------------------------------------------------
function makePathRelativeToPage($path)
{
    if (!$path) {
        return '';
    }
    if ((($ch1=$path[0]) != '/') && ($ch1 != '~') && ($ch1 != '.')/* && ($ch1 != '_')*/) {	//default to path local to page ???always ok?
        $path = '~page/'.$path;
    }
    return $path;
} // makePathRelativeToPage



//------------------------------------------------------------
function resolveAllPaths($html)
{
	global $globalParams;
	$pathToRoot = $globalParams['pathToRoot'];
	$html = preg_replace('|~/|', $pathToRoot, $html);
	$html = preg_replace('|~sys/|', $pathToRoot.SYSTEM_PATH, $html);
	$html = preg_replace(['|~page/|', '|\^/|'], $pathToRoot.$globalParams['pagePath'], $html);
	return $html;
} // resolveAllPaths



//-----------------------------------------------------------------------
function parseNumbersetDescriptor($descr, $minValue = 1, $maxValue = 9, $headers = false)
// extract patterns such as '1,3, 5-8', or '-3, 5, 7-'
// don't parse if pattern contains ':' because that means it's a key:value
{
    $names = false;
	$set = parseArgumentStr($descr);
	if (!isset($set[0])) {
        foreach (array_values($set) as $i => $hdr) {
            $names[] = $hdr ? $hdr : ((isset($headers[$i])) ? $headers[$i] : array_keys($set)[$i]);
	    }
	    $set = array_keys($set);
    }

	$out = [];
	foreach ($set as $i => $elem) {
	    if ((strpos($elem, ':') === false) && preg_match('/(\S*)\s*\-\s*(\S*)/', $elem, $m)) {
			$from = ($m[1]) ? alphaIndexToInt($m[1], $headers) : $minValue;
			$to = ($m[2]) ? alphaIndexToInt($m[2], $headers) : $maxValue;
			$out = array_merge($out, range($from, $to));
		} else {
		    if (preg_match('/^(\w+):(.*)/', $elem, $m)) {
		        $elem = $m[1];
		        $names[$i] = $m[2];
            }
			$inx = alphaIndexToInt($elem, $headers);
			if ($inx == 0) {
			    die("Error in table()-macro: unknown element '$elem'");
            }
			$out[] = $inx; //alphaIndexToInt($elem, $headers);
		}
	}
	if ($names) {
	    $keys = $out;
	    $out = [];
        foreach ($keys as $i => $inx) {
            $out[] = [$inx, isset($names[$i])? $names[$i] : $set[$i] ];
        }
    }
	return $out;
} // parseNumbersetDescriptor



define('ORD_0', ord('a')-1);
//-----------------------------------------------------------------------
function alphaIndexToInt($str, $headers = false, $ignoreCase = true)
{
    if ($ignoreCase) {
        $str = strtolower($str);
        if ($headers) {
            $headers = array_map('strtolower', $headers);
        }
    }
    if ($headers && (($i = array_search($str, $headers)) !== false)) {
        $int = $i+1;

    } elseif (preg_match('/^[a-z]{1,2}$/', strtolower($str))) {
		$str = strtolower($str);
		$int = ord($str) - ORD_0;
		if (strlen($str) > 1) {
			$int = $int * 26 + ord($str[1]) - ORD_0;
		}

	} else {
		$int = intval($str);
	}
	return $int;
} // alphaIndexToInt



//-----------------------------------------------------------------------
function getCliArg($argname)
{
	$cliarg = null;
	if (isset($GLOBALS['argv'])) {
		foreach ($GLOBALS['argv'] as $arg) {
			if (preg_match("/".preg_quote($argname)."=?(\S*)/", $arg, $m)) {
				$cliarg = $m[1];
				break;
			}
		}
	}
	return $cliarg;
} // getCliArg



//-----------------------------------------------------------------------
function get_url_arg($tag, $simple_mode = false, $unset = false) {
// in case of arg that is present but empty:
// simple_mode: returns false
// otherwise, returns true
	$out = false;
	if (isset($_GET[$tag])) {
		$arg = safeStr($_GET[$tag], false, true);
		if ((!$arg && $simple_mode) || ($arg == 'true')) {
			$out = true;
		} elseif ((!$arg) || ($arg == 'false')) {
			$out = false;
		} else {
			$out = $arg;
		}
		if ($unset) {
			unset($_GET[$tag]);
		}
	}
	return $out;
} // get_url_arg



//-------------------------------------------------------------------------
function getUrlArgStatic($tag, $varName = false)
{
	if (!$varName) {
		$varName = $tag;
	}
	$stat = false;
	if (isset($_GET[$tag])) {
		$arg = safeStr($_GET[$tag], false, true);
		if (($arg == '') || ($arg == 'true') || ($arg == '1') || ($arg == 'on')) {
			$stat = true;
		} else {
			$stat = false;
		}
		$_SESSION[$varName] = $stat;
	} else {
		if (!isset($_SESSION[$varName])) {
			$_SESSION[$varName] = false;
		}
		$stat = $_SESSION[$varName];
	}
	return $stat;
} // getUrlArgStatic



//-------------------------------------------------------------------------
function getStaticArg($varName)
{
	if (isset($_SESSION[$varName])) {
		return $_SESSION[$varName];
	} else {
		return null;
	}
} // getStaticArg



//-------------------------------------------------------------------------
function goto_page($target) {
	header("Location: $target");
	exit;
} // goto_page



//------------------------------------------------------------
function get_post_data($varName) {
	$out = false;
	if (isset($_POST) && isset($_POST[$varName])) {
		$out = $_POST[$varName];
		$out = safeStr($out);
	}
	return $out;
} // get_post_data



//------------------------------------------------------------
function path_info($file) {
	if (substr($file, -1) == '/') {
		$pi['dirname'] = $file;
		$pi['filename'] = '';
		$pi['extension'] = '';
	} else {
		$pi = pathinfo($file);
		$pi['dirname'] = correctPath(isset($pi['dirname']) ? $pi['dirname'] : '');
		$pi['filename'] = isset($pi['filename']) ? $pi['filename'] : '';
		$pi['extension'] = isset($pi['extension']) ? $pi['extension'] : '';
	}
	return $pi;
} // path_info



//------------------------------------------------------------
function preparePath($path)
{
	$path = dirname($path.'x');
    if (!file_exists($path)) {
        if (!mkdir($path, 0777, true)) {
            die("Error: failed to create folder '$path'");
        }
    }
} // preparePath



//------------------------------------------------------------
function is_legal_email_address($str) {
// multiple address allowed, if separated by comma.
		if (!is_safe($str)) {
			return false;
		}
		foreach (explode(',', $str) as $s) {
				if (!preg_match('/^\w(([_\.\-\']?\w+)*)@(\w+)(([\.\-]?\w+)*)\.([a-z]{2,})$/i', trim($s))) {
						return false;
				}
		}
		return true;
} // is_legal_email_address



//------------------------------------------------------------
function is_safe($str, $multiline = false) {
//??? not implemented correctly yet!
	if ($multiline) {
		$str = str_replace(PHP_EOL, '', $str);
		$str = str_replace("\r", '', $str);
		return !preg_match("/[^\pL\pS\pP\pN\pZ]/um", $str);
	} else {
		return !preg_match("/[^\pL\pS\pP\pN\pZ]/um", $str);
	}
} // is_safe



//---------------------------------------------------------------------------
function safeStr($str) {
	if (preg_match('/^\s*$/', $str)) {
		return '';
	}
	$str = substr($str, 0, MAX_URL_ARG_SIZE);	// restrict size to safe value
	$str = preg_replace('/[^[:print:]À-ž]/m', '#', $str);
	return $str;
} // safeStr



//-------------------------------------------------------------------------
function strToASCII($str) {
// translates special characters (such as ä, ö, ü) into pure ASCII
	$specChars = array('ä','ö','ü','Ä','Ö','Ü','é','â','á','à',
		'ç','ñ','Ñ','Ç','É','Â','Á','À','ß','ø','å');
	$specCodes2 = array('ae','oe','ue','Ae',
		'Oe','Ue','e','a','a','a','c',
		'n','N','C','E','A','A','A',
		'ss','o','a');
	return str_replace($specChars, $specCodes2, $str);
} // strToASCII



//------------------------------------------------------------
function timestamp($short = false) {
	if (!$short) {
		return date('Y-m-d H:i:s');
	} else {
		return date('Y-m-d');
	}
} // timestamp



//-------------------------------------------------------------------------
function touchFile($file, $time = false)
{	// work-around: PHP's touch() fails if http-user is not owner of file 
	if ($time) {
        shell_exec("touch -t ".date("YmdHi.s", $time)." $file"); 
	} else {
		touch($file);
	}
} // touchFile



//-------------------------------------------------------------------------
function translateToFilename($str, $appendExt = true) {
// translates special characters (such as , , ) into "filename-safe" non-special equivalents (a, o, U)
	$str = strToASCII(trim(mb_strtolower($str)));	// replace special chars
	$str = strip_tags($str);						// strip any html tags
	$str = str_replace(' ', '_', $str);				// replace blanks with _
	$str = str_replace('/', '_', $str);				// replace '/' with _
	$str = preg_replace("/[^[:alnum:]\._-`]/m", '', $str);	// remove any non-printables
	$str = preg_replace("/\.+/", '.', $str);		// reduce multiple ... to one .
	if ($appendExt && !preg_match('/\.html?$/', $str)) {	// append file extension '.html'
		if ($appendExt === true) {
			$str .= '.html';
		} else {
			$str .= '.'.$appendExt;
		}
	}
	return $str;

} // translateToFilename



//-------------------------------------------------------------------------
function translateToIdentifier($str, $removeDashes = false) {
// translates special characters (such as , , ) into "filename-safe" non-special equivalents (a, o, U)
	$str = strToASCII(mb_strtolower(trim($str)));		// replace special chars
	$str = strip_tags($str);							// strip any html tags
	$str = preg_replace('/\s+/', '_', $str);			// replace blanks with _
	$str = preg_replace("/[^[:alnum:]_-]/m", '', $str);	// remove any non-letters, except _ and -
	if ($removeDashes) {
		$str = str_replace("-", '_', $str);				// remove -, if requested
	}
	return $str;
} // translateToIdentifier



//------------------------------------------------------------
function mylog($str, $destination = false) {
	writeLog($str, $destination);
} // mylog



//------------------------------------------------------------
function writeLog($str, $destination = false)
{
    global $globalParams;

    if ($path = $globalParams['logPath']) {
        if ($destination) {
            if (($destination[0] == '~') || ($destination[0] == '/')) {
                $destination = resolvePath($destination);
            } else {
                $destination = $path.$destination;
            }
        } else {
            $destination = $path.'log.txt';
        }
        preparePath($destination);
        file_put_contents($destination, timestamp()."  $str\n", FILE_APPEND);
    }
} // writeLog



//------------------------------------------------------------------------------
function show_msg($msg, $title = '') {
	if (!$title) {
		$title = basename(__FILE__, '.php');
	}
	$msg = shield_str($msg);
	echo shell_exec("whoami");

//	shell_exec("/usr/local/bin/terminal-notifier -message \"$msg\" -title \"$title\"");
} // show_msg



//------------------------------------------------------------------------------
function shield_str($s) {
	return str_replace('"', '\\"', $s);
} // shield_str



//------------------------------------------------------------------------------
function var_r($var, $varName = '', $flat = false)
{
	$out = "<p>$varName:</p>\n<pre>".var_export($var, true)."\n</pre>\n";
	if ($flat) {
		$out = pre_replace("/\n/", '', $out);
	}
	return $out;
}



//------------------------------------------------------------------------------
function darken($hexColor, $decr) {
	if (!preg_match('/^\#([\da-f])([\da-f])([\da-f])$/i', trim($hexColor), $m) &&
		!preg_match('/^\#([\da-f][\da-f])([\da-f][\da-f])([\da-f][\da-f])$/i', trim($hexColor), $m)) {
			die("$macroName: bad color value: $hexColor");
	}
	if (!$decr) {
		$decr = 1;
	}
	if (strlen($m[1]) == 2) {
		$decr *= 16;
	}
	$r = dechex(hexdec($m[1]) - $decr);
	$g = dechex(hexdec($m[2]) - $decr);
	$b = dechex(hexdec($m[3]) - $decr);
	return "#$r$g$b";
} // darken



//------------------------------------------------------------------------------
function createWarning($msg) {
	return "\t\t<div class='MsgBox Warning'>$msg</div>\n";
} // createWarning



//------------------------------------------------------------------------------
function createDebugOutput($msg) {
	if ($msg) {
		return "\t\t<div id='log'>$msg</div>\n";
//		return "\t\t<div class='DebugFrame'>$msg</div>\n";
	} else {
		return '';
	}
} // createDebugOutput



$timer = 0;
//------------------------------------------------------------------------------
function startTimer() {
	global $timer;
	$timer = microtime(true);
} // startTimer



//------------------------------------------------------------------------------
function readTimer() {
	global $timer;
	return "Time: ".(round((microtime(true) - $timer)*1000000) / 1000 - 0.005).' ms';
} // readTimer



//------------------------------------------------------------------------------
function renderLink($href, $text = '', $type = '', $class = '')
{
	$target = '';
	$title = '';
	$hiddenText = '';
	if (stripos($href, 'mailto:') !== false) {
		$class = ($class) ?  "$class mail_link" : 'mail_link';
		$title = " title='`opens mail app`'";
		if (!$text) {
			$text = substr($href, 7);
		} else {
			$hiddenText = "<span class='print_only'> [$href]</span>";
		}
	}
	if (!$text) {
		$text = $href;
	} else {
		$hiddenText = "<span class='print_only'> [$href]</span>";
	}
	if ((stripos($type, 'extern') !== false) || (stripos($href, 'http') === 0)) {
		$target = " target='_blank'";
		$class = ($class) ?  "$class external_link" : 'external_link';
		$title = " title='`opens in new win`'";
	}
	$class = " class='$class'";
	$str = "<a href='$href' $class$title$target>$text$hiddenText</a>";
	return $str;
} // renderLink



//------------------------------------------------------------------------------
function mb_str_pad ($input, $pad_length, $pad_string, $pad_style=STR_PAD_RIGHT, $encoding="UTF-8") { 
   return str_pad($input, strlen($input)-mb_strlen($input,$encoding)+$pad_length, $pad_string, $pad_style); 
} // mb_str_pad



//-----------------------------------------------------------------------------
function stripNewlinesWithinTransvars($str)
{
    $p1 = strpos($str, '{{');
	if ($p1 === false) {
		return $str;
	}
    do {
        list($p1, $p2) =  strPosMatching($str, '{{',  '}}',$p1);

        if ($p1 === false) {
            break;
        }
        $s = substr($str, $p1, $p2-$p1+2);
        $s = preg_replace("/\n\s*/ms", '↵ ',$s);
 
        $str = substr($str, 0, $p1) . $s . substr($str, $p2+2);
        $p1 += strlen($s);
    } while (strpos($str, '{{', $p1) !== false);
	
    return $str;
} // stripNewlinesWithinTransvars



//-----------------------------------------------------------------------------
function strPosMatching($str, $pat1 = '{{', $pat2 = '}}', $p0 = 0)
{	// returns positions of opening and closing patterns, ignoring shielded patters (e.g. \{{ )

	$d = strlen($pat2);
    if ((strlen($str) < 4) || ($p0 > strlen($str))) {
        return [false, false];
    }

    if (!checkNesting($str, $pat1, $pat2)) {
        return [false, false];
    }

	$p1 = $p0 = findNextPattern($str, $pat1, $p0);
	$cnt = 0;
	do {
		$p3 = findNextPattern($str, $pat1, $p1+$d); // next opening pat
		$p2 = findNextPattern($str, $pat2, $p1+$d); // next closing pat
        if ($p2 === false) { // no more closing pat
                return [false, false];
		}
		if ($cnt == 0) {	// not in nexted structure
			if ($p3 === false) {	// no more opening pat
				return [$p0, $p2];
			}
			if ($p2 < $p3) { // no more opening patterns or closing before next opening
				return [$p0, $p2];
			} else {
				$cnt++;
				$p1 = $p3;
			}
		} else {	// within nexted structure
			if ($p3 === false) {	// no more opening pat
				$cnt--;
				$p1 = $p2;
			} else {
				if ($p2 < $p3) { // no more opening patterns or closing before next opening
					$cnt--;
					$p1 = $p2;
				} else {
					$cnt++;
					$p1 = $p3;
				}
			}
		}
	} while (true);
} // strPosMatching




//-----------------------------------------------------------------------------
function checkNesting($str, $pat1, $pat2)
{
    $n1 = substr_count($str, $pat1);
    $n2 = substr_count($str, $pat2);
    if ($n1 > $n2) {
        die("Nesting Error in string '$str'");
    }
    return $n1;
} // checkNesting



//-----------------------------------------------------------------------------
function findNextPattern($str, $pat, $p1 = 0)
{
	while (($p1 = strpos($str, $pat, $p1)) !== false) {
		if (($p1 == 0) || (substr($str, $p1-1, 1) != '\\')) {
			break;
		}
		$p1 += strlen($pat);
	}
	return $p1;
} // findNextPattern



//-----------------------------------------------------------------------------
function trunkPath($path, $n = 1)
{
//	$path = ($path[strlen($path)-1] == '/') ? rtrim($path, '/') : dirname($path);
//	return implode('/', explode('/', $path, -$n)).'/';
	if ($n>0) {
		$path = ($path[strlen($path)-1] == '/') ? rtrim($path, '/') : dirname($path);
		return implode('/', explode('/', $path, -$n)).'/';
	} else {
		$path = ($path[0] == '/') ? substr($path,1) : $path;
		$parray = explode('/', $path);
		return implode('/', array_splice($parray, -$n));
	}
} // trunkPath



//-----------------------------------------------------------------------------
function sort2dArray($array, $col, $hasHeaders = true)
{
    if ($hasHeaders) {
        $headers = $array[0];
        array_shift($array);
    }
    usort($array, make_comparer($col));
    
    if ($hasHeaders) {
        $array = array_merge([$headers], $array);
    }
    return $array;
} // sort2dArray



//-----------------------------------------------------------------------------
function make_comparer() {
    // Normalize criteria up front so that the comparer finds everything tidy
    $criteria = func_get_args();
    foreach ($criteria as $index => $criterion) {
        $criteria[$index] = is_array($criterion)
            ? array_pad($criterion, 3, null)
            : array($criterion, SORT_ASC, null);
    }

    return function($first, $second) use (&$criteria) {
        foreach ($criteria as $criterion) {
            // How will we compare this round?
            list($column, $sortOrder, $projection) = $criterion;
            $sortOrder = $sortOrder === SORT_DESC ? -1 : 1;

            // If a projection was defined project the values now
            if ($projection) {
                $lhs = call_user_func($projection, $first[$column]);
                $rhs = call_user_func($projection, $second[$column]);
            }
            else {
                $lhs = $first[$column];
                $rhs = $second[$column];
            }

            // Do the actual comparison; do not return if equal
            if ($lhs < $rhs) {
                return -1 * $sortOrder;
            }
            else if ($lhs > $rhs) {
                return 1 * $sortOrder;
            }
        }

        return 0; // tiebreakers exhausted, so $first == $second
    };
} // make_comparer



//-----------------------------------------------------------------------------
function compileMarkdownStr($mdStr, $removeWrappingPTags = false)
{
    $md = new MyMarkdown();
    $str = $md->parseStr($mdStr);
    if ($removeWrappingPTags) {
        $str = preg_replace('/^\<p>(.*)\<\/p>\n$/', "$1", $str);
    }
    return $str;
} // compileMarkdownStr