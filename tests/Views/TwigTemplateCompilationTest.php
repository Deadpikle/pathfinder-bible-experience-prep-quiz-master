<?php
declare(strict_types=1);

namespace Tests\Views;

use App\ViewExtensions\AppViewExtension;
use App\ViewExtensions\PHPFuncExtension;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigTemplateCompilationTest extends TestCase
{
    public function testEveryTwigTemplateCompilesWithTheApplicationExtension(): void
    {
        $views = dirname(__DIR__, 2) . '/views';
        $twig = new Environment(new FilesystemLoader($views), ['cache' => false]);
        $twig->addExtension(new PHPFuncExtension());
        $twig->addExtension(new AppViewExtension());

        $templates = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views));
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'twig') {
                $templates[] = str_replace('\\', '/', substr($file->getPathname(), strlen($views) + 1));
            }
        }
        sort($templates);
        self::assertNotEmpty($templates);
        foreach ($templates as $template) {
            $twig->load($template);
            $this->addToAssertionCount(1);
        }
    }
}
