// edit-data.js

debugOutput = ($('.debug').length !== 0);

//=== edit-data ===
// call this function to dynamically load data from host before opening the edit-data form:
//  Note: recKey: either integer key into DB (starting at 0)
//                or string selector identifying recID (starting at 1)
function editDataLoadData( recKey, $form ) {
    if (typeof recKey !== 'number') {
        if ( $( recKey ).length ) {
            var $recId = $( recKey );
            if ( $recId[0].nodeName === 'INPUT') {
                recKey = $recId.val();
            } else {
                recKey = $recId.text();
            }
            recKey = parseInt( recKey ) - 1;
        }
    }

    var dataRef = '';
    if (typeof $form !== 'undefined') {
        dataRef = $('input[name=data-ref]', $form).attr('value');
    } else {
        dataRef = $('.lzy-edit-data-form input[name=data-ref]').attr('value');
    }
    execAjax({ds: dataRef, recKey: recKey }, 'get-rec', updateFormData);
} // editDataLoadData




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
                var valTags = 'INPUT,SELECT';
                if (valTags.includes(tag)) {
                    $targ.val(val).attr('value', val).attr('data-value', val);
                } else {
                    $targ.text(val).attr('value', val).attr('data-value', val);
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


function editDataSubmitForm( $form, onSuccess )
{
    var url = appRoot + '_lizzy/_ajax_server.php?save-rec';
    console.log('submit ' + url);

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
    console.log(data);
    var json = JSON.stringify(data);
    $.ajax({
        url: url,
        type: 'post',
        data: {ds: dataRef, lzy_data_input_form: json},
    }).done(function( json ) {
        console.log( 'editDataSubmitForm -> host-response: ' + json );
        if (typeof onSuccess === 'function') {
            goOn = onSuccess( json );
        }
    });
} // editDataSubmitForm




// === Submit Form ================
$('.lzy-edit-data-form').submit(function() {
    pgLeaveWarnings = false;
    var $form = $( this );
    editDataSubmitForm( $form );

    return false;
});
