<?php
namespace App\Request;

/**
 * Class PanelRequest
 * @package App\Request
 */
abstract class PanelRequest {

    const BASE_PATH = '/pm';

    private $_host;
    private $_path;
    private $_secure = true;
    private $_request_error;
    private $_connect_timeout = 10;

    /**
     * Конструктор.
     * @param string $host Имя хоста.
     * @param boolean $secure Флаг шифрованное соединение.
     */
    public function __construct($host, $secure)
    {
        $this->_host = $host;
        $this->_secure = (boolean) $secure;
    }

    /**
     * Сеттер свойств.
     * @param string $name Название свойства.
     * @param mixed $value Значение свойства.
     */
    public function __set($name, $value)
    {
        $name = '_' . $name;
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    /**
     * Геттер свойств.
     * @param string $name Название свойства.
     * @return mixed|null
     */
    public function __get($name)
    {
        $name = '_' . $name;
        return isset($this->$name) ? $this->$name : null;
    }

    /**
     * Возвращает тип протокола запроса.
     * @return string
     */
    private function _shema()
    {
        return $this->_secure ? 'https://' : 'http://';
    }

    /**
     * Возвращает форматированую строку запроса.
     * @param array $param Список параметров строки запроса.
     * @param string $question
     * @return string
     */
    private function _query($param, $question = '?')
    {
        if (is_array($param)) {
            $query_string = http_build_query($param);

            return empty($query_string) ? '' : $question . preg_replace('/%5B(\d+?)%5D=/', '[]=', $query_string);
        } else {
            return '';
        }
    }

    /**
     * Формирует url из параметров строки запроса.
     * @param array $param Список параметров строки запроса.
     * @return string
     */
    private function _url($param)
    {
        return $this->_shema()
            . $this->_host
            . static::BASE_PATH
            . $this->_path
            . $this->_query($param);
    }

    /**
     * Форматирует заголовок из переданного массива параметров.
     * @param array $header Список параметров заголовка Имя параметра => Значение заголовка.
     * @return array
     */
    private function _header($header)
    {
        $formated_header = [];
        if ($header) {
            foreach ($header as $name => $value) {
                $formated_header[] = $name . ': ' . $value;
            }
        }
        return $formated_header;
    }

    /**
     * Возвращает результат запроса.
     * @param array $params Список параметров строки запроса.
     * @param array $headers
     * @param array $post_data
     * @param boolean $patch
     * @return string|boolean
     */
    private function _request($params, $headers = [], $post_data = [], $patch = false)
    {
        $url = $this->_url($params);

        $c = curl_init();

        curl_setopt($c, CURLOPT_URL, $url);
        //curl_setopt($c, CURLINFO_HEADER_OUT, true);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($c, CURLOPT_USERAGENT, "DigitalBis-API-client/1.0 ");
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, $this->_connect_timeout);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);

        if (!empty($post_data)) {
            if (is_array($post_data)) {
                if ($patch) {
                    curl_setopt($c, CURLOPT_CUSTOMREQUEST, 'PATCH');
                } else {
                    curl_setopt($c, CURLOPT_POST, 1);
                }

                curl_setopt($c, CURLOPT_POSTFIELDS, $this->_query($post_data, ''));
            } else {
                curl_setopt($c, CURLOPT_POST, 1);
                curl_setopt($c, CURLOPT_POSTFIELDS, $post_data);
            }
        }

        if (!empty($headers)) {
            curl_setopt($c, CURLOPT_HTTPHEADER, $this->_header($headers));
        }

        $contents = curl_exec($c);

        $this->_request_error = curl_error($c);
        if ($this->_request_error != "") {
            curl_close($c);
            return false;
        }

        curl_close($c);

        return $contents;
    }

    /**
     * Выполняет запрос и возвращает результат.
     * @param array $params Список параметров строки запроса, пара название и значение.
     * @param array $header Список передаваемых в запросе заголовков.
     * @return string
     */
    protected function sendRequest($params, $header = [])
    {
        return $this->_request($params, $header);
    }

    /**
     * Выполняет запрос POST и возвращает результат.
     * @param array $params Список параметров строки запроса, пара название и значение.
     * @param array $post_data Передаваемые данные.
     * @param array $header Список передаваемых в запросе заголовков.
     * @return string
     */
    protected function sendPostRequest($params, $post_data = [], $header = [])
    {
        return $this->_request($params, $header, $post_data);
    }

    /**
     * Выполняет запрос PATCH и возвращает результат.
     * @param array $params Список параметров строки запроса, пара название и значение.
     * @param array $post_data Передаваемые данные.
     * @param array $header Список передаваемых в запросе заголовков.
     * @return string
     */
    protected function sendPatchRequest($params, $post_data = [], $header = [])
    {
        return $this->_request($params, $header, $post_data, true);
    }
}