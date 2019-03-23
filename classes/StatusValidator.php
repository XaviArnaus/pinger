<?php

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