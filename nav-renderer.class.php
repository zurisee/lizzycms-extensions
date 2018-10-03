<?php

// type: [top, side, sitemap, in-page] Specifies the type of output to be rendered.
// layout: [horizontal, vertical] Specifies direction of top-level items.
// animation: [dropdown, slidedown, collapsable] Defines the type of animation applied to the rendered tree.
// options: [top-level, curr-branch, hidden] These are filters that render a subset of items.


class NavRenderer
{
    public function __construct($lzy)
    {
        $this->lzy = $lzy;
        $this->tree = $lzy->siteStructure->getSiteTree();
        $this->page = $lzy->page;
        $this->config = $lzy->config;
    }




    public function render($options)
    {
        if ($this->tree == false) {     // it's a "one-pager", don't render any navigation
            return '';
        }

        $type =  trim($options['type']);

        if ($type) {

            // if a php-file exists with that name, execute it:
            $rendererFile = "nav-renderer-$type.php";
            if (file_exists($this->config->path_userCodePath . "$rendererFile")) {
                require_once($this->config->path_userCodePath . "$rendererFile");
                return renderMenu($this, $this->page, $options);

            } elseif (file_exists(SYSTEM_PATH . $rendererFile)) {
                require_once(SYSTEM_PATH . $rendererFile);
                return renderMenu($this, $this->page, $options);

            }
        }

        $options['listWrapper'] = ($options['listWrapper']) ? $options['listWrapper'] : 'div'; // listWrapper by default
        $layoutClass = '';

        // no specific php-file, so render standard output of predefined types:
        if ($type == 'top') {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-top-horizontal lzy-nav-indented lzy-nav-slidedown lzy-nav-animated lzy-nav-hover lzy-encapsulate');
            $options['options'] .= ' editable';

        } elseif ($type == 'side') {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-accordion lzy-nav-animated lzy-nav-indented lzy-nav-open-current lzy-encapsulate');
            $options['options'] .= ' editable';

        } elseif ($type == 'sitemap') {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-indented lzy-encapsulate');

        } elseif ($type == 'sitemap-accordion') {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-accordion lzy-nav-animated lzy-nav-indented lzy-encapsulate');

        } elseif ($type == 'breadcrumb') {
            return $this->renderBreadcrumb($options);

        } elseif ($type != '') {
            return "<div style='background-color: yellow;'>Warning: <br />Nav-Renderer: unknown type '$type'.<br />Please chose one of [top, top-dropdown, side, sitemap-accordion, sitemap, breadcrumb, in-page]</div>";

        } else {
            $layout = $options['layout'] ? $options['layout'] : '';
            if ($layout == 'vertical') {
                $layoutClass = '';
            } elseif ($layout == 'horizontal') { // horizontal
                $layoutClass = 'lzy-nav-top-horizontal';
            }

            $animation = $options['animation'] ? $options['animation'] : '';
            if ($animation) {
                $animClass = ' lzy-nav-animated';
            } else {
                $animClass = '';
            }

            $options['navClass'] = trim($options['navClass']."$layoutClass$animClass");
        }

        if (strpos($options['options'],'showTransition') !== false) {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-animated');
        }

        return $this->renderSitemap($options);
    } // render




    //....................................................
    public function renderBreadcrumb($options)
    {
        $elem = $this->lzy->siteStructure->currPageRec;
        $list = $this->lzy->siteStructure->getSiteList();
        $elems = [];
        while ($elem) {
            if ($elem['inx'] == 0) {
                break;
            }
            $elems[] = $elem;
            $parent = $elem['parent'];
            if ($parent === null) {
                break;
            }
            $elem = isset($list[$parent]) ? $list[$parent] : null;
        }
        $elems = array_reverse($elems);

        $sep = "<span class='lzy-nav-breadcrumb-separator'>&gt;</span>";

        $link = "<a href='~/{$list[0]['folder']}'>{$list[0]['name']}</a>";
        $out = "<li>$link</li>";
        foreach ($elems as $rec) {
            $link = "<a href='~/{$rec['folder']}'>{$rec['name']}</a>";
            $out .= "<li>$sep$link</li>";
        }

        $out = <<<EOT
    <ol class='lzy-nav-breadcrumb'>$out</ol>
EOT;

        return $out;
    } // renderBreadcrumb




