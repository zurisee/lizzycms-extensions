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

    if ($state == 'islocalhost') {
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
        return $then;
    } else {
        return $else;
    }
});


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