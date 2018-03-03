<?php

require_once './imap_client.php';

abstract class Page {

    function invoke(ImapClient $imapClient) {
    }

    function if_invalid_redirect_to_random(User $user, array $config_domains) {
        if ($user->isInvalid($config_domains)) {
            $this->redirect_to_random($config_domains);
            exit();
        }
    }

    function redirect_to_random(array $domains) {
        $wordLength = rand(3, 8);
        $container = new PronounceableWord_DependencyInjectionContainer();
        $generator = $container->getGenerator();
        $word = $generator->generateWordOfGivenLength($wordLength);
        $nr = rand(51, 91);
        $name = $word . $nr;

        $domain = $domains[array_rand($domains)];
        header("location: ?$name@$domain");
    }

    /**
     * print error and stop program.
     * @param $status integer http status
     * @param $text string error text
     */
    function error($status, $text) {
        @http_response_code($status);
        die("{\"error\": \"$text\"}");
    }

}

class RedirectToAddressPage extends Page {
    private $username;
    private $domain;
    private $config_blocked_usernames;

    public function __construct(string $username, string $domain, array $config_blocked_usernames) {
        $this->username = $username;
        $this->domain = $domain;
        $this->config_blocked_usernames = $config_blocked_usernames;
    }

    function invoke(ImapClient $imapClient) {
        $user = User::parseUsernameAndDomain($this->username, $this->domain, $this->config_blocked_usernames);
        header("location: ?" . $user->username . "@" . $user->domain);
    }
}

class DownloadEmailPage extends Page {

    private $email_id;
    private $address;
    private $config_domains;
    private $config_blocked_usernames;

    public function __construct(string $email_id, string $address, array $config_domains, array $config_blocked_usernames) {
        $this->email_id = $email_id;
        $this->address = $address;
        $this->config_domains = $config_domains;
        $this->config_blocked_usernames = $config_blocked_usernames;
    }


    function invoke(ImapClient $imapClient) {
        $user = User::parseDomain($this->address, $this->config_blocked_usernames);
        $this->if_invalid_redirect_to_random($user, $this->config_domains);

        $download_email_id = filter_var($this->email_id, FILTER_SANITIZE_NUMBER_INT);
        if ($imapClient->load_one_email($download_email_id, $user) !== null) {
            header("Content-Type: message/rfc822; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"" . $user->address . "-" . $download_email_id . ".eml\"");

            $headers = imap_fetchheader($this->mailbox->getImapStream(), $download_email_id, FT_UID);
            $body = imap_body($this->mailbox->getImapStream(), $download_email_id, FT_UID);
            print $headers . "\n" . $body;
        } else {
            $this->error(404, 'download error: invalid username/mailid combination');
        }
    }
}


class DeleteEmailPage extends Page {
    private $email_id;
    private $address;
    private $config_domains;
    private $config_blocked_usernames;

    public function __construct($email_id, $address, $config_domains, array $config_blocked_usernames) {
        $this->email_id = $email_id;
        $this->address = $address;
        $this->config_domains = $config_domains;
        $this->config_blocked_usernames = $config_blocked_usernames;
    }

    function invoke(ImapClient $imapClient) {
        $user = User::parseDomain($this->address, $this->config_blocked_usernames);
        $this->if_invalid_redirect_to_random($user, $this->config_domains);

        $delete_email_id = filter_var($this->email_id, FILTER_SANITIZE_NUMBER_INT);
        if ($imapClient->delete_email($delete_email_id, $user)) {
            header("location: ?" . $user->address);
        } else {
            $this->error(404, 'delete error: invalid username/mailid combination');
        }
    }
}

class RedirectToRandomAddressPage extends Page {
    private $config_domains;

    public function __construct($config_domains) {
        $this->config_domains = $config_domains;
    }

    function invoke(ImapClient $imapClient) {
        $this->redirect_to_random($this->config_domains);
    }

}

class DisplayEmailsPage extends Page {
    private $address;
    private $config;

    public function __construct($address, $config) {
        $this->address = $address;
        $this->config = $config;
    }


    function invoke(ImapClient $imapClient) {
        // print emails with html template
        $user = User::parseDomain($this->address, $this->config['blocked_usernames']);
        $this->if_invalid_redirect_to_random($user, $this->config['domains']);

        // Set variables for frontend template:
        $emails = $imapClient->get_emails($user);
        $config = $this->config;
        require "frontend.template.php";
    }
}

class InvalidRequestPage extends Page {
    function invoke(ImapClient $imapClient) {
        $this->error(400, "Bad Request");
    }
}