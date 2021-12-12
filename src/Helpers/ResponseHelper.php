<?php
namespace App\Helpers;
use Psr\Http\Message\ResponseInterface;

class ResponseHelper
{
    public static function success(ResponseInterface $response, $data = ['msg' => 'success']) {
        $response->getBody()->write(json_encode(['error' => false, 'data' => $data]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function error(ResponseInterface $response, $data = ['msg' => 'error']) {
        $response->getBody()->write(json_encode(['error' => true, 'data' => $data]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}