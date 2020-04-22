// edit-data.js

debugOutput = ($('.debug').length !== 0);

//=== edit-data ===
function editDataLoadData( recId ) {
    var dataRef = $('form [data-value]').attr('data-value');
    execAjax({ds: dataRef, recId: recId }, 'get-rec', updateFormData);
}




function updateFormData( json ) {
    if (!json) {
        console.log('No data received');
        return;
    }
    try {
        var data = JSON.parse(json);
    } catch (e) {
        console.log('Error condition detected');
        console.log(json);
        return false;
    }

    for (var targSel in data.data) {
        var val = data.data[targSel];
        $( targSel ).each(function() {
            var $targ = $( this );

            var goOn = true;
            var callback = $('.lzy-live-data').attr('data-live-callback');
            if (typeof window[callback] === 'function') {
                goOn = window[callback]( targSel, val );
            }
            if (goOn) {
                var tag = $targ.prop('tagName');
                var valTags = 'INPUT,SELECT,TEXTAREA';
                if (valTags.includes(tag)) {
                    $targ.val(val);
                } else {
                    $targ.text(val);
                }
            }
        });
        if (debugOutput) {
            console.log(targSel + ' -> ' + val);
        }
    }
} // updateFormData



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


// === Submit Form ================
$('form').submit(function() {
    var url = appRoot + '_lizzy/_ajax_server.php?save-rec';
    console.log('submit ' + url);
    var $form = $( this );

    var dataRef = $('[name=data-ref]', $form).val();
    var data = {};

    $('input', $form).each(function () {
        var name = $( this ).attr('name');
        var val = $( this ).val();
        if (typeof name !== 'undefined') {
            data[name] = val;
        }
    });
    $('textarea', $form).each(function () {
        var name = $( this ).attr('name');
        var val = $( this ).val();
        if (typeof name !== 'undefined') {
            data[name] = val;
        }
    });
    // $('.lzy-data-input-rec').each(function() {
    //     var $this = $(this);
    //     var d = $('.lzy-data-key[type=date]', $this).val();
    //     var t = $('.lzy-data-key[type=time]', $this).val();
    //     if (d && (typeof t !== 'undefined')) {
    //         d = d + 'T' + t;
    //     }
    //     // var d = $('input[type=date]', $this).val();
    //     data[d] = {};
    //     $('.lzy-data-input-field', $this).each(function() {
    //         var $inp = $( 'input', $(this) );
    //         var name = $inp.attr('name');
    //         var val = $inp.val();
    //         data[d][name] = val;
    //     });
    // });
    console.log(data);
    var json = JSON.stringify(data);
    $.ajax({
        url: url,
        type: 'post',
        data: {ds: dataRef, lzy_data_input_form: json},
    }).done(function( json ) {
        console.log( json );
    });
    return false;
});
