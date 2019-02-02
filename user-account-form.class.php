<?php

define('MSG', 'lzy-account-form-message');
define('NOTI', 'lzy-account-form-notification');

$lizzyAccountCounter = 0;

class UserAccountForm
{
    public function __construct($lzy, $infoIcon = '&#9432;')
    {
        global $lizzyAccountCounter;
        $this->infoIcon = $infoIcon;
        if (!isset($GLOBALS['globalParams']['legacyBrowser']) || $GLOBALS['globalParams']['legacyBrowser']) {
            $this->infoIcon = '(i)';
        }
        if ($lzy) {
            $this->config = $lzy->config;
            $this->page = $lzy->page;
            if (isset($lzy->trans)) {      //??? hack -> needs to be cleaned up: invoked from diff places
                $this->trans = $lzy->trans;
            } else {
                $this->trans = $lzy;
            }
            $adminTransvars = resolvePath('~sys/config/admin.yaml');
            $this->trans->readTransvarsFromFile($adminTransvars);
            $this->checkInsecureConnection();
            $this->page->addModules('USER_ADMIN');
        } else {
            $this->config = null;
            $this->page = null;
            $this->trans = null;
        }
        $this->loggedInUser = (isset($_SESSION['lizzy']['user'])) ? $_SESSION['lizzy']['user'] : false;
        $this->inx = &$lizzyAccountCounter;
        $this->message = (isset($lzy->auth->message)) ? $lzy->auth->message : '';
        $this->warning = (isset($lzy->auth->warning)) ? $lzy->auth->warning : '';
    } // __construct



    public function renderLoginForm($notification = '', $message = '', $returnRaw = false)
    {
        $this->page = new Page;
        if ($this->config->admin_enableAccessLink) {
            $str = $this->createMultimodeLoginForm($notification, $message);
        } else {
            $str = $this->createPWAccessForm($notification, $message);
        }
        if ($returnRaw) {
            return $str;
        }

        $this->page->addOverride($str);
        $this->page->addModules('PANELS');
        $this->page->setOverrideMdCompile(false);

        return $this->page;
    } // authForm




    public function renderLoginUnPwForm($notification = '', $message = '')
    {
        $this->page = new Page;
        $str = $this->createPWAccessForm($notification, $message);

        $this->page->addOverride($str);

        return $this->page;
    } // authForm




    public function renderLoginAcessLinkForm($notification = '', $message = '')
    {
        $this->page = new Page;
        $str = $this->createLinkAccessForm($notification);

        $this->page->addOverride($str);

        return $this->page;
    } // authForm




    public function renderSignUpForm($group, $notification = '', $message = '')
    {
        setStaticVariable('self-signup-to-group', $group);
        if ($message) {
            $str = $this->createSignUpForm($notification, '');
            $str = str_replace('$$', $str, $message);

        } else {
            $str = "<h2>{{ lzy-self-sign-up }}</h2>";
            $this->page = new Page;
            $str .= $this->createSignUpForm($notification);
        }

        $this->page->addOverride($str);

        return $this->page;
    } // authForm




    public function renderAddUserForm($group, $notification = '', $message = '')
    {
        $str = "<h2>{{ lzy-add-user }}</h2>";
        $this->page = new Page;
        $str .= $this->createAddUserForm($notification, $message, $group);

        $this->page->addOverride($str);

        return $this->page;
    }




    public function renderAddUsersForm($group, $notification = '', $message = '')
    {
        $str = "<h2>{{ lzy-add-users-to-group }} \"$group\"</h2>";
        $this->page = new Page;
        $str .= $this->createAddUsersForm($notification, $message, $group);

        $this->page->addOverride($str);

        return $this->page;
    }




    public function renderChangePwForm($user, $notification = '', $message = '')
    {
        $str = "<h2>{{ lzy-change-password }}</h2>";
        $this->page = new Page;
        $str .= $this->createChangePwForm($user, $notification, $message);

        $this->page->addOverride($str);

        return $this->page;
    }




