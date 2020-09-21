
//  Reservation jQuery Scripts --------------------------------


$( document ).ready(function() {
    if (!$('.lzy-reservation-timeout').length) {
        return;
    }
    const timeout = parseInt( $('.lzy-reservation-timeout').val() );
    setTimeout(function () {
        lzyPopup({
            contentRef: '#lzy-reservation-timed-out-msg',
            trigger: true,
            closeOnBgClick: false,
        });
    }, timeout * 1000);
    $('#lzy-reservation-timed-out-btn').click(function () {
        $('.lzy-form-cmd').val('_ignore_');
        $('.lzy-reservation-form').submit();
    });
});



// handle submit button in add dialog:
$('[type=submit]').click(function(e) {
    const $form = $( this ).closest('form');

    if (checkInput( $form )) {
        $( this ).prop('disabled',true);
        $( $form ).submit();
    } else {
        e.preventDefault();
    }
});


$('[type=reset]').click(function() {
    const $form = $( this ).closest('form');
    $('.lzy-form-cmd', $form).val('_delete_');
    $( $form ).submit();
});



function checkInput()
// function checkInput( $form )
{
    return true;
}


function onUnloadReservationPage() {
    $('form.lzy-reservation-form').each(function() {
        $('.lzy-form-cmd').val('_clear_');
        const data = $( this ).serialize();
        $.post( './', data );
    });
    onUnloadPage();
}
window.onbeforeunload = onUnloadReservationPage;
