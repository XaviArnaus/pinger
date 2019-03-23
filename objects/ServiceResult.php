<?php

class ServiceResult {
    public $service_id;
    public $service_name;
    public $request;
    public $error;
    public $validation_results = [];
    public $duration;
}