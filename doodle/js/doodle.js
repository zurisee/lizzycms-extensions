
$( document ).ready(function() {
	$('.lzy-doodle-answer input').change(function() {	// update sums
        var $thisForm = $( this ).closest('form');
        var n = $('[name=lzy-doodle-number-of-options]', $thisForm).val();
        var type = $('[name=lzy-doodle-type]', $thisForm).val();

        // create empty sums array:
        var sums = [];
        for (var c=0; c<n; c++) {
            sums[c] = 0;
        }

        // get sums of checked elements in rows:
        var r = 0;
        $('.lzy-doodle-answer-row', $thisForm).each(function() {
            var $this = $( this );
            var c = 0;
            $('.lzy-doodle-elem input', $this).each(function() {
                // if (typeof $(this).attr('checked') != 'undefined') {
                if ( $(this).prop('checked') ) {
                    sums[c] += 1;
                }
                c++;
            });
            r++;
        });

        // add current choice:
        var c = 0;
        $('.lzy-doodle-new-entry-row .lzy-doodle-answer input', $thisForm).each(function() {
            // if (typeof $(this).attr('checked') != 'undefined') {
            if ($(this).prop('checked')) {
                sums[c] += 1;
            }
            c++;
        });

        // write to sums row:
        var c = 0;
        $('.lzy-doodle-sums-row .lzy-doodle-elem', $thisForm).each(function() {
            $(this).text(sums[c]);
            c++;
        });
	});


    $('.lzy-doodle-answer-submit').click(function(e) {	// submit
        var $thisForm = $( this ).parent().parent();
        var name = $('.lzy-doodle-entry-name', $thisForm).val();
        if (!name) {
            e.preventDefault();
            $('.lzy-doodle-err-msg').show();
        } else {
            $('.lzy-doodle-name-elem', $thisForm).each(function() {
                var n = $(this).text();
                if (name === n) {
                    var ow = $('[data-lzy-doodle-ow=true]', $thisForm).length;
                    if (ow) {
                        if (!confirm('Name exists, overwrite that entry?')) {
                            e.preventDefault();
                            $('#lzy-doodle-entry-name', $thisForm).val('');
                        }
                    } else {
                        alert('Name already exists');
                    }
                }
            });
        }
    });
});

