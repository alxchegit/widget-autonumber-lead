<?php
    namespace App\Controller;

    use AmoCRM\Exceptions\AmoCRMApiConnectExceptionException;
    use AmoCRM\Exceptions\AmoCRMApiErrorResponseException;
    use AmoCRM\Exceptions\AmoCRMApiException;
    use AmoCRM\Exceptions\AmoCRMApiHttpClientException;
    use AmoCRM\Exceptions\AmoCRMoAuthApiException;
    use App\Helpers\ResponseHelper;
    use App\Helpers\WLogger;
    use App\Model\Token;
    use Illuminate\Database\QueryException;

    use AmoCRM\Client\AmoCRMApiClient;
    use League\OAuth2\Client\Token\AccessToken;
    use League\OAuth2\Client\Token\AccessTokenInterface;

    /**
     * Class AmoController
     * @package App\Controller
     */
    class AmoController extends BaseController {

        /**
         * @var int $accountId
         */
        private $accountId;

        /**
         * @param array $config
         */
        public function __construct($config) {
            parent::__construct($config);
        }

        /**
         * @param $request
         * @param $response
         * @return mixed
         */
        public function redirectUri($request, $response) {

            // GET параметры
            $params = $this->parseQuery($request->getUri()->getQuery());

            // AMO конфиг
            $amo_conf = $this->getConfig('amo');

            // AMO клииент
            $apiClient = new AmoCRMApiClient($amo_conf['clientId'], $amo_conf['clientSecret'], $amo_conf['redirectUri']);

            // получаем поддомен
            $baseDomain = $params['referer'] ?? false;

            if (!$baseDomain)
                return ResponseHelper::error($response);

            $apiClient->setAccountBaseDomain($baseDomain);

            /**
             * Ловим обратный код
             */
            try {
                $accessToken = $apiClient->getOAuthClient()->getAccessTokenByCode($params['code']);

                $accountDomain = $apiClient->getOAuthClient()->getAccountDomain($accessToken);

                $apiClient->setAccessToken($accessToken);

                $this->saveAmoToken($accountDomain->getId(), [
                    'access_token'  => $accessToken->getToken(),
                    'refresh_token' => $accessToken->getRefreshToken(),
                    'expires'    => $accessToken->getExpires(),
                    'base_domain'   => $accountDomain->getDomain(),
                ]);

                $apiClient->getOAuthClient()->setAccessTokenRefreshCallback(
                    function ($accessToken, string $baseDomain) {
                        WLogger::log_it('onAccessTokenRefresh' . PHP_EOL . 'accountId: ' . $this->accountId . PHP_EOL . 'token: ' . json_encode([
                                'access_token'  => $accessToken->getToken(),
                                'refresh_token' => $accessToken->getRefreshToken(),
                                'expires'    => $accessToken->getExpires(),
                                'base_domain'   => $baseDomain,
                            ]), __LINE__);
                        global $apiClient;

                        $this->saveAmoToken($apiClient->getOAuthClient()->getAccountDomain($accessToken)->getId(), [
                            'access_token'  => $accessToken->getToken(),
                            'refresh_token' => $accessToken->getRefreshToken(),
                            'expires'    => $accessToken->getExpires(),
                            'base_domain'   => $baseDomain
                        ]);
                });


                // return ResponseHelper::success($response, ['msg' => 'Виджет успешно установлен на Ваш аккаунт!']);
                $response->getBody()->write('Виджет успешно установлен на Ваш аккаунт!');

                return $response->withHeader('Content-Type', 'text/html');
            } catch (AmoCRMoAuthApiException $e) {
                // return  ResponseHelper::error($response, ['msg' => $e->getMessage()]);
                WLogger::log_it($e->getMessage(), __LINE__);
            } catch (AmoCRMApiConnectExceptionException $e) {
                // return ResponseHelper::error($response, ['msg' => $e->getMessage()]);
                WLogger::log_it($e->getMessage(), __LINE__);
            } catch (AmoCRMApiErrorResponseException $e) {
                // return ResponseHelper::error($response, ['msg' => $e->getMessage()]);
                WLogger::log_it($e->getMessage(), __LINE__);
            } catch (AmoCRMApiHttpClientException $e) {
                // return ResponseHelper::error($response, ['msg' => $e->getMessage()]);
                WLogger::log_it($e->getMessage(), __LINE__);
            } catch (AmoCRMApiException $e) {
                WLogger::log_it($e->getMessage(), __LINE__);
            }

            $response->getBody()->write('Возникла ошибка при установке виджета.<br> Пожалуйста, обратитесь в службу технической поддержки по эл. адресу <a href="mailto:hi@digitalbis.ru">hi@digitalbis.ru</a>.');

            return $response->withHeader('Content-Type', 'text/html');
        }

        /**
         * @param $accountId
         * @return AmoCRMApiClient
         */
        public function auth($accountId)
        {
            $this->accountId = $accountId;

            // Получим конфигурацию амо
            $amo_conf = $this->getConfig('amo');

            WLogger::log_it('Amo conf:', __LINE__);
            WLogger::log_it($amo_conf, __LINE__);

            // Подключим клиента
            $apiClient = new AmoCRMApiClient($amo_conf['clientId'], $amo_conf['clientSecret'], $amo_conf['redirectUri']);

            // Получим токен
            $tokenModel = Token::query()->find($accountId) ?? false;

            if (!$tokenModel)
                WLogger::log_it('token not found!', __LINE__);

            $tokenModel = json_decode($tokenModel->token, true);

            WLogger::log_it('Token founded! Token:', __LINE__);
            WLogger::log_it($tokenModel, __LINE__);

            $amoToken = new AccessToken($tokenModel);

            if ($amoToken->hasExpired())
                WLogger::log_it('Token is expired...', __LINE__);

            $apiClient->setAccessToken($amoToken)
                ->setAccountBaseDomain($tokenModel['base_domain'])
                ->onAccessTokenRefresh(
                    function (AccessTokenInterface $accessToken, string $baseDomain) {
                        WLogger::log_it('onAccessTokenRefresh accountId: ' . $this->accountId . 'token: ', __LINE__);
                        WLogger::log_it($accessToken->getValues(), __LINE__);

                        $this->saveAmoToken($this->accountId, [
                            'access_token'  => $accessToken->getToken(),
                            'refresh_token' => $accessToken->getRefreshToken(),
                            'expires'       => $accessToken->getExpires(),
                            'base_domain'   => $baseDomain
                        ]);
                    }
                );

            return $apiClient;
        }

        /**
         * @param $accountId
         * @param $accessToken
         * @return void|null
         */
        public function saveAmoToken($accountId, $accessToken)
        {
            WLogger::log_it('saving token to DB', __LINE__);
            WLogger::log_it($accountId, __LINE__);
            WLogger::log_it($accessToken, __LINE__);

            if (
                isset($accountId)
                && isset($accessToken)
                && isset($accessToken['access_token'])
                && isset($accessToken['refresh_token'])
                && isset($accessToken['expires'])
            ) {

                $token = json_encode($accessToken);

                try {
                    Token::query()->updateOrCreate([Token::ID => $accountId], [
                        Token::ID => $accountId, # ID аккаунта
                        Token::TOKEN => $token # token
                    ]);
                } catch (QueryException $e) {
                    WLogger::log_it($e->getMessage(), __LINE__);

                    return null;
                }
            } else {
                WLogger::log_it('Invalid access token ' . var_export($accessToken, true), __LINE__);

                return null;
            }
        }
    }