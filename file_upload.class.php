<?php

/*
 * Module provides code for the 'File Upload' feature of the page editor
 * -> Based on Bootstrap
 */

class FileUpload
{
    public function __construct($lzy)
    {
        $this->lzy = $lzy;
        $this->page = $lzy->page;
    } // __construct
    
    public function render($filePath)
    {
        $this->page->addCssFiles([
            //	<!-- Bootstrap styles -->
            '//netdna.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css',
            //	<!-- Generic page styles -->
            '~sys/third-party/jquery-upload/css/style.css',
            //'~sys/third-party/jquery-upload/css/blueimp-gallery.min.css',
            //	<!-- CSS to style the file input field as button and adjust the Bootstrap progress bars -->
            '~sys/third-party/jquery-upload/css/jquery.fileupload.css',
            '~sys/third-party/jquery-upload/css/jquery.fileupload-ui.css']);

        $html = <<<EOT

<div class="lzy-file-uploader container">
    <button id='lzy-show-files-btn'>Show Files</button>
    <!-- The file upload form used as target for the file upload widget -->
    <form id="lzy-fileupload" action="" method="POST" enctype="multipart/form-data" data-upload-path="$filePath">
        <!-- The fileupload-buttonbar contains buttons to add/delete files and start/cancel the upload -->
        <div class="row lzy-fileupload-buttonbar">
            <div class="col-lg-7">
                <!-- The fileinput-button span is used to style the file input field as button -->
                <span class="btn btn-success fileinput-button">
                    <i class="glyphicon glyphicon-plus"></i>
                    <span>Add files...</span>
                    <input type="file" name="files[]" multiple>
                </span>
                <button type="submit" class="btn btn-primary start">
                    <i class="glyphicon glyphicon-upload"></i>
                    <span>Start upload</span>
                </button>
                <button type="reset" class="btn btn-warning cancel">
                    <i class="glyphicon glyphicon-ban-circle"></i>
                    <span>Cancel upload</span>
                </button>
                <button type="button" class="btn btn-danger delete">
                    <i class="glyphicon glyphicon-trash"></i>
                    <span>Delete</span>
                </button>
                <input type="checkbox" class="toggle">
                <!-- The global file processing state -->
                <span class="lzy-fileupload-process"></span>
            </div>
            <!-- The global progress state -->
            <div class="col-lg-5 lzy-fileupload-progress fade">
                <!-- The global progress bar -->
                <div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar progress-bar-success" style="width:0%;"></div>
                </div>
                <!-- The extended global progress state -->
                <div class="progress-extended">&nbsp;</div>
            </div>
        </div>
        <!-- The table listing the files available for upload/download -->
        <table role="presentation" class="table table-striped"><tbody class="files"></tbody></table>
    </form>
    <br>
</div>
<!-- The blueimp Gallery widget -->
<div id="blueimp-gallery" class="blueimp-gallery blueimp-gallery-controls" data-filter=":even">
    <div class="slides"></div>
    <h3 class="title"></h3>
    <a class="prev">‹</a>
    <a class="next">›</a>
    <a class="close">×</a>
    <a class="play-pause"></a>
    <ol class="indicator"></ol>
</div>


<!-- The template to display files available for upload -->
<script id="template-upload" type="text/x-tmpl">
{% for (var i=0, file; file=o.files[i]; i++) { %}
    <tr class="template-upload fade">
        <td>
            <span class="preview"></span>
        </td>
        <td>
            <p class="name">{%=file.name%}</p>
            <strong class="error text-danger"></strong>
        </td>
        <td>
            <p class="size">Processing...</p>
            <div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar progress-bar-success" style="width:0%;"></div></div>
        </td>
        <td>
            {% if (!i && !o.options.autoUpload) { %}
                <button class="btn btn-primary start" disabled>
                    <i class="glyphicon glyphicon-upload"></i>
                    <span>Start</span>
                </button>
            {% } %}
            {% if (!i) { %}
                <button class="btn btn-warning cancel">
                    <i class="glyphicon glyphicon-ban-circle"></i>
                    <span>Cancel</span>
                </button>
            {% } %}
        </td>
    </tr>
{% } %}
</script>
<!-- The template to display files available for download -->
<script id="template-download" type="text/x-tmpl">
{% for (var i=0, file; file=o.files[i]; i++) { %}
    <tr class="template-download fade">
        <td>
            <span class="preview">
                {% if (file.thumbnailUrl) { %}
                    <a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" data-gallery><img src="{%=file.thumbnailUrl%}"></a>
                {% } %}
            </span>
        </td>
        <td>
            <p class="name">
                {% if (file.url) { %}
                    <a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" {%=file.thumbnailUrl?'data-gallery':''%}>{%=file.name%}</a>
                {% } else { %}
                    <span>{%=file.name%}</span>
                {% } %}
            </p>
            {% if (file.error) { %}
                <div><span class="label label-danger">Error</span> {%=file.error%}</div>
            {% } %}
        </td>
        <td>
            <span class="size">{%=o.formatFileSize(file.size)%}</span>
        </td>
        <td>
            {% if (file.deleteUrl) { %}
                <button class="btn btn-danger delete" data-type="{%=file.deleteType%}" data-url="{%=file.deleteUrl%}"{% if (file.deleteWithCredentials) { %} data-xhr-fields='{"withCredentials":true}'{% } %}>
                    <i class="glyphicon glyphicon-trash"></i>
                    <span>Delete</span>
                </button>
                <input type="checkbox" name="delete" value="1" class="toggle">
            {% } else { %}
                <button class="btn btn-warning cancel">
                    <i class="glyphicon glyphicon-ban-circle"></i>
                    <span>Cancel</span>
                </button>
            {% } %}
        </td>
    </tr>
{% } %}
</script>

EOT;

    $this->page->addJqFiles([
        "JQUERY", "~sys/third-party/jquery-upload/js/vendor/jquery.ui.widget.js",
        "~sys/third-party/blueimp/js/tmpl.min.js",
        "//blueimp.github.io/JavaScript-Templates/js/tmpl.min.js",
        "~sys/third-party/blueimp/js/load-image.all.min.js",
        "//blueimp.github.io/JavaScript-Load-Image/js/load-image.all.min.js",
        "~sys/third-party/blueimp/js/canvas-to-blob.min.js",
        "//blueimp.github.io/JavaScript-Canvas-to-Blob/js/canvas-to-blob.min.js",
        "//netdna.bootstrapcdn.com/bootstrap/3.2.0/js/bootstrap.min.js",
        "~sys/third-party/blueimp/js/jquery.blueimp-gallery.min.js",
        "~sys/third-party/jquery-upload/js/jquery.iframe-transport.js",
        "~sys/third-party/jquery-upload/js/jquery.fileupload.js",
        "~sys/third-party/jquery-upload/js/jquery.fileupload-process.js",
        "~sys/third-party/jquery-upload/js/jquery.fileupload-image.js",
        "~sys/third-party/jquery-upload/js/jquery.fileupload-audio.js",
        "~sys/third-party/jquery-upload/js/jquery.fileupload-video.js",
        "~sys/third-party/jquery-upload/js/jquery.fileupload-validate.js",
        "~sys/third-party/jquery-upload/js/jquery.fileupload-ui.js",
        "~sys/file-upload/jquery-upload-main.js"]);

        return $html;
    } // render
} // FileUpload
