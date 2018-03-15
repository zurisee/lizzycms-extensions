<?php

// @info: Renders various forms for user administration, e.g. login, change-password etc..

$macroName = basename(__FILE__, '.php');

$page->addJqFiles('USER_ADMIN');
$page->addCssFiles('USER_ADMIN_CSS');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $mode = $this->getArg($macroName, 'mode', '', 'login');
    $group = $this->getArg($macroName, 'group', '', '');

    $loginUser = getStaticVariable('user');

    $accountForm = new UserAccountForm($this);

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
            if ($loginUser) {
                return "<div class='lzy-admin-task-response'>{{ lzy-one-time-access-code-success }}</div>";
            }
            $group = ($group) ? $group : $this->config->admin_defaultGuestGroup;
            setStaticVariable('self-signup-to-group', $group);
            $pg = $accountForm->renderSignUpForm($group);
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
//
//    $this->page->merge($pg);

    return $form;

    /*
        $auth = new UserAccountForm($this);
        $notification = getStaticVariable('lastLoginMsg');
        if (!$_SESSION['lizzy']['user']) {

            if ($mode == 'login') {
                $pg = $auth->renderLoginForm($notification);

            } elseif ($mode == 'self-signup') {
                $pg = $auth->renderSignUpForm($notification);

            }

            // only logged-in users permitted beyond this point
    //        if (!(isset($GLOBALS['globalParams']['user']) && !$GLOBALS['globalParams']['user']) && !$GLOBALS['globalParams']['isAdmin']) {
    //        if ((isset($GLOBALS['globalParams']['user']) && $GLOBALS['globalParams']['user'])) {
    //            if ($mode == 'change-password') {
    //                $pg = $auth->renderChangePwForm($GLOBALS['globalParams']['user'], $group, $notification);
    //                return '';
    //            }
    //
    //        } elseif (!$GLOBALS['globalParams']['isAdmin']) {
    //            return '';
    //        }



            if (!$GLOBALS['globalParams']['isAdmin']) {
                return '';
            }

            // only Admins permitted beyond this point
            if ($mode == 'add-users') {
                $pg = $auth->renderAddUsersForm($group, $notification);

            } elseif ($mode == 'add-user') {
                $pg = $auth->renderAddUserForm($group, $notification);

            } elseif ($mode == 'change-password') {
                $str = "<div class='lzy-adduser-wrapper'>{{ change-password-not-logged-in-response }}</div>";
                return $str;

            } else {
                return "Error: mode unknown '$mode'";
            }
            $loginForm = $pg->get('override', true);
            $this->page->merge($pg);

        } else {
            if ($mode == 'change-password') {
    //            $pg = $auth->renderChangePwForm($_SESSION['lizzy']['user'], $notification, '{{ change-password-requirements }}');
                $pg = $auth->renderChangePwForm($_SESSION['lizzy']['user'], $notification);
            } else {
                return '';
            }
            $loginForm = $pg->get('override', true);
            $this->page->merge($pg);

    //        $loginForm = <<<EOT
    //    <div class="lzy-logged-in-confirmation">{{ You are logged in as }} {$_SESSION['lizzy']['user']}</div>
    //EOT;
        }

    */
    $loginForm = '';
	return $loginForm;
});
