<?php

class Request {
    public $url;
    public $status_code;
    public $header;
    public $body;
    public $duration;
    public $server_ip;
    public $favicon;

    public function __construct($url) {
        $this->url = $url;
    }
}