<?php

namespace App\Request;

/**
 * Description of PanelWidgetRequest
 *
 *
 */
class PanelWidgetRequest extends PanelRequest
{

    /**
     * Конструктор.
     * @param String $host Имя хоста.
     * @param Boolean $secure Флаг шифрованного соединение.
     */
    public function __construct($host, $secure = false)
    {
        parent::__construct($host, $secure);
    }

    public function install($params, $post_data, $headers)
    {
        $this->path = "/install";
        return $this->sendPostRequest([], $post_data, $headers);
    }

    public function status($params, $post_data, $headers)
    {
        $this->path = "/status";
        return $this->sendPostRequest([], $post_data, $headers);
    }

    public function order($params, $post_data, $headers)
    {
        $this->path = "/order";
        return $this->sendPostRequest([], $post_data, $headers);
    }
}