    public function renderOnetimeLinkEntryForm($user, $validUntilStr, $prefix)
    {
        $form = <<<EOT

    <div class='lzy-onetime-link-sent'>
    {{ $prefix sent }}

    <form class="lzy-onetime-code-entry" method="post">
        <label for="">{{ lzy-enter onetime code }}</label>
        <input type="hidden" value="$user" name="lzy-login-user" />
        <input id="lzy-onetime-code" type="text" name="lzy-onetime-code" style="text-transform:uppercase;width:6em;" />
        <input type="submit" class='lzy-button' value="{{ submit }}" />
    </form>

    <p> {{ $prefix sent2 }} $validUntilStr</p>
    <p> {{ $prefix sent3 }}</p>
    {{ lzy-sign-up further info }}
    </div>

EOT;
        return $form;
    }



    public function renderEditProfileForm($userRec, $notification = '', $message = '')
    {
        $user = isset($userRec['name']) ? $userRec['name'] : '';
        $email = isset($userRec['email']) ? $userRec['email'] : '';
        $form1 = $this->createChangePwForm($user, $notification, $message);
        $username = $this->createChangeUsernameForm($user, $notification, $message);
        $emailForm = $this->createChangeEmailForm($user, $notification, $message);
        $delete = $this->createDeleteProfileForm($user, $notification, $message);

        $html = <<<EOT
        <h2>{{ lzy-edit-profile }} &laquo;$user&raquo;</h2>
$message
        <div class="lzy-panels-widget lzy-tilted accordion one-open-only lzy-account-form lzy-login-multi-mode">
            <div>
            <h1>{{ lzy-change-password }}</h1>
      
            <h2>{{ lzy-change-password }}</h2>
$form1      
            
            </div><!-- /lzy-panel-page -->
            
            
            <div>
            <h1>{{ lzy-change-name }}</h1>
            
            <h2>{{ lzy-change-user-name }}</h2>
$username
            </div><!-- /lzy-panel-page -->
    
    
            <div>
            <h1>{{ lzy-change-e-mail }}</h1>
            
            <h2>{{ lzy-change-e-mail }} ($email)</h2>
$emailForm
            </div><!-- /lzy-panel-page -->
    
    
            <div>
            <h1>{{ lzy-delete-profile }}</h1>
            
            <h2>{{ lzy-delete-profile }}</h2>
$delete

            </div><!-- /lzy-panel-page -->
    
    </div><!-- / .lzy-panels-widget -->

EOT;

        $userAdmCss = '~/css/user_admin.css';
        if (!file_exists(resolvePath($userAdmCss))) {
            $userAdmCss = '';
        }
        $this->page->addModules("USER_ADMIN, $userAdmCss" );

        return $html;
    }





//-------------------------------------------------------------
    public function createMultimodeLoginForm($notification, $message = '')
    {
        global $globalParams;
        $message = $this->wrapTag(MSG, $message);

        $subject = '{{ lzy-login-problems }}';
        $body = '%0a%0a{{page}}:%20' . $globalParams['pageUrl'];
        $loginProblemMail = "{{ concat('lzy-forgot-password1', webmaster-email,'?subject=$subject&body=$body', 'lzy-forgot-password2') }}";

        $form1 = $this->createLinkAccessForm($notification);
        $form2 = $this->createPWAccessForm($notification);

        $html = <<<EOT
        <h1>{{ lzy-login-with-choice }}</h1>
$message
        <div class="lzy-panels-widget lzy-tilted one-open-only lzy-account-form lzy-login-multi-mode">
            <div><!-- lzy-panel-page -->
            <h2>{{ lzy-login-without-password }}</h2>
      
$form1      
            
            </div><!-- /lzy-panel-page -->
            
            <div><!-- lzy-panel-page -->
            <h2>{{ lzy-login-with-password }}</h2>
            
$form2

            </div><!-- /lzy-panel-page -->
    
    </div><!-- / .lzy-panels-widget -->

EOT;

        return $html;
    } // createMultimodeLoginForm






