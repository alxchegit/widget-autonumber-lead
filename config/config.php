<?php
    use App\Controller\BaseController;

    return [
        'config' => [
            BaseController::HOST_PANEl => 'amoai.ru',
            'db' => DI\get('db_conf'),
            'amo' => DI\get('amo_conf'),
            'commands' => DI\get('commands')
        ]
    ];