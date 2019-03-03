<?php
// @info: -> one line description of macro <-


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $state = $this->getArg($macroName, 'state', '[isLocalhost, isPrivileged] The system state to be checked.', '');
    $config = $this->getArg($macroName, 'config', 'Name of a config-value (as in config/config.yaml)', '');
    $file = $this->getArg($macroName, 'file', 'File name to be checked (relative to page path by default)', '');
    $urlArg = $this->getArg($macroName, 'urlArg', 'Name of URL-argument, e.g. "?arg=true"', '');
    $request = $this->getArg($macroName, 'request', 'Name of request-argument, either GET or POST as submitted by a form', '');
    $variable = $this->getArg($macroName, 'request', 'Name of a Session-Variable', '');
    $op = $this->getArg($macroName, 'op', "[==, <, >, <=, >=, !=] Operand to be applied in comparison of config-value and argument.  \nOr file-op [exists, empty, <, >]", '');
    $arg = $this->getArg($macroName, 'arg', 'Argument to be applied in comparison', '');
    $then = $this->getArg($macroName, 'then', 'What to return if the state is active', '');
    $else = $this->getArg($macroName, 'else', 'What to return if the state is not active', '');

    $inx = $this->invocationCounter[$macroName] + 1;

    $res = false;
    if (is_string($state)) {
        $state = strtolower($state);
    }

    if (($state === true) || ($state === 'true')) {
        $res = true;

    } elseif (($state === false) || ($state === 'false')) {
        $res = false;

    } elseif ($state == 'islocalhost') {
        $res = isLocalCall();

    } elseif ($state == 'isprivileged') {
        $res = $this->lzy->config->isPrivileged;

    } elseif ($config) {
        if (isset($this->lzy->config->$config)) {
            $val = $this->lzy->config->$config;
            $res = evalOp($arg, $op, $val);
        }

    } elseif($file) {       // if file exists and is not empty:
        $file = resolvePath($file, true);
        if (!$op) {
            $res = (file_exists($file) && filesize($file));
        } elseif ($op == 'exists') {
            $res = file_exists($file);
        } elseif ($op == 'empty') {
            $res = (file_exists($file) && (filesize($file) == 0));
        } elseif (($op == 'lt') || ($op == '<')) {
            $res = (!file_exists($file) || (filesize($file) < intval($arg)));
        } elseif (($op == 'gt') || ($op == '>')) {
            $res = (file_exists($file) && (filesize($file) > intval($arg)));
        }

    } elseif ($urlArg) {
        if (!$op) {
            $res = getUrlArg($urlArg);
        } else {
            $val = getUrlArg($urlArg, true);
            $res = evalOp($val, $op, $arg);
        }
    } elseif ($request) {
        if (!$op) {
            $res = isset($_REQUEST['$request']) ? $_REQUEST['$request'] : '';
        } else {
            $val = isset($_REQUEST['$request']) ? $_REQUEST['$request'] : '';
            $res = evalOp($val, $op, $arg);
        }
    } elseif ($variable) {
        $res = isset($_SESSION['lizzy']['$variable']) ? $_REQUEST['$variable'] : '';
    }
    $this->optionAddNoComment = true;

    if ($res) {
        $then =  evalResult($this, $then, $inx);
        return $then;
    } else {
        $else =  evalResult($this, $else, $inx);
        return $else;
    }
});



function evalResult($trans, $code, $inx)
{
    $out = $code;
    if (preg_match('/^\%(\w+):(.*)/', $code, $m)) {
        $code = $m[1];
        $arg = trim($m[2]);
        $out = '';
        switch ($code) {
            case 'redirect' :
                $trans->page->addRedirect($arg);
                break;

            case 'message' :
                $trans->page->addMessage($arg);
                break;

            case 'debugMsg' :
                $trans->page->addDebugMsg($arg);
                break;

            case 'overlay' :
                $trans->page->addOverlay($arg);
                $trans->page->setOverlayMdCompile(true);
                break;

            case 'override' :
                $trans->page->addOverride($arg);
                $trans->page->setOverrideMdCompile(true);
                break;

            case 'pageSubstitution' :
                $trans->page->addPageSubstitution($arg);
                break;

            case 'description' :
                $trans->page->addDescription($arg);
                break;

            case 'keywords' :
                $trans->page->addKeywords($arg);
                break;

            case 'popup' :
                $trans->page->addPopup($arg);
                break;

            case 'macro' :
                if (preg_match('/([\w-]+) \( (.*) \)/x', $arg, $m)) {
                    $macro = $m[1];
                    $arg = $m[2];
                    $out = $trans->translateMacro($macro, $arg);
                }
                break;

            case 'contentFrom' :
                $arg = html_entity_decode($arg);
                if (preg_match('/^ ["\']? ([\#\.] [\w-]+)/x', $arg, $m)) {
                    $id = "lzy-content-from-if{$inx}";
                    $selector = $m[1];
                    $jq = "\n\t$('#$id').html( $('$selector').html() );";
                    $trans->page->addJq($jq);
                    $trans->optionAddNoComment = true;
                    $out = "\t\t<div id='$id' class='lzy-content-from'></div>\n";
                }
                break;

            case 'include' :
                $filename = resolvePath($arg, true);
                $str = getFile($filename);
                if ($str) {
                    $str = compileMarkdownStr($str);
                    $trans->page->addContent($str);
                }
                break;

            default:
                $out = $code;
        }
    } elseif (preg_match('/([\w-]+) \( (.*) \)/x', $code, $m)) {
        $macro = $m[1];
        $arg = $m[2];
        $out = $trans->translateMacro($macro, $arg);
    }

    return $out;
}



function evalOp($arg, $op, $val) {
    if (($op == 'eq') || ($op == '==')) {
        $res = ($val == $arg);
    } elseif (($op == 'lt') || ($op == '<')) {
        $res = ($val < $arg);
    } elseif (($op == 'le') || ($op == '<=')) {
        $res = ($val <= $arg);
    } elseif (($op == 'ge') || ($op == '>=')) {
        $res = ($val >= $arg);
    } elseif (($op == 'ne') || ($op == '!=')) {
        $res = ($val != $arg);
    }
    return $res;
}