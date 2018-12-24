<?php
// @info: -> one line description of macro <-


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $state = $this->getArg($macroName, 'state', '[isLocalhost, isPrivileged] The system state to be checked.', '');
    $config = $this->getArg($macroName, 'config', 'Name of a config-value (as in config/config.yaml)', '');
    $file = $this->getArg($macroName, 'file', 'File name to be checked (relative to page path by default)', '');
    $urlArg = $this->getArg($macroName, 'urlArg', 'Name of URL-argument, e.g. "?arg=true"', '');
    $op = $this->getArg($macroName, 'op', "[==, <, >, <=, >=, !=] Operand to be applied in comparison of config-value and argument.  \nOr file-op [exists, empty, <, >]", '');
    $arg = $this->getArg($macroName, 'arg', 'Argument to be applied in comparison', '');
    $then = $this->getArg($macroName, 'then', 'What to return if the state is active', '');
    $else = $this->getArg($macroName, 'else', 'What to return if the state is not active', '');

    $res = false;
    $state = strtolower($state);

    if ($state == 'true') {
        $res = true;

    } elseif ($state == 'false') {
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
    }
    $this->optionAddNoComment = true;

    if ($res) {
        $then =  evalResult($this, $then);
        return $then;
    } else {
        $else =  evalResult($this, $else);
        return $else;
    }
});



function evalResult($trans, $code)
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

            default:
                $out = $code;
        }
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