<?php
    ini_set('error_reporting', E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);

    require_once __DIR__ . '/../vendor/autoload.php';

    use App\Helpers\ResponseHelper;
    use App\Controller\AmoController;
    use App\Controller\PanelController;
    use App\Controller\WidgetController;
    use App\Controller\HookController;

    // Создаем контейнер
    $containerBuilder = new \DI\ContainerBuilder();
    $containerBuilder->addDefinitions(__DIR__ . '/../config/config.database.php');
    $containerBuilder->addDefinitions(__DIR__ . '/../config/config.amocrm.php');
    $containerBuilder->addDefinitions(__DIR__ . '/../config/config.commands.php');
    $containerBuilder->addDefinitions(__DIR__ . '/../config/config.classimport.php');
    $containerBuilder->addDefinitions(__DIR__ . '/../config/config.php');

    $container = $containerBuilder->build();

    // Создаем приложение
    $app = \DI\Bridge\Slim\Bridge::create($container);

    // Указываем путь к проекту
    $app->setBasePath('/ws/autonumber_lead');


    /* Panel commands */

    // Установка виджета (settings)
    $app->post('/install', [PanelController::class, 'install']);

    // Покупка виджета (settings)
    $app->post('/order', [PanelController::class, 'order']);

    // Проверка статуса виджета (settings / DP)
    $app->post('/status', [PanelController::class, 'status']);

    /* end Panel commands */


    /* Widget commands */

    // Получение процесса (DP)
    $app->post('/process', [WidgetController::class, 'process']);

    // Сохранение процесса (DP)
    $app->post('/saveProcess', [WidgetController::class, 'saveProcess']);

    // Удаление процесса (DP)
    $app->post('/deleteProcess', [WidgetController::class, 'deleteProcess']);


    /* end Widget commands */


    /* HOOKS && AUTH */

    // Получение токена amoCRM
    $app->get('/redirectUri', [AmoController::class, 'redirectUri']);

    // Обработка вебхука (DP) с помощью очереди
    $app->post('/dpHook', [HookController::class, 'producerDpHook']);

    /* end HOOKS */

    // Возвращаем приложение
    return $app;