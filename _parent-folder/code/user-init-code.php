<?php

/*
**	Lizzy Installation: security check
**      Ask user to change password until it's been changed.
*/

$thisFile = __FILE__;
$userFileName = CONFIG_PATH.$this->config->admin_usersFile;

$out = <<<EOT

<div class="chg-standard-pw" style="border:1px solid red;padding:20px;margin:3em;background:#fee;">
    @msg
    <h2>Change Admin Password:</h2>
    
   
    <form action="?" method="post">
        <label for='new-password'>New Password for User 'admin':</label>
        <input type="input" name="init-password" id="new-password">
        <input type="submit" value="Save">
    </form>
    <p>&nbsp;</p>
    <p><strong>Note:</strong><br>You may change the password later again, see file '$userFileName'.</p>
</div>
EOT;

$msg = "<p><strong>Warning:</strong><br> Standard admin password as predefined at installation time is still active!</p>
<p>Please change it ASAP!</p><p>For now admin credentials are: <code>admin / insecure-pw</code></p>";


$stdPWhash = '$2y$10$koBJgyhl0QgBwmiE/.MdXOXR0mrTG29bjq37VHxBdZJJTDY2GE/n2';

$userFile = getFile($userFileName);
if (strpos($userFile, $stdPWhash) === false) {
    return;
} else {    // std pw still there
    if (isset($_POST['init-password'])) {
        if (strlen($_POST['init-password']) < 6) {
            $msg = "<p>Note: the new password needs to be at least 6 charachters long.</p>";

        } else {
            $newPWhash = password_hash($_POST['init-password'], PASSWORD_DEFAULT);
            $userFile = str_replace($stdPWhash, $newPWhash, $userFile);
            $userFile = str_replace("\n# The standard password is 'insecure-pw' - please change immediately!\n", '', $userFile);
            file_put_contents($userFileName, $userFile);

            $newUserCodeFileName = dir_name($thisFile).'##'.base_name($thisFile);
            rename($thisFile, $newUserCodeFileName);
            return;
        }
    }
}

$out = str_replace('@msg', $msg, $out);
$this->page->addContent($out);  // append to content area


return;
