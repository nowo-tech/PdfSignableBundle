<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\DemoMenu;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

use function is_string;

final class DemosTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('demos_menu', $this->demosMenu(...)),
            new TwigFunction('demos_prev_next', $this->demosPrevNext(...)),
            new TwigFunction('demos_home_sections', $this->demosHomeSections(...)),
        ];
    }

    /**
     * @return list<array{title: string, items: list<array{type: string, title?: string, col_class?: string, cards?: list<array{title: string, bullets: list<string>, route: string, btn_class: string, card_class?: string}>}>}>
     */
    public function demosHomeSections(): array
    {
        return DemoMenu::homeSections();
    }

    /**
     * @return list<array{group: string, items: list<array{route: string, label: string}>}>
     */
    public function demosMenu(): array
    {
        return DemoMenu::grouped();
    }

    /**
     * @return array{prev: array{route: string, label: string}|null, next: array{route: string, label: string}|null}
     */
    public function demosPrevNext(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $route   = $request?->attributes->get('_route');
        if (!$route || !is_string($route)) {
            return ['prev' => null, 'next' => null];
        }

        return DemoMenu::prevNext($route);
    }
}
