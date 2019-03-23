<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "classes/Config.php";
require_once "classes/Retriever.php";
require_once "classes/Validator.php";
require_once "classes/StatusValidator.php";
require_once "classes/ContentValidator.php";
require_once "classes/Writer.php";
require_once "classes/FilesystemCleaner.php";
require_once "classes/Timer.php";

require_once "objects/Request.php";
require_once "objects/ValidationResult.php";
require_once "objects/ServiceResult.php";
require_once "objects/Result.php";

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
