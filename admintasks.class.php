<?php

class AdminTasks
{
    private $pendingMail = false;

    public function __construct($that = null)
    {
        if ($that && get_class($that) == 'Lizzy') {
            $this->auth = $that->auth;
            $this->page = $that->page;
            $this->trans = $that->trans;

            $adminTransvars = resolvePath('~sys/config/admin.yaml');
            $this->trans->readTransvarsFromFile($adminTransvars);

        } else {
            $this->auth = $that;
            $this->page = null;
        }
    }




    public function execute($that, $adminTask)
    {
        $notification = getStaticVariable('lastLoginMsg');
        $group = getUrlArg('group', true);

        $accountForm = new UserAccountForm($that);
        if (!$_SESSION['lizzy']['user']) {

            // everyone who is not logged in:
            if ($adminTask == 'login') {
                $pg = $accountForm->renderLoginForm($notification);

            } elseif (($adminTask == 'self-signup') && $this->auth->config->feature_enableSelfSignUp) {
                $pg = $accountForm->renderSignUpForm($notification);
                return $pg;

            } elseif ($adminTask == 'change-password') {
                $str = "<div class='lzy-admin-task-response'>{{ lzy-change-password-not-logged-in-response }}</div>";
                $this->page->addOverride($str, true);
                return $str;

            } elseif ($adminTask == 'change-email') {
                $str = "<div class='lzy-admin-task-response'>{{ lzy-change-password-not-logged-in-response }}</div>";
                $this->page->addOverride($str, true);
                return $str;

            }
            if (!$GLOBALS['globalParams']['isAdmin']) {
                return;
            }
        }



        // for logged in users of any group:
        if ($adminTask == 'change-password') {
            $pg = $accountForm->renderChangePwForm($_SESSION['lizzy']['user'], $notification);
            $this->page->merge($pg);
            return $pg;

        } elseif ($that->config->admin_userAllowSelfAdmin && ($adminTask == 'edit-profile')) {
            $userRec = $that->auth->getLoggedInUser(true);
            $html = $accountForm->renderEditProfileForm($userRec, $notification);
            $this->page->addOverride($html, true, false);
            return '';
        }



        if (!$GLOBALS['globalParams']['isAdmin']) {
            return '';
        }

        // only Admins permitted beyond this point
        if ($adminTask == 'add-users') {
            $pg = $accountForm->renderAddUsersForm($group, $notification);

        } elseif ($adminTask == 'add-user') {
            $pg = $accountForm->renderAddUserForm($group, $notification);

        } else {
            return "Error: mode unknown 'adminTask'";
        }
        $loginForm = $pg->get('override', true);
        $this->page->merge($pg);

    } // execute





    //....................................................
    public function sendSignupMail($email, $group = 'guest')
    {
        $accessCodeValidyTime = 900; //???
        $message = $this->sendCodeByMail($email, 'email-signup', $accessCodeValidyTime, 'unknown-user', $group, false);
        return $message;
    } // sendSignupMail




    //....................................................
    public function sendChangeMailAddress_Mail($email)
    {
        $accessCodeValidyTime = 900; //???
        $user = $this->auth->getLoggedInUser();
        $message = $this->sendCodeByMail($email, 'email-change-mail', $accessCodeValidyTime, $user, '', false);
        return $message;
    } // sendChangeMailAddress_Mail




