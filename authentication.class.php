<?php

class Authentication
{
	public $message = '';
	private $userRec = false;
    public $mailIsPending = false;


    public function __construct($lzy)
    {

        //$this->config->configPath.$this->config->admin_usersFile, $this->config
        $this->lzy = $lzy;
        $this->config = $this->lzy->config;
        $this->localCall = $lzy->config->isLocalhost;
        $this->userDB = $usersFile = $this->config->configPath.$this->config->admin_usersFile;
    	$this->knownUsers = getYamlFile($usersFile);
        $this->loginTimes = (isset($_SESSION['lizzy']['loginTimes'])) ? unserialize($_SESSION['lizzy']['loginTimes']) : array();
		if (!isset($_SESSION['lizzy']['user'])) {
			$_SESSION['lizzy']['user'] = false;
		}
        setStaticVariable('lastLoginMsg', '');
    } // __construct




    public function authenticate()
    {
        $ip = getClientIP();
        $uname = "unknown [$ip]";

        $res = null;
        if (isset($_POST['lzy-onetime-code']) && isset($_POST['lzy-login-user'])) {                         // user sent accessCode
            $str = $this->validateOnetimeAccessCode(false, $_POST['lzy-onetime-code']);
            $res = [false, $str, 'Override'];

        } elseif (get_post_data('lzy-user-signup') == 'signup-email') {      // user sent email for signing up
            $email = get_post_data('lzy-self-signup-email-email');
            $group = getStaticVariable('self-signup-to-group');

            require_once SYSTEM_PATH.'admintasks.class.php';
            $adm = new AdminTasks($this);
            if ($this->isKnownUser($email)) {
                $res = $this->checkAccessCode($email);
                writeLog("one time link sent: $uname", LOGIN_LOG_FILENAME);
                if ($res !== false) {
                    $this->mailIsPending = true;
                    $res = [false, $res, 'Overlay'];   // if successful, a mail with a link has been sent and user will be authenticated on using that link
                } else {
                    fatalError('not impl yet');
                }
            } else {
                $str = $adm->sendSignupMail($email, $group);
                $this->mailIsPending = true;
                $res = [false, $str, 'Override'];
            }

        } elseif (isset($_POST['lzy-login-username']) && isset($_POST['lzy-login-password-password'])) {    // user sent un & pw
            $credentials = array('username' => $_POST['lzy-login-username'], 'password' => $_POST['lzy-login-password-password']);
            if (!$this->validateCredentials($credentials)) {
                return null;
            }

        } elseif (isset($_POST['lzy-onetimelogin-email-email'])) {                                          // user sent email for logging in
            $emailRequest = $_POST['lzy-onetimelogin-email-email'];
            $res = $this->checkAccessCode($emailRequest);
            writeLog("one time link sent: $uname", LOGIN_LOG_FILENAME);
            if ($res !== false) {
                $this->mailIsPending = true;
                $res = [false, $res, 'Overlay'];   // if successful, a mail with a link has been sent and user will be authenticated on using that link
            }
        }
        if ($res === null) {        // no login attempt detected -> check whether already logged in:
            $this->getLoggedInUser();
            $res = (isset($this->userRec['name'])) ? $this->userRec['name'] : false;
        }
        return $res;
    }




