<?php

namespace App\Models;

class ValidationStatus
{
    public bool $didValidate;
    public $output;
    public string $error;

    public function __construct(bool $didValidate, $output, string $error = '')
    {
        $this->didValidate = $didValidate;
        $this->output = $output;
        $this->error = $error;
    }
}
