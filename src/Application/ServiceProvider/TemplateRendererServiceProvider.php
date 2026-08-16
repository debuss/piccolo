<?php declare(strict_types=1);

namespace Application\ServiceProvider;

use League\Container\ServiceProvider\AbstractServiceProvider;
use League\Plates\Engine;
use Mezzio\Plates\PlatesRenderer;
use Mezzio\Template\TemplateRendererInterface;

/**
 * Template Renderer Service Provider
 *
 * @see https://docs.mezzio.dev/mezzio/v3/features/template/interface/
 */
class TemplateRendererServiceProvider extends AbstractServiceProvider
{

    public function provides(string $id): bool
    {
        return $id === TemplateRendererInterface::class;
    }

    public function register(): void
    {
        $this
            ->getContainer()
            ->add(TemplateRendererInterface::class, function (): TemplateRendererInterface {
                $plates = new Engine();
                $plates->addFolder('app', storage_path('templates'));

                return new PlatesRenderer($plates);
            });
    }
}