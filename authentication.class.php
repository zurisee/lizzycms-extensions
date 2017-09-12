<?php

class Authentication
{
	public $message = '';
	private $userRec = false;
	
    public function __construct($usersFile)
    {
    	$this->users = getYamlFile($usersFile);
        $this->loginTimes = (isset($_SESSION['loginTimes'])) ? unserialize($_SESSION['loginTimes']) : array();
		if (!isset($_SESSION['user'])) {
			$_SESSION['user'] = false;
		}
    } // __construct
    
    public function authenticate($credentials = false)
    {
        if (!$credentials && (isset($_POST['login_user']) && isset($_POST['login_password']))) {
            $credentials = array('username' => $_POST['login_user'], 'password' => $_POST['login_password']);
        }
        if ($credentials) {
			$this->checkCredentials($credentials);

		} else {    // check credentials
			$this->getLoggedInUser();
		}
		return $this->userRec['name'];
	} // authenticate
	
	public function checkRole($requiredRole)
	{
		if (isset($this->userRec['roles'])) {
			$usersRoles = str_replace(' ', '', ','.$this->userRec['roles'].',');
			if ((strpos($usersRoles, ",$requiredRole,") !== false) || (strpos($usersRoles, ",admin,") !== false)){
				return true;
			}
		}
		return false;
	} // checkRole
	
	private function checkCredentials($credentials)
	{
		$requestingUser = $credentials['username'];
		if (!isset($this->users[$requestingUser])) {
			$this->message = '{{ Login failed }}';
			return;
		}
		$rec = $this->users[$requestingUser];
		$rec['name'] = $requestingUser;
		
		if (password_verify($credentials['password'], $rec['password'])) {
			$this->loginTimes[$requestingUser] = time();
			session_regenerate_id();
			$_SESSION['loginTimes'] = serialize($this->loginTimes);
			$_SESSION['user'] = $requestingUser;
			$this->userRec = $rec;
		} else {
			$this->message = '{{ Login failed }}';
		}
	} // checkCredentials
			
	private function getLoggedInUser()
	{
		$rec = false;
		$user = isset($_SESSION['user']) ? $_SESSION['user'] : false;
		if ($user) {
			$rec = (isset($this->users[$user])) ? $this->users[$user] : false;
			$rec['name'] = $user;
			$lastLogin = (isset($this->loginTimes[$user])) ? $this->loginTimes[$user] : 0;
			$validityPeriod = isset($this->users[$user]['validity-period']) ? $this->users[$user]['validity-period'] : 0;
			if ($lastLogin < (time() - $validityPeriod)) {
				$rec = false;
				$this->message = '{{ validity-period expired }}';
			}
		}
		$this->userRec = $rec;
    } // getLoggedInUser
    
	public function checkAdmission($lockProfile)
	{
		if ($lockProfile == false) {	// no restriction
			return true;
		}
		
		$rec = $this->userRec;
		$lockProfiles = explode(',', $lockProfile);
		foreach ($lockProfiles as $lp) {
			$lp = trim($lp);
			if ($lp == $rec['name']) {
				return true;
			} elseif (isset($rec['group']) && ($lp == $rec['group'])) {
				return true;
			}
		}
		if ($rec && !$this->message) {
			$this->message = '{{ Insufficient privileges }}';
		}
		return false;
	} // checkAdmission
	
    public function authForm($message)
    {	
		$message = $this->message;
		$url = '';
		$page = new Page;
        $str = <<<EOT

<div class='login_wrapper'>
<h2>{{ Login }}</h2>
<div class='login_message'>$message</div>
<form action="$url" method="POST">
<label for="fld_name" id="lbl_login_user"><span class='c1'>{{ Username }}:</span>
<input type="text" id="fld_username" name="login_user" required aria-required="true">
<output class='err_msg' aria-live="polite" aria-relevant="additions"></output>
</label>
<label for="fld_password" id="lbl_login_password"><span class='c1'>{{ Password }}:</span>
<input type="password" id="fld_password" name='login_password' required aria-required="true">
<output class='err_msg' aria-live="polite" aria-relevant="additions"></output>
</label>
<button name="btn_submit" value="submit" id="btn_submit">{{ Login }}</button>
</form>
<p>{{ Login comment }}</p>
</div>

EOT;
		$page->addOverride($str);
		
		$str = <<<EOT
	$('#fld_username').focus();
	$('#btn_submit').click(function(e) {
		e.preventDefault();
		var url = window.location.href;
		if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/) )) {
			alert('{{ Warning insecure connection }}');
			return;
			//if (!confirm('{{ Warning insecure connection }}')) {
			//	return;
			//}
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
		$page->addJQ($str);
        return $page;
    } // authForm
}
