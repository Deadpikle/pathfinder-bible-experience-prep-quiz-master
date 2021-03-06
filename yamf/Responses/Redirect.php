<?php

namespace Yamf\Responses;

use Yamf\AppConfig;

class Redirect extends Response
{

    public $redirectPath; // if redirecting within current website, include beginning slash
    // true if redirecting within the current website (e.g. to /about from /blog);
    // false if redirecting to another website entirely (e.g. from this site to www.example.com)
    public $isInternalSiteRedirect;
    
    public function __construct($redirectPath, $isInternalRedirect = true)
    {
        parent::__construct(302);
        $this->redirectPath = $redirectPath;
        $this->isInternalSiteRedirect = $isInternalRedirect;
    }

    public function output(AppConfig $app)
    {
        parent::output($app);
        if ($this->isInternalSiteRedirect) {
            header("Location: " . $app->yurl($this->redirectPath));
        } else {
            header("Location: " . $this->redirectPath);
        }
        die();
    }
}
