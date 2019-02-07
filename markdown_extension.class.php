<?php

class MyExtendedMarkdown extends \cebe\markdown\MarkdownExtra
{
    private $cssAttrNames =
        ['align', 'all', 'animation', 'backface', 'background', 'border', 'bottom', 'box',
            'break', 'caption', 'caret', 'charset', 'clear', 'clip', 'color', 'column', 'columns',
            'content', 'counter', 'cursor', 'direction', 'display', 'empty', 'filter', 'flex',
            'float', 'font', 'grid', 'hanging', 'height', 'hyphens', 'image', 'import', 'isolation',
            'justify', 'keyframes', 'left', 'letter', 'line', 'list', 'margin', 'max', 'media', 'min',
            'mix', 'object', 'opacity', 'order', 'orphans', 'outline', 'overflow', 'Specifies',
            'padding', 'page', 'perspective', 'pointer', 'position', 'quotes', 'resize', 'right',
            'scroll', 'tab', 'table', 'text', 'top', 'transform', 'transition', 'unicode', 'user',
            'vertical', 'visibility', 'white', 'widows', 'width', 'word', 'writing', 'z-index'];

    public function __construct($page = false)
    {
        $this->page = $page;
    } // __construct
    
    protected function identifyAuthorsDirective($line, $lines, $current)
    {
        // if a line starts with at least 3 colons it is identified as a fenced code block
        if (strpos($line, '((') !== false) {
            return 'authorsDirective';
        }
        return false;
    }
    
