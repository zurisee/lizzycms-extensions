
//  Reservation jQuery Scripts --------------------------------


$( document ).ready(function() {
    var timeout = parseInt( $('[name=_lzy-reservation-timeout]').val() );
    setTimeout(function () {
        lzyPopup({
            contentRef: '#lzy-reservation-timed-out-msg',
            trigger: true,
            closeOnBgClick: false,
        });
    }, timeout * 1000);
    $('#lzy-reservation-timed-out-btn').click(function () {
        $('.lizzy_next').val('_ignore_');
        $('.lzy-reservation-form').submit();
    });
});



// handle submit button in add dialog:
$('[type=submit]').click(function(e) {
    var $form = $( this ).closest('form');

    if (checkInput( $form )) {
        $( this ).prop('disabled',true);
        $( $form ).submit();
    } else {
        e.preventDefault();
    }
});


$('[type=reset]').click(function(e) {
    var $form = $( this ).closest('form');
    $('[name=lizzy_next', $form).val('_delete_');
    $( $form ).submit();
});





