<?php

namespace Yamf;

class Request
{
    public string $route; // raw route string for this request
    public string $controller; // string name of controller
    public string $function; // string name of controller function to call
    public array $routeParams; // any params in the route such as {id}. Format: ['id' => value]
    public array $get; // any GET params found in the URL -- same format as $_GET (no extra processing performed)
    public array $post; // any POST params -- same format as $_POST (no extra processing performed)
    public array $files; // any $_FILES params
    public ?string $anchor; // If used, the # portion of the url (without the #). Router is smart enough to not match on URLs like `/blah/#/foo`.

    public function __construct()
    {
        $this->route = '';
        $this->controller = '';
        $this->function = '';
        $this->routeParams = [];
        $this->get = [];
        $this->post = [];
        $this->files = [];
        $this->anchor = '';
    }
}
