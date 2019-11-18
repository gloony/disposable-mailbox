<?php
    // Mail2RSS - Display your latest mails in a RSS feed
    // GitHub: https://github.com/vekin03/Mail2RSS
    // Version: 0.1 alpha
    // Author: Kevin VUILLEUMIER (kevinvuilleumier.net)
    // Licence: http://www.opensource.org/licenses/zlib-license.php
    // Fork for disposable-mailbox by gloony (https://github.com/synox/disposable-mailbox)

    require_once './config_helper.php';
    load_config();

    if (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']){
        $HTTP = 'https';
    } else {
        $HTTP = 'http';
    }

    ob_start();

    header('Content-Type: application/rss+xml; charset=utf-8');

    $mbox = imap_open($config['imap']['url'], $config['imap']['username'], $config['imap']['password']);

    if ($mbox) {
        $num = imap_num_msg($mbox);

        if ($num >0) {
            echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
            echo "\t".'<rss xmlns:atom="http://www.w3.org/2005/Atom" version="2.0">'."\n";
            echo "\t\t".'<channel>'."\n";
            echo "\t\t\t".'<title>'.htmlspecialchars($User).'</title>'."\n";
            echo "\t\t\t".'<link>'.$HTTP.'://'.$_SERVER["SERVER_NAME"].'/'.$User.'</link>'."\n";
            echo "\t\t\t".'<description>Latest incoming mails in the mailbox</description>'."\n";
            echo "\t\t\t".'<atom:link rel="self" href="'.$HTTP.'://'.$_SERVER["SERVER_NAME"].'/rss.php?email='.str_replace('@', '%40', $User).'"/>'."\n";
            echo "\t\t\t".'<language>en</language>'."\n";

            for ($i = $num, $j = 0; $i >= 1 && $j < 20; $i--) {
                $headerText = imap_fetchHeader($mbox, $i);
                $header = imap_rfc822_parse_headers($headerText);

                $to = $header->to;
                $to_mail = $to[0]->mailbox.'@'.$to[0]->host;
                if($to_mail!=$User) break;

                $struct = imap_fetchstructure($mbox, $i);
                $is_plain_text = false;
                $content = '';
                $charset = '';
                $title = '';

                if (!isset($struct->parts)) {
                    $content = imap_fetchbody($mbox, $i, 1, FT_PEEK);
                    $is_plain_text = true;

                    foreach ($struct->parameters as $param) {
                        if (strtolower($param->attribute) == "charset") {
                            $charset = strtolower($param->value);
                        }
                    }
                } else {
                    $content = imap_fetchbody($mbox, $i, 2, FT_PEEK);

                    foreach ($struct->parts[0]->parameters as $param) {
                        if (strtolower($param->attribute) == "charset") {
                            $charset = strtolower($param->value);
                        }
                    }
                }

                $from = $header->from;
                $from_mail = $from[0]->mailbox.'@'.$from[0]->host;
                $date = date(DATE_RSS, strtotime($header->date));
                $guid = $header->message_id;
                $subject = $header->subject;

                if ($charset == "utf-8") {
                    $title = iconv_mime_decode($subject, 0, 'UTF-8');
                    $content = quoted_printable_decode($content);
                } else {
                    $title = utf8_encode(imap_utf8($subject));
                    $content = utf8_encode(imap_utf8($content));
                }

                if ($is_plain_text) {
                    $content = nl2br($content);
                }

                echo "\t\t\t".'<item>'."\n";
                echo "\t\t\t\t".'<title>['.htmlspecialchars($from_mail).'] '.htmlspecialchars($title).'</title>'."\n";
                echo "\t\t\t\t".'<guid isPermaLink="false">'.htmlspecialchars($guid).'</guid>'."\n";
                echo "\t\t\t\t".'<pubDate>'.$date.'</pubDate>'."\n";
                echo "\t\t\t\t".'<description>'."\n";
                echo '<![CDATA['."\n";
                echo $content."\n";
                echo ']]>'."\n";
                echo "\t\t\t\t".'</description>'."\n";
                echo "\t\t\t</item>\n";

                $j++;
            }

            echo "\t</channel>\n</rss>";
        }

        imap_close($mbox);
        ob_end_flush();
    }