    //....................................................
    public function renderSitemap($options)
    {
        $type = isset($options[0]) ? $options[0] :  (isset($options['type']) ? $options['type'] : '');
        $navClass = (isset($options['navClass'])) ? $options['navClass']: '';
        $ulClass = (isset($options['ulClass'])) ? $options['ulClass']: '';
        $liClass = (isset($options['liClass'])) ? $options['liClass']: '';
        $hasChildrenClass = (isset($options['hasChildrenClass'])) ? $options['hasChildrenClass']: '';
        $aClass = (isset($options['aClass'])) ? $options['aClass']: '';
        $navWrapperClass = (isset($options['navWrapperClass'])) ? $options['navWrapperClass']: '';
        $navOptions = (isset($options['options'])) ? $options['options']: '';
        $title = (isset($options['title'])) ? $options['title']: '';
        $this->listTag = (isset($options['listTag'])) ? $options['listTag']: 'ol';
        $this->listWrapper = (isset($options['listWrapper'])) ? $options['listWrapper']: '';

        $this->ulClass = $ulClass;
        $this->liClass = $liClass;
        $this->hasChildrenClass = ($hasChildrenClass) ? $hasChildrenClass : 'has-children';
        $this->aClass  = $aClass;

        $nav = $this->_renderSitemap(false, $type, 0, "\t\t", $navOptions);

        $navClass = trim('lzy-nav '.$navClass);
        $navClass = " class='$navClass'";
        $navWrapperClass = $navWrapperClass ? " $navWrapperClass" : '';
        if ($title && (strpos($title, '<') === false)) {
            $title = "<h1>$title</h1>";
        }
        if (($this->lzy->getEditingMode()) && (strpos($options['options'], 'editable') !== false)) {
            $dataAttr = " data-lzy-filename='sitemap'";
            $editClass = 'lzy-src-wrapper lzy-edit-sitemap ';
            $edWrapper = <<<EOT

      <div id='lzy-editor-wrapper1' class='lzy-sitemap-editor-wrapper'>

EOT;
            $_edWrapper = <<<EOT

      </div><!-- /lzy-editor-wrapper -->

EOT;

        } else {
            $dataAttr = '';
            $editClass = '';
            $edWrapper = '';
            $_edWrapper = '';
        }

        // lzy-nav-accordion relies on js, add some code to cope if js is off:
        $this->addNoScriptCode($navClass);

        $out = <<<EOT

  <div class='{$editClass}lzy-nav-wrapper$navWrapperClass'$dataAttr>$edWrapper
$title	  <nav$navClass>
$nav
	  </nav>
$_edWrapper  </div>

EOT;
        return $out;
    } // renderSitemap




