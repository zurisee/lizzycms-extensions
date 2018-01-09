<?php

class Authentication
{
	public $message = '';
	private $userRec = false;

    public function __construct($usersFile, $config)
    {
        $this->config = $config;
        $this->localCall = $config->isLocalhost;
    	$this->knownUsers = getYamlFile($usersFile);
        $this->loginTimes = (isset($_SESSION['lizzy']['loginTimes'])) ? unserialize($_SESSION['lizzy']['loginTimes']) : array();
		if (!isset($_SESSION['lizzy']['user'])) {
			$_SESSION['lizzy']['user'] = false;
		}
    } // __construct




    public function authenticate($credentials = false, $trans = null)
    {
        $this->trans = $trans;
        $uname = 'unknown';
        $emailRequest = false;
        if (!$credentials) {
            if (isset($_POST['onetime-code']) && isset($_POST['login_user'])) {     // user sent accessCode
                $this->validateOnetimeAccessCode(false, $_POST['onetime-code']);

            } elseif (isset($_POST['login_name']) && isset($_POST['login_password'])) { // user sent un & pw
                $credentials = array('username' => $_POST['login_name'], 'password' => $_POST['login_password']);
                $uname = (isset($_POST['login_user'])) ?$_POST['login_user'] : '';

            } elseif (isset($_POST['login_email'])) {           // user sent email
                $emailRequest = $_POST['login_email'];
            }
        }

        $ip = getClientIP();
        $uname .= " [$ip]";
        // Case request for one-time-url-passcode -> email supplied as key:
        if ($emailRequest) {
            $res = $this->checkAccessCode($emailRequest);
            writeLog("one time link sent: $uname", LOGIN_LOG_FILENAME);
            if ($res === false) {
                return false;
            } else {
                return [false, $res];   // if successful, a mail with a link has been sent and user will be authenticated on using that link
                // so, if we get to this point, user has NOT yet been authenticated.
            }

		} elseif ($credentials) {
            $this->validateCredentials($credentials);
            if ($this->userRec['name'] && getUrlArg('login')) {
                reloadAgent();  // to get rid of '?login'
            }

        } else {
            $this->getLoggedInUser();   // check whether user is already logged in
		}
		return (isset($this->userRec['name'])) ? $this->userRec['name'] : false;
	} // authenticate





	private function validateCredentials($credentials)
	{
        $requestingUser = strtolower($credentials['username']);
        if (($requestingUser == 'admin') && $this->localCall && !$this->config->autoAdminOnLocalhost) { // allow empty pw for admin on localhost
            $this->setUserAsLoggedIn('admin', null, 'Localhost Admin');
            reloadAgent();
            return;
        }

		$rec = (isset($this->knownUsers[$requestingUser])) ? $this->knownUsers[$requestingUser] : [];
		$rec['name'] = $requestingUser;
		$correctPW = (isset($rec['password'])) ? $rec['password'] : '';
		$providedPW = isset($credentials['password']) ? $credentials['password'] : '####';

		if (password_verify($providedPW, $correctPW)) {  // login succeeded
            writeLog("logged in: $requestingUser [".getClientIP(true).']', LOGIN_LOG_FILENAME);
		    $this->setUserAsLoggedIn($requestingUser, $rec);

		} else {                                        // login failed: pw wrong
            $rep = '';
            if ($this->handleFailedLogins()) {
                $rep = ' REPEATED';
            }
            $this->monitorFailedLoginAttempts();
            writeLog("*** Login failed$rep (wrong pw): $requestingUser [".getClientIP(true).']', LOGIN_LOG_FILENAME);
			$this->message = '{{ Login failed }}';

            $this->unsetLoggedInUser();
		}
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
                            if (!$email) { continue; }
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

        $accessCodeValidyTime = isset($rec['accessCodeValidyTime']) ? $rec['accessCodeValidyTime'] : $this->config->defaultAccessLinkValidyTime;
        $validUntil = time() + $accessCodeValidyTime;
        setlocale (LC_TIME, 'de_DE.utf-8');     //??? should adapt to ... what?
        $validUntilStr = strftime('%R  (%x)', $validUntil);

        $hash = $this->createHash();

        $onetime[time()] = ['hash' => $hash, 'user' => $name, 'validUntil' => $validUntil];
        writeToYamlFile(ONETIME_PASSCODE_FILE, $onetime);

        $url = $globalParams['pageUrl'].$hash . '/';
        $subject = "[{{ site_title }}] {{ Email Access-Link Subject }} {$globalParams['host']}";
        $message = "{{ Email Access-Link1 }} $url {{ Email Access-Link2 }} $hash {{ Email Access-Link3 }} \n";

        $this->sendMail($submittedEmail, $subject, $message);



        $message = <<<EOT

    <div class='onetime-link-sent'>
    {{ onetime access link sent }}

    <form class="onetime-code-entry" method="post">
        <label for="">{{ enter onetime code }}</label>
        <input type="hidden" value="$user" name="login_user" />
        <input id="ontime-code" type="text" name="onetime-code" style="text-transform:uppercase;width:6em;" />
        <input type="submit" class='ios_button' value="{{ submit }}" />
    </form>

    <p> {{ onetime access link sent2 }} $validUntilStr</p>
    <p> {{ onetime access link sent3 }}</p>
    </div>

EOT;

            return $message;
    } // checkAccessCode




