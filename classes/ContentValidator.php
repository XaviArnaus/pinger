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
        if (strlen($this->valid_value) > 0) {
            return strpos($request->body, $this->valid_value) !== false;
        } else {
            return strlen($this->valid_value) == strlen($request->body);
        }
    }
}