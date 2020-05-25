<?php

namespace App\Models;

class ValidationStatus
{
    public $didValidate;
    public $output;
    public $error;

    public function __construct(bool $didValidate, $output, string $error = '')
    {
        $this->didValidate = $didValidate;
        $this->output = $output;
        $this->error = $error;
    }
}
