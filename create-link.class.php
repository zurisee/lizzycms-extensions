<?php


class CreateLink
{

    //----------------------------------------------------------
    public function render($args)
    {
        $href = $args['href'];
        $text = $args['text'];
        $type = $args['type'];
        $class = $args['class'];
        $title = $args['title'];
        $target = $args['target'];
        $subject = $args['subject'];
        $body = $args['body'];

        if ($title) {
            $title = " title='$title'";
        }
        $hiddenText = '';
        if ((stripos($href, 'mailto:') !== false) || (stripos($type, 'mail') !== false)) {
            $class = ($class) ?  "$class mail_link" : 'mail_link';
            $title = ($title) ? $title : " title='{{ opens mail app }}'";
            $arg = '';
            $body = str_replace(' ', '%20', $body);
            $body = str_replace(['\n', "\n"], '%0A', $body);
            if ($subject) {
                $subject = str_replace(' ', '%20', $subject);
                $arg = "?subject=$subject";
                if ($body) {
                    $arg .= "&body=$body";
                }
            } elseif ($body) {
                $arg = "?body=$body";
            }
            if (!$text) {
                $text = substr($href, 7);
            } else {
                $hiddenText = "<span class='print_only'> [$href]</span>";
            }
            $href .= $arg;

        } else {
            $href0 = $href;
            if ((strpos($href, 'http') !== 0) && (stripos($type, 'intern') === false) && preg_match('/[\w-]+\.[\w-]{2,10}/', $href, $m)) {
                $href = 'https://'.$href;
            }
            $href = resolvePath($href, false, 'https');
            if (!$text) {
                $rec = isset($this->siteStructure) ? $this->siteStructure->findSiteElem($href0, true) : false;
                if ($rec) {
                    $href = resolvePath('~/'.$rec['folder'], false, 'https');
                    $text = $rec['name'];
                } else {
                    $text = $href;
                }
            } else {
                $hiddenText = "<span class='print_only'> [$href]</span>";
            }

            if ($target) {
                $target = " target='$target' rel='noopener'";
                // see: https://developers.google.com/web/tools/lighthouse/audits/noopener

            } elseif (stripos($type, 'extern') !== false) {
                $target = " target='_blank' rel='noopener'";
                $class = ($class) ? "$class external_link" : 'external_link';
                $title = $title ? $title : " title='{{ opens in new win }}'";
            }
        }
        $class = ($class) ? " class='$class'" : '';
        $str = "<a href='$href' $class$title$target>$text$hiddenText</a>";

        return $str;
    } // render


} // CreateLink



