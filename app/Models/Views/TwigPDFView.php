<?php

namespace App\Models\Views;

use App\ViewExtensions\AppViewExtension;
use Exception;
use Yamf\AppConfig;
use Yamf\Responses\Response;

use App\ViewExtensions\PHPFuncExtension;
use Mpdf\Mpdf;

class TwigPDFView extends Response
{
    public string $name;
    public ?array $data;
    public string $title; // (default: '')
    public string $filename;

    public function __construct(string $name, ?array $data = [], string $title = '', string $filename = '')
    {
        parent::__construct();
        $this->name = $name;
        $this->data = $data ?? [];
        $this->title = $title;
        $this->filename = $filename;
    }

    public function output(AppConfig $app)
    {
        parent::output($app);

        $loader = new \Twig\Loader\FilesystemLoader($app->viewsFolderName);
        if (!file_exists('views/_cache')) {
            mkdir('views/_cache');
        }
        $twig = new \Twig\Environment($loader, [
            'cache' => 'views/_cache',
            'auto_reload' => true
        ]);
        $twig->addExtension(new PHPFuncExtension());
        $twig->addExtension(new AppViewExtension());

        if ($this->data === null) {
            $this->data = [];
        }
        $this->data['app'] = $app;
        $this->data['title'] = $this->title;
        $this->data['_get'] = $_GET;
        $this->data['_post'] = $_POST;

        $filename = $this->name . '.twig';
        try {
            $pdfHTML = $twig->render($filename, $this->data ?? ['app' => $app]);
            $options = [
                'orientation' => 'P',
                'mode' => 'utf-8',
                'format' => 'Legal',
                'tempDir' => 'views/_cache',
                //'default_font' => 'sans'
            ];
            $mpdf = new Mpdf($options);
            $mpdf->SetTitle($this->title);
            $mpdf->SetHTMLFooter('<p class="center">Generated via the PBE Prep & Quiz Master Website — pbeprep.com — © 2017 - ' . date('Y') . '</p>');
            $mpdf->SetAuthor('PBE Prep & Quiz Master'); // TODO: set to website name (and above footer)
            $mpdf->WriteHTML($pdfHTML);
            $mpdf->Output($this->filename, 'I');
        } catch (Exception $e) {
            echo $e;
        }
    }
}