    //....................................................
    private function _renderSitemap($tree, $type, $level, $indent, $navOptions = false)
    {
        $level++;
        $indent = str_replace('\t', "\t", $indent);
        if ($indent == '') {
            $indent = "\t";
        }
        if (!$tree) {
            $tree = $this->tree;
        }

        // options: [top-level, curr-branch, hidden] These are filters that render a subset of items.

        $stop = false;
        if (strpos($navOptions,'top-level') !== false) {
            $stop = true;
        }
        $showHidden = false;
        if (strpos($navOptions,'hidden') !== false) {
            $showHidden = true;
        }
        $currBranch = false;
        if (strpos($navOptions,'curr-branch') !== false) {
            $currBranch = true;
        }


        if ($mutliLang = $this->config->site_multiLanguageSupport) {
            $currLang = $this->config->lang;
        }
        if ($this->listTag == 'div') {
            $li = 'div';
        } else {
            $li = 'li';
        }

        if ($this->listWrapper) {
            $listWrapper = "$indent\t  <{$this->listWrapper}>\n";
            $_listWrapper = "$indent\t  </{$this->listWrapper}>\n";
        } else {
            $listWrapper = '';
            $_listWrapper = '';
        }

        $ulClass = ($this->ulClass) ? " class='{$this->ulClass}'" : '';

        $out = "$indent<{$this->listTag}$ulClass>\n";

        $aClass = ($this->aClass) ? " class='{$this->aClass}'" : '';
        foreach($tree as $n => $elem) {
            if (!is_int($n)) { continue; }
            $currClass = '';
            if ($mutliLang && isset($elem[$currLang])) {
                $name = $elem[$currLang];
            } else {
                $name = $elem['name'];
            }
            if (isset($elem['goto'])) {
                $targInx = $this->lzy->siteStructure->findSiteElem($elem['goto']);
                $list = $this->lzy->siteStructure->getSiteList();
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
            $liClass = $this->liClass." lzy-lvl$level";
            if ($elem['isCurrPage']) {
                $liClass .= ' curr active';
                if ($this->config->feature_selflinkAvoid) {
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
            $activeAncestor = $this->lzy->siteStructure->hasActiveAncestor($elem);
            if ($currBranch && !$activeAncestor) {
                continue;
            }
            if ((!$elem['hide']) || $showHidden) {
                if (!$stop && isset($elem[0])) {	// does it have children?
                    if ($elem['hasChildren']) {
                        $liClass .= ' '.$this->hasChildrenClass;
                    }
                    $liClass = trim($liClass);
                    $liClass = ($liClass) ? " class='$liClass'" : '';
                    $out .= "$indent\t<$li$liClass><a href='$path'$aClass$target>$name</a>\n$listWrapper";

                    $out .= $this->_renderSitemap($elem, $type, $level, "$indent\t\t", $navOptions);

                    $out .= "$_listWrapper$indent\t  </$li>\n";

                } else {
                    $liClass = trim($liClass);
                    $liClass = ($liClass) ? " class='{$liClass}'" : '';
                    $out .= "$indent\t<$li$liClass><a href='$path'$aClass$target>$name</a></$li>\n";
                }
            }
        }

        $out .= "$indent</{$this->listTag}>\n";
        return $out;
    } // _renderSitemap







    //....................................................
    public function renderDropdownMenu($options)
    {
        $type =  isset($options[0]) ? $options[0] :  $options['type'];
        $navClass = (isset($options['navClass'])) ? $options['navClass']: '';
        $ulClass = (isset($options['ulClass'])) ? $options['ulClass']: '';
        $liClass = (isset($options['liClass'])) ? $options['liClass']: '';
        $hasChildrenClass = (isset($options['hasChildrenClass'])) ? $options['hasChildrenClass']: '';
        $aClass = (isset($options['aClass'])) ? $options['aClass']: '';
        $navWrapperClass = (isset($options['navWrapperClass'])) ? $options['navWrapperClass']: '';
        $showHidden = (isset($options['showHidden'])) ? $options['showHidden']: '';
        $title = (isset($options['title'])) ? $options['title']: '';
        $this->dropDownIndicatorPrefix = (isset($options['dropDownIndicatorPrefix'])) ? $options['dropDownIndicatorPrefix']: ''; //'▸&nbsp;'
        $this->dropDownIndicatorPostfix = (isset($options['dropDownIndicatorPostfix'])) ? $options['dropDownIndicatorPostfix']: ' &hellip;';
        $this->listTag = (isset($options['listTag'])) ? $options['listTag']: 'ol';
        $this->listWrapper = (isset($options['listWrapper'])) ? $options['listWrapper']: '';

        $this->ulClass = $ulClass;
        $this->liClass = $liClass;
        $this->hasChildrenClass = ($hasChildrenClass) ? $hasChildrenClass : 'has-children';
        $this->aClass  = $aClass;

        if ($title && (strpos($title, '<') === false)) {
            $title = "<h1>$title</h1>";
        }
        $nav = $this->_renderDropdownMenu($navClass, false, $type, '', $showHidden);

        $navClass = trim('lzy-nav '.$navClass);
        $navClass = " class='$navClass'";
        $navWrapperClass = $navWrapperClass ? " $navWrapperClass" : '';

        if (strpos($options['options'], 'editable') !== false) {
            $dataAttr = " data-lzy-filename='sitemap'";
            $editClass = 'lzy-src-wrapper lzy-edit-sitemap ';
            $edWrapper = <<<EOT

      <div id='lzy-editor-wrapper1' class='lzy-sitemap-editor-wrapper'>

EOT;
            $_edWrapper = <<<EOT

      </div><!-- /lzy-editor-wrapper -->

EOT;
//            $_edWrapper = <<<EOT
//
//      </div><!-- /lzy-editor-wrapper -->
//    </section><!-- /lzy-src-wrapper -->
//
//EOT;

        } else {
            $dataAttr = '';
            $editClass = '';
            $edWrapper = '';
            $_edWrapper = '';
        }

        $out = <<<EOT

  <div class='{$editClass}lzy-nav-wrapper$navWrapperClass'$dataAttr>$edWrapper
	  <nav$navClass>
$nav
	  </nav>
    <div style="clear:left;"></div>
$_edWrapper  </div>

EOT;

        return $out;
    } // renderDropdownMenu



    //....................................................
    private function _renderDropdownMenu($navClass, $tree, $type, $indent, $showHidden = false)
    {
        $indent = str_replace('\t', "\t", $indent);
        if ($indent == '') {
            $indent = "\t";
        }
        $dropDownIndicatorPrefix = $this->dropDownIndicatorPrefix;
        $dropDownIndicatorPostfix = $this->dropDownIndicatorPostfix;
        if (!$tree) {
            $tree = $this->tree;
//            $dropDownIndicatorPostfix = '';
        }
        $stop = ($type == 'top-level');
//        $nav = '';
//        $_nav = '';

        if ($mutliLang = $this->config->site_multiLanguageSupport) {
            $currLang = $this->config->lang;
        }

        if ($this->listTag == 'div') {
            $li = 'div';
        } else {
            $li = 'li';
        }

        if ($this->listWrapper) {
            $listWrapper = "\n$indent\t  <{$this->listWrapper}>";
            $_listWrapper = "$indent\t  </{$this->listWrapper}>\n";
        } else {
            $listWrapper = '';
            $_listWrapper = '';
        }

        $ulClass = ($this->ulClass) ? " class='{$this->ulClass}'" : '';
		$out = "$indent <{$this->listTag}$ulClass>\n";
//		$out = "$nav$indent <{$this->listTag}$ulClass>\n";
        $aClass = ($this->aClass) ? " class='{$this->aClass}'" : '';
        foreach($tree as $n => $elem) {
            if (!is_int($n)) { continue; }
            
            if ($mutliLang && isset($elem[$currLang])) {
                $name = $elem[$currLang];
            } else {
                $name = $elem['name'];
            }
            if (isset($elem['goto'])) {
                $targInx = $this->lzy->siteStructure->findSiteElem($elem['goto']);
                $list = $this->lzy->siteStructure->getSiteList();
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
            $liClass = $this->liClass;
            if ($elem['isCurrPage']) {
                $liClass .= ' curr active';
                if ($this->config->feature_selflinkAvoid) {
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
                if (!$stop && isset($elem[0])) {	// does it have children?
                    if ($elem['hasChildren']) {
                        $liClass .= ' '.$this->hasChildrenClass;
                    }
                    if (($elem['isCurrPage']) || ($elem['active'])) {
                        $liClass .= ' open';
                    }
                    $liClass = trim($liClass);
                    $liClass = ($liClass) ? " class='$liClass'" : '';
                    $out .= "$indent\t<$li$liClass>\n$indent\t  <button aria-expanded='false' title='$name'>$dropDownIndicatorPrefix$name$dropDownIndicatorPostfix</button>$listWrapper\n";

                    $out .= $this->_renderDropdownMenu('', $elem, $type, "$indent\t\t", $showHidden);

                    $out .= "$_listWrapper$indent\t</$li>\n";
                } else {
                    $liClass = trim($liClass);
                    $liClass = ($liClass) ? " class='{$liClass}'" : '';
                    $out .= "$indent\t<$li$liClass><a href='$path'$aClass$target>$name</a></$li>\n";
                }
            }
        }

        $out .= "$indent </{$this->listTag}>\n";
//        $out .= "$indent </{$this->listTag}>\n$_nav";
        return $out;
    } // _renderDropdownMenu




    //....................................................
    private function addNoScriptCode($navClass)
    {
        if (isset($GLOBALS['globalParams']['noScriptAdded'])) {
            return;
        }
        if ((strpos($navClass, 'lzy-nav-accordion') === false) &&
            (strpos($navClass, 'lzy-nav-slidedown') === false) ) {
            return;
        }

        $noscript = <<<EOT
    <noscript>
        <style>
            #lzy .lzy-nav-accordion li.has-children ol { margin-top: 0!important; }
            #lzy .lzy-nav-slidedown li:focus-within ol { margin-top: 0!important; }
        </style>
    </noscript>
EOT;

        $GLOBALS['globalParams']['noScriptAdded'] = true;
        $this->page->addHead($noscript);
    }
}
