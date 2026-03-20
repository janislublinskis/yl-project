<?php

declare(strict_types=1);

use Phparkitect\Architecture\DSL\ArchitectureAssertionBuilder;
use Phparkitect\Architecture\DSL\That;
use Phparkitect\Rules\Rule;
use Phparkitect\ClassSet;
use Phparkitect\ClassSetRules;

return static function (ArchitectureAssertionBuilder $builder): void {

    // ── Scan all three packages ───────────────────────────────────
    $classSet = ClassSet::fromDir(__DIR__ . '/packages/yl');

    $builder->add(
        ClassSetRules::create($classSet,

            // ── Module decoupling ─────────────────────────────────
            // The most important rule: Products must not know about Posts
            // and vice versa. This enforces requirement 5 in code.
            Rule::allClasses()
                ->that(ResideInOneOfTheseNamespaces('Yl\Products'))
                ->should(NotDependOnTheseNamespaces('Yl\Posts'))
                ->because('Products and Posts modules must remain fully decoupled'),

            Rule::allClasses()
                ->that(ResideInOneOfTheseNamespaces('Yl\Posts'))
                ->should(NotDependOnTheseNamespaces('Yl\Products'))
                ->because('Posts and Products modules must remain fully decoupled'),

            // Helper module must not depend on any other YL module
            Rule::allClasses()
                ->that(ResideInOneOfTheseNamespaces('Yl\Helper'))
                ->should(NotDependOnTheseNamespaces('Yl\Products', 'Yl\Posts'))
                ->because('Helper is a zero-dependency shared utility module'),

            // ── Controller rules ──────────────────────────────────
            // All module controllers must extend BaseApiController
            // to guarantee consistent ApiResponse usage
            Rule::allClasses()
                ->that(ResideInOneOfTheseNamespaces('Yl\Products\Http\Controllers'))
                ->should(Extend('Yl\Helper\Http\Controllers\BaseApiController'))
                ->because('All controllers must use the shared BaseApiController for consistent responses'),

            Rule::allClasses()
                ->that(ResideInOneOfTheseNamespaces('Yl\Posts\Http\Controllers'))
                ->should(Extend('Yl\Helper\Http\Controllers\BaseApiController'))
                ->because('All controllers must use the shared BaseApiController for consistent responses'),

            // ── Job rules ─────────────────────────────────────────
            // All jobs must implement ShouldQueue — no synchronous jobs allowed
            Rule::allClasses()
                ->that(ResideInOneOfTheseNamespaces('Yl\Products\Jobs'))
                ->should(Implement('Illuminate\Contracts\Queue\ShouldQueue'))
                ->because('All jobs must be asynchronous and routed through RabbitMQ'),

            Rule::allClasses()
                ->that(ResideInOneOfTheseNamespaces('Yl\Posts\Jobs'))
                ->should(Implement('Illuminate\Contracts\Queue\ShouldQueue'))
                ->because('All jobs must be asynchronous and routed through RabbitMQ'),

            // ── Naming conventions ────────────────────────────────
            // Controllers must be suffixed Controller
            Rule::allClasses()
                ->that(ResideInOneOfTheseNamespaces('Yl\Products\Http\Controllers', 'Yl\Posts\Http\Controllers'))
                ->should(HaveNameMatching('*Controller'))
                ->because('PSR and Laravel convention: controller classes must end in Controller'),

            // Jobs must be suffixed Job
            Rule::allClasses()
                ->that(ResideInOneOfTheseNamespaces('Yl\Products\Jobs', 'Yl\Posts\Jobs'))
                ->should(HaveNameMatching('*Job'))
                ->because('Convention: job classes must end in Job'),

            // Requests must be suffixed Request
            Rule::allClasses()
                ->that(ResideInOneOfTheseNamespaces('Yl\Products\Http\Requests', 'Yl\Posts\Http\Requests'))
                ->should(HaveNameMatching('*Request'))
                ->because('Convention: form request classes must end in Request')
        )
    );
};