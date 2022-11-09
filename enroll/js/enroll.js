//  Enrollment jQuery --------------------------------


var request_uri = location.pathname + location.search;



function checkInput( $form ) {
    var formType = $('[name=lzy-enroll-type]', $form).val();
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
    let dialogRef = $thisList.attr('data-dialog-ref');
    let formId = '#lzy-enroll-form-' + dialogRef;
    let $dialogWrapper = $( formId ).closest('.lzy-enroll-dialog');
    let $row = $this.closest('.lzy-enroll-row');

    // reveal dialog:
    lzyPopup({
        contentRef: '#lzy-enroll-dialog-' + dialogRef,
        header: $(formId + ' .lzy-enroll-title').text(),
        draggable: true,
        closeOnBgClick: false,
    });

    // copy data-source-ref to form:
    let dataRef = $thisList.attr('data-datasrc-ref');
    $('[name=_lizzy-data-ref]', $dialogWrapper).val( dataRef );

    // copy rec-key to form:
    let recKey = $row.data('rec-key');
    if (typeof recKey === 'undefined') {
        recKey = '';
    }
    $('[name=_rec-key]', $dialogWrapper).val( recKey );


    let extendedMode = $thisList.hasClass('lzy-enroll-auxfields');
    let $popupTitle = $('.lzy-popup-header > div', $dialogWrapper );

    // preset name in delete and modify forms:
    let addMode = $(this).closest('.lzy-enroll-add-field').length;
    if ( addMode ) {
        let addTitle = $('.lzy-enroll-add-title', $dialogWrapper).text();
        $popupTitle.text( addTitle );

        $dialogWrapper.removeClass('lzy-enroll-delete-entry lzy-enroll-modify-entry').addClass('lzy-enroll-add-entry');

        $('.lzy-enroll-name', $dialogWrapper).val('').prop('readonly', false);

        // clear form fields skipping hidden system fields, e.g. _rec-key:
        $('.lzy-form-field-wrapper input', $dialogWrapper).each(function () {
            let name = $(this).attr('name');
            if ((typeof name !== 'undefined') && (name.charAt(0) !== '_')) {
                $(this).val('');
            }
        });
        $('.lzy-enroll-comment', $dialogWrapper).hide();
        $('.lzy-enroll-add-comment', $dialogWrapper).show();


    // delete/modify mode:
    } else {
        let addTitle = null;
        if (extendedMode) {
            addTitle = $('.lzy-enroll-modify-title', $dialogWrapper).text();
            $dialogWrapper.removeClass('lzy-enroll-delete-entry lzy-enroll-add-entry').addClass('lzy-enroll-modify-entry');
            $('.lzy-enroll-comment', $dialogWrapper).hide();
            $('.lzy-enroll-modify-comment', $dialogWrapper).show();

        } else {
            addTitle = $('.lzy-enroll-delete-title', $dialogWrapper).text();
            $dialogWrapper.removeClass('lzy-enroll-add-entry lzy-enroll-modify-entry').addClass('lzy-enroll-delete-entry');
            $('.lzy-enroll-comment', $dialogWrapper).hide();
            $('.lzy-enroll-delete-comment', $dialogWrapper).show();
        }
        $popupTitle.text( addTitle );

        let name = $('.lzy-enroll-name', $row).text();
        $('.lzy-enroll-name', $dialogWrapper).val( name ).prop('readonly', true);

        // preset custom fields in dialog:
        let col = 3;
        $('.lzy-enroll-aux-field', $this.closest('.lzy-enroll-row')).each(function () {
            var $this = $( this );
            var value = $this.text().trim();
            $('.lzy-col' + col++, $dialogWrapper).val( value );
        });
    }
}); // open dialog



// close dialog:
$('[type=reset], .lzy-enroll-dialog-close').click(function (e) {
    e.preventDefault();
    e.stopImmediatePropagation();
    lzyPopupClose(this);
});





