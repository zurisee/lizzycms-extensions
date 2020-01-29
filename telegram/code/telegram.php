<?php

// initialize MadelineProto:
$pwd = getcwd();
$cwd = __DIR__;
chdir($cwd);
include 'madeline.php';
chdir($pwd);

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $str = '';

    $to = $this->getArg($macroName, 'to', 'A Telegram peer id, e.g. "@buddy".', '');
    $msg = $this->getArg($macroName, 'msg', 'The message string to be sent.', '');
    $from = $this->getArg($macroName, 'from', 'An optional id-string (in case you want to use multiple telegram bots in parallel). E.g. "bot1" (use any string you like).', '');

    if ($to === 'help') {
        return '';
    }
    if ($to && $msg) {
        $str = SendTelegram($to, $msg, $from);
    }

    return $str;
});





function SendTelegram($to, $msg, $from = '')
{
    if ($from) {
        if (file_exists($from)) {
            $sessionFile = $from;
        } else {
            $sessionFile = "data/telegram/$from.session.madeline";
        }
    } else {
        $sessionFile = 'data/telegram/session.madeline';
    }

    $MadelineProto = new \danog\MadelineProto\API($sessionFile);
    $MadelineProto->async(true);
    $MadelineProto->loop(function () use ($MadelineProto, $to, $msg) {
        yield $MadelineProto->start();
        yield $MadelineProto->messages->sendMessage(['peer' => $to, 'message' => $msg]);
    });
}