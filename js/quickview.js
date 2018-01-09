

$.fn.quickview = function ()
{
	$( this ).each( function() {
		var $this = $(this);
		var id = $(this).attr('id');
        var _id = '#' + id;
        var _idQuickview = _id + '-quickview';
        var $id = $( _id );

		var qvSrc = $this.attr('data-qv-src');
		var qvWidth = $this.attr('data-qv-width');
		var qvHeight = $this.attr('data-qv-height');
		if ((typeof qvSrc == 'undefined') || (typeof qvWidth == 'undefined') || (typeof qvHeight == 'undefined')) {
			console.log('Error: data-attribute missing for ' + id);
			return;
		}
		$( 'body' ).append("<div id='" + id + "-quickview' class='quickview-overlay'><img src='" + qvSrc + "' width='" + qvWidth + "' height='" + qvHeight + "' aria-hidden='true' /><span class='sr-only'>This is only visual enhancement. No additional information is provided. Press Escape to go back.</span></div>");
		var padding = 30;
		var xL = padding;
		var yL = padding;
		var x;
		var y;
		var w;
		var h;

		// open Quickview:
        $id.click(function() {
			var vpWidth = $(window).width();
			var vpHeight = $(window).height();
			padding = parseInt( Math.min(vpWidth, vpHeight) * 0.02);
			var overlayOffset = $( _idQuickview ).offset();
			var vpTopOffset = overlayOffset.top;
			w = $id.width();
			h = $id.height();
            y = $id[0].getBoundingClientRect().top;
            x = $id[0].getBoundingClientRect().left;
			var wOrig = parseInt($( _idQuickview + ' img' ).attr('width'));
			var hOrig = parseInt($( _idQuickview + ' img' ).attr('height'));
			var aRatio = hOrig / wOrig;
			var wL = Math.min(wOrig, (vpWidth - 2*padding));
			var hL = Math.min(hOrig, (vpHeight - 2*padding));
			if ((hL / wL) > aRatio) {
				hL = wL * aRatio;
			} else {
				wL = hL / aRatio;
			}
			var xL = (vpWidth - wL) / 2;
			var yL = (vpHeight - hL) / 2;
			$( _idQuickview ).addClass('quickview-overlay-active').attr({ 'data-qv-x': x, 'data-qv-y': y, 'data-qv-w': w, 'data-qv-h': h });
			$( _idQuickview + ' img' ).css({ left: x, top: y, width: w-10, height: h-20, zIndex: 9999 });
			$( _idQuickview + ' img' ).animate({ width: wL, height: hL, left: xL, top: yL, opacity: 1 }, 200);
		});

		// open Quickview:
		$( _idQuickview + ' img' ).click( function () {	// click on image
 			qvClose();
		});
		$( _idQuickview ).click( function () {			// click on background
			qvClose();
		});

        $( 'body' ).keydown( function (e) {
			var keycode = e.which;
			if (keycode == 27) {
				qvClose();
			}
        });
	}); // each
} // quickView

function qvClose()
{
	$( '.quickview-overlay-active').each( function() {
		var $this = $(this);
		var $img = $( 'img', $this);
		var x = $this.attr( 'data-qv-x' );
		var y = $this.attr( 'data-qv-y' );
		var w = $this.attr( 'data-qv-w' );
		var h = $this.attr( 'data-qv-h' );
		$img.animate({ width: w-10, height: h-20, left: x, top: y }, 200);
		setTimeout( function() { 
			$this.removeClass('quickview-overlay-active').attr('style', '');
 			$img.css('opacity', 0).attr('style', '');
		}, 200);
	});
} // qvClose