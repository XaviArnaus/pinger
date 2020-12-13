<?php

class Retriever {

    private function getHost($url) {
        $parseUrl = parse_url(trim($url));
        return trim($parseUrl['host'] ? $parseUrl['host'] : array_shift(explode('/', $parseUrl['path'], 2)));
    }

    public function requestUrl(Request &$request) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request->url);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $timer = Timer::quickStart('retriever');
        $answer = curl_exec($ch);
        $request->duration = $timer->stop('retriever');

        $header_len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $request->header = substr($answer, 0, $header_len);
        $request->body = substr($answer, $header_len);

        $request->status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $request->server_ip = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        $request->favicon = "https://www.google.com/s2/favicons?domain=" . $this->getHost($request->url);
        curl_close($ch);
    }
}