<?php

class Config {
    private $config_file = "./config.json";
    private $config = [];

    public function __construct($config_file = null) {
        if (!is_null($config_file)) $this->config_file = $config_file;

        $this->load();
    }

    public function load() {
        try {
            $json_file = $this->readFile();
            $this->config = $this->getJson($json_file);
        } catch (Exception $e) {
            print "Impossible to read Config file at [" . realpath($this->config_file) . "]: " . $e->getMessage();
        }
    }

    public function getParam($param_name, $default = null) {
        if (isset($this->config[$param_name])) return $this->config[$param_name];
        elseif ($default != null) return $default;
        else throw new RuntimeException("Parameter " . $param_name . " not found");
    }

    public function getServices() {
        return $this->config['services'];
    }

    public function getService($service_id) {
        return array_filter($this->config['services'], function ($service) use ($service_id) {
            return $service['id'] == $service_id;
        });
    }

    private function readFile() {
        return file_get_contents($this->config_file);
    }

    private function getJson($file_contents) {
        return json_decode($file_contents, true);
    }
}