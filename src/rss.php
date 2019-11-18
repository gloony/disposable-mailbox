<?php
	// Mail2RSS - Display your latest mails in a RSS feed
	// GitHub: https://github.com/vekin03/Mail2RSS
	// Version: 0.1 alpha
	// Author: Kevin VUILLEUMIER (kevinvuilleumier.net)
	// Licence: http://www.opensource.org/licenses/zlib-license.php
	// Fork for disposable-mailbox by gloony (https://github.com/synox/disposable-mailbox)

	$User = $_SERVER['QUERY_STRING'];

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
			echo "\t".'<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">'."\n";
			echo '<channel><title>'.htmlspecialchars($User).'</title><link>'.$HTTP.'://'.$_SERVER["SERVER_NAME"].'?'.$User.'</link>';
			echo '<description>Latest incoming mails in the mailbox</description><language>en-en</language><copyright></copyright>'."\n\n";

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
				$date = date(DATE_RFC822, strtotime($header->date));
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

		$body = imap_body($mbox, $i, FT_PEEK);
		$body = quoted_printable_decode($body);
		$body = html_entity_decode($body);
		$pos = strpos($body, '</div>');
		if(substr($body, $pos, strlen('</div><div>'))!='</div><div>'&&substr($body, $pos, strlen('</div><div><br'))=='</div><div><br') $body = substr($body, 0, $pos)."\n".substr($body, $pos);
		$body = str_replace('<div><br></div>', "\n", $body);
		$body = str_replace('<br', "\n<br", $body);
		$body = str_replace('<div>', "\n<div>", $body);
		$body = str_replace('</p>', "</p>\n", $body);
		$body = str_replace("\r\n", "\n", $body);
		$body = strip_tags($body);
		while(substr($body, strlen($body) - strlen("\n"))=="\n") $body = substr($body, 0, strlen($body) - strlen("\n"));
		while(substr($body, 0, strlen("\n"))=="\n") $body = substr($body, strlen("\n"));

				echo '<item><title>['.htmlspecialchars($from_mail).'] '.htmlspecialchars($title).'</title><guid isPermaLink="false">'.htmlspecialchars($guid).'</guid><pubDate>'.htmlspecialchars($date).'</pubDate>';
				echo '<description>Mail sent by '.htmlspecialchars($from_mail).' on '.htmlspecialchars($date).'</description><content:encoded><![CDATA['.$body.']]></content:encoded>'."\n</item>\n";

				$j++;
			}

			echo "</channel>\n</rss>";
		}

		imap_close($mbox);
		ob_end_flush();
	}
