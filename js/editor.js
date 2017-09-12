/*
**	needs overhaul! -> implement as object!
*/

var hideWhileEditing = []; //  ['.header-redstripe', '.header-bar', '.header-redbox'];

var lastMdUpdateTime = 0;
var lastMdUpdate = '';
var $edWrapper = null;
var origText = '';
var simplemde = null;
var cmdKeyPressed = false;
var filename = '';
var $editBtn = null;

$( document ).ready(function() {

	$('#btn_show_files').click(function() {	// button to open file list and upload functions
		$(this).hide();
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

	});
	
    $('.btn_editor').click(function() {
		hideElements(true);
        $editBtn = $(this);
        startEditor( $editBtn );
    });
    $('.editor_wrapper').click(function() {
		if (simplemde === null) {
			var $wrapper = $(this).parent();
			$editBtn = $('.btn_editor', $wrapper);
			startEditor( $editBtn );
        }
    });

	document.onkeydown = function(e) {
		var href = window.location.pathname;
		var keycode = (window.event) ? event.keyCode : e.keyCode;
		//console.log(keycode);
        if (keycode == 115) {   // F4
			var $editBtns = $('.btn_editor');
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
		if (keycode == 27) {	// ESC -> end editing mode     
			if ( simplemde === null) {
				var call = window.location.pathname.replace(/\?.*/, '') + '?edit=false';
				window.location.assign(call);
			} else {
				abortEditor();
			}
		}
		if ((keycode == 224) ||			// CMD (Firefox)
			(keycode == 91) ||			// CMD (Chrome, Safari, Opera)
			(keycode == 17)) {			// Ctrl (Win10)
			cmdKeyPressed = true;
			return;
		}
		if (cmdKeyPressed && ( simplemde !== null ) &&
			((keycode == 13) || (keycode == 83))) {	// Meta+Enter or Meta-S
			saveData( true );
		}
		cmdKeyPressed = false;
    };

    function startEditor( $editBtn ) {
        $editBtn.hide();
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
                $('#btn_cancel').click(function() {
					var href = window.location.pathname;
					window.location.href = href;
                });
  
                $('#btn_save').click(function() {
					saveData( false );
                });

                $('#btn_done').click(function() {
					saveData( true );
                });
            },
        });
    }
});

function abortEditor()
{
	hideElements(false);
	$editBtn.show();
	simplemde.toTextArea();
	simplemde = null;
	$edWrapper.html(origText);
} // abortEditor


function saveData( leaveEditor )
{
	if (leaveEditor) {
		hideElements(false);
		$editBtn.show();
	}
	var mdStr = simplemde.value();
	$.ajax({
		type: "POST",
		url: systemPath + '?compile&save',
		data: { filename: filename, md: mdStr },
		success: function( data ) {
			if (leaveEditor) {
				var href = window.location.pathname;
				window.location.href = href;
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
	hideWhileEditing.forEach(function(elem) {
		if (state) {
			$(elem).hide();
		} else {
			$(elem).show();
		}
	});
}


