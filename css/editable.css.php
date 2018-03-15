<?php
header("Content-type: text/css; charset: UTF-8");

/*
**	Editable Styles
*/

$editable = 'lzy-editable';
if (isset($_GET['editable'])) {
    $editable = $_GET['editable'];
}


$css = <<<EOT
.$editable {
	position: relative;
	border: none;
	padding: 0;
	overflow: hidden;
	width: 20em;
	height: 2em;
}
.$editable > * {
	box-sizing: border-box;
}
.lzy-editable_active {
	padding: 0;
}
.$editable input {
	background: #eff;
	border: 1px solid #ddd;
	padding: 0 5px;
	position: absolute;
	top: 0;
	left: 0;
	z-index: 90;
}
.$editable input:focus {
	background: #fff;
}
.$editable .lzy-locked {
	font-style: italic;
	cursor: not-allowed;
	background: #fee;
	animation-name: scrollleft;
	animation-duration: .5s;
	animation-iteration-count: infinite;
	animation-timing-function: linear;
	background: -webkit-repeating-linear-gradient(-45deg, transparent, transparent 5px, #ffe5d2 5px, #ffe5d2 10px), -webkit-linear-gradient(top, #fff3ea, #B79E9E);
	background: repeating-linear-gradient(-45deg, transparent, transparent 5px, #ffe5d2 5px, #ffe5d2 10px), linear-gradient(to bottom, #fff3ea, #B79E9E);
}
@keyframes scrollleft {
	from {background-position: 0px;}
	to {background-position: -14px;}
}

.lzy-edit_buttons, .lzy-edit_buttons button {
	position: relative;
	text-align: center;
	vertical-align: top;
	padding: 0;
	margin: 0;
}
.lzy-edit_buttons button {
	display: inline-block;
	margin-left: 4px;
	margin-top: 1px;
	font-size: 14pt;
}
div.lzy-edit_buttons  {
	position: absolute;
	top: 0;
	right: 0;
	z-index: 70;
}
.$editable.lzy-wait,
.$editable.lzy-wait input {
	cursor: wait!important;
}
.$editable::after {
    content: '⏳';
    position: absolute;
    top: 0; right: 0; bottom: 0; left: 0;
    text-align: center;
    font-size: 120%;
    padding-top: 2%;
    z-index: 0;
}

.$editable.lzy-wait::after {
    z-index: 100;
}

EOT;

exit($css);