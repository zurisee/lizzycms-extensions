

$.fn.quickview = function ()
{
    // initialize:
	$( this ).each( function() {
		var $this = $(this);
		var id = $(this).attr('id');
        var lateImgLoading = $this.hasClass('lzy-late-loading');

		var qvSrc = $this.attr('data-qv-src');
		var qvWidth = $this.attr('data-qv-width');
		var qvHeight = $this.attr('data-qv-height');
		if ((typeof qvSrc == 'undefined') || (typeof qvWidth == 'undefined') || (typeof qvHeight == 'undefined')) {
			console.log('Error: data-attribute missing for ' + id);
			return;
		}

		// find srcset for inserting in quickview-overlay:
        var qvSrcset = $this.attr('srcset');
        if (typeof qvSrcset != 'undefined') {
            if (lateImgLoading) {
                qvSrcset = " data-srcset='"+qvSrcset+"'";
            } else {
                qvSrcset = " srcset='"+qvSrcset+"'";
            }
        } else {
            qvSrcset = '';
        }

        // create quickview overlay:
        if (lateImgLoading) {
            $('body').append("<div id='" + id + "-quickview' class='lzy-quickview-overlay'><img class='lzy-laziest-load' data-src='" + qvSrc + "'" + qvSrcset + " width='" + qvWidth + "' height='" + qvHeight + "' aria-hidden='true' /><span class='sr-only'>This is only visual enhancement. No additional information is provided. Press Escape to go back.</span></div>");
        } else {
            $('body').append("<div id='" + id + "-quickview' class='lzy-quickview-overlay'><img src='" + qvSrc  + "'" + qvSrcset + " width='" + qvWidth + "' height='" + qvHeight + "' aria-hidden='true' /><span class='sr-only'>This is only visual enhancement. No additional information is provided. Press Escape to go back.</span></div>");
        }
	}); // each


    // open large Quickview image:
    $('img.lzy-quickview').click(function() {
        var $this = $( this );
        if ( $this.parent().prop("tagName") == 'A' ) {
            return; // don't quickview, open link directly
        }
        var id = $this.attr('id');
        var _id = '#' + id;
        var $id = $( _id );
        var _idQuickview = _id + '-quickview';
        var $idQuickview = $( _idQuickview );
        var $idQuickviewImg = $( _idQuickview + ' img' );

        var vpWidth = $(window).width();
        var vpHeight = $(window).height();
        var padding = parseInt( Math.min(vpWidth, vpHeight) * 0.02);
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
        $idQuickview.addClass('lzy-quickview-overlay-active').attr({ 'data-qv-x': x, 'data-qv-y': y, 'data-qv-w': w, 'data-qv-h': h });
        $idQuickviewImg.css({ left: x, top: y, width: w-10, height: h-20, zIndex: 9999 });
        $idQuickviewImg.animate({ width: wL, height: hL, left: xL, top: yL, opacity: 1 }, 200);

        // in late-loading mode: load fullscreen image only when invoked:
        if ($idQuickviewImg.hasClass('lzy-laziest-load')) {
            var src = $idQuickviewImg.attr('data-src');
            if (typeof src != 'undefined') {
                console.log('late loading image ' + $idQuickviewImg.attr('data-src'));
                $idQuickviewImg.attr({srcset: $idQuickviewImg.attr('data-srcset') }).removeAttr('data-srcset');
                $idQuickviewImg.attr({src: $idQuickviewImg.attr('data-src')}).removeAttr('data-src');
            }
        }
        $( 'body' ).keydown( function (e) {
            if (e.which == 27) {
                qvClose();
            }
        });

    });


    // close Quickview:
    $( '.lzy-quickview-overlay img' ).click( function () {	// click on image
        qvClose();
    });
    $( '.lzy-quickview-overlay' ).click( function () {		// click on background
        qvClose();
    });

} // quickView


// late-loading:
$('img.lzy-late-loading').each(function() {
    var $this = $( this );
    console.log('late loading image ' + $this.attr('data-src'));
    $this.attr({src: $this.attr('data-src') }).removeAttr('data-src');
    $this.attr({srcset: $this.attr('data-srcset') }).removeAttr('data-srcset');
});


function qvClose()
{
	$( '.lzy-quickview-overlay-active').each( function() {
		var $this = $(this);
		var $img = $( 'img', $this);
		var x = $this.attr( 'data-qv-x' );
		var y = $this.attr( 'data-qv-y' );
		var w = $this.attr( 'data-qv-w' );
		var h = $this.attr( 'data-qv-h' );
		$img.animate({ width: w-10, height: h-20, left: x, top: y }, 200);
		setTimeout( function() { 
			$this.removeClass('lzy-quickview-overlay-active').attr('style', '');
 			$img.css('opacity', 0).attr('style', '');
		}, 200);
	});
} // qvClose