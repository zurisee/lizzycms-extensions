/*
**	JS for in-browser editor
*/


var lastMdUpdateTime = 0;
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
    $('.lzy-edit-sitemap').prepend('<button class="lzy-editor-btn">Edit</button>');
    $('body').addClass('lzy-editor-mode');
} // prepareEditing




function setupEventHandlers()
{
    $('#lzy-show-files-btn').click(function() {	// button to open file list and upload functions
        showFiles( this );
    });


    $('.lzy-editor-btn').click(function() {
        $editBtn = $(this);
        startEditor( $editBtn );
    });


    $('.lzy-editor-wrapper').click(function() {
        if (simplemde === null) {   // avoid starting editor if already started
            var $wrapper = $(this).parent();
            $editBtn = $('.lzy-editor-btn', $wrapper);
            startEditor( $editBtn );
        }
    });



    $('.lzy-edit-sitemap .lzy-editor-btn').click(function() {
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
            var $editBtns = $('main .lzy-editor-btn');
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
    $('body').addClass('lzy-editing');
    var $section = $editBtn.parent();
    $edWrapper = $('div', $section);
    origText = $edWrapper.html();
    $edWrapper.html('');
    $section.addClass('lzy-editing');
    filename = $section.attr('data-filename');

    $.ajax({
        type: "POST",
        url: systemPath + '_ajax_server.php?getfile',
        data: { lzy_filename: filename },
        success: function( data ) {
            var editingHtml = $('#lzy-editing-html').html();
            editingHtml = editingHtml.replace(/@data/, data);
            $edWrapper.html(editingHtml);
            simplemde = new SimpleMDE({
                element: $('textarea', $edWrapper)[0],
                spellChecker: false,
                placeholder: 'Write here...',
                autofocus: true,
                allowAtxHeaderWithoutSpace: true,
                previewRender: function(plainText, preview) { // Async method
                    if (lastMdUpdateTime) { // reset timer if there was one set
                        clearTimeout(lastMdUpdateTime);
                    }
                    lastMdUpdateTime = setTimeout(function(){   // invoke update with delay
                        customMarkdownParser(encodeURI(plainText), preview);
                    }, 1000);
                    return origText;
                },
                hideIcons: ["guide"],
                showIcons: ["code", "table", "horizontal-rule"],
                tabSize: 4,


            });
            $('.lzy-ed-selector').hide();

            $('.lzy-cancel-btn').click(function() {
                var href = window.location.pathname;
                window.location.href = href;
            });

            $('.lzy-save-btn').click(function() {
                saveData( false );
            });

            $('.lzy-done-btn').click(function() {
                saveData( true );
            });
        },
    });
} // startEditor





function startSitemapEditor() {
    $('body').addClass('lzy-editing');
    $('header').addClass('dispno');
    $edWrapper = $('main section');
    origText = $edWrapper.html();

    filename = 'sitemap';
    $.ajax({
        type: "POST",
        url: systemPath + '_ajax_server.php?getfile',
        data: { lzy_filename: filename },
        success: function( data ) {
            var editingHtml = $('#lzy-editing-html').html();
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
            $('.lzy-cancel-btn').click(function() {
                var href = window.location.pathname;
                window.location.href = href;
            });

            $('.lzy-save-btn').click(function() {
                saveSitemapData( false );
            });

            $('.lzy-done-btn').click(function() {
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
    $('#lzy-fileupload').show();

    $.ajax({	// load uploader (from main.js)
        // Uncomment the following to send cross-domain cookies:
        //xhrFields: {withCredentials: true},
        url: $('#lzy-fileupload').lzy-fileupload('option', 'url'),
        dataType: 'json',
        context: $('#lzy-fileupload')[0]
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
    sitemap = encodeURI(sitemap);   // -> php urldecode() to decode
	$.ajax({
		type: "POST",
		url: '?lzy-save',
		// url: systemPath + '?save',
		data: { lzy_filename: 'sitemap', sitemap: sitemap },
		success: function( data ) {
			if (leaveEditor) {
				// var href = window.location.pathname;
				location.href = location.pathname;
				// window.location.href = window.location.pathname;
			} else {
                $('textarea.lzy-editor').html(data);
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
		url: '?lzy-compile&lzy-save',
        'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8',
		// url: systemPath + '?compile&save',
		data: { lzy_filename: filename, lzy_md: mdStr },
		success: function( data ) {
			if (leaveEditor) {
				// var href = window.location.pathname;
				// window.location.href = window.location.pathname;
                location.reload();
			} else {
                $('textarea.lzy-editor').html(data);
			}
		},
	});
} // saveData




function customMarkdownParser(mdStr, preview)
{   // requests the server to return compiled markdown
    $.ajax({
        type: "POST",
        url: systemPath + '?lzy-compile',
        data: { lzy_md: mdStr },
        async: true,
        success: function( html ) {
            preview.innerHTML = html;
            origText = html;
        }

    });
} // customMarkdownParser





function hideElements( state ) {
    if (typeof admin_hideWhileEditing == 'object') {
        admin_hideWhileEditing.forEach(function (elem) {
            if (state || (typeof state == 'unknown')) {
                $(elem).hide();
            } else {
                $(elem).show();
            }
        });
    }
    if (state || (typeof state == 'unknown')) {
        $('.lzy-admin-hideWhileEditing').hide();
    } else {
        $('.lzy-admin-hideWhileEditing').show();
    }
} // hideElements
