<?php

// from https://github.com/umpirsky/twig-php-function/

namespace App\ViewExtensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PHPFuncExtension extends AbstractExtension
{
    private $functions = array(
        'floor',
        'ceil',
        'addslashes',
        'chr',
        'chunk_split',
        'explode',
        'implode',
        'sha1',
        'strpos',
        'strrpos',
        'ucwords',
        'gettype',
        'filemtime',
        'mb_split',
        'print_r',
        'in_array',
        'json_encode',
        'json_decode',
        'count',
        'var_dump',
        'str_contains',
        'str_replace',
        'substr'
    );

    public function __construct(array $functions = [])
    {
        if ($functions) {
            $this->allowFunctions($functions);
        }
    }

    public function getFunctions()
    {
        $twigFunctions = [];
        foreach ($this->functions as $function) {
            $twigFunctions[] = new TwigFunction($function, $function);
        }
        return $twigFunctions;
    }

    public function allowFunction($function)
    {
        $this->functions[] = $function;
    }

    public function allowFunctions(array $functions)
    {
        $this->functions = $functions;
    }

    public function getName()
    {
        return 'php_function';
    }
}
