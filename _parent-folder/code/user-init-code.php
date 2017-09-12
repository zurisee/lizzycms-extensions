<?php

/*
**	$this => class Lizzy
**
** For manipulating the embedding page, use $this->page:
**		$this->page->addHead('');
**		$this->page->addCssFiles('');
**		$this->page->addCss('');
**		$this->page->addJsFiles('');
**		$this->page->addJs('');
**		$this->page->addJqFiles('');
**		$this->page->addJq('');
**		$this->page->addBody_end_injections('');
**		$this->page->addMessage('');
**		$this->page->addPageReplacement('');
**		$this->page->addOverride('');
**		$this->page->addOverlay('');
*/

$out = <<<EOT

<div style="border:1px solid red; padding: 20px;margin-top: 2em;background:#fee;">
    <h1>Please Change Admin Password ASAP</h1>
    
   
    <form action="?" method="post">
        <label for='new-password'>New Password for User 'admin':</label>
        <input type="password" name="init-password" id="new-password">
        <input type="submit" value="Save">
    </form>
</div>
EOT;

$stdPWentry = '	password:	$2y$10$koBJgyhl0QgBwmiE/.MdXOXR0mrTG29bjq37VHxBdZJJTDY2GE/n2';

if (!$this->config->isLocalhost && !$this->loggedInUser) {
    $userFile = getFile(DEFAULT_CONFIG_FILE);
    if (($p=strpos($userFile, $stdPWentry)) === false) {
        return;
    } else {
        if (isset($_POST['init-password'])) {
            $newPWhash = password_hash($_POST['init-password'], PASSWORD_DEFAULT);
            $userFile = substr($userFile,0,$p)."\tpassword:	$newPWhash\n#".substr($userFile,$p);
            file_put_contents(DEFAULT_CONFIG_FILE, $userFile);
        }
    }


    $this->page->addContent($out);

    $notice = <<<EOT

<p style='margin-top:1em;'>As you are not working on a localhost you need to  {{ link('?login', 'log in') }} first.</p>

<p>Standard login after installation is: </p>
<p><span style='width:5em;display:inline-block;margin-left: 2em;'>Username:</span>admin<br/>
<span style='width:5em;display:inline-block;margin-left: 2em;'>Password:</span>insecure-pw</p>

EOT;

    $this->trans->addVariable('login-notice', $notice);
}

