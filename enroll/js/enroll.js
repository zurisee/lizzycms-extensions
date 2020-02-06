
//  Enrollment jQuery Scripts --------------------------------
var request_uri = location.pathname + location.search;



function checkInput( $form ) {
    var formType = $('.lzy-enroll-type', $form).val();
    var name = $('.lzy-enroll-name', $form).val();

    // check name field not empty: (only for add dialog)
    if (!name && (formType === 'add')) {
        $('.lzy-enroll-name', $form).parent().before( '<div class="lzy-enroll-errmsg">' + $('.lzy-enroll-name-required').html() + '</div>');
        $('.lzy-enroll-name', $form).focus();
        return false;
    }

    // check email field not empty and valid format:
    var email = $('.lzy-enroll-email', $form).val();
    if (!email) {
        $('.lzy-enroll-email', $form).parent().before( '<div class="lzy-enroll-errmsg">' + $('.lzy-enroll-email-required').html() + '</div>');
        $('.lzy-enroll-email', $form).focus();
        return false;
    } else if (!isValidEmail( email )) {
        $('.lzy-enroll-email', $form).parent().before( '<div class="lzy-enroll-errmsg">' + $('.lzy-enroll-email-invalid').html() + '</div>');
        $('.lzy-enroll-email', $form).focus();
        return false;
    }

    return true;
} // checkAddInput





// open dialog:
$('.lzy-enrollment-list a').click(function(e) {
    e.preventDefault();
    var $this = $( this );
    var listId = $(this).closest('.lzy-enrollment-list').attr('data-dialog-id');
    var id = $this.attr('href');
    var $id = $( id );

    // reveal dialog:
    $id.removeClass('lzy-enroll-hide-dialog');
    $id.parent().removeClass('lzy-enroll-hide-dialog');

    // insert list name into dialog hidden field:
    $('.lzy-enroll-list-id', $id).val( listId );

    // preset name in delete and modify forms:
    if (id.match(/delDialog/) || id.match(/modifyDialog/)) {
        var name = $('.lzy-name', $this).text();
        $('.lzy-enroll-name-text', $id).text(name);
        $('.lzy-enroll-name', $id).val(name);
    }

    // preset custom fields in dialog:
    $('.lzy-enroll-aux-field', $this.closest('.lzy-enroll-row')).each(function () {
        var $this = $( this );
        var value = $this.text().trim();
        var dialogField = $this.attr('data-class');
        $('.' + dialogField, $id).val( value );
    });
});



// close dialog:
$('[type=reset], .lzy-enroll-dialog-close').click(function (e) {
    e.preventDefault();
    var $currDialog = $(this).closest('.lzy-enrollment-dialog');
    $currDialog.addClass('lzy-enroll-hide-dialog');
    $currDialog.parent().addClass('lzy-enroll-hide-dialog');
});



// handle submit button in add dialog:
$('[type=submit]').click(function(e) {
    var $form = $( this ).closest('form');

    if ($( this ).hasClass('lzy-enroll-delete-entry')) {
        $('.lzy-enroll-type', $form).val('delete');
    }
    if (checkInput( $form )) {
        $( this ).prop('disabled',true);
        $( $form ).submit();
    } else {
        e.preventDefault();
    }
});





