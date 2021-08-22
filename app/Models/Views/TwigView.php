<?php

namespace App\Models\Views;

use App\ViewExtensions\AFMAppViewExtension;
use App\ViewExtensions\AppViewExtension;
use Exception;
use Yamf\AppConfig;
use Yamf\Responses\Response;

use App\ViewExtensions\PHPFuncExtension;

class TwigView extends Response
{
    public $name;
    public $data;
    public $title; // (default: '')

    public function __construct($name, $data = [], $title = '')
    {
        parent::__construct();
        $this->name = $name;
        $this->data = $data;
        $this->title = $title;
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
            echo $twig->render($filename, $this->data ?? ['app' => $app]);
        } catch (Exception $e) {
            echo $e;
        }
    }
}
