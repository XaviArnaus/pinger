<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

class Retriever {

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
        curl_close($ch);
    }
}

class Request {
    public $url;
    public $status_code;
    public $header;
    public $body;
    public $duration;

    public function __construct($url) {
        $this->url = $url;
    }
}

interface Validator {
    public function setValidValue($value);
    public function getValidValue();
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
        return strpos($request->body, $this->valid_value) !== false;
    }
}

class Writer {

    const DEFAULT_TEMPLATE = "default_%s_%d.json";
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
        $jsonized = json_encode($result, JSON_PRETTY_PRINT);
        return file_put_contents($this->getOutput(true), $jsonized);
    }

    private function suggestFilename(Config $config) {
        $template = $config->getParam("filename_template", self::DEFAULT_TEMPLATE);

        $counter = 0;
        while(true) {
            $suggestion = sprintf($template, $this->now, ++$counter);
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
        if ($with_filename) return $this->output_path . '/' . $this->file_name;
        else return $this->output_path . '/';
    }
}

class Timer {
  private $timers = [];

  public static function quickStart($timer_name) {
    $quick = new self();
    $quick->start($timer_name);
    return $quick;
  }

  public function start($timer_name) {
    if (!isset($this->timers[$timer_name])) {
      $this->timers[$timer_name] = microtime(true);
    }
  }

  public function stop($timer_name) {
    if (isset($this->timers[$timer_name])) {
      $elapsed = microtime(true) - $this->timers[$timer_name];
      unset($this->timers[$timer_name]);

      // Already in seconds, as a float.
      return $elapsed;
    }
  }
}

class ValidationResult {
    public $validation_class;
    public $valid_value;
    public $is_valid;
}

class ServiceResult {
    public $service_id;
    public $service_name;
    public $request;
    public $error;
    public $validation_results = [];
    public $duration;
}

class Result {
  public $version;
  public $date;
  public $service_results = [];
  public $duration;
}

class Main {
    const VERSION = 1;
    private $config;
    private $writer;
    private $timer;

    public function init() {
        $this->config = new Config();
        $this->writer = new Writer($this->config);
        $this->timer = new Timer();
    }

    public function run() {
        try{
            $this->timer->start('general');

            $result = new Result();
            $result->version = self::VERSION;
            $result->date = date("Y-m-d H:i:s");

            $services = $this->config->getServices();
            foreach($services as $service) {
                $this->timer->start($service['id']);
                $service_result = new ServiceResult();
                $service_result->service_id = $service['id'];
                $service_result->service_name = $service['name'];

                $request = new Request($service['url']);
                $validators = $this->setValidatorsFromService($service);

                try {
                    (new Retriever())->requestUrl($request);
                    $service_result->request = $request;

                    foreach($validators as $validator) {
                        $validation_result = new ValidationResult();
                        $validation_result->validation_class = get_class($validator);
                        $validation_result->valid_value = $validator->getValidValue();
                        $validation_result->is_valid = $validator->isValid($request);

                        $service_result->validation_results[] = $validation_result;
                    }

                    $service_result->error = false;
                } catch (Exception $e) {
                    $service_result->request = $request;
                    $service_result->error = $e;
                }

                $service_result->duration = $this->timer->stop($service['id']);
                $result->service_results[] = $service_result;
            }

            $result->duration = $this->timer->stop('general');
            $this->writer->writeResults($result);
        } catch (Exception $e) {
            var_dump($e);
        }

    }

    private function setValidatorsFromService($service) {
        $validators = [];
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
            $validators[] = $validator;
        }
        return $validators;
    }
}

$app = new Main();
$app->init();
$app->run();

?>
