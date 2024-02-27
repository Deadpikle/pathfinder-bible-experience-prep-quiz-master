<?php

namespace App\Models\Views;

use Yamf\AppConfig;
use Yamf\Responses\JsonResponse;

class JsonStatusCodeResponse extends JsonResponse
{
    public int $statusCode;
    
    public function __construct($data, int $statusCode = 200, $jsonEncodeOptions = 0)
    {
        parent::__construct($data, $jsonEncodeOptions);
        $this->data = $data;
        $this->jsonEncodeOptions = $jsonEncodeOptions;
        $this->statusCode = $statusCode;
    }

    public function output(AppConfig $app)
    {
        parent::output($app);
    }
}
