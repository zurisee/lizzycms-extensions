
//  Reservation jQuery Scripts --------------------------------

var submitting = false;


// try to send _clear_ to host when user leaves page (unless submitting data):
function onUnloadReservationPage() {
    if (submitting) {
        return;
    }
    $('form.lzy-reservation-form').each(function() {
        $('.lzy-form-cmd').val('_clear_');
        const data = $( this ).serialize();
        $.post( './', data );
    });
    onUnloadPage();
}
window.onbeforeunload = onUnloadReservationPage;

// handle submit button in dialog: avoid unwanted _clear_
$('[type=submit]').click(function(e) {
    submitting = true;
});

// handle reset button in dialog:
$('.lzy-reservation-form input[type=reset]').click(function() {
    const $form = $( this ).closest('form');
    $('.lzy-form-cmd', $form).val('_delete_');
    $( $form ).submit();
});



