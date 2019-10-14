
var nRecs = parseInt($('input[name=lzy-data-input-form]').val());
$('.lzy-data-input-add-rec').click(function() {
    console.log('lzy-data-input-add-rec');
    var $rec = $( this ).closest('.lzy-data-input-rec');
    nRecs = nRecs + 1;
    var html = $('.lzy-data-imput-template').html();
    html = html.replace(/@/g, nRecs);
    $( html ).insertBefore( $rec );
    
    return false;
});
$('.lzy-data-input-del-rec').click(function() {
    console.log('lzy-data-input-del-rec');
    var $rec = $( this ).closest('.lzy-data-input-rec');
    $rec.remove();
    return false;
});
$('input[type=reset]').click(function() {
    console.log('reload page');
    var response = confirm("{{ lzy-discard-all-changes }}");
    if (response) {
        lzyReload();
    }
});
$('form').submit(function() {
    var url = window.location.href;
    console.log('submit ' + url);
    // var $form = $( this );
    var data = {};
    $('.lzy-data-input-rec').each(function() {
        var $this = $(this);
        var d = $('.lzy-data-key[type=date]', $this).val();
        var t = $('.lzy-data-key[type=time]', $this).val();
        if (d && (typeof t !== 'undefined')) {
            d = d + 'T' + t;
        }
        // var d = $('input[type=date]', $this).val();
        data[d] = {};
        $('.lzy-data-input-field', $this).each(function() {
            var $inp = $( 'input', $(this) );
            var name = $inp.attr('name');
            var val = $inp.val();
            data[d][name] = val;
        });
    });
    console.log(data);
    var json = JSON.stringify(data);
    $.ajax({
        url: url,
        type: 'post',
        data: {lzy_data_input_form: json},
        success: function( response ) {
            lzyReload();
        }
    });
    return false;
});
