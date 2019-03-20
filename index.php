<?php


class Config {
    private $config_file = "./config.json";
    private $config = [];

    public function __construct() {
        $this->load();
    }

    public function load() {
        try {
            $json_file = $this->readFile();
            $this->config = $this->getJson($json_file);
        } catch (Exception $e) {
            print "Impossible to read Config file: " . $e->getMessage();
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
        return json_decode($file_contents);
    }
}

class Retriever {

    public function requestUrl(Request &$request) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request->url);
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, TRUE); // remove body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $request->content = curl_exec($ch);
        $request->statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    }
}

class Request {
    public $url;
    public $status_code;
    public $content;

    public function __construct($url) {
        $this->url = $url;
    }
}

interface Validator {
    public function setValidValue($value);
    public function isValid(Request $value);
}

class StatusValidator implements Validator {
    private $valid_value;

    public function setValidValue($value) {
        $this->valid_value = $value;
    }

    public function getValidValue() {
        return $this->valid_value;
    }


    public function isValid(Request $request) {
        return $request->status_code == $this->valid_value;
    }
}

class ContentValidator implements Validator {
    private $valid_value;

    public function setValidValue($value) {
        $this->valid_value = $value;
    }

    public function getValidValue() {
        return $this->valid_value;
    }

    public function isValid(Request $request) {
        return strpos($request->content, $this->valid_value) !== false;
    }
}

class Writer {

    const DEFAULT_TEMPLATE = "default_%s_%d";
    private $now = "";
    private $output_path = "";
    private $file_name = "";

    public function __construct(Config $config)  {
        $this->now = date("Y-m-d");
        $this->output_path = $config->getParam("output_path", "results");
        $this->createOutputPath();
        $this->suggestFilename($config);
    }

    public function writeResults(Result $result) {
        $jsonized = json_encode($result);
        return file_put_contents($this->output_path . $this->file_name, $jsonized);
    }

    private function suggestFilename(Config $config) {
        $template = $config->getParam("filename_template", self::DEFAULT_TEMPLATE);

        $counter = 0;
        while(true) {
            $suggestion = sprintf($template, $this->now, $counter++);
            if (!file_exists($this->output_path . $suggestion)) {
                $this->file_name = $suggestion;
                break;
            }
        }
    }

    private function createOutputPath() {
        if (file_exists($this->output_path)) return;

        if (!mkdir($this->output_path, 0777, true)) {
            throw new RuntimeException("Could not create output dir. Check permissions!");
        }
    }
}

class ValidationResult {
    public $validation_class;
    public $valid_value;
    public $is_valid;
}

class Result {
    public $service_id;
    public $service_name;
    public $request;
    public $error;
    public $validationResults = [];
}

class Main {
    private $config;
    private $validators = [];
    private $writer;

    public function init() {
        $this->config = new Config();
        $this->writer = new Writer($this->config);
    }

    public function run() {
        $services = $this->config->getServices();

        foreach($services as $service) {
            $result = new Result();
            $result->service_id = $service['id'];
            $result->service_name = $service['name'];

            $request = new Request($service['url']);
            $this->setValidatorsFromService($service);

            try {
                (new Retriever())->requestUrl($request);
                $result->request = $request;
            } catch (Exception $e) {
                $result->request = $request;
                $result->error = $e;
            }

            foreach($this->validators as $validator) {
                $validation_result = new ValidationResult();
                $validation_result->valid_value = $validator->getValidValue();
                $validation_result->is_valid = $validator->isValid($request);

                $result->validationResults[] = $validation_result;
            }

            $this->writer->writeResults($result);
        }
    }

    private function setValidatorsFromService($service) {
        foreach($service["validate"] as $rule => $value) {

            $validatorClass = "";
            switch ($rule) {
                case "http-status":
                    $validatorClass = StatusValidator::class;
                    break;
                case "dom-contains":
                    $validatorClass = ContentValidator::class;
                    break;
            }

            $validator = new $validatorClass();
            $validator->setValidValue($value);
            $this->validators[] = $validator;
        }

    }
}

?>
