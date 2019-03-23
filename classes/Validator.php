<?php

interface Validator {
    public function setValidValue($value);
    public function getValidValue();
    public function isValid(Request $value);
}