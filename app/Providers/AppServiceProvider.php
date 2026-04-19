<?php

namespace App\Providers;

use App\Mcp\Content\EmbeddedResource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Mcp\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerMcpResponseMacros();
    }

    /**
     * Register helper macros for the laravel/mcp Response class.
     *
     * The framework's built-in Response factory exposes text/json/blob/image/audio
     * but no first-class embedded-resource part. Nexus tools (REQ-M1-004) need
     * to attach a `text/html` resource alongside their text part, so we expose
     * Response::embeddedResource(...) here.
     */
    protected function registerMcpResponseMacros(): void
    {
        Response::macro('embeddedResource', function (string $uri, string $mimeType, string $text): Response {
            // @phpstan-ignore-next-line — protected ctor accessible via Macroable's class-bound closure.
            return new self(new EmbeddedResource($uri, $mimeType, $text));
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)
                ->letters()
                ->numbers()
            : null,
        );
    }
}
