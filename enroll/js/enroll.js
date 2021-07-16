
//  Enrollment jQuery Scripts --------------------------------
var request_uri = location.pathname + location.search;



function checkInput( $form ) {
    var formType = $('[name=lzy-enroll-type]', $form).val();
    // var formType = $('.lzy-enroll-type', $form).val();
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
    let $this = $( this );
    let $thisList = $this.closest('.lzy-enrollment-list');
    let listId = $thisList.attr('data-dialog-id');
    let formId = '#' + $thisList.attr('data-dialog-id');
    // let dataRef = $thisList.attr('data-source');
    // let id = $this.attr('href');
    let $row = $this.closest('.lzy-enroll-row');
    // let $id = $( id );
    let $formWrapper = $( formId );

    // reveal dialog:
    lzyPopup({
        contentRef: formId,
        // contentRef: '#lzy-enroll-add-dialog',
        header: $(formId + ' .lzy-enroll-title').text(),
        // header: $('#lzy-enroll-add-dialog .lzy-enroll-title').text(),
        draggable: true,
        closeOnBgClick: false,
    });

    // copy data-source-ref to form:
    let dataRef = $thisList.attr('data-datasrc-ref');
    // let dataRef = $thisList.data('datasrc-ref');
    $('[name=_lizzy-data-ref]', $formWrapper).val( dataRef );

    // copy rec-key to form:
    let recKey = $row.data('rec-key');
    if (typeof recKey !== 'undefined') {
        $('[name=_rec-key]', $formWrapper).val( recKey );
    }

    // lzy-form-wrapper -> mode

    // insert list name into dialog hidden field:
    // $('.lzy-enroll-list-id', $formWrapper).val( listId );
    // $('.lizzy-data-ref', $formWrapper).val( dataRef );

    let extendedMode = $thisList.hasClass('lzy-enroll-auxfields');
    let $popupTitle = $('.lzy-popup-header > div', $formWrapper );

    // preset name in delete and modify forms:
    let $addFieldWrapper = $(this).closest('.lzy-enroll-add-field');
    if ( $addFieldWrapper.length ) {
        let addTitle = $('.lzy-enroll-add-title', $formWrapper).text();
        $popupTitle.text( addTitle );

        $formWrapper.removeClass('lzy-enroll-delete-entry lzy-enroll-modify-entry').addClass('lzy-enroll-add-entry');

        $('.lzy-enroll-name', $formWrapper).val('').prop('disabled', false);
        $('.lzy-form-field-wrapper input', $formWrapper).each(function () {
            $(this).val('');
        });

    } else {
        let addTitle = null;
        if (extendedMode) {
            addTitle = $('.lzy-enroll-modify-title', $formWrapper).text();
            $formWrapper.removeClass('lzy-enroll-delete-entry lzy-enroll-add-entry').addClass('lzy-enroll-modify-entry');
        } else {
            addTitle = $('.lzy-enroll-delete-title', $formWrapper).text();
            $formWrapper.removeClass('lzy-enroll-add-entry lzy-enroll-modify-entry').addClass('lzy-enroll-delete-entry');
        }
        $popupTitle.text( addTitle );

        // $formWrapper.addClass('lzy-enroll-modify-entry');
        // let name = $('.lzy-name', $this).text();
        // $('.lzy-enroll-name-text', $id).text(name);
        // $('.lzy-enroll-name', $id).val(name);
        // let $row = $this.closest('.lzy-enroll-row');
        let name = $('.lzy-enroll-name', $row).text();
//let xx = $('.lzy-enroll-name', $formWrapper);
//         $('.lzy-enroll-name', $formWrapper).val( name );
        // $('.lzy-enroll-name', $formWrapper).val( name ).prop('disabled', true);
        $('.lzy-enroll-name', $formWrapper).val( name ).prop('readonly', true);

        // preset custom fields in dialog:
        let col = 3;
        $('.lzy-enroll-aux-field', $this.closest('.lzy-enroll-row')).each(function () {
            var $this = $( this );
            var value = $this.text().trim();
            $('.lzy-col' + col++, $formWrapper).val( value );
            // var dialogField = $this.attr('data-class');
            // $('.' + dialogField, $formWrapper).val( value );
        });
    }
}); // open dialog



// close dialog:
$('[type=reset], .lzy-enroll-dialog-close').click(function (e) {
    e.preventDefault();
    // e.stopPropagation();
    e.stopImmediatePropagation();
    lzyPopupClose(this);
    // var $currDialog = $(this).closest('.lzy-enrollment-dialog');
    // $currDialog.addClass('lzy-enroll-hide-dialog');
    // $currDialog.parent().addClass('lzy-enroll-hide-dialog');
});



// handle submit button in add dialog:
// $('[type=submit]').click(function(e) {
//     var $form = $( this ).closest('form');
//
//     if ($( this ).hasClass('lzy-enroll-delete-entry')) {
//         $('.lzy-enroll-type', $form).val('delete');
//     }
//     if (checkInput( $form )) {
//         $( this ).prop('disabled',true);
//         $( $form ).submit();
//     } else {
//         e.preventDefault();
//     }
// });





