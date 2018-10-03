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


$(function () {
    'use strict';

    var serverUrl = appRoot+'_lizzy/file-upload/_upload_server.php';

    // Initialize the jQuery File Upload widget:
    $('#lzy-fileupload').fileupload({
        url: serverUrl
    });

    $('#lzy-fileupload').addClass('fileupload-processing');

});