    private function createHash($size = 6)
    {
        $hash = chr(rand(65, 90));
        $hash .= strtoupper(substr(sha1(rand()), 0, $size-1));
        return $hash;
    } // createHash




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

        $tlim = time() - 3600;  // max defaultAccessLinkValidyTime
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
            if (isset($rec['accessCodeValidyTime'])) {
                $accessCodeValidyTime = $rec['accessCodeValidyTime'];
            } else {
                $accessCodeValidyTime = $this->config->defaultAccessLinkValidyTime;
            }
            $tlim = time() - $accessCodeValidyTime;
            $aaa = strftime('%c', $tlim);
            if ($t < $tlim) {
                $found = false;
                $user = false;
                unset($onetime[$t]);
            }
        }

        writeToYamlFile(ONETIME_PASSCODE_FILE, $onetime);
        if ($found) {
            $displayName = $this->setUserAsLoggedIn($user);

            if ($displayName) {
                $user .= " ({$displayName})";
            }
            writeLog("one time link accepted: $user [".getClientIP().']', LOGIN_LOG_FILENAME);
            reloadAgent('~/'.$pagePath); // access granted, remove hash-code from url
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




    private function setUserAsLoggedIn($user, $rec = null, $displayName = '')
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




    private function getLoggedInUser()
	{
		$rec = false;
		$user = isset($_SESSION['lizzy']['user']) ? $_SESSION['lizzy']['user'] : false;
		if ($user) {
			$rec = (isset($this->knownUsers[$user])) ? $this->knownUsers[$user] : false;
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
		$this->userRec = $rec;
		return ($rec != false);
    } // getLoggedInUser




    public function checkGroupMembership($requiredGroup)
    {
        if ($this->localCall && $this->config->autoAdminOnLocalhost) {	// no restriction
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
		if (($lockProfile == false) || ($this->localCall && $this->config->autoAdminOnLocalhost)) {	// no restriction
			return true;
		}
		
		$rec = $this->userRec;
		if (!$rec) {
		    return false;
        } elseif (!isset($rec['name'])) {
            $rec['name'] = '';
        }

        if ($this->isGroupMember($rec['group'], 'admins')) { // admins have access by default
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




    public function renderForm($message = '', $warning = '')
    {
        $warning = ($warning) ? $warning : $this->message;
		$this->page = new Page;
		if ($this->config->permitOneTimeAccessLink) {
		    $str = $this->createMultimodeLoginForm($message, $warning);
        } else {
		    $str = $this->createPWAccessForm($message, $warning);
        }

		$this->page->addOverride($str);

        return $this->page;
    } // authForm



    //-------------------------------------------------------------
    private function createMultimodeLoginForm($message, $warning)
    {
        global $globalParams;
        if ($message) {
            $message = "<div class='lizzy-login-message'>$message</div>";
        }
        if (!$warning) {
            $warning = $this->message;
        }
        $subject = '{{login-problems}}';
        $body = '%0a%0a{{page}}:%20' . $globalParams['pageUrl'];
        $loginProblemMail = "{{ concat('forgot password1', webmaster-email,'?subject=$subject&body=$body', 'forgot password2') }}";
        $html = <<<EOT

<div class='lizzy-login-wrapper'>
    <h2>{{ Login }}</h2>
    $message

    <div class="lizzy-login">
        <input class='lizzy-login-tab-radio lizzy-login-tab-radio1' id='lizzy-login-without_password' type='radio' name='lizzy-login-tab-radio1' checked="checked" /><input class='lizzy-login-tab-radio lizzy-login-tab-radio2' id='lizzy-login-with_password' type='radio' name='lizzy-login-tab-radio1' />
    
        <nav class="lizzy-login-tab-labels">
            <ul>
                <li class='lizzy-login-tab-label1'><label for='lizzy-login-without_password'>{{ login without password }}</label></li>
                <li class='lizzy-login-tab-label2'><label for='lizzy-login-with_password'>{{ login with password }}</label></li>
    
            </ul>
        </nav>
        <div class="lizzy-login-tabs">
            <div class="lizzy-login-tab1">
    
                    <form action="./" method="POST">
                        <div class='login_message'>$warning</div>
                        <label for="fld_name" id="lbl_login_user"><span class='c1'>{{ Login-Email }}:</span><a href="#" id="info-onetimelogin" title="{{ info-onetimelogin-title }}">&#9432;</a> <span id="info-onetimelogin-text" class="login-info-panel dispno">{{ info-onetimelogin }}</span></label>
                        <input type="text" id="login_email" name="login_email" required aria-required="true" placeholder="name@domain.net">
                        <output id="msg_un" class='err_msg' aria-live="polite" aria-relevant="additions"></output>
                               
                        <button class="ios_button submit" name="btn_submit" value="submit" id="btn_submit" >{{ onetime-link-send }}</button>
                    </form>
                
            </div><!-- / .lizzy-login-tab1 -->
            
            <div class="lizzy-login-tab2">
               
                    <form action="./" method="POST">
                        <div class='login_message'>$warning</div>
                        <label for="fld_name" id="lbl_login_user2"><span class='c1'>{{ Username }}:</span><a href="#" id="info-onetimelogin2" title="{{ info-onetimelogin-title2 }}">&#9432;</a> <span id="info-onetimelogin-text2" class="login-info-panel dispno">{{ info-onetimelogin2 }}</span></label>
                        <input type="text" id="fld_username" name="login_name" required aria-required="true" placeholder="name@domain.net">
                        <output id="msg_un2" class='err_msg' aria-live="polite" aria-relevant="additions"></output>
                        
                        <label for="fld_password" id="lbl_login_password"><span class='c1'>{{ Password }}:</span><a href="#" id="password-alternative" title="{{ password-alternative-title }}">&#9432;</a> 
                        <div id="show_password"><input type="checkbox" id="fld_show_password"><label for="fld_show_password">{{ Login show password }}</label></div></label>
                        <span id="info-password-alternative-text" class="login-info-panel dispno">{{ password-info }}</span>
                        <input type="password" id="fld_password" name='login_password' >
                        <output id="msg_pw" class='err_msg2' aria-live="polite" aria-relevant="additions"></output>
                        
                        <div class="lizzi-login-no-password">$loginProblemMail</div>
                        <button class="ios_button submit" name="btn_submit" value="submit" id="btn_submit2">{{ onetime-link-send }}</button>
                    </form>
    
            </div><!-- / .lizzy-login-tab2 -->
    
        </div><!-- / .lizzy-login-tabs -->
    
    </div><!-- / .lizzy-login -->
</div><!-- / .login-wrapper -->


EOT;

        $jq = <<<EOT
	$('#login_email').focus();
	$('#fld_show_password').click(function(e) {
	    if ($('#fld_password').attr('type') == 'text') {
    	    $('#fld_password').attr('type', 'password');
    	} else {
    	    $('#fld_password').attr('type', 'text');
    	} 
	});
	$('#info-onetimelogin').click(function(e) {
	    $('#info-onetimelogin-text').toggleClass('dispno');
	});
	$('#info-onetimelogin2').click(function(e) {
	    $('#info-onetimelogin-text2').toggleClass('dispno');
	});
	$('#password-alternative').click(function(e) {
	    $('#info-password-alternative-text').toggleClass('dispno');
	});
	$('#one-time-access-link').click(function(e) {
		e.preventDefault();
	    var loginEmail = $('#fld_username').val();
	    if (loginEmail.match(/[^@]+@[^@]+\.\w+/)) {
	        console.log('email address looks good');
	    } else {
	        $('#lbl_login_user .err_msg').text('{{ email required for one-time-access-link }}');
        	$('#fld_username').focus();
	    }
	});
	$('#btn_submit').click(function(e) {
		e.preventDefault();
		$( this ).prop('disabled', true);
		var url = window.location.href;
		if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/) )) {
			alert('{{ Warning insecure connection }}');
			return;
		}
		$('.lizzy-login-tab1 form').attr('action', url);
		$("#lbl_login_user output").text('');
		$("#lbl_login_password output").text('');
		var un = $('#login_email').val();
		if (!un) {
			$("#msg_un").text('{{ Err empty email }}');
    		$( this ).prop('disabled', false);
			return;
		}
		if (false && (location.protocol != 'https:')) {
			alert('No HTTPS Connection!');
			return;
		}
		$('.lizzy-login-tab1 form').submit();
	});


	$('#btn_submit2').click(function(e) {
		e.preventDefault();
		$( this ).prop('disabled', true);
		var url = window.location.href;
		if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/) )) {
    		$( this ).prop('disabled', false);
			alert('{{ Warning insecure connection }}');
			return;
		}
		$('.lizzy-login-wrapper form').attr('action', url);
		$("#lbl_login_user output").text('');
		$("#lbl_login_password output").text('');
		var un = $('#fld_username').val();
		var pw = $('#fld_password').val();
		if (!un) {
			$("#msg_un2").text('{{ Err empty username or email }}');
    		$( this ).prop('disabled', false);
			return;
		}
		if (!pw) {
		    $('#fld_password').focus();
    		$( this ).prop('disabled', false);
		    return;
		}
		if (false && (location.protocol != 'https:')) {
			alert('No HTTPS Connection!');
			return;
		}
		$('.lizzy-login-tab2 form').submit();
		
	});
    $('.lizzy-login-tab-label1').click(function() {
        setTimeout(function(){ $('#login_email').focus(); }, 20);
    });
    $('.lizzy-login-tab-label2').click(function() {
        setTimeout(function(){ $('#fld_username').focus(); }, 20);
    });

