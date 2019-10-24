<?php

/*
 * Module provides code for the 'File Upload' feature of the page editor
 * -> Based on Bootstrap
 */

class FileUpload
{
    public function __construct($lzy, $ticket)
    {
        $this->lzy = $lzy;
        $this->page = $lzy->page;
        $this->ticket = $ticket;
    } // __construct
    
    public function render($filePath)
    {
        $hide = ' style="display: none;"';
        $hide1 = '';
//        if (isset($_SESSION['lzy-backend']['dataRef']) && ($this->ticket === $_SESSION['lzy-backend']['dataRef'])) {
        if (isset($_SESSION['lzy-backend']['dataRef'])) {
            $hide = '';
            $hide1 = ' style="display: none;"';
        }
        $html = <<<EOT

<div class="lzy-file-uploader-wrapper1"$hide1>
    <button id="lzy-file-uploader-open-button" class="lzy-button">{{lzy-editor-open-files }}</button>
</div>
<div class="lzy-file-uploader-wrapper"$hide>
  <div class="lzy-file-uploader file-uploader container">
    <form id="lzy-fileupload" action="" method="POST" enctype="multipart/form-data">
        <input id='lzy-upload-id' type="hidden" name="lzy-upload" value="{$this->ticket}" />
        <div class="row lzy-fileupload-buttonbar fileupload-buttonbar">
            <div class="">
                <button type="button" class="lzy-button lzy-editor-new-file">
                    <i class="lzy-icon-doc"></i>
                    <span>{{ lzy-editor-new-file }}</span>
                </button>
                <span class="">
                    <label class="lzy-button lzy-editor-add-files" for="lzy-file-uploader-input"><i class="lzy-icon-select"></i> {{ lzy-editor-add-files }}</label>
                    <input type="file" id="lzy-file-uploader-input" class='lzy-invisible' name="files[]" multiple />
                </span>
                <button type="submit" class="lzy-button lzy-editor-start-upload btn btn-primary start">
                    <i class="lzy-icon-upload"></i>
                    <span>{{ lzy-editor-start-upload }}</span>
               </button>
                <button type="reset" class="lzy-button lzy-editor-cancel-upload cancel">
                    <i class="lzy-icon-cancel"></i>
                    <span>{{ lzy-editor-cancel-upload }}</span>
                </button>
                <button type="button" class="lzy-button lzy-editor-delete-file delete">
                    <i class="lzy-icon-trash"></i>
                    <span>{{ lzy-editor-delete-file }}</span>
                </button>
                <input type="checkbox" class="toggle">
                <span class="lzy-fileupload-process"></span>
            </div>
            <div id="lzy-editor-new-file-name-box" style="display: none;">
                <label for="lzy-editor-new-file-input" >{{ lzy-editor-name-of-new-file }}</label>
                <input type="text" id="lzy-editor-new-file-input" />
                <button class="lzy-button" id="lzy-editor-new-file-input-button">{{ lzy-editor-new-file-input-button }}</button>
            </div>
            <div class="lzy-fileupload-progress fileupload-progress fade">
                <div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar progress-bar-success" style="width:0%;"></div>
                </div>
                <div class="progress-extended">&nbsp;</div>
            </div>
        </div>
        <table role="presentation" class="table table-striped"><tbody class="files"></tbody></table>
    </form>

  </div> <!-- /lzy-file-uploader container-->
</div> <!-- /lzy-file-uploader-wrapper -->


<div id="lzy-edit-new-filename-container" style="display: none;">
    <h1>{{ lzy-editor-new-file }}</h1>
    <label for="lzy-edit-new-filename">{{ lzy-editor-name-of-new-file }}</label>
    <input type="text" id="lzy-edit-new-filename" />
    <button id="lzy-edit-new-filename-confirm" class='lzy-popup-confirm lzy-popup-button'>Ok</button>
</div> <!-- /lzy-edit-new-filename-container -->


<div id="lzy-edit-rename-file-container" style="display: none">
      <h1>{{ lzy-editor-rename-file }}</h1>
<div>
    <label for="lzy-edit-rename-file">{{ lzy-editor-new-filename }}</label>
    <input type="text" id="lzy-edit-rename-file" />
    <div id="lzy-edit-rename-file-confirm" class='lzy-popup-confirm lzy-popup-button'>{{ lzy-editor-ok }}</div>
  </div>
</div> <!-- /lzy-edit-new-filename-container -->


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
                  {% if (window.innerWidth > 480 || !o.options.loadImageFileTypes.test(file.type)) { %}
                      <p class="name">{%=file.name%}</p>
                  {% } %}
                  <strong class="error text-danger"></strong>
              </td>
              <td>
                  <p class="size">Processing...</p>
                  <div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar progress-bar-success" style="width:0%;"></div></div>
              </td>
              <td>
                  {% if (!o.options.autoUpload && o.options.edit && o.options.loadImageFileTypes.test(file.type)) { %}
                    <button class="lzy-button lzy-editor-edit edit" data-index="{%=i%}" disabled>
<!--                        <i class="lzy-icon-edit"></i>-->
                        <span>{{ lzy-editor-edit }}</span>
                    </button>
                  {% } %}
                  {% if (!i && !o.options.autoUpload) { %}
                      <button class="lzy-button lzy-editor-start-upload start" disabled title="{{ lzy-editor-start-upload }}">
                          <i class="lzy-icon-upload"></i>
<!--                          <span>{{ lzy-editor-start-upload }}</span>-->
                      </button>
                  {% } %}
                  {% if (!i) { %}
                      <button class="lzy-button lzy-editor-cancel-upload cancel" title="{{ lzy-editor-cancel-upload }}">
                          <i class="lzy-icon-cancel"></i>
<!--                          <span>{{ lzy-editor-cancel-upload }}</span>-->
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
                  {% if (window.innerWidth > 480 || !file.thumbnailUrl) { %}
                      <p class="name">
                          {% if (file.url) { %}
                              <a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" {%=file.thumbnailUrl?'data-gallery':''%}>{%=file.name%}</a>
                          {% } else { %}
                              <span>{%=file.name%}</span>
                          {% } %}
                      </p>
                  {% } %}
                  {% if (file.error) { %}
                      <div><span class="label label-danger">Error</span> {%=file.error%}</div>
                  {% } %}
              </td>
              <td>
                  <span class="size">{%=o.formatFileSize(file.size)%}</span>
              </td>
              <td>
                  {% if (file.deleteUrl) { %}
                      <button class="lzy-button lzy-editor-delete-file delete" title="{{ lzy-editor-delete-file }}" data-type="{%=file.deleteType%}" data-url="{%=file.deleteUrl%}"{% if (file.deleteWithCredentials) { %} data-xhr-fields='{"withCredentials":true}'{% } %}>
                          <i class="lzy-icon-trash"></i>
<!--                          <span>{{ lzy-editor-delete-file }}</span>-->
                      </button>
                      <input type="checkbox" name="delete" value="1" class="toggle">
                  {% } else { %}
                      <button class="lzy-button lzy-editor-cancel-upload cancel" title="lzy-editor-cancel-upload">
                          <i class="lzy-icon-cancel"></i>
<!--                          <span>{{ lzy-editor-cancel-upload }}</span>-->
                      </button>
                  {% } %}
              </td>
          </tr>
      {% } %}
    </script>


EOT;

//        $html = <<<EOT
//
//<div class="lzy-file-uploader-wrapper">
//<!-- <div class="lzy-file-uploader-wrapper" style="display: none;"> -->
//<div class="lzy-file-uploader container">
//    <!-- The file upload form used as target for the file upload widget -->
//    <form id="lzy-fileupload" action="" method="POST" enctype="multipart/form-data" data-upload-path="$filePath">
//        <!-- The fileupload-buttonbar contains buttons to add/delete files and start/cancel the upload -->
//        <input type="hidden" name="lzy-upload" value="{$this->ticket}" />
//        <div class="row lzy-fileupload-buttonbar">
//            <div class="col-lg-7">
//                <!-- The fileinput-button span is used to style the file input field as button -->
//                <button type="button" class="btn btn-success new">
//                    <span>{{ lzy-editor-new-file }}</span>
//                </button>
//                <span class="btn btn-success fileinput-button">
//                    <span>{{ lzy-editor-add-files }}</span>
//                    <input type="file" name="files[]" multiple>
//                </span>
//                <button type="submit" class="btn btn-primary start">
//                    <span>{{ lzy-editor-start-upload }}</span>
//               </button>
//                <button type="reset" class="btn btn-warning cancel">
//                    <span>{{ lzy-editor-cancel-upload }}</span>
//                </button>
//                <button type="button" class="btn btn-danger delete">
//                    <span>{{ lzy-editor-delete-file }}</span>
//                </button>
//                <input type="checkbox" class="toggle">
//                <!-- The global file processing state -->
//                <span class="lzy-fileupload-process"></span>
//            </div>
//            <!-- The global progress state -->
//            <div class="col-lg-5 lzy-fileupload-progress fade">
//                <!-- The global progress bar -->
//                <div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100">
//                    <div class="progress-bar progress-bar-success" style="width:0%;"></div>
//                </div>
//                <!-- The extended global progress state -->
//                <div class="progress-extended">&nbsp;</div>
//            </div>
//        </div>
//        <!-- The table listing the files available for upload/download -->
//        <table role="presentation" class="table table-striped"><tbody class="files"></tbody></table>
//    </form>
//    <br>
//</div>
//</div> <!-- /lzy-file-uploader-wrapper -->
//
//
//<div id="lzy-edit-new-filename-container" style="display: none;">
//    <h1>New File:</h1>
//    <label for="lzy-edit-new-filename">{{ lzy-editor-name-of-new-file }}</label>
//    <input type="text" id="lzy-edit-new-filename" />
//    <button id="lzy-edit-new-filename-confirm" class='lzy-popup-confirm lzy-popup-button'>Ok</button>
//</div> <!-- /lzy-edit-new-filename-container -->
//
//
//<div id="lzy-edit-rename-file-container" style="display: none">
//      <h1>{{ lzy-editor-rename-file }}</h1>
//<div>
//    <label for="lzy-edit-rename-file">{{ lzy-editor-new-filename }}</label>
//    <input type="text" id="lzy-edit-rename-file" />
//    <div id="lzy-edit-rename-file-confirm" class='lzy-popup-confirm lzy-popup-button'>{{ lzy-editor-ok }}</div>
//  </div>
//</div> <!-- /lzy-edit-new-filename-container -->
//
//
//<!-- The blueimp Gallery widget -->
//<div id="blueimp-gallery" class="blueimp-gallery blueimp-gallery-controls" data-filter=":even">
//    <div class="slides"></div>
//    <h3 class="title"></h3>
//    <a class="prev">‹</a>
//    <a class="next">›</a>
//    <a class="close">×</a>
//    <a class="play-pause"></a>
//    <ol class="indicator"></ol>
//</div>
//
//
//<!-- The template to display files available for upload -->
//<script id="template-upload" type="text/x-tmpl">
//{% for (var i=0, file; file=o.files[i]; i++) { %}
//    <tr class="template-upload fade">
//        <td>
//            <span class="preview"></span>
//        </td>
//        <td>
//            <p class="name">{%=file.name%}</p>
//            <strong class="error text-danger"></strong>
//        </td>
//        <td>
//            <p class="size">Processing...</p>
//            <div class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar progress-bar-success" style="width:0%;"></div></div>
//        </td>
//        <td>
//            {% if (!i && !o.options.autoUpload) { %}
//                <button class="btn btn-primary start" disabled>
//                    <i class="glyphicon glyphicon-upload"></i>
//                    <span>Start</span>
//                </button>
//            {% } %}
//            {% if (!i) { %}
//                <button class="btn btn-warning cancel">
//                    <i class="glyphicon glyphicon-ban-circle"></i>
//                    <span>Cancel</span>
//                </button>
//            {% } %}
//        </td>
//    </tr>
//{% } %}
//</script>
//<!-- The template to display files available for download -->
//<script id="template-download" type="text/x-tmpl">
//{% for (var i=0, file; file=o.files[i]; i++) { %}
//    <tr class="template-download fade">
//        <td>
//            <span class="preview">
//                {% if (file.thumbnailUrl) { %}
//                    <a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" data-gallery><img src="{%=file.thumbnailUrl%}"></a>
//                {% } %}
//            </span>
//        </td>
//        <td>
//            <p class="name">
//                {% if (file.url) { %}
//                    <a href="{%=file.url%}" title="{%=file.name%}" download="{%=file.name%}" {%=file.thumbnailUrl?'data-gallery':''%}>{%=file.name%}</a>
//                {% } else { %}
//                    <span>{%=file.name%}</span>
//                {% } %}
//            </p>
//            {% if (file.error) { %}
//                <div><span class="label label-danger">Error</span> {%=file.error%}</div>
//            {% } %}
//        </td>
//        <td>
//            <span class="size">{%=o.formatFileSize(file.size)%}</span>
//        </td>
//        <td>
//            {% if (file.deleteUrl) { %}
//                <!--<button class='btn btn-success lzy-btn-rename-file'>Rename</button>-->
//                <div class='btn btn-success lzy-btn-rename-file'>Rename</div>
//                <button class="btn btn-danger delete" data-type="{%=file.deleteType%}" data-url="{%=file.deleteUrl%}"{% if (file.deleteWithCredentials) { %} data-xhr-fields='{"withCredentials":true}'{% } %}>
//                    <i class="glyphicon glyphicon-trash"></i>
//                    <span>Delete</span>
//                </button>
//                <input type="checkbox" name="delete" value="1" class="toggle">
//            {% } else { %}
//                <button class="btn btn-warning cancel">
//                    <i class="glyphicon glyphicon-ban-circle"></i>
//                    <span>Cancel</span>
//                </button>
//            {% } %}
//        </td>
//    </tr>
//{% } %}
//</script>
//
//EOT;
//
        $this->page->addCssFiles([
            '~sys/third-party/blueimp/blueimp-gallery.min.css',
            '~sys/third-party/jquery-upload/css/style.css',
            '~sys/third-party/jquery-upload/css/jquery.fileupload.css',
            '~sys/third-party/jquery-upload/css/jquery.fileupload-ui.css']);

        $this->page->addJqFiles([
            "~sys/third-party/jquery-upload/js/vendor/jquery.ui.widget.js",//
            "~sys/third-party/blueimp/tmpl.min.js",//
            "~sys/third-party/blueimp/load-image.all.min.js",//
            "~sys/third-party/blueimp/canvas-to-blob.min.js",//
//            "~sys/third-party/bootstrap/js/bootstrap.min.js",
            "~sys/third-party/blueimp/jquery.blueimp-gallery.min.js",//
            "~sys/third-party/jquery-upload/js/jquery.iframe-transport.js",
            "~sys/third-party/jquery-upload/js/jquery.fileupload.js",
            "~sys/third-party/jquery-upload/js/jquery.fileupload-process.js",
            "~sys/third-party/jquery-upload/js/jquery.fileupload-image.js",
            "~sys/third-party/jquery-upload/js/jquery.fileupload-audio.js",
            "~sys/third-party/jquery-upload/js/jquery.fileupload-video.js",
            "~sys/third-party/jquery-upload/js/jquery.fileupload-validate.js",
            "~sys/third-party/jquery-upload/js/jquery.fileupload-ui.js",
            "~sys/file-upload/jquery-upload-main.js"]);
//        $this->page->addJqFiles([
//            "JQUERY",
//            "~sys/third-party/jquery-upload/js/vendor/jquery.ui.widget.js",
//            "~sys/third-party/blueimp/tmpl.min.js",
//            "~sys/third-party/blueimp/load-image.all.min.js",
//            "~sys/third-party/blueimp/canvas-to-blob.min.js",
////            "~sys/third-party/bootstrap/js/bootstrap.min.js",
//            "~sys/third-party/blueimp/jquery.blueimp-gallery.min.js",
//            "~sys/third-party/jquery-upload/js/jquery.iframe-transport.js",
//            "~sys/third-party/jquery-upload/js/jquery.fileupload.js",
//            "~sys/third-party/jquery-upload/js/jquery.fileupload-process.js",
//            "~sys/third-party/jquery-upload/js/jquery.fileupload-image.js",
//            "~sys/third-party/jquery-upload/js/jquery.fileupload-audio.js",
//            "~sys/third-party/jquery-upload/js/jquery.fileupload-video.js",
//            "~sys/third-party/jquery-upload/js/jquery.fileupload-validate.js",
//            "~sys/third-party/jquery-upload/js/jquery.fileupload-ui.js",
//            "~sys/file-upload/jquery-upload-main.js"]);

        return $html;
    } // render
} // FileUpload
