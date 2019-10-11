<?php
$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');

    $name = $this->getArg($macroName, 'name', 'Name of the requested icon', '');
    $class = $this->getArg($macroName, 'class', '(optional) Class to be applied to the icon', '');
    $id = $this->getArg($macroName, 'id', '(optional) Id to be applied to the icon', '');
    $sizeFactor = $this->getArg($macroName, 'sizeFactor', '(optional) Factor by which the icon will be scaled up. E.g. sizeFactor:1.5', '1');
    $color = $this->getArg($macroName, 'color', '(optional) Color to be applied to the icon.', '');

    $name = strtolower($name);
    $supportedIcons = ',cancel,cloud,config,copy,desktop,doc,down,edit,enlarge,enlarge2,error,exit,geo,gsm,hide,info,link,locked,mail,map,menu,mobile,newwin,nosmile,ok,paste,pdf,pdf2,reduce,reduce2,search,send,settings,show,show2,slack,smile,sms,tel,trash,unlocked,up,user,';
    if ($name === 'help') {
        $str = str_replace(',', "\n- ", rtrim($supportedIcons, ','));
        $this->compileMd = true;
        return "::: .lzy-icon-help-list\n## Supported Icon Names:\n\n $str\n:::\n";
    }
    
    if (strpos($supportedIcons, ",$name,") === false) {
        return "<div class='lzy-warning'>Icon name unknown: '$name'";
    }
    if ($id) {
        $id = " id='$id'";
    }
    if ($class) {
        $class = " $class";
    }
    $style = '';
    if ($sizeFactor) {
        $style = "--lzy-icon-factor:$sizeFactor;";
    }
    if ($color) {
        $style = "$style color:$color;";
    }
    if ($style) {
        $style = " style='$style'";
    }
    $this->optionAddNoComment = true;

    $str = "<span$id class='lzy-icon lzy-icon-$name$class'$style></span>";
	return $str;
});
