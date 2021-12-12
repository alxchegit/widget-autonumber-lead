<?php

namespace App\Controller;

use App\Helpers\ResponseHelper;
use App\Helpers\WLogger;
use App\Request\PanelWidgetRequest;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;


class HookController extends BaseController
{
    /**
     * constructor
     * @param array $config Массив параметров.
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
    public function producerDpHook($request, $response)
    {
        $post_data = $request->getParsedBody();
        WLogger::log_it('DP Hook producer: ' . json_encode($post_data), __LINE__);

        // Получаем данные по событию
        if($post_data['event']['data']['element_type'] != 2) {
            WLogger::log_it('wrong element type! type is ' . $post_data['event']['data']['element_type'], __LINE__);
            return ResponseHelper::error($response, ['msg' => 'Неподходящий тип элемента!']);
        }

        // Выполняем запрос в панель мониторинга (проверяем статус виджета)
        $req_data = $this->parseRequest($request);
        $panel_request = new PanelWidgetRequest($req_data[static::HOST]);
        $statusResponse = json_decode($panel_request->status($req_data[static::PARAMS], ['account_id' => $post_data['account_id'], 'widget_code' => 'autonumber_lead'], $req_data[static::HEADERS]), true);

        // Если виджет не активен (просрочен тест период / не оплачен)
        if(!$statusResponse['data']['active']) {
            WLogger::log_it('widget is not activated!', __LINE__);
            return ResponseHelper::error($response, ['msg' => $statusResponse['data']['msg']]);
        }

        $connection = new AMQPStreamConnection('localhost', 5672, 'admin', 'r_q3DudzBg');
        $channel = $connection->channel();

        $channel->queue_declare('autonumber_lead', false, true, false, false);

        $msg = new AMQPMessage(json_encode($post_data), ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $channel->basic_publish($msg, '', 'autonumber_lead');

        $channel->close();
        $connection->close();

        WLogger::log_it('successful send message to queue!', __LINE__);

        return ResponseHelper::success($response, ['msg' => 'Задача успешно добавлена!']);
    }
}