    public function adminActivities()
    {
        if (!$this->isAdmin() || !isset($_POST['lzy-user-admin'])) {
            return false;
        }

        require_once SYSTEM_PATH.'admintasks.class.php';
        $adm = new AdminTasks($this);

        $requestType = get_post_data('lzy-user-admin');
        $res = false;
        if ($requestType == 'add-users') {                // admin is adding users
            $emails = get_post_data('lzy-add-users-email-list');
            $group = get_post_data('lzy-add-user-group');
            $str = $adm->addUsers($emails, $group);
            $res = [false, $str, 'Override'];

        } elseif ($requestType == 'add-user') {           // admin is adding a user
            $email = get_post_data('lzy-add-user-email');
            $un = get_post_data('lzy-add-user-username');
            $key = ($un) ? $un : $email;
            $rec[$key] = [
                'email' => $email,
                'group' => get_post_data('lzy-add-user-group'),
                'username' => $un,
                'displayName' => get_post_data('lzy-add-user-displayname'),
                'emaillist' => get_post_data('lzy-add-user-emaillist')
            ];
            $str = $adm->addUser($rec);
            $res = [false, $str, 'Override'];


        } elseif ($requestType == 'change-password') {           // user is changing his/her password:
            $user = get_post_data('lzy-user');
            $password = get_post_data('lzy-change-password-password');
            $password2 = get_post_data('lzy-change-password2-password2');
            $res = $this->isValidPassword($password, $password2);
            if ($res == '') {
                $str = $adm->changePassword($user, $password);
            } else {
                $this->message = "<div class='lzy-adduser-wrapper'>$res</div>";
                return false;
            }
            $res = [false, $str, 'Override'];


        } elseif ($requestType == 'lzy-change-username') {        // user changes username
            $username = get_post_data('lzy-change-user-username');
            $displayName = get_post_data('lzy-change-user-displayname');
            $str = $adm->changeUsername($username, $displayName);
            $res = [false, $str, 'Override'];


        } elseif ($requestType == 'lzy-change-email') {           // user changes mail address
            $email = get_post_data('lzy-change-user-email-email');
            require_once SYSTEM_PATH.'admintasks.class.php';
            $adm = new AdminTasks($this);
            $str = $adm->sendChangeMailAddress_Mail($email);
            $this->mailIsPending = true;
            $res = [false, $str, 'Override'];


        } elseif ($requestType == 'lzy-delete-account') {           // user deletes account
            $str = $adm->deleteUserAccount();
            $res = [false, $str, 'Override'];
        }
        return $res;
    }






	private function validateCredentials($credentials)
	{
        $requestingUser = strtolower($credentials['username']);


		if (isset($this->knownUsers[$requestingUser])) {    // user found in user-DB:
            $rec = $this->knownUsers[$requestingUser];
            $rec['name'] = $requestingUser; //???
            $correctPW = (isset($rec['password'])) ? $rec['password'] : '';
            $providedPW = isset($credentials['password']) ? trim($credentials['password']) : '####';

            // check username and password:
            if (password_verify($providedPW, $correctPW)) {  // login succeeded
                writeLog("logged in: $requestingUser [{$rec['group']}] (" . getClientIP(true) . ')', LOGIN_LOG_FILENAME);
                $this->setUserAsLoggedIn($requestingUser, $rec);
                return true;

            } else {                                        // login failed: pw wrong
                $rep = '';
                if ($this->handleFailedLogins()) {
                    $rep = ' REPEATED';
                }
                $this->monitorFailedLoginAttempts();
                writeLog("*** Login failed$rep (wrong pw): $requestingUser [" . getClientIP(true) . ']', LOGIN_LOG_FILENAME);
                $this->message = '{{ Login failed }}';
                setStaticVariable('lastLoginMsg', '{{ Login failed }}');
                $this->unsetLoggedInUser();
            }
        }
        return false;
    } // validateCredentials





    private function monitorFailedLoginAttempts()
    // More than HACKING_THRESHOLD failed login attempts within 15 minutes are considered a hacking attempt.
    // If that is detected, we delay ALL login attempts by 5 seconds.
    {
        $tnow = time();
        file_put_contents(HACK_MONITORING_FILE, $tnow."\n", FILE_APPEND);
        $lines = file(HACK_MONITORING_FILE);
        $cnt = 0;
        $out = '';
        foreach ($lines as $t) {
            if (intval($t) < ($tnow - 900)) {   // omit old entries
                continue;
            }
            $cnt++;
            $out .= $t;
        }
        file_put_contents(HACK_MONITORING_FILE, $out);

        if ($cnt > HACKING_THRESHOLD) {
            writeLog("!!!!! Possible hacking attempt [".getClientIP().']', LOGIN_LOG_FILENAME);
            sleep(5);
        }
    } // monitorFailedLoginAttempts




