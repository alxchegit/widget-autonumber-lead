<?php
    use Symfony\Component\Console\Application;
    use Symfony\Component\Console\Input\ArgvInput;
    use Symfony\Component\Console\Input\InputOption;

    require_once __DIR__ . '/../vendor/autoload.php';

    $env = (new ArgvInput())->getParameterOption(['--env', '-e'], 'development');

    if ($env) {
        $_ENV['APP_ENV'] = $env;
    }

    // Получаем контейнер
    $container = (require __DIR__ . '/../config/bootstrap.php')->getContainer();

    // Создаем приложение
    $application = new Application();

    $application->getDefinition()->addOption(
        new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', 'development')
    );

    foreach ($container->get('commands') as $class) {
        $application->add($container->get($class));
    }

    $application->run();