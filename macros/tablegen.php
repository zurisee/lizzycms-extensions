<?php
$page->addCssFiles(["~sys/css/tablegen.css", "EDITABLE_CSS"]);
$page->addJqFiles('JQUERY2, EDITABLE');

require_once SYSTEM_PATH.'htmltable.class.php';


$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $args = $this->getArgsArray($macroName);
    if (isset($args['dataSource']) && (strpos($args['dataSource'], '~') !== 0)) {
        $args['dataSource'] = resolvePath('~page/'.$args['dataSource']);
    }

    if (isset($args['class']) && (strpos($this->page->get('jq'), "\$('.editable')") === false)) {
		if (preg_match('/\beditable\b/', $args['class'])) {
			$this->page->addJQ("\t\t$('.{$args['class']}').editable();\n");
		}
	}



    $tabGen = new HtmlTable( $args );
    $table = $tabGen->render();
	return $table;
});