EOT;
        $this->page->addJQ($jq);

        $css = <<<EOT
.lizzy-login-wrapper {
    width: 27em;
    max-width: 100%;
    min-width:   19em;
}
.lizzy-login-wrapper form {
    border: none;
}

nav.lizzy-login-tab-labels {
  margin-bottom: 0!important; }

.lizzy-login-message {
    margin-bottom: 1em; }
.lizzy-login-tabs {
  border: 1px solid #ddd;
  padding: 15px; 
  margin-top: -4px;}

.lizzy-login-tab-radio, .lizzy-login-tabs > div {
  display: none; }

.lizzy-login-tab-radio1:checked ~ .lizzy-login-tabs .lizzy-login-tab1, 
.lizzy-login-tab-radio2:checked ~ .lizzy-login-tabs .lizzy-login-tab2 {
  display: block; }

  .lizzy-login-tab-labels ul {
    list-style: none;
    margin: 0;
    padding: 0;
    font-size: 0; }
    .lizzy-login-tab-labels ul li {
      padding: 0;
      margin: 0;
      font-size: 12pt; }
      .lizzy-login-tab-labels ul li label {
        float: left;
        padding: 15px 25px;
        border: 1px solid #ddd;
        border-bottom: 0;
        background: #eee;
        color: #444; 
        border-radius: 0.7em 0.7em 0 0;}
        .lizzy-login-tab-labels ul li label:hover {
          background: #ddd; }
        .lizzy-login-tab-labels ul li label:active {
          background: #fff; }
      .lizzy-login-tab-labels ul li:not(:last-child) label {
        border-right-width: 0; }

.lizzy-login-tab-radio1:checked ~ nav .lizzy-login-tab-label1 label, 
.lizzy-login-tab-radio2:checked ~ nav .lizzy-login-tab-label2 label {
    background: white;
    color: #111;
    position: relative; }
.lizzy-login-tab-radio1:checked ~ nav .lizzy-login-tab-label1 label:after, 
.lizzy-login-tab-radio2:checked ~ nav .lizzy-login-tab-label2 label:after {
      content: '';
      display: block;
      position: absolute;
      height: 2px;
      width: 100%;
      background: #fff;
      left: 0;
      bottom: -1px; }
.lizzi-login-no-password {
    margin: 1em 0;
     text-align: right;}
.lizzy-login-tab-labels ul li {
    width: 50%;}
.lizzy-login-tab-labels ul li label {
    width: 100%;}



@media screen and (max-width : 480px) {
    .lizzy-login-tab-labels label {
        font-size: 4.5vw;
    }
}

EOT;

        $this->page->addCss($css);

        $html = preg_replace("/\n\s*/m", "\n", $html);  // make sure the MD-compiler doesn't get in the way
        $html = preg_replace("/\n\s*\n/m", "\n", $html);  // make sure the MD-compiler doesn't get in the way
        return $html;
    } // createOneTimeAccessForm


    //-------------------------------------------------------------
    private function createPWAccessForm($message, $warning)
    {
        if ($message) {
            $message = "<p>$message</p>";
        }
        if (!$warning) {
            $warning = $this->message;
        }
        $str = <<<EOT

<div class='login_wrapper'>
    <h2>{{ Login }}</h2>
    $message
    <form action="./" method="POST">
       <div class='login_message'>$warning</div>
       <label for="fld_name" id="lbl_login_user"><span class='c1'>{{ Username }}:</span>
            <input type="text" id="fld_username" name="login_user" required aria-required="true">
            <output class='err_msg' aria-live="polite" aria-relevant="additions"></output>
        </label>
        <label for="fld_password" id="lbl_login_password"><span class='c1'>{{ Password }}:</span>
            <input type="password" id="fld_password" name='login_password' required aria-required="true">
            <output class='err_msg' aria-live="polite" aria-relevant="additions"></output>
        </label>
        <button class="ios_button" name="btn_submit" value="submit" id="btn_submit">{{ Login }}</button>
    </form>
</div>

EOT;

        $jq = <<<EOT
	$('#fld_username').focus();
	$('#btn_submit').click(function(e) {
		e.preventDefault();
		var url = window.location.href;
		if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/) )) {
			alert('{{ Warning insecure connection }}');
			return;
		}
		$('.login_wrapper form').attr('action', url);
		$("#lbl_login_user output").text('');
		$("#lbl_login_password output").text('');
		var un = $('#fld_username').val();
		var pw = $('#fld_password').val();
		console.log('un: '+un+' pw: '+pw);
		if (!un) {
			$("#lbl_login_user output").text('{{ Err empty username }}');
			return;
		}
		if (!pw) {
			$("#lbl_login_password output").text('{{ Err empty password }}');
			return;
		}
		if (false && (location.protocol != 'https:')) {
			alert('No HTTPS Connection!');
			return;
		}
		$('.login_wrapper form').submit();
	});
EOT;
        $this->page->addJQ($jq);
        return $str;
    } // createPWAccessForm



    //-------------------------------------------------------------
    private function sendMail($to, $subject, $message)
    {
        $from = isset($this->config->webmasterEmail) ? $this->config->webmasterEmail : 'webmaster@domain.net';
        $this->pendingMail = ['from' => $from, 'to' => $to, 'subject' => $subject, 'message' => $message];
    } // sendMail



    //-------------------------------------------------------------
    public function getPendingMail()
    {
        return (isset($this->pendingMail)) ? $this->pendingMail : null;
    } // getPendingMail



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




    //-------------------------------------------------------------
    public function isPrivileged()
    {
        return $this->checkAdmission('admins,editors');
    } // isPrivileged




    //-------------------------------------------------------------
    public function isAdmin()
    {
        return $this->checkAdmission('admins');
    } // isAdmin

} // class Authentication