	private function checkAccessCode($emailRequest)
	{
	    global $globalParams;
        if (!$emailRequest) {
            return false;
        }
        $submittedEmail = strtolower($emailRequest);
        $group = '';
        $found = false;
        $user = '';
        if (isset($this->knownUsers[$submittedEmail])) {    // check whether it's a username rather than an email
            $user = $submittedEmail;
            $rec = $this->knownUsers[$submittedEmail];
            if (isset($rec['email'])) {
                $submittedEmail = $rec['email'];
                $found = true;
                $rec['name'] = $user;
                $name = $user;
            }

        } else {
            foreach ($this->knownUsers as $name => $rec) {
                if (isset($rec['accessCodeEnabled']) && ($rec['accessCodeEnabled'] == false)) {
                    continue;
                }

                if (isset($rec['email']) && ($rec['email'] == $submittedEmail)) {
                    $found = true;
                    break;

                }

                if (isset($rec['emailList']) && $rec['emailList']) {
                    $filename = $this->config->configPath . $rec['emailList'];
                    if (file_exists($filename)) {
                        $emails = file($filename);
                        $e2 = [];
                        foreach ($emails as $i => $email) {
                            $e = strtolower(trim(preg_replace('/.*\<([^\>]+)\>.*/', "$1", $email)));
                            $e = preg_split("/[\s,;]/m", $e);
                            if (sizeof($e) > 1) {
                                $e2 = array_merge($e2, $e);
                                unset($emails[$i]);
                            }
                        }
                        $emails = array_merge($emails, $e2);
                        foreach ($emails as $email) {
                            if (!trim($email)) { continue; }
                            $email = strtolower(trim(preg_replace('/.*\<([^\>]+)\>.*/', "$1", $email)));
                            if (($email{0} != '#') && ($email == $submittedEmail)) {
                                $found = true;
                                $user = $email;
                                $group = (isset($rec['group'])) ? $rec['group'] : '';
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        if (!$found) {
            return false;
        }

        require_once SYSTEM_PATH.'admintasks.class.php';
        $adm = new AdminTasks();

        $accessCodeValidyTime = isset($rec['accessCodeValidyTime']) ? $rec['accessCodeValidyTime'] : $this->config->admin_defaultAccessLinkValidyTime;
        $message = $adm->sendCodeByMail($submittedEmail, 'email-login', $accessCodeValidyTime, $name, $user, $group);

        return $message;
    } // checkAccessCode






    //....................................................
    public function validateOnetimeAccessCode($pagePath, $code = false)
    {
        global $globalParams;
        $user = false;
        if ($code) {
            if (!$pagePath) {
                $pagePath = $globalParams['pagePath'];
            }
            $codeCandidate = strtoupper(trim($code));
        } else {
            $codeCandidate = basename($pagePath);
            if (!preg_match('/^[A-Z][A-Z0-9]{5,}$/', $codeCandidate)) {
                return $pagePath;
            }
            $pagePath = trunkPath($pagePath,1, false);
        }

        if ($this->validateAccessCode($codeCandidate)) {
            reloadAgent('~/'.$pagePath); // access NOT granted, remove hash-code from url
            return $pagePath;
        }

        $onetime = getYamlFile(ONETIME_PASSCODE_FILE);
        if (!$onetime) {
            $this->monitorFailedLoginAttempts();
            writeLog("*** one-time link rejected: $codeCandidate [".getClientIP().']', LOGIN_LOG_FILENAME);
            reloadAgent('~/'.$pagePath); // access NOT granted, remove hash-code from url
            return $pagePath;
        }

        $tlim = time() - 3600;  // max admin_defaultAccessLinkValidyTime
        $found = false;
        foreach ($onetime as $t => $rec) {  // find matching entry in list of one-time-hashes
            if ($t < $tlim) {
                unset($onetime[$t]);
                continue;
            }
            if ($codeCandidate == $rec['hash']) {
                $user = $rec['user'];
                unset($onetime[$t]);
                $found = true;
                break;
            }
        }

        if (isset($this->knownUsers[$user])) {
            $rec = $this->knownUsers[$user];
            if (!isset($rec['user'])) {
                $rec['user'] = $user;
            }
            if (isset($rec['accessCodeValidyTime'])) {
                $accessCodeValidyTime = $rec['accessCodeValidyTime'];
            } else {
                $accessCodeValidyTime = $this->config->admin_defaultAccessLinkValidyTime;
            }
            $tlim = time() - $accessCodeValidyTime;
            if ($t < $tlim) {
                $found = false;
                $user = false;
                unset($onetime[$t]);
            }

        } elseif (isset($rec['mode']) && ($rec['mode'] == 'email-signup')) {
            require_once SYSTEM_PATH.'admintasks.class.php';
            $adm = new AdminTasks($this);
            if (!$adm->createGuestUserAccount($rec['user'])) {
//??? notify user if account creation failed
            }

        } elseif (isset($rec['mode']) && ($rec['mode'] == 'email-change-email')) {
            require_once SYSTEM_PATH.'admintasks.class.php';
            $adm = new AdminTasks($this);
            $adm->changeEMail($rec['user']);
        }

        writeToYamlFile(ONETIME_PASSCODE_FILE, $onetime);
        if ($found) {
            $this->knownUsers[$rec['user']] = ['email' => $rec['user'], 'group' => $rec['group']];
            $displayName = $this->setUserAsLoggedIn($user);

            if ($displayName) {
                $user .= " ({$displayName})";
            }
            writeLog("one time link accepted: $user [".getClientIP().']', LOGIN_LOG_FILENAME);
            // access granted, remove hash-code from url
            reloadAgent(true); // access granted, remove hash-code from url
        }
        $rep = '';
        if ($this->handleFailedLogins()) {
            $rep = ' REPEATED';
        }
        $this->monitorFailedLoginAttempts();
        writeLog("*** one time link rejected$rep: $codeCandidate $user [".getClientIP().']', LOGIN_LOG_FILENAME);
        reloadAgent($pagePath); // access NOT granted, remove hash-code from url
    } // validateOnetimeAccessCode




    private function validateAccessCode($codeCandidate)
    {
        if (!$this->knownUsers) {
            return false;
        }
        foreach ($this->knownUsers as $user => $rec) {
            if (isset($rec['accessCode'])) {
                $code = $rec['accessCode'];
                if (password_verify($codeCandidate, $code)) {
                    if (isset($rec['accessCodeValidUntil'])) {
                        $validUntil = strtotime($rec['accessCodeValidUntil']);
                        if ($validUntil < time()) {
                            return false;
                        }
                    }
                    $this->setUserAsLoggedIn($user, $rec);
                    return true;
                }
            }
        }
        return false;
    } // validateAccessCode




    public function setUserAsLoggedIn($user, $rec = null, $displayName = '')
	{
	    if (($rec == null) && isset($this->knownUsers[$user])) {
	        $rec = $this->knownUsers[$user];
        }
        if (!$rec) {
            fatalError("Error: unknown user", 'File: '.__FILE__.' Line: '.__LINE__);
        }
        $this->userRec = $rec;

        $this->loginTimes[$user] = time();
        session_regenerate_id();
        $_SESSION['lizzy']['user'] = $user;
        $GLOBALS['globalParams']['isAdmin'] = $this->isAdmin();
        $_SESSION['lizzy']['isAdmin'] = $this->isAdmin();
        $_SESSION['lizzy']['loginTimes'] = serialize($this->loginTimes);

        if ($displayName) {
            $_SESSION['lizzy']['userDisplayName'] = $displayName;   // case override

        } elseif (isset($rec['displayName'])) {
            $displayName = $rec['displayName']; // displayName from user rec
            $_SESSION['lizzy']['userDisplayName'] = $displayName;

        } else {
            $_SESSION['lizzy']['userDisplayName'] = $user;
        }
        return $displayName;
    } // setUserAsLoggedIn




    public function getLoggedInUser( $returnRec = false )
	{
		$rec = false;
		$user = isset($_SESSION['lizzy']['user']) ? $_SESSION['lizzy']['user'] : false;
		if ($user) {
			$rec = (isset($this->knownUsers[$user])) ? $this->knownUsers[$user] : false;
			if (!$rec) {    // just to be safe: if logged in user has nor record, don't allow to proceed
			    $user = $_SESSION['lizzy']['user'] = false;
            } else {
                $rec['name'] = $user;

                $lastLogin = (isset($this->loginTimes[$user])) ? $this->loginTimes[$user] : 0;  // check maxSessionTime
                if (isset($this->knownUsers[$user]['maxSessionTime'])) {
                    $validityPeriod = $this->knownUsers[$user]['maxSessionTime'];
                    if ($lastLogin < (time() - $validityPeriod)) {
                        $rec = false;
                        $this->message = '{{ validity-period expired }}';
                        $this->unsetLoggedInUser();
                    }
                }
            }
		}
		if ($returnRec) {
		    return $rec;

        } else {
            $this->userRec = $rec;
            return ($rec != false);
        }
    } // getLoggedInUser




    public function getKnownUsers()
    {
        if (is_array($this->knownUsers)) {
            return $this->knownUsers;
        } else {
            return [];
        }
    }


    public function isKnownUser($user)
    {
        if (is_array($this->knownUsers)) {
            if (in_array($user, array_keys($this->knownUsers))) {
                return true;
            }
        }
        return false;
    }


    public function checkGroupMembership($requiredGroup)
    {
        if ($this->localCall && $this->config->admin_autoAdminOnLocalhost) {	// no restriction
	        return true;
        }

        if (isset($this->userRec['group'])) {
            $requiredGroups = explode(',', $requiredGroup);
            $usersGroups = strtolower(str_replace(' ', '', ','.$this->userRec['group'].','));
            foreach ($requiredGroups as $rG) {
                $rG = strtolower(trim($rG));
                if ((strpos($usersGroups, ",$rG,") !== false) ||
                    (strpos($usersGroups, ",admins,") !== false)) {
                    return true;
                }
            }
        }
        return false;
    } // checkGroupMembership




    public function checkAdmission($lockProfile)
	{
		if (($lockProfile == false) || ($this->localCall && $this->config->admin_autoAdminOnLocalhost)) {	// no restriction
			return true;
		}
		
		$rec = $this->userRec;
		if (!$rec) {
		    return false;
        } elseif (!isset($rec['name'])) {
            $rec['name'] = '';
        }

        if (isset($rec['group']) && $this->isGroupMember($rec['group'], 'admins')) { // admins have access by default
		    return true;
        }

		$lockProfiles = explode(',', $lockProfile);
		foreach ($lockProfiles as $lp) {
			$lp = trim($lp);
			if ($lp == $rec['name']) {
				return true;
			} elseif (isset($rec['group']) && ($lp == $rec['group'])) { //???
				return true;
			}
		}
		if ($rec && !$this->message) {
			$this->message = '{{ Insufficient privileges }}';
		}
		return false;
	} // checkAdmission




    private function isGroupMember($usersGroups, $groupToCheckAgainst)
    {
        if (!$usersGroups) {
            return false;
        }
        $usersGroups = str_replace(' ', '', ",$usersGroups,");
        $groupToCheckAgainst = ",$groupToCheckAgainst,";
        $res = (strpos($usersGroups, $groupToCheckAgainst) !== false);
        return $res;
    } // isGroupMember





    //-------------------------------------------------------------
    public function logout()
    {
        $user = getStaticVariable('user');
        $user .= (isset($_SESSION['lizzy']['userDisplayName'])) ? ' ('.$_SESSION['lizzy']['userDisplayName'].')' : '';
        writeLog("logged out: $user [".getClientIP(true).']', LOGIN_LOG_FILENAME);

        $this->unsetLoggedInUser();
    } // logout



    //-------------------------------------------------------------
    public function unsetLoggedInUser($user = '')
    {
        if ($user) {
            $this->loginTimes[$user] = 0;
        }
        $this->userRec = null;
        $_SESSION['lizzy']['user'] = false;
        $_SESSION['lizzy']['userDisplayName'] = false;
        $_SESSION['lizzy']['isAdmin'] = false;
        $GLOBALS['globalParams']['isAdmin'] = false;
    } // unsetLoggedInUser



    //-------------------------------------------------------------
    private function handleFailedLogins()
    {
        $repeated = false;
        $ip = getClientIP();
        $failedLogins = getYamlFile(FAILED_LOGIN_FILE);     // enforce delay for retries from same ip
        $tnow = time();
        foreach ($failedLogins as $t => $ip1) {
            if ($t < ($tnow - 60)) {
                unset($failedLogins[$t]);
            } elseif ($ip == $ip1) {
                sleep(3);
                $repeated = true;
                unset($failedLogins[$t]);
            }
        }
        $failedLogins[time()] = $ip;
        writeToYamlFile(FAILED_LOGIN_FILE, $failedLogins);
        return $repeated;
    } // handleFailedLogins




    private function isValidPassword($password, $password2 = false)
    {
        if ($password2 && ($password != $password2)) {
            return '{{ lzy-change-password-not-equal-response }}';
        }
        if (strlen($password) < 8) {
            return '{{ lzy-change-password-too-short-response }}';
        }
        if (!preg_match('/[A-Z]/', $password) ||
            !preg_match('/\d/', $password) ||
            !preg_match('/[^\w\d]/', $password)) {
            return '{{ lzy-change-password-insufficient-response }}';
        }
        return '';
    }


    //-------------------------------------------------------------
    public function isPrivileged()
    {
        return $this->checkAdmission('admins,editors');
    } // isPrivileged




    //-------------------------------------------------------------
    public function isAdmin()
    {
        if (getStaticVariable('isAdmin')) {
            return true; //??? secure?
        }
        return $this->checkAdmission('admins');
    }


} // class Authentication
