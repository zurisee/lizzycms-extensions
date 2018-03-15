<?php

//....................................................
function renderMenu($site, $page, $options)
{

    $navClass = (isset($options['navClass'])) ? ' '.$options['navClass'] : '';
    $showHidden = (isset($options['showHidden'])) ? $options['showHidden']: '';
    $title = (isset($options['title'])) ? "<a class=\"navbar-brand\" href=\"#\">{$options['title']}</a>" : '';

    $nav = _renderBootstrapMenu(0, $site, 'nav navbar-nav', '', 'nav-link', false, '', $showHidden);

    $out = <<<EOT

        <nav class="navbar navbar-default">
          <div class="container-fluid">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header">
              <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
                <span class="sr-only">{{ Toggle navigation }}</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
              </button>
              <a class="navbar-brand" href="#">$title</a>
            </div>
            <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">

$nav

            </div><!-- /.navbar-collapse -->
          </div><!-- /.container-fluid -->
        </nav>
EOT;
    return $out;

} // renderBootstrapMenu

//....................................................
function _renderBootstrapMenu($inx, $site, $ulClass, $liClass, $aClass, $tree, $indent, $showHidden = false)
{
    $indent = str_replace('\t', "\t", $indent);
    if ($indent == '') {
        $indent = "\t";
    }
    if (!$tree) {
        $tree = $site->getSiteTree();
    }

    if ($mutliLang = $site->config->site_multiLanguageSupport) {
        $currLang = $site->config->lang;
    }

    $out = "$indent\t<ul class='$ulClass' aria-labelledby='dropdown$inx'>\n";                                 // ul

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
            $list = $site->getSiteList();
            $targ = $list[$targInx];
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
            if ($site->config->feature_selflinkAvoid) {
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
                $out .= "$indent\t    <a href='$path'$target class='$aClass dropdown-toggle' id='dropdown$inx' data-toggle=\"dropdown\" aria-haspopup=\"true\" aria-expanded=\"false\"><span style='width: calc(100% - 1em);'>$name</span></a>\n";
                $out .= _renderBootstrapMenu($inx, $site,'dropdown-menu', '', 'dropdown-item', $elem, "$indent\t\t", $showHidden);
                $out .= "$indent\t  </li>\n";


            } else {
                $out .= "$indent\t  <li class='$liClass'><a href='$path' class='$aClass' $target>$name</a></li>\n";
            }
        }
    }

    $out .= "$indent</ul>\n";
    return $out;
} // _renderBootstrapMenu