    private function createLinkAccessForm($notification, $message = '')
    {
        $message = $this->wrapTag(MSG, $message);
        $notification = $this->wrapTag(NOTI, $notification);
        $this->inx++;

        $email = $this->renderEMailInput('lzy-onetimelogin-request-', true);
        $submitButton = $this->createSubmitButton('lzy-onetime-link-');

        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-login-form lzy-login-by-email"  action="./" method="POST">
$notification
$email
$submitButton
                </form>
            </div><!-- /lzy-account-form-wrapper -->

EOT;

        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createLinkAccessForm




    private function createPWAccessForm($notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $usernameInput = $this->renderUsernameInput('lzy-login-');
        $passwordInput = $this->renderPasswordInput('lzy-login-password-', false);
        $submitButton = $this->createSubmitButton('lzy-login-');

        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-login-form"  action="./" method="POST">
$notification
$usernameInput
$passwordInput                    
$submitButton
                </form>
            </div><!-- /lzy-account-form-wrapper -->

EOT;

        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm




    private function createSignUpForm($notification, $message)
    {
        $message = $this->wrapTag(MSG, $message);
        $notification = $this->wrapTag(NOTI, $notification);
        $this->inx++;

        $email = $this->renderEMailInput('lzy-self-signup-email-', true, true);
        $submitButton = $this->createSubmitButton('lzy-self-signup-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-signup-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-signup" value="signup-email" />
$notification
$email
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    }



    private function createAddUserForm($notification, $message, $group)
    {
        $message = $this->wrapTag(MSG, $message);
        $notification = $this->wrapTag(NOTI, $notification);
        $this->inx++;

        $email = $this->renderEMailInput('lzy-add-user-', true,true);
        $username = $this->renderTextlineInput('lzy-add-user-', 'username');
        $this->inx++;
        $displayName = $this->renderTextlineInput('lzy-add-user-', 'displayname');
        $this->inx++;
        if ($group) {
            $groupField = $this->renderHiddenInput('lzy-add-user-group', $group);
            $groupField .= "<div class=''><span>{{ lzy-add-user-group-is }}:</span><span>$group</span></div>";
        } else {
            $groupField = $this->renderTextlineInput('lzy-add-user-', 'group');
        }
        $this->inx++;
        $emailList = $this->renderTextlineInput('lzy-add-user-', 'emaillist');
        $submitButton = $this->createSubmitButton('lzy-add-user-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-signup-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="add-user" />
$notification
$email
$username
$displayName
$groupField
$emailList
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm



    private function createAddUsersForm($notification, $message, $group)
    {
        $message = $this->wrapTag(MSG, $message);
        $notification = $this->wrapTag(NOTI, $notification);
        $this->inx++;

        $newUser = $this->renderTextareaInput('lzy-add-users-', 'email-list', false,'Name &lt;name@domain.net>');
        $submitButton = $this->createSubmitButton('lzy-add-users-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-signup-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="add-users" />
                    <input type="hidden" name="lzy-add-user-group" value="$group" />
$notification
$newUser
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm




    public function createChangePwForm($user, $notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $passwordInput = $this->renderPasswordInput('lzy-change-password-', true);
        $this->inx++;
        $passwordInput2 = $this->renderPasswordInput('lzy-change-password2-', true,true);
        $submitButton = $this->createCancelButton('lzy-change-password-');
        $submitButton .= $this->createSubmitButton('lzy-change-password-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-login-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="change-password" />
                    <input type="hidden" name="lzy-user" value="$user" />
$notification
$passwordInput  
$passwordInput2                  
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->page->addJQFiles('USER_ADMIN');
        $jq = "\t$('.lzy-change-password-cancel').click(function(){ lzyReload(); });\n";
        $this->page->addJq($jq);
        return $str;
    } // createPWAccessForm





    public function createChangeUsernameForm($user, $notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $username = $this->renderTextlineInput('lzy-change-user-', 'username');
        $this->inx++;
        $displayName = $this->renderTextlineInput('lzy-change-user-', 'displayname');
        $submitButton = $this->createCancelButton('lzy-change-username-');
        $submitButton .= $this->createSubmitButton('lzy-change-username-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-login-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="lzy-change-username" />
                    <input type="hidden" name="lzy-user" value="$user" />
$notification
$username  
$displayName                 
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm





    private function createChangeEmailForm($user, $notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $email = $this->renderEMailInput('lzy-change-user-email-', true,true);
        $submitButton = $this->createSubmitButton('lzy-change-user-email-');


        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <form class="lzy-login-form"  action="./" method="POST">
                    <input type="hidden" name="lzy-user-admin" value="lzy-change-email" />
                    <input type="hidden" name="lzy-user" value="$user" />
$notification
$email                 
$submitButton
                </form>
            </div><!-- /account-form -->

EOT;

        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm



    private function createDeleteProfileForm($user, $notification, $message = '')
    {
        $notification = $this->wrapTag(NOTI, $notification);
        $message = $this->wrapTag(MSG, $message);
        $this->inx++;

        $str = <<<EOT

            <div class="lzy-account-form-wrapper">
$message
                <div>{{ lzy-delete-profile-text }}</div>
                <button class="lzy-button lzy-login-form-button lzy-delete-profile-request-button">{{ lzy-delete-profile-request-button }}</button>

            </div><!-- /account-form -->

EOT;

        $this->page->addPopup([
            'type' => 'confirm',
            'text' => '{{ lzy-delete-profile-confirm-prompt }}',
            'triggerSource' => '.lzy-delete-profile-request-button',
            'onConfirm' => 'lzyReload("?lzy-user-admin=lzy-delete-account");',
            'onCancel' => 'lzyReload();',
        ]);
        $this->page->addJQFiles('USER_ADMIN');
        return $str;
    } // createPWAccessForm






    private function createSubmitButton($prefix)
    {
        return <<<EOT
                    <button id="lzy-login-submit-button{$this->inx}" class="lzy-admin-submit-button {$prefix}submit-button lzy-button" name="btn_submit" value="submit">{{ {$prefix}send }}</button>

EOT;
    }




    private function createCancelButton($prefix)
    {
        return <<<EOT
                    <button id="lzy-login-cancel-button{$this->inx}" class="lzy-admin-cancel-button lzy-button" onclick="lzyReload(); return false;">{{ lzy-admin-cancel-button }}</button>

EOT;
    }




    private function renderPasswordInput($prefix, $required = true, $hideShowPwIcon = false)
    {
        $i = '';
        if (preg_match('/(\d+)/', $prefix, $m)) {
            $i = $m[1];
        }
        $required = ($required) ? ' required aria-required="true"': '';
        $showPwIcon = (!$hideShowPwIcon) ? '<div class="lzy-form-show-password"><a href="#" aria-label="{{ lzy-login-show-password }}"><img src="~sys/rsc/show.png" class="login-form-icon" alt="" title="{{ lzy-login-show-password }}" /></a></div>': '';

        return <<<EOT
                    <label for="lzy-login-password{$this->inx}" class="lzy-form-password-label">
                        <span>{{ {$prefix}prompt }}:</span>
                        <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                        <span class="lzy-admin-info lzy-{$prefix}info" style="display: none">{{ {$prefix}info }}</span>
$showPwIcon
                        <input type="password" id="lzy-login-password{$this->inx}" class="lzy-form-password" name='{$prefix}password$i'$required />
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </label>

EOT;
    }




    private function renderEMailInput($prefix, $required = false, $forceEmailType = false)
    {
        $required = ($required) ? " required aria-required='true'" : '';
        $type = ($forceEmailType) ? 'email': 'text';
        $js = ($forceEmailType) ? ' onkeyup="this.setAttribute(\'value\', this.value);"': '';

        return <<<EOT
                    <label for="lzy-login-email{$this->inx}" class="lzy-form-email-label">
                        <span>{{ {$prefix}prompt }}:</span>
                        <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                        <span class="lzy-admin-info" style="display: none">{{ {$prefix}info-text }}</span>
                    </label>
                    <input type="$type" id="lzy-login-email{$this->inx}"  class="lzy-login-email" name="{$prefix}email"$required placeholder="name@domain.net"$js>
                    <output class='lzy-error-message' aria-live="polite" aria-relevant="additions" style="display: none;">{{ lzy-error-email-required }}</output>
EOT;
    }




    private function renderUsernameInput($prefix)
    {
        return <<<EOT
                   <label for="lzy-login-username{$this->inx}" class="lzy-form-username-label">
                        <span>{{ {$prefix}username-prompt }}:</span>
                        <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}username-info-title }}" aria-label="{{ {$prefix}username-info-title }}">{$this->infoIcon}</a> 
                        <span class="lzy-admin-info" style="display: none">{{ {$prefix}username-info-text }}</span>
                        <input type="text" id="lzy-login-username{$this->inx}" class="lzy-login-username" name="{$prefix}username" required aria-required="true">
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </label>

EOT;
    }


    private function renderHiddenInput($fieldName, $value)
    {
        return "\t\t\t<input type='hidden' name='$fieldName' value='$value' />\n";
    }



    private function renderTextlineInput($prefix, $fieldName, $required = false, $placeholder = '')
    {
        $required = ($required) ? " required aria-required='true'" : '';
        $placeholder = ($placeholder) ? " placeholder='$placeholder'" : '';
        return <<<EOT
                   <label for="lzy-login-textinput{$this->inx}" class="lzy-form-textinput-label">
                        <span>{{ {$prefix}{$fieldName}-prompt }}:</span>
                        <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                        <span class="lzy-admin-info" style="display: none">{{ {$prefix}{$fieldName}-login-info }}</span>
                        <input type="text" id="lzy-login-textinput{$this->inx}" class="lzy-login-textinput" name="{$prefix}$fieldName"$required$placeholder>
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </label>

EOT;
    }


    private function renderTextareaInput($prefix, $fieldName, $required = false, $placeholder = '')
    {
        $required = ($required) ? " required aria-required='true'" : '';
        $placeholder = ($placeholder) ? " placeholder='$placeholder'" : '';
        return <<<EOT
                   <label for="lzy-textarea{$this->inx}" class="lzy-textarea-label">
                        <span>{{ {$prefix}prompt }}:</span>
                        <a href="#" class="lzy-admin-show-info" title="{{ {$prefix}info-title }}" aria-label="{{ {$prefix}info-title }}">{$this->infoIcon}</a> 
                        <span class="lzy-admin-info lzy-{$prefix}info" style="display: none">{{ {$prefix}info }}</span>
                        
                        <textarea id="lzy-textarea{$this->inx}" class="lzy-textarea" name="{$prefix}$fieldName"$required$placeholder></textarea>
                        
                        <output class='lzy-error-message' aria-live="polite" aria-relevant="additions"></output>
                    </label>

EOT;
    }




    public function renderLoginLink()
    {
        $linkToThisPage = $GLOBALS['globalParams']['pageUrl'];
        if ($this->loggedInUser) {
            $logInVar = $this->renderLoginAccountMenu();
            $this->page->addPopup(['contentFrom' => '.lzy-login-menu', 'triggerSource' => '.lzy-login-link-menu > a']);

        } else {
            if ($this->config->isLocalhost) {
                if ($this->config->admin_autoAdminOnLocalhost) {
                    $loggedInUser = 'Localhost-Admin';
                } else {
                    $loggedInUser = '{{ LoginLink }}';
                }
            } else {
                $loggedInUser = '{{ LoginLink }}';
            }
            $logInVar = <<<EOT
<div><a href='$linkToThisPage?login' class='lzy-login-link' title="$loggedInUser">{{ user_icon }}</a></div>

EOT;
        }
        return $logInVar;
    }




    /**
     * @param $username
     * @return string
     */
    private function renderLoginAccountMenu()
    {
        $pageUrl = $GLOBALS['globalParams']['pageUrl'];
        $username = $this->getUsername();

        $option = '';
        if ($this->config->admin_userAllowSelfAdmin) {
            $option = "\t\t\t<li><a href='$pageUrl?admin=edit-profile'>{{ Your Profile }}</a></li>\n";
        }

        $logInVar = <<<EOT
<div class="lzy-login-link-menu"> <a href="#" title="{{ Logged in as }} $username">{{ user_icon }}</a>
    <div class="lzy-login-menu" style="display:none;">
        <div>{{ User account }} <strong>$username</strong></div>
        <ol>
            <li><a href='$pageUrl?logout'>{{ Logout }}</a></li>$option
        </ol>
    </div>
</div>

EOT;
        return $logInVar;
    }




    /**
     * @return bool
     */
    public function getUsername()
    {
        if (isset($_SESSION['lizzy']['userDisplayName'])) {
            $username = $_SESSION['lizzy']['userDisplayName'];
        } else {
            $username = $this->loggedInUser;
        }
        return $username;
    }




    //....................................................
    private function checkInsecureConnection()
    {
        global $globalParams;
        if ($this->config->debug_suppressInsecureConnectWarning) {
            return true;
        }
        if (!$this->config->isLocalhost && !(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == 'on')) {
            $url = str_replace('http://', 'https://', $globalParams['pageUrl']);
            $this->page->addMessage("{{ Warning insecure connection }}<br />{{ Please switch to }}: <a href='$url'>$url</a>");
            return false;
        }
        return true;
    } // checkInsecureConnection




    private function wrapTag($className, $str)
    {
        if (($className == MSG) && isset($GLOBALS['globalParams']['auth-message'])) {
            $str .= ' '.$GLOBALS['globalParams']['auth-message'];
        }
        if ($str) {
            $str = "\t\t\t<div class='$className'>$str</div>\n";
        }
        return $str;
    }

}
