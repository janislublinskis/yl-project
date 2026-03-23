<?php

declare(strict_types=1);

use Arkitect\CLI\Config;
use Arkitect\ClassSet;
use Arkitect\Rules\Rule;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\Extend;
use Arkitect\Expression\ForClasses\Implement;
use Arkitect\Expression\ForClasses\HaveNameMatching;

return static function (Config $config): void {

    // ── Scan all three packages ───────────────────────────────────
    $classSet = ClassSet::fromDir(__DIR__ . '/packages/yl');

    $config->add(
        $classSet,

        // ── Module decoupling ─────────────────────────────────
        // The most important rule: Products must not know about Posts
        // and vice versa. This enforces requirement 5 in code.
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('Yl\Products'))
            ->should(new NotDependsOnTheseNamespaces(['Yl\Posts']))
            ->because('Products and Posts modules must remain fully decoupled'),

        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('Yl\Posts'))
            ->should(new NotDependsOnTheseNamespaces(['Yl\Products']))
            ->because('Posts and Products modules must remain fully decoupled'),

        // Helper module must not depend on any other YL module
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('Yl\Helper'))
            ->should(new NotDependsOnTheseNamespaces(['Yl\Products', 'Yl\Posts']))
            ->because('Helper is a zero-dependency shared utility module'),

        // ── Controller rules ──────────────────────────────────
        // All module controllers must extend BaseApiController
        // to guarantee consistent ApiResponse usage
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('Yl\Products\Http\Controllers'))
            ->should(new Extend('Yl\Helper\Http\Controllers\BaseApiController'))
            ->because('All controllers must use the shared BaseApiController for consistent responses'),

        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('Yl\Posts\Http\Controllers'))
            ->should(new Extend('Yl\Helper\Http\Controllers\BaseApiController'))
            ->because('All controllers must use the shared BaseApiController for consistent responses'),

        // ── Job rules ─────────────────────────────────────────
        // All jobs must implement ShouldQueue — no synchronous jobs allowed
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('Yl\Products\Jobs'))
            ->should(new Implement('Illuminate\Contracts\Queue\ShouldQueue'))
            ->because('All jobs must be asynchronous and routed through RabbitMQ'),

        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('Yl\Posts\Jobs'))
            ->should(new Implement('Illuminate\Contracts\Queue\ShouldQueue'))
            ->because('All jobs must be asynchronous and routed through RabbitMQ'),

        // ── Naming conventions ────────────────────────────────
        // Controllers must be suffixed Controller
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('Yl\Products\Http\Controllers', 'Yl\Posts\Http\Controllers'))
            ->should(new HaveNameMatching('*Controller'))
            ->because('PSR and Laravel convention: controller classes must end in Controller'),

        // Jobs must be suffixed Job
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('Yl\Products\Jobs', 'Yl\Posts\Jobs'))
            ->should(new HaveNameMatching('*Job'))
            ->because('Convention: job classes must end in Job'),

        // Requests must be suffixed Request
        Rule::allClasses()
            ->that(new ResideInOneOfTheseNamespaces('Yl\Products\Http\Requests', 'Yl\Posts\Http\Requests'))
            ->should(new HaveNameMatching('*Request'))
            ->because('Convention: form request classes must end in Request')
    );
};
