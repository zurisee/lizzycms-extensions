// Folding Menu Support for Lizzy

(function ( $ ) {
	$('nav .lzy-has-children > a').click(function(e) {
		if (e.target.tagName != 'SPAN') {
			e.preventDefault();
		}
		$(this).parent().toggleClass('open');
		return;
	});
}( jQuery ));
