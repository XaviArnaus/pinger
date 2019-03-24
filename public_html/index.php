<?php
require_once "../classes/Config.php";
require_once "../classes/Reader.php";
require_once "../classes/Render.php";


class App {
    const CONFIG_FILE = "../config.json";
    private $config;
    private $reader;
    private $render;

    public function __construct() {
        $this->config = new Config(self::CONFIG_FILE);
        $this->reader = new Reader($this->config);
        $this->render = new Render($this->config);
    }

    public function run() {
        $this->render->setResultsToDisplay(json_decode($this->getLastResult(), true));
        $this->render->show();
    }

    private function getLastResult() {
        return $this->reader->getLastDayResults()[0];
    }
}

$app = new App();
$app->run();