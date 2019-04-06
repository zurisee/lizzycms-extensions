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
        $arg = '';
        if ((stripos($href, 'mailto:') === 0) || (stripos($type, 'mail') !== false)) {
            $class = ($class) ?  "$class mail_link" : 'mail_link';
            $title = ($title) ? $title : " title='{{ opens mail app }}'";
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
                $text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $href);
            } else {
                $hiddenText = "<span class='print_only'> [$href]</span>";
            }
            $href .= $arg;

        } elseif ((stripos($href, 'sms:') === 0) || (stripos($type, 'sms') !== false)) {
            $class = ($class) ?  "$class sms_link" : 'sms_link';
            $title = ($title) ? $title : " title='{{ opens messaging app }}'";
            if (!$text) {
                $text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $href);
            }
            if ($body) {
                $href .= "?&body=$body";
            }

        } elseif ((stripos($href, 'tel:') === 0) || (stripos($type, 'tel') !== false)) {
            $class = ($class) ?  "$class tel_link" : 'tel_link';
            $title = ($title) ? $title : " title='{{ opens telephone app }}'";
            if (!$text) {
                $text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $href);
            }

        } elseif ((stripos($href, 'geo:') === 0) || (stripos($type, 'geo') !== false)) {
            $class = ($class) ?  "$class geo_link" : 'geo_link';
            $title = ($title) ? $title : " title='{{ opens map app }}'";
            if (!$text) {
                $text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $href);
            }

        } elseif ((stripos($href, 'pdf:') === 0) || (stripos($type, 'pdf') !== false)) {
            $class = ($class) ?  "$class pdf_link" : 'pdf_link';
            $title = ($title) ? $title : " title='{{ opens PDF in new window }}'";
            if (!$text) {
                $text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $href);
            }
            $href = resolvePath(str_replace('pdf:', '', $href), true, true);
            if ($target) {
                $target = ($target === 'newwin')? '_blank': $target;
                $target = " target='$target' rel='noopener'";
                // see: https://developers.google.com/web/tools/lighthouse/audits/noopener
            }

        } else {
            $href0 = $href;
            if (!preg_match('/: [^\?&]*/x', $href)) {
                if ((strpos($href, 'http') !== 0) && (stripos($type, 'intern') === false) && preg_match('/[\w-]+\.[\w-]{2,10}/', $href, $m)) {
                    $href = 'https://' . $href;
                }
                $href = resolvePath($href, false, 'https');
            }
            if (!$text) {
                $rec = isset($this->siteStructure) ? $this->siteStructure->findSiteElem($href0, true) : false;
                if ($rec) {
                    $href = resolvePath('~/'.$rec['folder'], false, 'https');
                    $text = $rec['name'];
                } else {
                    $text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $href);
                }
            } else {
                $hiddenText = "<span class='print_only'> [$href]</span>";
            }

            if ($target) {
                $target = ($target === 'newwin')? '_blank': $target;
                $target = " target='$target' rel='noopener'";
                // see: https://developers.google.com/web/tools/lighthouse/audits/noopener

            } elseif (stripos($type, 'extern') !== false) {
                $target = " target='_blank' rel='noopener'";
                $class = ($class) ? "$class external_link" : 'external_link';
                $title = $title ? $title : " title='{{ opens in new win }}'";
            }
        }
        $class = ($class) ? " class='$class'" : '';
        if (preg_match('/^ ([^\?&]*) (.*)/x', $href, $m)) {     // remove blanks from href
            $href = str_replace(' ', '', $m[1]).str_replace(' ', '%20', $m[2]);
        }
        $str = "<a href='$href' $class$title$target>$text$hiddenText</a>";

        return $str;
    } // render


} // CreateLink

