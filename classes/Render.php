<?php

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