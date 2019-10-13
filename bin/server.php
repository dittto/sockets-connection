<?php
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Server\IoServer;
use Game\ConnectionLayer;

error_reporting(E_ALL);
ini_set('display_errors', '1');

require dirname(__DIR__) . '/vendor/autoload.php';

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ConnectionLayer()
        )
    ),
    8080
);

$server->run();
