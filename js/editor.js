/*
**	JS for in-browser editor
*/


var lastMdUpdateTime = 0;
var lastMdUpdate = '';
var $edWrapper = null;
var origText = '';
var simplemde = null;
var cmdKeyPressed = false;
var filename = '';
var $editBtn = null;


$( document ).ready(function() {

    prepareEditing();       // insert editing buttons

    setupEventHandlers();   // show-files-, edit-, cancel-buttons, page-history etc.

    setupKeyHandlers();     // F4, ESC, meta-s, meta-Return

}); // ready




function prepareEditing()
{
    $('.edit-sitemap').prepend('<button class="btn_editor">Edit</button>');
    $('body').addClass('editor-mode');
} // prepareEditing




function setupEventHandlers()
{
    $('#btn_show_files').click(function() {	// button to open file list and upload functions
        showFiles( this );
    });


    $('.btn_editor').click(function() {
        $editBtn = $(this);
        startEditor( $editBtn );
    });


    $('.editor_wrapper').click(function() {
        if (simplemde === null) {   // avoid starting editor if already started
            var $wrapper = $(this).parent();
            $editBtn = $('.btn_editor', $wrapper);
            startEditor( $editBtn );
        }
    });



    $('.edit-sitemap .btn_editor').click(function() {
        startSitemapEditor();
    });

    // page-history drop-down list:
    $( "select" ).change(function() {       // handle edition-dropdown-list
        filename = $( this ).parent().parent().attr('data-filename');
        $( "select option:selected" ).each(function() {
            var href = $(this).val();
            if (href != '') {
                href = './?ed=-' + (parseInt($(this).val()) + 1) + '&file=' + filename;
                window.location.href = href;
            }
        });
    });

} // setupEventHandler




function setupKeyHandlers()
{
    document.onkeydown = function (e) {     // intercept cmd-s and ctrl-s to save
        var keyCode = (window.event) ? event.keyCode : e.keyCode;
        if ( simplemde !== null) {  // only when editor is active:
            if ((keyCode == 13) && (event.ctrlKey || macKeys.cmdKey)) {
                event.preventDefault();
                console.log('meta-s intercepted');
                saveData( true );
                return false;
            }

            if ((keyCode == 83) && (event.ctrlKey || macKeys.cmdKey)) {
                event.preventDefault();
                console.log('meta-s intercepted');
                saveData( false );
                return false;
            }

            if (keyCode == 27) {	// ESC -> end editing mode
                abortEditor();
                return false;
            }
        }

        if (keyCode == 27) {	// ESC -> end editing mode
            console.log('ESC intercepted');
            var call = window.location.pathname.replace(/\?.*/, '') + '?edit=false';
            window.location.assign(call);
        }

        if (keyCode == 115) {   // F4
            var $editBtns = $('main .btn_editor');
            $editBtn = $editBtns.first();
            if (simplemde === null) {
                startEditor( $editBtn );
            } else {
                hideElements(false);
                $editBtn.show();
                simplemde.toTextArea();
                simplemde = null;
                $edWrapper.html(origText);
            }
        }

    };

} // setupKeyHandlers





function startEditor( $editBtn ) {
    $editBtn.hide();
    hideElements(true);
    $('body').addClass('editing');
    var $section = $editBtn.parent();
    $edWrapper = $('div', $section);
    origText = $edWrapper.html();
    $edWrapper.html('');
    $section.addClass('editing');
    filename = $section.attr('data-filename');

    $.ajax({
        type: "POST",
        url: systemPath + '_ajax_server.php?getfile',
        data: { filename: filename },
        success: function( data ) {
            var editingHtml = $('#editing-html').html();
            editingHtml = editingHtml.replace(/@data/, data);
            $edWrapper.html(editingHtml);
            simplemde = new SimpleMDE({
                element: $('textarea', $edWrapper)[0],
                spellChecker: false,
                placeholder: 'Hier schreiben...',
                autofocus: true,
                allowAtxHeaderWithoutSpace: true,
                previewRender: function(plainText, preview) { // Async method
                    setTimeout(function(){
                        preview.innerHTML = customMarkdownParser(plainText);
                    }, 250);
                    return "Loading...";
                },
                hideIcons: ["guide"],
                showIcons: ["code", "table", "horizontal-rule"],
                tabSize: 4,


            });
            $('.ed-selector').hide();

            $('.btn_cancel').click(function() {
                var href = window.location.pathname;
                window.location.href = href;
            });

            $('.btn_save').click(function() {
                saveData( false );
            });

            $('.btn_done').click(function() {
                saveData( true );
            });
        },
    });
} // startEditor