    protected function consumeAuthorsDirective($lines, $current)
    {
        // create block array
        $block = [
            'authorsDirective',
            'content' => [],
        ];
        $line = rtrim($lines[$current]);
    
        // detect class or id and fence length (can be more than 3 backticks)
        $p1 = strpos($line, '((');
        $p2 = strrpos($line, '))');
        $directive = trim(substr($line, $p1+2, $p2-$p1-2));
        $class = '';
        if (!empty($directive)) {
            if (preg_match('/^([\w\-\#\.]+)/', $directive, $m)) {
                $class = $m[1];
            }
        }
        $block['class'] = $class;
        $block['content'][] = substr($line, 0, $p1).substr($line, $p2+2);
        $block['directive'] = $directive;
    
        // consume all lines until \n\n
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            if (!empty($lines[$i]) || !empty($lines[$i-1])) {
                $block['content'][] = $lines[$i];
            } else {
                // stop consuming when code block is over
                break;
            }
        }
        return [$block, $i];
    }

    protected function renderAuthorsDirective($block)
    {
        $class = ' class="authors_directive '.$block['class'].'"';
        $out = implode("\n", $block['content']);
        $out = \cebe\markdown\Markdown::parse($out);
        $out = "\t<span class='authors_directive_tag'>(( {$block['directive']} ))</span>\n\t<div$class>\n$out\n\t</div>\n";
        return $out;
    }




    // ---------------------------------------------------------------
    protected function identifyAsciiTable($line, $lines, $current)
    {
        // asciiTable starts with '|==='
        if (strncmp($line, '|===', 4) === 0) {
            return 'asciiTable';
        }
        return false;
    }



    protected function consumeAsciiTable($lines, $current)
    {
        $block = [
            'asciiTable',
            'content' => [],
            'caption' => false,
            'header' => false
        ];
        $firstLine = $lines[$current];
        if (preg_match('/^\|===*\s+(.+)$/', $firstLine, $m)) {
            $a = preg_split('/(?<!\\\)\|/', $m[1]);
            if (sizeof($a) > 1) {
                $block['caption'] = str_replace('\|','|', array_shift($a));
                $block['header'] = $a;
            } else {
                $block['caption'] = str_replace('\|','|', $m[1]);
            }
        }
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (strncmp($line, '|===', 4) !== 0) {
                $block['content'][] = $line;
            } else {
                // stop consuming when code block is over
                break;
            }
        }
        return [$block, $i];
    }



    protected function renderAsciiTable($block)
    {
        $table = [];
        $nCols = 0;
        $row = 0;
        $col = -1;

        for ($i = 0; $i < sizeof($block['content']); $i++) {
            $line = $block['content'][$i];

            if (strncmp($line, '|---', 4) === 0) {  // new row
                $row++;
                $col = -1;
                continue;
            }

            if (isset($line[0]) && ($line[0] == '|')) {  // next cell starts
                $line = trim($line, '|');
                $cells = preg_split('/(?<!\\\)\|/', $line);
                foreach ($cells as $cell) {
                    $col++;
                    $table[$row][$col] = str_replace('\|','|', $cell);
                }

            } else {
                $table[$row][$col] .= "\n$line";
            }
            $nCols = max($nCols, $col);
        }
        $nCols++;
        $nRows = $row+1;


        // now render the table:
        $out = "\t<table><!-- asciiTable -->\n";
        if ($block['caption']) {
            $out .= "\t  <caption>{$block['caption']}</caption>\n";
        }

        if ($block['header']) {     // table header
            $out .= "\t  <thead>\n";
            for ($col = 0; $col < $nCols; $col++) {
                $th = isset($block['header'][$col]) ? str_replace('\|','|', $block['header'][$col]) : '';
                $out .= "\t\t\t<th class='th$col'>$th</th>\n";
            }
            $out .= "\t  </thead>\n";
        }

        $out .= "\t  <tbody>\n";
        for ($row = 0; $row < $nRows; $row++) {
            $out .= "\t\t<tr>\n";
            for ($col = 0; $col < $nCols; $col++) {
                $cell = isset($table[$row][$col]) ? trim($table[$row][$col]) : '';
                if ($cell) {
                    $cell = compileMarkdownStr($cell);
                    $cell = trim($cell);
                    if (preg_match('|^<p>(.*)</p>$|', $cell, $m)) {
                        $cell = $m[1];
                    }
                }
                $out .= "\t\t\t<td class='row".($row+1)." col".($col+1)."'>$cell</td>\n";
            }
            $out .= "\t\t</tr>\n";
        }

        $out .= "\t  </tbody>\n";
        $out .= "\t</table><!-- /asciiTable -->\n";

        return $out;
    } // AsciiTable




    // ---------------------------------------------------------------
    protected function identifyDivBlock($line, $lines, $current)
    {
        // if a line starts with at least 3 colons it is identified as a fenced code block
        if (strncmp($line, ':::', 3) === 0) {
            return 'fencedCode';
        }
        return false;
    }
    
    protected function consumeDivBlock($lines, $current)
    {
        // create block array
        $block = [
            'divBlock',
            'content' => [],
            'tag' => 'div',
            'attributes' => '',
            'literal' => false
        ];
        $line = rtrim($lines[$current]);
    
        // detect class or id and fence length (can be more than 3 backticks)
        if (preg_match('/(:{3,10})(.*)/',$line, $m)) {
            $fence = $m[1];
            $rest = $m[2];
        } else {
            fatalError("Error in Markdown source line $current: $line", 'File: '.__FILE__.' Line: '.__LINE__);
        }

        list($tag, $attr, $lang, $comment, $literal, $mdCompile) = $this->parseInlineStyling($rest);

        $block['tag'] = $tag ? $tag : 'div';
        $block['attributes'] = $attr;
        $block['comment'] = $comment;
        $block['lang'] = $lang;
        $block['literal'] = $literal;
        $block['mdcompile'] = $mdCompile;

        // consume all lines until :::
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (preg_match('/^(:{3,10})\s*(.*)/', $line, $m)) { // it's a potential fence line
                $fenceEndCandidate = $m[1];
                $rest = $m[2];
                if ($fence != $fenceEndCandidate) {
                    $block['content'][] = $line;
                } elseif ($rest != '') {
                    $i--;
                    break;
                } else {
                    break;
                }
            } else {
                $block['content'][] = $line;
            }
        }
        if ($block['literal']) {     // for shielding, we just encode the entire block to hide it from further processing
            $content = implode("\n", $block['content']);
            unset($block['content']);
            $content = str_replace(['@/@lt@\\@', '@/@gt@\\@'], ['<', '>'], $content);
            $block['content'][0] = base64_encode($content);
        }
        return [$block, $i];
    }

    protected function renderDivBlock($block)
    {
        $tag = $block['tag'];
        $attrs = $block['attributes'];
        $comment = $block['comment'];

        $out = implode("\n", $block['content']);

        if (!$block['literal'] && $block['mdcompile']) {  // within such a block we need to compile explicitly:
            $out = \cebe\markdown\Markdown::parse($out);
        }
        if ($block['literal']) {     // flag block for post-processing
            $attrs = trim("$attrs data-lzy-literal-block='true'");
        }
        return "\t\t<$tag $attrs>\n$out\n\t\t</$tag><!-- /$comment -->\n\n";
    } // renderDivBlock




    // ---------------------------------------------------------------
    protected function identifyTabulator($line, $lines, $current)
    {
        if (preg_match('/\{\{ \s* tab\b [^\}]* \s* \}\}/x', $line)) { // identify patterns like '{{ tab( 7em ) }}'
            return 'tabulator';
        }
        return false;
    }




    protected function consumeTabulator($lines, $current)
    {
        $block = [
            'tabulator',
            'content' => [],
        ];

        $last = $current;
        // consume following lines containing {tab}
        for($i = $current, $count = count($lines); $i < $count-1; $i++) {
            $line = $lines[$i];
            if (preg_match('/\{\{\s* tab\b[^\}]* \s*\}\}/x', $line, $m)) {
                $block['content'][] = $line;
                $last = $i;
            } elseif (empty($line)) {
                continue;
            } else {
                $i--;
                break;
            }
        }
        return [$block, $last];
    }




    protected function renderTabulator($block)
    {
        $out = '';
        $s = isset($block['content'][0]) ? $block['content'][0] : '';
        $wrapperAttr = '';
        if (strpos($s, "@/@ul@\\@") !== false) {        // handle 'ul'
            $tag = 'li';
            $wrapperTag = 'ul';

        } elseif (strpos($s, "@/@ol@\\@") !== false) {  // handle 'ol', optionally with start value
            if (preg_match("|^!(\d+)@(.*)|", substr($s, 8), $m)) {
                $wrapperTag = "ol";
                $wrapperAttr = " start='{$m[1]}'";
                $block['content'][0] = $m[2];
            } else {
                $wrapperTag = 'ol';
            }
            $tag = 'li';

        } else {        // all other cases:
            $tag = 'div';
            $wrapperTag = 'div';
        }

        foreach ($block['content'] as $l) {
            $l = preg_replace('/\{\{\s* tab\b \(? \s* ([^\)\s\}]*) \s* \)? \s*\}\}/x', "@@$1@@tab@@", $l);
            $parts = explode('@@tab@@', $l);
            $line = '';
            foreach ($parts as $n => $elem) {
                if (preg_match('/(.*)@@(\w*)$/', $elem, $m)) {
                    $elem = $m[1];
                    if ($m[2]) {
                        $width[$n] = $m[2];
                    }
                }
                $style = (isset($width[$n])) ? " style='width:{$width[$n]};'" : '';
                $line .= "@/@lt@\\@span class='c".($n+1)."'$style@/@gt@\\@$elem@/@lt@\\@/span@/@gt@\\@";
            }
            $out .= "@/@lt@\\@$tag@/@gt@\\@$line@/@lt@\\@/$tag@/@gt@\\@\n";
        }
        $out = \cebe\markdown\Markdown::parse($out);
        $out = str_replace(['<p>', '</p>'], '', $out);
        $out = str_replace(['@/@ul@\\@', '@/@ol@\\@'], '', $out);
        return "<$wrapperTag$wrapperAttr class='tabulator_wrapper'>\n$out</$wrapperTag>\n";
    }




    // ---------------------------------------------------------------
    protected function identifyDefinitionList($line, $lines, $current)
    {
        // if a line starts with at least 3 colons it is identified as a fenced code block
        if (isset($lines[$current+1]) && strncmp($lines[$current+1], ': ', 2) === 0) {
            return 'definitionList';
        }
        return false;
    }



    
    protected function consumeDefinitionList($lines, $current)
    {
        // create block array
        $block = [
            'definitionList',
            'dt',
            'dd' => [],
        ];
        $block['dt'] = rtrim($lines[$current]);   
    
        // consume all lines until empty line
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (preg_match('/^\:\s+(.*)$/', $line, $m)) {
                $block['dd'][] = $m[1];
            } else {
                // stop consuming when code block is over
                break;
            }
        }
        return [$block, $i];
    }




    protected function renderDefinitionList($block)
    {
        $dt = "\t\t<dt>{$block['dt']}</dt>";
        $dd = implode("\n", $block['dd']);
        $dd = \cebe\markdown\Markdown::parse($dd);
        $dd = str_replace("  \n", "<br>\n", $dd);
        $dd = preg_replace('|\<p\>(.*)\</p\>\n|ms', "$1", $dd);
        $out = "\t<dl>\n$dt\n\t\t<dd>$dd</dd>\n\t</dl>\n";
        return $out;
    }




     // ---------------------------------------------------------------
    /**
     * @marker ~~
     */

    protected function parseStrike($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~~)
        if (preg_match('/^~~(.+?)~~/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['strike', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '~~'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderStrike($element)
    {
        return '<del>' . $this->renderAbsy($element[1]) . '</del>';
    }




    // ---------------------------------------------------------------
    /**
     * @marker ~
     */

    protected function parseSubscript($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^~(.{1,9}?)~/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['subscript', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~ we just return the marker and skip 1 character
        return [['text', '~'], 1];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderSubscript($element)
    {
        return '<sub>' . $this->renderAbsy($element[1]) . '</sub>';
    }




    // ---------------------------------------------------------------
    /**
     * @marker ^
     */
    protected function parseSuperscript($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^\^(.{1,20}?)\^/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['superscript', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 1 character
        return [['text', '^'], 1];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderSuperscript($element)
    {
        return '<sup>' . $this->renderAbsy($element[1]) . '</sup>';
    }




    // ---------------------------------------------------------------
    /**
     * @marker ==
     */
    protected function parseMarked($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ==)
        if (preg_match('/^==(.+?)==/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['marked', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '=='], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderMarked($element)
    {
        return '<mark>' . $this->renderAbsy($element[1]) . '</mark>';
    }




    // ---------------------------------------------------------------
    /**
     * @marker ++
     */
    protected function parseInserted($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^\+\+(.+?)\+\+/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['inserted', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '++'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderInserted($element)
    {
        return '<ins>' . $this->renderAbsy($element[1]) . '</ins>';
    }




    // ---------------------------------------------------------------
    /**
     * @marker __
     */
    protected function parseUnderlined($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^__(.+?)__/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['underlined', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '__'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderUnderlined($element)
    {
        return '<span class="underline">' . $this->renderAbsy($element[1]) . '</span>';
    }



    // ---------------------------------------------------------------
    /**
     * @marker ``
     */
    protected function parseDoubleBacktick($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing `)
        if (preg_match('/^``(.+?)``/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['doublebacktick', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '``'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderDoubleBacktick($element)
    {
        if (isset($this->page->doubleBacktick)) {
            $tag = $this->page->doubleBacktick;
        } else {
            $tag = 'samp';
        }
        return "<$tag>" . $this->renderAbsy($element[1]) .  "</$tag>";
    }




    //....................................................
    private function parseInlineStyling($line)
    {
        // examples: '.myclass.sndCls', '#myid', 'color:red; background: #ffe;'
        if (!$line) {
            return ['', '', '', '', false, false];
        }

        return parseInlineBlockArguments($line);
    } // parseInlineStyling


    private function isCssProperty($str)
    {
        $res = array_filter($this->cssAttrNames, function($attr) use ($str) {return (substr_compare($attr, $str, 0, strlen($attr)) == 0); });
        return (sizeof($res) > 0);
    }

} // class MyMarkdown
