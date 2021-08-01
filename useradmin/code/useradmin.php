<?php

require_once ADMIN_PATH.'user-admin.class.php';

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    
    $mode = $this->getArg($macroName, 'mode', '[signup,invite] Defines what to do', 'signup');

    if ($mode === 'help') {
        $this->getArg($macroName, 'proxyuser', 'Name of a proxy-user for self-signup', 'selfsignup');
        $this->getArg($macroName, 'group', 'Name of a user group(s)', 'guests');
        $this->getArg($macroName, 'registrationPeriod', '[1 day, 1 week, 1 month] The time you grant the invited person to register. (Default: 1 week)', '1 week');
        $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);
        return '';
    }

    $args = $this->getArgsArray($macroName);

    $usrAdm = new UserAdmin( $this->lzy );
    $html = $usrAdm->render( $args );
    $html = <<<EOT
  <div class='lzy-useradmin-wrapper'>
$html
  </div><!-- /lzy-useradmin-wrapper -->

EOT;


    $this->optionAddNoComment = true;
    return $html;
});