    //....................................................
    public function addUsers($emails, $group)
    {
        $lines = explode("\n", $emails);
        $newRecs = [];
        foreach ($lines as $line)
        {
            if (preg_match('/(.*)\<(.*)\>/', $line, $m)) {
                $name = trim($m[1]);
                $email = $m[2];
            } elseif (preg_match('/.*\@.*\..*/', $line)) {
                $name = '';
                $email = $line;
            } else {
                continue;
            }
            $newRecs[$email] = ['email' => $email, 'displayName' => $name, 'groups' => $group];
        }

        if ($newRecs) {
            $str = '';
            foreach ($newRecs as $rec) {
                $str .= "<li><span class='lzy-adduser-mail'>{$rec['email']}</span> [<span class='lzy-adduser-name'>{$rec['displayName']}</span>]</li>\n";
            }
            $str = "<div class='lzy-adduser-wrapper'>\n<div>{{ lzy-add-users-response }} <strong>$group</strong> {{ lzy-add-users-response2 }}:</div>\n<ul>$str</ul>\n</div>\n";
            $this->addUsersToDB($newRecs);

        } else {
            $str = "<div class='lzy-adduser-wrapper'>{{ lzy-add-users-none-added }} <strong>$group</strong> {{ lzy-add-users-none-added2 }</div>";
        }
        return $str;
    }



    //....................................................
    public function addUser($rec)
    {
        $this->addUsersToDB($rec);
        $rec = array_pop($rec);
        $str = "<div class='lzy-adduser-wrapper'>{{ lzy-add-user-response }}: {$rec['email']}</div>";
        return $str;
    }



    //....................................................
    public function changePassword($user, $password)
    {
        $str = '';
        $knownUsers = $this->auth->getKnownUsers();
        if (isset($knownUsers[$user])) {
            $this->updateDbUserRec($user, ['password' => password_hash($password, PASSWORD_DEFAULT)]);
            $str = "<div class='lzy-admin-task-response'>{{ lzy-password-changed-response }}</div>";
        }
        return $str;
    }



    //....................................................
    public function changeUsername($newUsername, $displayName)
    {
        $rec = $this->auth->getLoggedInUser( true );
        $user = $rec['name'];

        if (!$newUsername && !$displayName) {
            return "<div class='lzy-admin-task-response'>{{ lzy-username-change-no-change-response }}</div>";
        }
        if ($user == $newUsername) {
            if (!$displayName) {
                return "<div class='lzy-admin-task-response'>{{ lzy-username-change-no-change-response }}</div>";
            }
            $newUsername = '';
        }

        if (!$newUsername) {
            $newUsername = $user;
            $res = false;
        } else {
            $newUsername = strtolower($newUsername);
            $res = $this->isInvalidUsername($newUsername);
        }
        if ($res) { // user name already in use or invalid!
            $str = "<div class='lzy-admin-task-response'>$res</div>";
            return $str;
        }
        if ($user != $rec['name']) {
            $str = "<div class='lzy-admin-task-response'>{{ lzy-username-change-illegal-name-response }}</div>";

        } else {
            if ($displayName) {
                $rec['displayName'] = $displayName;
            }
            $this->deleteDbUserRec($user);
            $rec['name'] = $newUsername;
            $this->addUserToDB($newUsername, $rec);
            $str = "<div class='lzy-admin-task-response'>{{ lzy-username-changed-response }}</div>";
        }
        return $str;
    } // changeUsername



    private function isInvalidUsername($username) {
        if ($username == 'admin') {
            return '{{ lzy-username-changed-error-name-taken }}';

        } elseif ($res = $this->auth->findUserRecKey($username, '*')) {
            return '{{ lzy-username-changed-error-name-taken }}';

        } elseif ($res = $this->auth->findEmailInEmailList($username)) {
            return '{{ lzy-username-changed-error-name-taken }}';
        }

        if (!preg_match('/^\w{2,15}$/', $username)) {
            return '{{ lzy-username-changed-error-illegal-name }}';
        }
        return false;
    } // checkValidUsername



    public function deleteUserAccount($user = false)
    {
        if (!$user) {
            if (!$user = $_SESSION['lizzy']['user']) {
                return "<div class='lzy-admin-task-response'>{{ lzy-delete-account-failed-response }}</div>";
            }
        } else {
            if (!$this->auth->isAdmin()) {
                return "<div class='lzy-admin-task-response'>{{ lzy-delete-account-failed-response }}</div>";
            }
        }
        $this->deleteDbUserRec($user);
        $this->auth->logout();
        return "<div class='lzy-admin-task-response'>{{ lzy-delete-account-success-response }}</div>";
    }



