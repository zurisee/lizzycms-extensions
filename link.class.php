<?php


class CreateLink
{
    public function __construct($lzy)
    {
        $this->lzy = $lzy;
    }

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
            $target = " target='_blank' rel='noopener noreferrer'";
            if (preg_match('|^(\w+:) ([^/]{2} .*)|x', $href, $m)) {
                $href = "{$m[1]}//{$m[2]}";
            }

        } elseif ((stripos($href, 'tel:') === 0) || (stripos($type, 'tel') !== false)) {
            $class = ($class) ?  "$class tel_link" : 'tel_link';
            $title = ($title) ? $title : " title='{{ opens telephone app }}'";
            if (!$text) {
                $text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $href);
            }
            $target = " target='_blank' rel='noopener noreferrer'";
            if (preg_match('|^(\w+:) ([^/]{2} .*)|x', $href, $m)) {
                $href = "{$m[1]}//{$m[2]}";
            }

        } elseif ((stripos($href, 'geo:') === 0) || (stripos($type, 'geo') !== false)) {
            $class = ($class) ?  "$class geo_link" : 'geo_link';
            $title = ($title) ? $title : " title='{{ opens map app }}'";
            if (!$text) {
                $text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $href);
            }
            $target = " target='_blank' rel='noopener noreferrer'";

        } elseif ((stripos($href, 'slack:') === 0) || (stripos($type, 'slack') !== false)) {
            $class = ($class) ?  "$class slack_link" : 'slack_link';
            $title = ($title) ? $title : " title='{{ opens slack app }}'";
            if (!$text) {
                $text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $href);
            }
            $target = " target='_blank' rel='noopener noreferrer'";

        } elseif ((stripos($href, 'pdf:') === 0) || (stripos($href, '.pdf') !== false) || (stripos($type, 'pdf') !== false)) {
            $class = ($class) ?  "$class pdf_link" : 'pdf_link';
            $title = ($title) ? $title : " title='{{ opens PDF in new window }}'";
            if (!$text) {
                $text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $href);
            }
            $href = resolvePath(str_replace('pdf:', '', $href), true, true);
            if ($target) {
                $target = ($target === 'newwin')? '_blank': $target;
                $target = " target='$target' rel='noopener noreferrer'";
                // see: https://developers.google.com/web/tools/lighthouse/audits/noopener
            }

        } else {
            if ($href[0] === '~') {
                $href = resolvePath($href, true, true);

            } else {
                // prepend 'https://' unless 'http' or something like mailto:
                if (!preg_match('/: [^\?&]*/x', $href)) {
                    if ((strpos($href, 'http') !== 0) &&
                        (stripos($type, 'intern') === false) &&
                        preg_match('/[\w-]+\.[\w-]{2,10}/', $href, $m)) {
                            $href = 'https://' . $href;
                    }
                    $href = resolvePath($href, false, 'https');
                }
            }

            $href1 = $href;
            if (strpos($href, './') === 0) {
                $href1 = substr($href,2);
            }

            // check whether URL matches with a page-path or page-name in the sitemap:
            $rec = $this->lzy->siteStructure->findSiteElem($href1, true, true);
            if ($rec) {
                $href = resolvePath('~/'.$rec['folder'], false, true);
            }

            if (!$text) {
                if ($rec) {
                    $text = $rec['name'];
                } else {
                    $text = preg_replace('|^.*:/?/? ([^\?\&]*) .*|x', "$1", $href);
                }
            } else {
                $hiddenText = "<span class='print_only'> [$href]</span>";
            }

            if ($target) {
                $target = ($target === 'newwin')? '_blank': $target;
                $target = " target='$target' rel='noopener noreferrer'";
                // see: https://developers.google.com/web/tools/lighthouse/audits/noopener

            } elseif (stripos($type, 'extern') !== false) {
                $target = " target='_blank' rel='noopener noreferrer'";
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

