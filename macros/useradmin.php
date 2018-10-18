<?php

// @info: Renders various forms for user administration, e.g. login, change-password etc..

$this->readTransvarsFromFile('~sys/config/useradmin.yaml');

$macroName = basename(__FILE__, '.php');

$page->addJqFiles('USER_ADMIN');
$page->addCssFiles('USER_ADMIN_CSS');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    
    $mode = $this->getArg($macroName, 'mode', '[login,login-by-credentials,login-by-emai,self-signupl,change-password,add-users,add-user] Defines what to do', 'login');
    $msgBefore = $this->getArg($macroName, 'commentBefore', 'Text that will be shown BEFORE the form (unless already logged in)', '');
    $msgAfter = $this->getArg($macroName, 'commentAfter', 'Text that will be shown AFTER the form (unless already logged in)', '');
    $loggedInMessage = $this->getArg($macroName, 'loggedInMessage', 'Text that will be shown if logged in', 'default');
    $group = $this->getArg($macroName, 'group', '(Optional) Name of a user group', 'guest');

    $notLoggedInMessage = "$msgBefore $$ $msgAfter";

    $loginUser = getStaticVariable('user');

    $accountForm = new UserAccountForm($this);
    $this->optionAddNoComment = true;

    switch ($mode) {
        case 'login':
            if ($loginUser) {
                return "<div class='lzy-admin-task-response'>{{ lzy-one-time-access-code-success }}</div>";
            }
            $pg = $accountForm->renderLoginForm();
            break;

        case 'login-by-credentials':
            if ($loginUser) {
                return "<div class='lzy-admin-task-response'>{{ lzy-one-time-access-code-success }}</div>";
            }
            $pg = $accountForm->renderLoginUnPwForm();
            break;

        case 'login-by-email':
            if ($loginUser) {
                return "<div class='lzy-admin-task-response'>{{ lzy-one-time-access-code-success }}</div>";
            }
            $pg = $accountForm->renderLoginAcessLinkForm();
            break;

        case 'self-signup':
            if ($loginUser) {   // already logged in, don't show form again:
                if ($loggedInMessage != 'default') {
                    return $loggedInMessage ? "<div class='lzy-admin-task-response'>$loggedInMessage</div>" : '';
                } else {
                    return "<div class='lzy-admin-task-response'>{{ lzy-one-time-access-code-success }}</div>";
                }
            }

            // render form for self-signup:
            $group = ($group) ? $group : $this->config->admin_defaultGuestGroup;
            setStaticVariable('self-signup-to-group', $group);
            $pg = $accountForm->renderSignUpForm($group, '', $notLoggedInMessage);
            break;

        case 'change-password':
            if ($loginUser) {
                $pg = $accountForm->renderChangePwForm($loginUser);
            } else {
                return "<div class='lzy-admin-task-response'>{{ lzy-change-password-need-to-be-logged-in }}</div>";
            }
            break;

        case 'add-users':
            if (getStaticVariable('isAdmin')) {
                $group = $group ? $group: 'guest';
                $pg = $accountForm->renderAddUsersForm($group);
            } else {
                return "<div class='lzy-admin-task-response'>{{ lzy-add-users-need-to-be-logged-in-as-admin }}</div>";
            }
            break;

        case 'add-user':
            if (getStaticVariable('isAdmin')) {
                $pg = $accountForm->renderAddUserForm($group);
            } else {
                return "<div class='lzy-admin-task-response'>{{ lzy-add-user-need-to-be-logged-in-as-admin }}</div>";
            }
            break;

        default:
            return "Error: unknown mode '$mode'";
    }

    $form = $pg->get('override', true);

    return $form;
});
