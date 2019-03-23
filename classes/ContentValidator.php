<?php

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