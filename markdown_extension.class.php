<?php

class MyExtendedMarkdown extends \cebe\markdown\MarkdownExtra
{
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
            'class' => '',
            'id' => '',
        ];
        $line = rtrim($lines[$current]);
    
        // detect class or id and fence length (can be more than 3 backticks)
        if (preg_match('/(:{3,10})(.*)/',$line, $m)) {
            $fence = $m[1];
            $rest = $m[2];
        } else {
            die("Error in Markdown source line $current: $line");
        }

        list($id, $class, $style) = $this->parseInlineStyling($rest);
        $block['id'] = $id;
        $block['class'] = $class;
        $block['style'] = $style;

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
        return [$block, $i];
    }

    protected function renderDivBlock($block)
    {
        $class = ($block['class']) ? ' class="'.$block['class'].'"' : '';
        $id = ($block['id']) ? " id='{$block['id']}'" : '';
        $style = ($block['style']) ? " style='{$block['style']}'" : '';
        $out = implode("\n", $block['content']);
        $out = \cebe\markdown\Markdown::parse($out);
        $comment = ($block['id']) ? ' #'.$block['id'] : '';
        $comment .= ($block['class']) ? ' .'.$block['class'] : '';
        $comment .= ($block['style']) ? ' '.$block['style'] : '';

        return "<div$id$class$style>\n$out</div><!-- /$comment -->\n\n";
    }




    // ---------------------------------------------------------------
    protected function identifyTabulator($line, $lines, $current)
    {
        if (preg_match('/\{\{\s*tab[^\}]*\s*\}\}/', $line)) {
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
            if (preg_match('/\{\{\s* tab[^\}]* \s*\}\}/x', $line, $m)) {
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
        foreach ($block['content'] as $l) {
            $l = preg_replace('/\{\{\s* tab \(? \s* ([^\)\s\}]*) \s* \)? \s*\}\}/x', "@@$1@@tab@@", $l);
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
            $out .= "@/@lt@\\@div@/@gt@\\@$line@/@lt@\\@/div@/@gt@\\@\n";
        }
        $out = \cebe\markdown\Markdown::parse($out);
        $out = str_replace(['<p>', '</p>'], '', $out);
        $out = str_replace(['@/@lt@\\@', '@/@gt@\\@'], ['<', '>'], $out);
        $out = str_replace('<div>', "\t<div>", $out);
        return "<div class='tabulator_wrapper'>\n$out</div>\n";
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
        if (preg_match('/^\^(.{1,11}?)\^/', $markdown, $matches)) {
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
     * @marker @@@
     */
    protected function parseBacktick($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing `)
        if (preg_match('/^@@@(.+?)@@@/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['backtick', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '@@@'], 3];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderBacktick($element)
    {
        if (isset($this->page->backtick)) {
            $tag = $this->page->backtick;
        } else {
            $tag = 'code';
        }
        return "<$tag>" . $this->renderAbsy($element[1]) .  "</$tag>";
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
            return ['', '', ''];
        }
        $id = '';
        $class = '';
        $style = '';
        if (preg_match('/\s* ([\.\#]?) ([\w_\-]+) (.*)/x', $line, $mm)) {        // class or id
            if (empty($mm[3]) || ($mm[3]{0} != ':')) {
                if ($mm[1] == '#') {
                    $id = $mm[2];
                } else {
                    $class = $mm[2];
                }
                $line = $mm[3];
            }
        }

        while ($line) {

            if (preg_match('/([^\#]*) \# ([\w_\-]+)(.*)/x', $line, $mm)) {        // id
                $id = $mm[2];
                $line = $mm[1] . $mm[3];
            } elseif (preg_match('/([^\.]*) \. ([\w_\-\.]+) (.*)/x', $line, $mm)) {        // class
                $class .= ' '.str_replace('.', ' ', $mm[2]);
                $line = $mm[1] . $mm[3];
            } else {
                break;
            }
            $line = trim($line);
        }

        $styles = parseArgumentStr($line,';');          // styles
        if ($styles) {
            foreach ($styles as $key => $val) {
                if (!is_int($key)) {
                    $style .= "$key:$val;";
                }
            }
        }
            $line = '';
        return [$id, $class, $style];
    } // parseInlineStyling


} // class MyMarkdown
