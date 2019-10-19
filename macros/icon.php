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
    $supportedIcons = ',calendar,error,user,settings,cloud,desktop,mobile,config,tel,geo,map,sms,info,doc,docs,trash,enlarge,reduce,smile,nosmile,paste2,link,menu,newwin,edit,mail,show2,enlarge2,reduce2,ok,cancel,locked,unlocked,exit,favorite,send,show,hide,source,search,up,down,slack,pdf2,pdf,gsm,upload,download,globe,key,bubble,stack,attachment,heart,fullscreen,cut,copy,paste,cancel2,clock,danger,wait,speed,crosshairs,picture,pictures,movie,sync,reload,power,insert,wifi,vol-up,volume,vol-down,flag,play,stop,mute,rec,forward,backward,start,print,save,pause,end,';
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
