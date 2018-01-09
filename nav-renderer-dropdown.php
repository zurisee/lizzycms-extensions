<?php

//....................................................
function renderMenu($site, $page, $options)
{
//    $page->addCssFiles('~sys/third-party/bootstrap4/css/bootstrap.min.css');
//    $page->addCssFiles("<link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css\" integrity=\"sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ\" crossorigin=\"anonymous\">");
//    $page->addCssFiles("~sys/css/bootstrap-menu.css");

//    $page->addJqFiles(['~sys/third-party/tether.js/tether.min.js', '~sys/third-party/bootstrap4/js/bootstrap.min.js']);
//    $page->addJqFiles('<script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js" integrity="sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb" crossorigin="anonymous"></script>');
//    $page->addJqFiles('~sys/third-party/bootstrap/js/bootstrap.min.js');
//    $page->addJqFiles('<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>');


    $jq = <<<EOT
    
        function dropdown(id) {
            $('#'+id).toggleClass('w3-show');
        }
    
EOT;

    $page->addJs($jq);

    $navClass = (isset($options['navClass'])) ? ' '.$options['navClass'] : '';
    $showHidden = (isset($options['showHidden'])) ? $options['showHidden']: '';
    $title = (isset($options['title'])) ? "<a class=\"navbar-brand\" href=\"#\">{$options['title']}</a>" : '';

    $nav = _renderDropdownMenu(0, $site, '', '', '', false, '', $showHidden);

    $out = <<<EOT
    
    <!-- Dropdown Navigation -->
    
	<nav class="$navClass">
      <button class="hamburger-button" tabindex="0" type="button" data-toggle="collapse" data-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
        <span class=""></span>
      </button>
      $title
      <div class="" id="mainNavbar">
$nav
      </div> 
	</nav>

    <!-- /Dropdown Navigation -->
    
EOT;
    return $out;

} // renderMenu

//....................................................
function _renderDropdownMenu($inx, $site, $ulClass, $liClass, $aClass, $tree, $indent, $showHidden = false)
{
    $indent = str_replace('\t', "\t", $indent);
    if ($indent == '') {
        $indent = "\t";
    }
    if (!$tree) {
        $tree = $site->getSiteTree();
    }

    if ($mutliLang = $site->config->multiLanguageSupport) {
        $currLang = $site->config->lang;
    }

    $out = "$indent\t<ul id='sub$inx' class='$ulClass' aria-labelledby='dropdown$inx'>\n";                                 // ul

    $liClass0 = $liClass;
    foreach($tree as $n => $elem) {
        $inx++;
        $liClass = $liClass0;
        if (!is_int($n)) { continue; }
        $currClass = '';
        if ($mutliLang && isset($elem[$currLang])) {
            $name = $elem[$currLang];
        } else {
            $name = $elem['name'];
        }
        if (isset($elem['goto'])) {
            $targInx = $site->findSiteElem($elem['goto']);
            $targ = $site->list[$targInx];
            $name = $targ['name'];
            $path = $targ['folder'];
        } else {
            $path = (isset($elem['folder'])) ? $elem['folder'] : '';
        }
        if ($path == '') {
            $path = '~/';
        } elseif (substr($path, 0, 2) != '~/') {
            $path = '~/'.$path;
        }
        $path .= $elem['urlExt'];

        if ($elem['isCurrPage']) {
            $liClass .= ' curr active';
            if ($site->config->selflinkAvoid) {
                $path = '#main';
            }
        } elseif ($elem['active']) {
            $liClass .= ' active';
        }
        if (isset($elem['target'])) {
            $target = " target='{$elem['target']}'";
        } else {
            $target = '';
        }
        if ((!$elem['hide']) || $showHidden) {
            if (isset($elem[0])) {	                                // itemhas children ----------------
                $out .= "$indent\t  <!-- =============================== -->\n";
                $out .= "$indent\t  <li class='$liClass dropdown'>\n";
                $out .= "$indent\t    <button tabindex=\"0\" onclick=\"dropdown('sub$inx')\"><span style='width: calc(100% - 1em);'>$name</span></button>\n";
//                $out .= "$indent\t    <a href='#'$target class='$aClass onclick='dropdown(\"sub$inx\")'><span style='width: calc(100% - 1em);'>$name</span></a>\n";
//                $out .= "$indent\t    <a href='$path'$target class='$aClass dropdown-toggle' id='dropdown$inx' data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\"><span style='width: calc(100% - 1em);'>$name</span></a>\n";
                $out .= _renderDropdownMenu($inx, $site,'dropdown-content', '', '', $elem, "$indent\t\t", $showHidden);
                $out .= "$indent\t  </li>\n";


            } else {
                $out .= "$indent\t  <li class='$liClass'><a href='$path' class='$aClass' $target>$name</a></li>\n";
            }
        }
    }

    $out .= "$indent</ul>\n";
    return $out;
} // _renderDropdownMenu

