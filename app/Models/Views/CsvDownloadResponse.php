<?php

namespace App\Models\Views;

use Yamf\AppConfig;
use Yamf\Responses\Response;

final class CsvDownloadResponse extends Response
{
    public function __construct(
        private readonly string $contents,
        private readonly string $filename
    ) {
        parent::__construct(200);
    }

    public function output(AppConfig $app)
    {
        parent::output($app);
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $this->filename) ?? 'questions.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        echo $this->contents;
    }
}
