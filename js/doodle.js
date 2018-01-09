
$( document ).ready(function() {
	$('.doodle_answer input').change(function() {	// update sums
		var targ = $( this ).attr('class');
		var $targ = $('.doodle-sums-row #'+targ);
		var diff = $( this ).is(":checked") ? 1 : -1;
		var val0 = $targ.text();
		if (val0 == '') {
            val0 = 0;
        } else {
        	val0 = parseInt(val0);
        }
		var val = val0 + diff;
		$targ.text( val );
	});


    $('.doodle_answer_submit').click(function(e) {	// submit
        var $thisForm = $( this ).parent().parent();
        var name = $('.doodle_entry_name', $thisForm).val();
        if (!name) {
            e.preventDefault();
        } else {
            $('.doodle-name-elem', $thisForm).each(function() {
                var n = $(this).text();
                if (name == n) {
                    if (!confirm('Name exists, overwrite that entry?')) {
                        e.preventDefault();
                        $('#doodle_entry_name', $thisForm).val('');
                    }
                }
            });
        }
    });
});

