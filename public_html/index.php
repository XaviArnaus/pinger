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
            if (count($directories) > 0) return array_shift($directories);
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
        $files =  array_filter(
            array_diff(scandir(self::DIRECTORY_PATH_MODIFICATOR . $directory, SCANDIR_SORT_DESCENDING), $this->unwanted_files),
            function ($filename) {
                // Only the JSON files.
                return strpos($filename, ".json") !== false;
            }
        );
        return $files;
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

class Render {

    const DIRECTORY_PATH_MODIFICATOR = '..' . DIRECTORY_SEPARATOR;
    const VALID = "valid";
    const INVALID = "invalid";

    private $results;
    private $templates;
    private $choosen_template;

    public function __construct(Config $config) {
        $this->choosen_template = $config->getParam("display_template", "light");
        $this->templates = [
            "styles"            => self::DIRECTORY_PATH_MODIFICATOR . "templates/" . $this->choosen_template . "/styles.css",
            "layout"            => self::DIRECTORY_PATH_MODIFICATOR . "templates/" . $this->choosen_template . "/layout.html",
            "main_info"         => self::DIRECTORY_PATH_MODIFICATOR . "templates/" . $this->choosen_template . "/main_info.html",
            "service_result"    => self::DIRECTORY_PATH_MODIFICATOR . "templates/" . $this->choosen_template . "/service_result.html",
            "validation_result" => self::DIRECTORY_PATH_MODIFICATOR . "templates/" . $this->choosen_template . "/validation_result.html",
        ];
    }

    public function setResultsToDisplay(array $results) {
        $this->results = $results;
    }

    public function show() {

        $service_content = "";
        foreach($this->results["service_results"] as $service_result) {
            $params = [
                "{%-NAME-%}" => $service_result["service_name"],
                "{%-URL-%}" => $service_result["request"]["url"],
                "{%-DURATION-%}" => $service_result["duration"],
                "{%-ERROR-%}" => $service_result["error"] ? $service_result["error"] : "No",
            ];
            $validation_content = "";
            foreach ($service_result["validation_results"] as $validation_result) {
                $validation_content .= $this->renderTemplate(
                    "validation_result",
                    [
                        "{%-CLASS-%}" => $validation_result["validation_class"],
                        "{%-VALUE-%}" => $validation_result["valid_value"],
                        "{%-VALIDATION_RESULT-%}" => boolval($validation_result["is_valid"]) ? self::VALID : self::INVALID
                    ]
                );
            }
            $service_content .= $this->renderTemplate(
                "service_result",
                array_merge($params, ["{%-VALIDATION RESULTS-%}" => $validation_content])
            );
        }

        $main_info_content = $this->renderTemplate(
            "main_info",
            [
                "{%-VERSION-%}" => $this->results["version"],
                "{%-DATE-%}" => $this->results["date"],
                "{%-DURATION-%}" => $this->results["duration"]
            ]
        );

        $content = $this->renderTemplate(
            "layout",
            [
                "{%-STYLES-%}" => $this->renderTemplate("styles"),
                "{%-MAIN_INFO-%}" => $main_info_content,
                "{%-RESULTS-%}" => $service_content
            ]
        );

        print $content;
    }

    private function renderTemplate($template_name, $parameters = []) {
        $content = $this->getTemplateContent($template_name);
        foreach ($parameters as $key => $value) {
            $content = str_replace($key,$value,$content);
        }
        return $content;
    }

    private function getTemplateContent($template_name) {
        return file_get_contents($this->templates[$template_name]);
    }
}


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