<?php
    namespace App\Controller;

    use App\Helpers\WLogger;
    use Illuminate\Database\Capsule\Manager as Capsule;

    /**
     * Description of BaseController
     *
     *
     */
    class BaseController
    {
        const HOST_PANEl = 'host_panel';
        const HOST = 'host';
        const PARAMS = 'params';
        const HEADERS = 'headers';

        /**
         * @var
         */
        protected $_config;

        /**
         * @var Capsule
         */
        private $_capsule;

        /**
         * Конструктор.
         * @param array $config Массив параметров.
         */
        public function __construct($config) {

            $this->_config = $config;
            $this->_capsule = new Capsule;

            if (isset($config['db'])) {
                $this->_capsule->addConnection($config['db']);
                $this->_capsule->setAsGlobal();
                $this->_capsule->bootEloquent();
            }
        }

        /**
         * @param string $conf
         * @return mixed|null
         */
        public function getConfig($conf) {
            return isset($this->_config[$conf]) ? $this->_config[$conf] : null;
        }

        /**
         * Возваращает разрешенные заголовки во входящем запросе.
         * @param array $headers
         * @return array
         */
        public function getHeaders($headers) {
            return is_array($headers) ? array_intersect(array_flip(self::ALLOWED_HEADERS), $headers) : [];
        }

        /**
         * Возваращает разрешенные заголовки во входящем запросе.
         * @param $request
         * @return array
         */
        public function parseRequest($request) {
            $headers = $request->getHeaders();

            $headers_data = [];

            /*
            if (isset($headers[self::HOLMROCK_AUTHORIZATION])) {
                $headers_data['Authorization'] = is_array($headers[self::HOLMROCK_AUTHORIZATION]) ? reset($headers[self::HOLMROCK_AUTHORIZATION]) : $headers[self::HOLMROCK_AUTHORIZATION];;
            }
            */

            $host = $this->getConfig(self::HOST_PANEl);

            return [
                self::HOST => $host,
                self::PARAMS => $this->parseQuery($request->getUri()->getQuery()),
                self::HEADERS => $headers_data
            ];
        }

        /**
         * Разбирает строку запроса.
         * @param string $query Строка запроса.
         * @return array
         */
        public function parseQuery($query) {

            $query_param = [];

            if (is_string($query)) {
                parse_str($query, $query_param);
            }

            return $query_param;
        }

        /**
         * @return Capsule
         */
        public function getCapsule() {
            return $this->_capsule;
        }
    }