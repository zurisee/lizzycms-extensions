/*
 *  Adapted for Lizzy
 */

/*
 * jQuery File Upload Plugin JS Example
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2010, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * https://opensource.org/licenses/MIT
 */

/* global $, window */

function createFile(ticket, url)
{
    var fname = $('#lzy-editor-new-file-input').val();
    if (!fname) {
        alert('Please enter file name');
        return;
    }
    $.ajax({
        url: url,
        method: 'POST',
        data: { 'lzy-upload': ticket, 'lzy-cmd': 'new-file', 'lzy-file-name': fname },
        dataType: 'json',
    }).done(function(data) {
        console.log('new file created: ' + data);
        lzyReload();
    });
}


$(function () {
    'use strict';

    var serverUrl = appRoot+'_lizzy/file-upload/_upload_server.php';
    $('#lzy-fileupload').fileupload({
        url: serverUrl
    });

    $('#lzy-fileupload').addClass('fileupload-processing');

    var url = $('#lzy-fileupload').fileupload('option', 'url');
    var ticket = $('#lzy-upload-id').val();
    // console.log('lzy-fileupload url: ' + url);
    // console.log($('#lzy-fileupload')[0]);

    $.ajax({
            // Uncomment the following to send cross-domain cookies:
            //xhrFields: {withCredentials: true},
            url: $('#lzy-fileupload').fileupload('option', 'url'),
            // method: 'POST',
            data: { 'lzy-upload': ticket},
            dataType: 'json',
            context: $('#lzy-fileupload')[0]
        })
        .always(function(result) {
            // console.log('lzy-fileupload always ');
            // console.log(result);
            $(this).removeClass('fileupload-processing');
        })
        .done(function(result) {
            // console.log('lzy-fileupload done: ' + result);
            // console.log(result);
            $(this)
                .fileupload('option', 'done')
                // eslint-disable-next-line new-cap
                .call(this, $.Event('done'), { result: result });
        });

    $('.lzy-editor-new-file').click(function (e) {
        e.preventDefault();
       // console.log('create new file...');
       $('#lzy-editor-new-file-name-box').show();
    });
    $('#lzy-editor-new-file-input-button').click(function (e) {
        e.preventDefault();
        createFile(ticket, url);
    });
    $('#lzy-editor-new-file-input').keypress(function(event){
        var keycode = (event.keyCode ? event.keyCode : event.which);
        if(keycode === 13){
            event.preventDefault();
            createFile(ticket, url);
        }
    });

    $('#lzy-file-uploader-open-button').click(function () {
        $('.lzy-file-uploader-wrapper1').hide();
        $('.lzy-file-uploader-wrapper').show();
    });
});

/*
TODO: Delete -> confirm
TODO: create -> trigger on Return
TODO: Button Files
 */