function startSitemapEditor() {
    $('body').addClass('editing');
    $('header').addClass('dispno');
    $edWrapper = $('main section');
    origText = $edWrapper.html();

    filename = 'sitemap';
    $.ajax({
        type: "POST",
        url: systemPath + '_ajax_server.php?getfile',
        data: { filename: filename },
        success: function( data ) {
            var editingHtml = $('#editing-html').html();
            editingHtml = editingHtml.replace(/@data/, data);
            $edWrapper.html(editingHtml);
            simplemde = new SimpleMDE({
                element: $('textarea', $edWrapper)[0],
                spellChecker: false,
                placeholder: 'Hier schreiben...',
                autofocus: true,
                allowAtxHeaderWithoutSpace: true,
                toolbar: false,
                tabSize: 8,
            });
            $('.btn_cancel').click(function() {
                var href = window.location.pathname;
                window.location.href = href;
            });

            $('.btn_save').click(function() {
                saveSitemapData( false );
            });

            $('.btn_done').click(function() {
                saveSitemapData( true );
            });
        }
    });
} // startSitemapEditor





function abortEditor()
{
	hideElements(false);
	$editBtn.show();
	simplemde.toTextArea();
	simplemde = null;
	$edWrapper.html(origText);
} // abortEditor




function showFiles( that )
{
    $(that).hide();
    $('#fileupload').show();

    $.ajax({	// load uploader (from main.js)
        // Uncomment the following to send cross-domain cookies:
        //xhrFields: {withCredentials: true},
        url: $('#fileupload').fileupload('option', 'url'),
        dataType: 'json',
        context: $('#fileupload')[0]
    }).always(function () {
        $(this).removeClass('fileupload-processing');
    }).done(function (result) {
        $(this).fileupload('option', 'done')
            .call(this, $.Event('done'), {result: result});
    });
} // showFiles




function saveSitemapData( leaveEditor )
{
	if (leaveEditor) {
		hideElements(false);
	}
	var sitemap = simplemde.value();
	$.ajax({
		type: "POST",
		url: '?save',
		// url: systemPath + '?save',
		data: { filename: 'sitemap', sitemap: sitemap },
		success: function( data ) {
			if (leaveEditor) {
				// var href = window.location.pathname;
				location.href = location.pathname;
				// window.location.href = window.location.pathname;
			} else {
                $('textarea.editor').html(data);
			}
		},
	});
} // saveSitemapData



function saveData( leaveEditor )
{
	if (leaveEditor) {
		hideElements(false);
		$editBtn.show();
	}
	var mdStr = simplemde.value();
    mdStr = encodeURI(mdStr);   // -> php urldecode() to decode
	$.ajax({
		type: "POST",
		url: '?compile&save',
        'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8',
		// url: systemPath + '?compile&save',
		data: { filename: filename, md: mdStr },
		success: function( data ) {
			if (leaveEditor) {
				// var href = window.location.pathname;
				// window.location.href = window.location.pathname;
                location.reload();
			} else {
                $('textarea.editor').html(data);
			}
		},
	});
} // saveData




function customMarkdownParser(mdStr) {
    var n = Date.now(); 
    if (lastMdUpdate && (lastMdUpdateTime > (n - 300))) {
        lastMdUpdateTime = n;
        return lastMdUpdate;
    }
    lastMdUpdateTime = n;
    lastMdUpdate = $.ajax({
        type: "POST",
        url: systemPath + '?compile',
        data: { md: mdStr },
        async: false
    }).responseText;
    return lastMdUpdate;
} // customMarkdownParser




function hideElements( state ) {
    if (typeof hideWhileEditing == 'object') {
        hideWhileEditing.forEach(function (elem) {
            if (state || (typeof state == 'unknown')) {
                $(elem).hide();
            } else {
                $(elem).show();
            }
        });
    }
} // hideElements
