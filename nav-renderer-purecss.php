<?php
/*
 * Nav-Renderer for Pure-CSS
 */

//....................................................
function renderMenu($site, $page, $options)
{
    $page->addCssFiles('~sys/third-party/pure-css-layout/css/layouts/side-menu.css');
    $page->addJsFiles('~sys/third-party/pure-css-layout/js/ui.js');

    $navClass = (isset($options['navClass'])) ? ' ' . $options['navClass'] : '';
    $showHidden = (isset($options['showHidden'])) ? ' ' . $options['showHidden'] : '';
    $title = (isset($options['title'])) ? ' ' . $options['title'] : '';

    $tree = $site->getSiteTree();

    $out = renderPureCss($site, $tree, $navClass, $showHidden, $title);

    return $out;
}



//....................................................
function renderPureCss($site, $tree, $navClass, $showHidden = false, $title = '')
{
    $navClass = ($navClass) ? " class='$navClass'" : '';
    $title = ($title) ? "<h1>$title</h1>" : '';
    $nav = _renderPureCss($site, $tree, '', $showHidden);
    $out = <<<EOT
	<nav$navClass>
		$title
$nav
	</nav>
EOT;
    return $out;
} // renderPureCss



//....................................................
function _renderPureCss($site, $tree, $indent, $showHidden = false)
{
    $indent = str_replace('\t', "\t", $indent);
    if ($indent == '') {
        $indent = "\t";
    }
    $nav = '';
    $_nav = '';

    $ulClass = " class='pure-menu-list'";
    $out = "$nav$indent<ul$ulClass>\n";
    $aClass = " class='pure-menu-link'";
    foreach($tree as $n => $elem) {
        if (!is_int($n)) { continue; }

        $name = $elem['name'];
        if (isset($elem['goto'])) {
            $goto = $elem['goto'];
            if (preg_match('|https?\://|i', $goto)) {
                $path = $goto;
            } else {
                $targInx = $site->findSiteElem($goto);
                $list = $site->getSiteList();
                $targ = $list[$targInx];
                $path = $targ['folder'];
            }
        } else {
            $path = (isset($elem['folder'])) ? $elem['folder'] : '';
        }
        if ($path == '') {
            $path = '~/';
        } elseif ((substr($path, 0, 2) != '~/') && (substr($path, 0, 4) != 'http')) {
            $path = '~/'.$path;
        }
        $liClass = 'pure-menu-item';
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
            if (isset($elem[0])) {	// does it have children?
                if ($elem['hasChildren']) {
                    $liClass .= ' has-children';
                }
                if (($elem['isCurrPage']) || ($elem['active'])) {
                    $liClass .= ' open';
                }
                $liClass = trim($liClass);
                $liClass = ($liClass) ? " class='$liClass'" : '';
                $out .= "$indent\t<li$liClass><a href='$path'$aClass$target><span style='width: calc(100% - 1em);'>$name</span></a>\n";
                $out .= _renderPureCss($site, $elem, "$indent\t\t", $showHidden);
                $out .= "$indent\t</li>\n";
            } else {
                $liClass = trim($liClass);
                $liClass = ($liClass) ? " class='{$liClass}'" : '';
                $out .= "$indent\t<li$liClass><a href='$path'$aClass$target>$name</a></li>\n";
            }
        }
    }

    $out .= "$indent</ul>\n$_nav";
    return $out;
} // _renderPureCss
