<?php

class PopupWidget
{

    public function __construct($page)
    {
        $this->page = $page;
    }



    public function createPopupTemplate()
    {
        $str = <<<EOT

    <div id="lzy-popup-template" style="display:none;">
        <div class='lzy-popup-background'></div>
        <div class="lzy-popup-wrapper">
            <div class="lzy-popup-header"></div>
            <button class="lzy-popup-close-button">{{ lzy-popup-close-button }}</button>
            <div class="lzy-popup-body"></div>
        </div>    
    </div>

EOT;

        $this->page->addBodyEndInjections($str);

        $this->page->addCssFiles('POPUPS_CSS');
        $this->page->addJqFiles('POPUPS');

    }



    //-----------------------------------------------------------------------
    public function addPopup($index, $args)
    {
        $id = isset($args['id']) ? $args['id'] : "lzy-popup-widget$index";
        $class = isset($args['class']) ? $args['class'] : '';
        $header = isset($args['header']) ? $args['header'] : '';
        $anker = isset($args['anker']) ? $args['anker'] : '';
        $text = isset($args['text']) ? $args['text'] : '';
        $contentFrom = isset($args['contentFrom']) ? $args['contentFrom'] : '';
        $width = isset($args['width']) ? $args['width'] : '200';
        $height = isset($args['height']) ? $args['height'] : '80';
        $offsetX = isset($args['offsetX']) ? $args['offsetX'] : '0';
        $offsetY = isset($args['offsetY']) ? $args['offsetY'] : '0';
        $draggable = (isset($args['draggable']) && ($args['draggable'] !== 'false')) ? 'true' : 'false'; // default = false
        $triggerSource = isset($args['triggerSource']) ? $args['triggerSource'] : '';
        $triggerEvent = isset($args['triggerEvent']) ? $args['triggerEvent'] : '';
        $showCloseButton = (isset($args['showCloseButton']) && ($args['showCloseButton'] === 'false')) ? 'false' : 'true'; // default = true
        $closeOnBgClick = (isset($args['closeOnBgClick']) && ($args['closeOnBgClick'] !== 'false')) ? 'true' : 'false'; // default = false
        $lightbox = isset($args['lightbox']) ? $args['lightbox'] : 'false';
        $delay = isset($args['delay']) ? $args['delay'] : '0';


        if (!preg_match('/\d\w+$/', $width)) {
            $width .= 'px';
        }
        if (!preg_match('/\d\w+$/', $height)) {
            $height .= 'px';
        }
        if (!preg_match('/\d\w+$/', $offsetX)) {
            $offsetX .= 'px';
        }
        if (!preg_match('/\d\w+$/', $offsetY)) {
            $offsetY .= 'px';
        }

        if (isset($args[0])) {
            $text = $args[0];
        }
        if ($contentFrom) {
            $ch1 = $contentFrom{0};
            if (($ch1 != '#') && ($ch1 != '.')) {
                $contentFrom = '#' . $contentFrom;
            }
        }

        if ($showCloseButton == 'false') {
            $this->page->addCss( "#$id .lzy-popup-close-button { display: none; }");
        }

        if ($triggerSource) {
            $ch1 = $triggerSource{0};
            if (($ch1 != '#') && ($ch1 != '.')) {
                $triggerSource = '#' . $triggerSource;
            }
        }

        if ($draggable == 'true') {
            $this->page->addJqFiles('PANZOOM');
            $this->page->addJq("\$('#$id .lzy-popup-wrapper').panzoom();");
        }

        if (!$contentFrom && (strlen($text) > 512)) {
            $contentFrom = "#$id-body";
            $this->registerPopupContent($contentFrom, $text);
            $text = '';
        }
        if ($text) {
            $text = str_replace("'", "\\'", $text);
        }

        $jq = <<<EOT

            $('#$id.lzy-popup').lzyPopup({
                'index': '$index',
                'id': '$id',
                'class': '$class',
                'header': '$header',
                'text': '$text',
                'contentFrom': '$contentFrom',
                'anker': '$anker',
                'width': '$width',
                'height': '$height',
                'offsetX': '$offsetX',
                'offsetY': '$offsetY',
                'draggable': $draggable,
                'triggerSource': '$triggerSource',
                'triggerEvent': '$triggerEvent',
                'showCloseButton': $showCloseButton,
                'closeOnBgClick': $closeOnBgClick,
                'lightbox': $lightbox,
                'delay': $delay,
            });

EOT;
        $this->page->addJQ( $jq);

        return '';

    } // addPopup




    public function registerPopupContent($id, $popupBody)
    {
        if ($id{0} == '#') {
            $id = substr($id, 1);
        }
        $html = <<<EOT

    <div id="$id" style="display: none">
$popupBody
    </div>
EOT;

        $this->page->addBodyEndInjections($html);
    }




    public function renderHelp()
    {
        $str = <<<EOT
<h2>Options for macro <em>poup()</em></h2>
<dl>
	<dt>id:</dt>
		<dd>(optional identifier) ID applied to the outer wrapper <samp>.lzy-popup-instance</samp> </dd>
		
	<dt>class:</dt>
		<dd>(optional identifier) ID applied to the inner wrapper <samp>.lzy-popup-wrapper</samp> </dd>
		
	<dt>header:</dt>
		<dd>(optional text) If set, a header in <samp>.lzy-popup-header</samp> will be included in the popup box </dd>
		
	<dt>anker:</dt>
		<dd>(optional CSS-selector) If set, the popup will be located relative to the anker element</dd>
		
	<dt>text:</dt>
		<dd>(optional text) If set, it will be displayed as the popup content in <samp>.lzy-popup-body</samp></dd>
		
	<dt>contentFrom:</dt>
		<dd>(optional CSS-selector) If set, content of the corresponding element will be retrieved and displayed in the popup inside <samp>.lzy-popup-body</samp> </dd>
		
	<dt>width:</dt>
		<dd>(optional length) Will set the popup's width</dd>
		
	<dt>height:</dt>
		<dd>(optional length) Will set the popup's min-height</dd>
		
	<dt>offsetX:</dt>
		<dd>(optional length) If set, the popup will appear with a horizontal offset from the anker location (see above)</dd>
		
	<dt>offsetY:</dt>
		<dd>(optional length) If set, the popup will appear with a vertical offset from the anker point (see above)</dd>
		
	<dt>draggable:</dt>
		<dd>(optional) [true|false] Permits the popup to be moved around on screen (Default is true)</dd>
		
	<dt>triggerSource:</dt>
		<dd>(optional CSS-selector) Specifies the element that shall trigger opening the popup &ndash; if omitted the popup will appear immediately after loading</dd>
		
	<dt>triggerEvent:</dt>
		<dd>(optional) [click|double-click|right-click|hover] Specifies the type of event that shall trigger the popup</dd>
		
	<dt>closeOnBgClick:</dt>
		<dd>(optional) [true|false] If true, clicking on the background closes the popup. (Default is false)</dd>
		
	<dt>showCloseButton:</dt>
		<dd>(optional) [true|false] If true, a close button will be displayed in the upper right corner (Default is false)</dd>
		
	<dt>lightbox:</dt>
		<dd>(optional) [true|false] If true, the background will be grayed out <br />&rarr; essentially turns the widget into a modal window (Default is false)</dd>
		
	<dt>delay:</dt>
		<dd>(optional) [time in ms] If present, will delay opeing the pop by the specified delay in milliseconds</dd>
		
</dl>

EOT;

        return $str;
    }
}