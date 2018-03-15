/*
**  Lizzy Account Module
 */


$( document ).ready(function() {
    init();
    setGenericEventHandlers();
    setupOnetimeLogin();

}); // ready

function init() {
    $('.lzy-login-username').focus();
    $('.lzy-signup-username').focus();

    $('#lzy-panel-id101').addClass('lzy-panel-page-open');  // pre-open first accordion panel
}

function setGenericEventHandlers() {

    $('.lzy-form-show-password a').click(function(e) {
        e.preventDefault();
        if ($('.lzy-form-password').attr('type') == 'text') {
            $('.lzy-form-password').attr('type', 'password');
            $('.login-form-icon').attr('src', systemPath+'rsc/show.png');
        } else {
            $('.lzy-form-password').attr('type', 'text');
            $('.login-form-icon').attr('src', systemPath+'rsc/hide.png');
        }
    });

    $('.lzy-admin-show-info').click(function(e) {
        e.preventDefault();
        $('.lzy-admin-info', $(this).parent()).toggle();
    });
}


function setupOnetimeLogin() {
    // $('.lzy-show-onetimelogin-info').click(function(e) {
    //     e.preventDefault();
    //     $('.lzy-onetimelogin-info').toggle();
    // });


}
    $('.lzy-show-un-pw-login-info').click(function(e) {
        e.preventDefault();
        $('.lzy-un-pw-login-info').toggle();
        // $('.lzy-pw-login-info').toggle();
    });

    $('.lzy-show-password-login-info').click(function(e) {
        e.preventDefault();
        $show = $('.lzy-password-login-info');
        if ($show.css('display') == 'block') {
            $show.css('display', 'none');
        } else {
            $show.css('display', 'block');
        }
        // $('.lzy-password-info').toggle();
    });

    /* SignUp Form: */
    $('.lzy-show-signup-login-info').click(function(e) {
        e.preventDefault();
        $('.lzy-signup-login-info').toggle();
    });

    $('.lzy-show-signup-password-info').click(function(e) {
        e.preventDefault();
        $('.lzy-signup-password-info').toggle();
    });

    $('.lzy-show-signup-password-again-info').click(function(e) {
        e.preventDefault();
        $('.lzy-signup-password-again-info').toggle();
    });

    $('.lzy-show-signup-info').click(function(e) {
        e.preventDefault();
        $('.lzy-signup-info').toggle();
    });
    $('.lzy-show-add-user-login-info').click(function(e) {
        e.preventDefault();
        $('.lzy-add-user-info').toggle();
    });




    /*
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
            $('#lzy-lbl-login-user .lzy-err-msg').text('{{ email required for one-time-access-link }}');
            $('#fld_username').focus();
        }
    });
    */

    $('.lzy-admin-submit-button').click(function(e) {
        e.preventDefault();
        $(this).prop('disabled', true);
        var url = window.location.href;
        if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/))) {
            alert('{{ Warning insecure connection }}');
            return;
        }
        var $form = $(this).closest('form');
        $form.attr('action', url);
        var which = $(this).attr('class').replace('lzy-admin-submit-button', '').replace('lzy-button', '').trim();
        switch (which) {
            case 'lzy-signup-submit-button':
                break;

            case 'lzy-add-user-submit-button':
                break;

            case 'lzy-login-submit-button':
                break;

        }
        $form.submit();

    });

/*
    $('.lzy-signup-submit-button').click(function(e) {
        e.preventDefault();
        $( this ).prop('disabled', true);
        var url = window.location.href;
        if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/) )) {
            alert('{{ Warning insecure connection }}');
            return;
        }
        var $form = $( this ).closest('form');
        $form.attr('action', url);

        $(".lzy-login-form output").text('');
        // $("#lzy-lbl-login-user output").text('');
        $(".lzy-form-password output").text('');
        var email = $('.lzy-login-email').val();
        if (!email) {
            $(".lzy-error-message").text('{{ Err empty email }}');
            $( this ).prop('disabled', false);
            return;
        }
        if (false && (location.protocol != 'https:')) {
            alert('No HTTPS Connection!');
            return;
        }
        $form.submit();
    });


    $('.lzy-add-user-submit-button').click(function(e) {
        e.preventDefault();
        $( this ).prop('disabled', true);
        var url = window.location.href;
        if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/) )) {
            alert('{{ Warning insecure connection }}');
            return;
        }
        var $form = $( this ).closest('form');
        $form.attr('action', url);

        $(".lzy-login-form output").text('');
        // $("#lzy-lbl-login-user output").text('');
        $(".lzy-form-password output").text('');
        var email = $('.lzy-textarea').val();
        if (!email) {
            $(".lzy-error-message").text('{{ Err empty emails }}');
            $( this ).prop('disabled', false);
            return;
        }
        if (false && (location.protocol != 'https:')) {
            alert('No HTTPS Connection!');
            return;
        }
        $form.submit();
    });



    // $('#btn_lzy-login-submit2').click(function(e) {
    $('.lzy-login-submit-button').click(function(e) {
        e.preventDefault();
        $( this ).prop('disabled', true);
        var url = window.location.href;
        if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/) )) {
            $( this ).prop('disabled', false);
            alert('{{ Warning insecure connection }}');
            return;
        }
        var $form = $( this ).closest('form');
        $form.attr('action', url);
        // $('.lzy-login-form form').attr('action', url);
        // $(".lzy-login-form output").text('');
        // $(".lzy-form-password output").text('');

        if ($( this ).closest('.lzy-login-by-email').length) {
            var em = $('.lzy-login-email').val();
            if (!em) {
                $(this).prop('disabled', false);
                $('.lzy-error-message').show();
                return;
            }

        } else {
            var un = $('.lzy-login-username').val();
            var pw = $('.lzy-form-password').val();
            if (!un) {
                $(this).prop('disabled', false);
                return;
            }
            if (!pw) {
                $('.lzy-form-password').focus();
                $(this).prop('disabled', false);
                return;
            }
            if (false && (location.protocol != 'https:')) {
                alert('No HTTPS Connection!');
                return;
            }
        }
        $form.submit();

    });

*/
    $('.lzy-login-tab-label1').click(function() {
        setTimeout(function(){ $('#login_email').focus(); }, 20);
    });


    $('.lzy-login-tab-label2').click(function() {
        setTimeout(function(){ $('#fld_username').focus(); }, 20);
    });


    // === login simple-mode
    $('.lzy-login-simple-mode #fld_username').focus();

    $('.lzy-login-simple-mode #btn_lzy-login-submit').click(function(e) {
        e.preventDefault();
        var url = window.location.href;
        if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/) )) {
            alert('{{ Warning insecure connection }}');
            return;
        }
        $('.login_wrapper form').attr('action', url);
        $("#lzy-lbl-login-user output").text('');
        $("#lbl_login_password output").text('');
        var un = $('#fld_username').val();
        var pw = $('#fld_password').val();
        if (!un) {
            $("#lzy-lbl-login-user output").text('{{ Err empty username }}');
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
        $('.lzy-login-simple-mode form').submit();
    });


