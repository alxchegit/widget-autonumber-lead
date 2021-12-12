<?php
    return [
        App\Controller\AmoController::class => DI\autowire()->constructor(DI\get('config')),
        App\Controller\PanelController::class => DI\autowire()->constructor(DI\get('config')),
        App\Controller\WidgetController::class => DI\autowire()->constructor(DI\get('config')),
        App\Controller\HookController::class => DI\autowire()->constructor(DI\get('config')),

        App\Console\ConsumerDpHookCommand::class => DI\autowire()->constructor(DI\get('config'))
    ];