    public function createGuestUserAccount($email)
    {
        $group = 'guest';
        if (isset($_SESSION['lizzy']['self-signup-to-group'])) {
            $group = $_SESSION['lizzy']['self-signup-to-group'];
            unset($_SESSION['lizzy']['self-signup-to-group']);
        }

        // for security: self-signup for admin-group only possible as long as there
        // no accounts registered at all, i.e. only the first signup may become admin:
        $knownUsers = $this->auth->getKnownUsers();

        if ($group == 'admin') {
            if (sizeOf($knownUsers) > 0) {
                writeLog("self-signup for group admin blocked - only allowed if there are NO accounts defined yet!");
                return false;

            } else {
                $this->addUsersToDB([ $email => ['email' => $email, 'groups' => $group]]);
                writeLog("new admin user added: $email [".getClientIP().']', LOGIN_LOG_FILENAME);
                return true;
            }

        } else {
            $this->addUsersToDB([ $email => ['email' => $email, 'groups' => $group]]);
            writeLog("new guest user added: $email [".getClientIP().']', LOGIN_LOG_FILENAME);
            return true;
        }
    } // createGuestUserAccount


    public function changeEMail($email)
    {
        if ($res = $this->isInvalidEmailAddress($email)) {
            return $res;
        }
        writeLog("email for user changed: $email [".getClientIP().']', LOGIN_LOG_FILENAME);
        $userRec = $this->auth->getLoggedInUser( true );
        $user = $userRec['name'];
        $userRec['email'] = $email;
        $this->updateDbUserRec($user, $userRec);
    } // changeEMail



    private function isInvalidEmailAddress($email) {
        if (!is_legal_email_address( $email )) {
            return 'email-changed-email-invalid';
        }
        if ($res = $this->auth->findUserRecKey($email, '*')) {
            return 'email-changed-email-in-use';

        }
        if ($res = $this->auth->findEmailInEmailList($email)) {
            return 'email-changed-email-in-use';
        }

        return false;
    } // isInvalidEmailAddress




    //-------------------------------------------------------------
    private function sendMail($to, $subject, $message)
    {
        $from = isset($this->config->admin_webmasterEmail) ? $this->config->admin_webmasterEmail : 'webmaster@domain.net';
        setStaticVariable('pendingMail', ['from' => $from, 'to' => $to, 'subject' => $subject, 'message' => $message]);
    } // sendMail



    //-------------------------------------------------------------
    public function getPendingMail()
    {
        return  getStaticVariable('pendingMail');
    } // getPendingMail





    private function addUserToDB($username, $userRec)
    {
        $userRecs = $this->auth->getKnownUsers();
        $userRecs[$username] = $userRec;
        writeToYamlFile($this->auth->userDB, $userRecs);
        return true;
    }




    private function addUsersToDB($userRecs)
    {
        $userRecs = array_merge($this->auth->getKnownUsers(), $userRecs);
        writeToYamlFile($this->auth->userDB, $userRecs);
        return true;
    }




    private function deleteDbUserRec($user)
    {
        $userRecs = $this->auth->getKnownUsers();
        if (isset($userRecs[$user])) {
            unset($userRecs[$user]);
            writeToYamlFile($this->auth->userDB, $userRecs);
            $this->auth->knownUsers = $userRecs;
        }
    }



    private function updateDbUserRec($user, $rec)
    {
        $userRecs = $this->auth->getKnownUsers();
        if (!isset($userRecs[$user])) {
            return false;
        }
        $userRec = &$userRecs[$user];
        foreach ($rec as $k => $v) {
            $userRec[$k] = $v;
        }
        writeToYamlFile($this->auth->userDB, $userRecs);
        return true;
    }


