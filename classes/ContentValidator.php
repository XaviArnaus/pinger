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
        if (len($this->valid_value) > 0) {
            return strpos($request->body, $this->valid_value) !== false;
        } else {
            return len($this->valid_value) == len($request->body);
        }
    }
}