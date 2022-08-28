<?php

namespace App\Models\Views;

use App\ViewExtensions\AFMAppViewExtension;
use App\ViewExtensions\AppViewExtension;
use Exception;
use Yamf\AppConfig;
use Yamf\Responses\Response;

use App\ViewExtensions\PHPFuncExtension;
use Mpdf\Mpdf;

class TwigPDFView extends Response
{
    public $name;
    public $data;
    public $title; // (default: '')
    public $filename;

    public function __construct($name, $data = [], $title = '', $filename = '')
    {
        parent::__construct();
        $this->name = $name;
        $this->data = $data;
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

        if ($this->data !== null) {
            $this->data['app'] = $app;
            $this->data['title'] = $this->title;
            $this->data['_get'] = $_GET;
            $this->data['_post'] = $_POST;
        }

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
            $mpdf->SetHTMLFooter('<p class="center">Generated via the PBE Prep & Quiz Master Website — pbeprep.com — 2022</p>');
            $mpdf->SetAuthor('PBE Prep & Quiz Master');
            $mpdf->WriteHTML($pdfHTML);
            $mpdf->Output($this->filename, 'I');
        } catch (Exception $e) {
            echo $e;
        }
    }
}