    public function sendCodeByMail($submittedEmail, $mode, $accessCodeValidyTime, $user, $group, $displayName)
    {
        global $globalParams;

        $message = '';
        $validUntil = time() + $accessCodeValidyTime;
        setlocale(LC_TIME, 'de_DE.utf-8');     //??? should adapt to ... what?
        $validUntilStr = strftime('%R  (%x)', $validUntil);

        $hash = $this->createHash();

        $onetime[time()] = ['hash' => $hash, 'user' => $user, 'email' => $submittedEmail,'groups' => $group, 'validUntil' => $validUntil, 'mode' => $mode];
        writeToYamlFile(ONETIME_PASSCODE_FILE, $onetime);

        $url = $globalParams['pageUrl'] . $hash . '/';
        if ($mode == 'email-login') {
            $subject = "[{{ site_title }}] {{ lzy-email-access-link-subject }} {$globalParams['host']}";
            $message = "{{ lzy-email-access-link0 }}$displayName{{ lzy-email-access-link1 }} $url {{ lzy-email-access-link2 }} $hash {{ lzy-email-access-link3 }} \n";

            $this->sendMail($submittedEmail, $subject, $message);

            $userAcc = new UserAccountForm(null);

            $message = $userAcc->renderOnetimeLinkEntryForm($user, $validUntilStr, 'lzy-onetime access link');

        } elseif ($mode == 'email-signup') {
            $subject = "[{{ site_title }}] {{ lzy-email-sign-up-subject }} {$globalParams['host']}";
            $message = "{{ lzy-email-sign-up1 }} $url {{ lzy-email-sign-up2 }} $hash {{ lzy-email-sign-up3 }} \n";

            $this->sendMail($submittedEmail, $subject, $message);

            $userAcc = new UserAccountForm(null);

            $message = $userAcc->renderOnetimeLinkEntryForm($submittedEmail, $validUntilStr, 'lzy-sign-up-link');

        } elseif ($mode == 'email-change-mail') {
            $rec = $this->auth->getLoggedInUser( true );
            if (isset($rec['email']) && ($rec['email'] == $submittedEmail)) {
                reloadAgent(false,"email-change-mail-unchanged");
            }
            $res = $this->isInvalidEmailAddress($submittedEmail);
            if ($res) {
                reloadAgent( false, $res );
            }
            $subject = "[{{ site_title }}] {{ lzy-email-change-mail-subject }} {$globalParams['host']}";
            $message = "{{ lzy-email-change-mail-up1 }} $url {{ lzy-email-change-mail-up2 }} $hash {{ lzy-email-change-mail-up3 }} \n";

            $this->sendMail($submittedEmail, $subject, $message);

            $userAcc = new UserAccountForm(null);

            $message = $userAcc->renderOnetimeLinkEntryForm($submittedEmail, $validUntilStr, 'lzy-change-mail-link');
        }
        return $message;
    }




    //....................................................
    public function sendAccessLinkMail()
    {
        if ($pM = $this->auth->getPendingMail()) {

            $headers = "From: {$pM['from']}\r\n" .
                'X-Mailer: PHP/' . phpversion();
            $subject = $this->trans->translate( $pM['subject'] );
            $message = $this->trans->translate( $pM['message'] );

            if ($this->localCall) {
                $this->page->addOverlay("<pre class='debug-mail'><div>Subject: $subject</div>\n<div>$message</div></pre>");
                $this->page->addJq("$( 'body' ).keydown( function (e) {if (e.which == 27) { $('.overlay').hide(); } });");
            } else {
                if (!mail($pM['to'], $subject, $message, $headers)) {
                    fatalError("Error: unable to send e-mail", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                }
            }
        }
    } // sendAccessLinkMail





    private function createHash($size = 6)
    {
        $hash = chr(rand(65, 90));
        $hash .= strtoupper(substr(sha1(rand()), 0, $size-1));
        return $hash;
    } // createHash

} // class