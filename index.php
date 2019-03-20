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

class FilesystemCleaner {
    const DEFAULT_TEMPLATE = "packed_%s.zip";
    private $output_path = "";
    private $output_path_zips = "";
    private $results_folders_pattern;
    private $unwanted_files = ['..', '.'];
    private $now;

    public function __construct(Config $config) {
      $this->now = date("Y-m-d");
      $this->output_path = sprintf($config->getParam("output_path", "results_%s"), $this->now);
      $this->output_path_zips = sprintf($config->getParam("output_path", "results_%s"), "old");
      $this->results_folders_pattern = sprintf($config->getParam("output_path", "results_%s"), "");
    }

    public function archiveOldResultFiles() {
        $this->createOutputPath();
        $files_by_dir = $this->getJsonFilesByDir();
        foreach($files_by_dir as $dir => $files) {
          $zip_filename = $this->output_path_zips . DIRECTORY_SEPARATOR . sprintf(
              self::DEFAULT_TEMPLATE,
              str_replace(
                  $this->results_folders_pattern,
                  "",
                  $dir
              )
          );

          $this->zipFiles($files, $zip_filename);
          $this->deleteOldDirs($dir);
        }
    }

    private function zipFiles($files, $zip_filename) {
        $zip = new ZipArchive;
        if ($zip->open($zip_filename, ZipArchive::CREATE) === TRUE) {
            foreach($files as $json_file) {
                  print $json_file . "\n";
                  $zip->addFile($json_file);
            }
            $zip->close();
        }
    }

    private function deleteOldDirs($dir) {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir($dir . DIRECTORY_SEPARATOR . $file)) ?
                $this->deleteOldDirs($dir . DIRECTORY_SEPARATOR . $file) :
                unlink($dir . DIRECTORY_SEPARATOR . $file); 
        }
        return rmdir($dir);
    }

    private function getZipFileNameFromJsonFile($filename) {
      return str_replace(".json", "", $filename);
    }

    private function getJsonFilesByDir() {
        $packages = [];
        $directories = $this->getResultDirectoriesToClean();
        foreach($directories as $directory) {
            $packages[$directory] = array_map(
                function($file) use ($directory) {
                    return $directory . DIRECTORY_SEPARATOR . $file;
                },
                array_filter(
                    array_diff(scandir($directory), $this->unwanted_files),
                    function ($filename) {
                        // Only the JSON files.
                        return strpos($filename, ".json") !== false;
                    }
                )
            );
        }

        return $packages;
    }

    private function getResultDirectoriesToClean() {
      // All but the dots and the last result.
      return array_filter(
          array_diff(scandir('.'), $this->unwanted_files),
          function ($filename) {
              // Only the JSON files.
              //return strpos($filename, ".json") !== false;
              return is_dir($filename) &&
                      strpos($filename, $this->results_folders_pattern) !== FALSE &&
                      $filename !== $this->output_path &&
                      $filename !== $this->output_path_zips;
          }
      );
    }

    private function createOutputPath() {
        if (file_exists($this->output_path_zips)) return;

        if (!mkdir($this->output_path_zips, 0777, true)) {
            throw new RuntimeException("Could not create output dir. Check permissions!");
        }
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
    private $fs_cleaner;

    public function init() {
        $this->config = new Config();
        $this->writer = new Writer($this->config);
        $this->timer = new Timer();
        $this->fs_cleaner = new FilesystemCleaner($this->config);
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

    public function maintenance() {
        $this->fs_cleaner->archiveOldResultFiles();
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
$app->maintenance();

?>
