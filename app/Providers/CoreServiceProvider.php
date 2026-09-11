<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Contracts\Mediator;
use Core\Mediator\ContainerMediator;
use Illuminate\Support\ServiceProvider;

final class CoreServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public const HANDLERS = [];

    /** @var list<class-string> */
    public const BEHAVIORS = [];

    public function register(): void
    {
        $this->app->singleton(Mediator::class, fn ($app): Mediator => new ContainerMediator(
            $app,
            self::HANDLERS,
            self::BEHAVIORS,
        ));
    }
}
