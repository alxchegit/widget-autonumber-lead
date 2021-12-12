<?php

namespace App\Controller;

use App\Request\PanelWidgetRequest;

class PanelController extends BaseController
{
    /**
     * Конструктор.
     * @param Array $config Массив параметров.
     */
    public function __construct($config)
    {
        parent::__construct($config);
    }

    /**
     * @param $request
     * @param $response
     * @return mixed
     */
    public function install($request, $response)
    {
        $post_data = $request->getParsedBody();

        // Выполняем запрос в панель мониторинга
        $req_data = $this->parseRequest($request);
        $panel_request = new PanelWidgetRequest($req_data[static::HOST]);

        $response->getBody()->write($panel_request->install($req_data[static::PARAMS], $post_data, $req_data[static::HEADERS]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param $request
     * @param $response
     * @return mixed
     */
    public function status($request, $response)
    {
        $post_data = $request->getParsedBody();

        // Выполняем запрос в панель мониторинга
        $req_data = $this->parseRequest($request);
        $panel_request = new PanelWidgetRequest($req_data[static::HOST]);

        $response->getBody()->write($panel_request->status($req_data[static::PARAMS], $post_data, $req_data[static::HEADERS]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param $request
     * @param $response
     * @return mixed
     */
    public function order($request, $response)
    {
        $post_data = $request->getParsedBody();

        // Выполняем запрос в панель мониторинга
        $req_data = $this->parseRequest($request);
        $panel_request = new PanelWidgetRequest($req_data[static::HOST]);

        $response->getBody()->write($panel_request->order($req_data[static::PARAMS], $post_data, $req_data[static::HEADERS]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}