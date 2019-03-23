<?php
require_once "../classes/Config.php";

class Reader {

    const DIRECTORY_PATH_MODIFICATOR = '..' . DIRECTORY_SEPARATOR;

    private $unwanted_files = ['..', '.'];
    private $output_path = "";
    private $output_path_zips = "";
    private $results_folders_pattern;
    private $now;

    public function __construct(Config $config) {
        $this->now = date("Y-m-d");
        $this->output_path = sprintf($config->getParam("output_path", "results_%s"), $this->now);
        $this->output_path_zips = sprintf($config->getParam("output_path", "results_%s"), "old");
        $this->results_folders_pattern = sprintf($config->getParam("output_path", "results_%s"), "");
    }

    public function getLastDayResultDirectory() {
        if (
            file_exists(self::DIRECTORY_PATH_MODIFICATOR . $this->output_path) &&
            is_dir(self::DIRECTORY_PATH_MODIFICATOR . $this->output_path)
        ) return $this->output_path;
        else {
            $directories = $this->getResultDirectories();
            if (count($directories) > 0) return $directories[0];
            else throw new RuntimeException("No result directories found");
        }
    }

    public function getLastDayResults() {
        $last_day_result_dir = $this->getLastDayResultDirectory();
        $last_day_json_files = $this->getJsonFilesAtDirectory($last_day_result_dir);

        return array_map(
            function ($filename) use ($last_day_result_dir) {
                return file_get_contents(self::DIRECTORY_PATH_MODIFICATOR . $last_day_result_dir . DIRECTORY_SEPARATOR . $filename);
            },
            $last_day_json_files
        );
    }

    private function getJsonFilesAtDirectory($directory) {
        return array_filter(
            array_diff(scandir(self::DIRECTORY_PATH_MODIFICATOR . $directory, SCANDIR_SORT_DESCENDING), $this->unwanted_files),
            function ($filename) {
                // Only the JSON files.
                return strpos($filename, ".json") !== false;
            }
        );
    }

    private function getResultDirectories() {
        // All but the dots and the last result.
        return array_filter(
            array_diff(scandir(self::DIRECTORY_PATH_MODIFICATOR . ".", SCANDIR_SORT_DESCENDING), $this->unwanted_files),
            function ($filename) {
                return is_dir(self::DIRECTORY_PATH_MODIFICATOR . $filename) &&
                    strpos($filename, $this->results_folders_pattern) !== FALSE &&
                    $filename !== $this->output_path_zips;
            }
        );
    }
}


class App {
    const CONFIG_FILE = "../config.json";
    private $config;
    private $reader;

    public function __construct() {
        $this->config = new Config(self::CONFIG_FILE);
        $this->reader = new Reader($this->config);
    }

    public function run() {
        print $this->getLastResult();
    }

    private function getLastResult() {
        return $this->reader->getLastDayResults()[0];
    }
}

$app = new App();
$app->run();