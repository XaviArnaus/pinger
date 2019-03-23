<?php

class Writer {
    const DEFAULT_TEMPLATE = "default_%s_%s.json";
    private $now = "";
    private $output_path = "";
    private $file_name = "";

    public function __construct(Config $config)  {
        $this->now = date("Y-m-d");
        $this->output_path = sprintf($config->getParam("output_path", "results_%s"), $this->now);
        $this->createOutputPath();
        $this->suggestFilename($config);
    }

    public function writeResults(Result $result) {
        $jsonized = json_encode($result, JSON_PRETTY_PRINT);
        return file_put_contents($this->getOutput(true), $jsonized);
    }

    private function suggestFilename(Config $config) {
        $template = $config->getParam("filename_template", self::DEFAULT_TEMPLATE);

        $counter = 0;
        while(true) {
            $suggestion = sprintf($template, $this->now, str_pad(++$counter, 3, "0", STR_PAD_LEFT));
            if (!file_exists($this->getOutput(false) . $suggestion)) {
                $this->file_name = $suggestion;
                break;
            }
        }
    }

    private function createOutputPath() {
        if (file_exists($this->getOutput(false))) return;

        if (!mkdir($this->getOutput(false), 0777, true)) {
            throw new RuntimeException("Could not create output dir. Check permissions!");
        }
    }

    private function getOutput($with_filename = true) {
        if ($with_filename) return $this->output_path . DIRECTORY_SEPARATOR . $this->file_name;
        else return $this->output_path . DIRECTORY_SEPARATOR;
    }
}