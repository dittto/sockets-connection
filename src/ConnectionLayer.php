<?php
namespace Game;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\WsConnection;

class ConnectionLayer implements MessageComponentInterface
{
    public const CONNECTION_CLIENT = 'client';
    public const CONNECTION_SERVER = 'server';

    public const INPUT_CONNECT = 'connect';
    public const INPUT_DATA = 'data';
    public const INPUT_CLIENT_ID = 'client_id';

    public const OUTPUT_CLIENT_ID = 'client_id';
    public const OUTPUT_CLIENT_DATA = 'client_data';
    public const OUTPUT_CLIENT_DISCONNECT = 'client_disconnect';
    public const OUTPUT_SERVER_DATA = 'server_data';
    public const OUTPUT_SERVER_DISCONNECT = 'server_disconnect';

    /** @var WsConnection[] */
    private $connections = [];
    private $clients = [];
    private $serverId = null;
    private $serverData = [];

    public function onOpen(ConnectionInterface $connection): void
    {
        $this->connections[$connection->resourceId] = $connection;

        echo 'New connection #' . $connection->resourceId . "\n";
    }

    public function onMessage(ConnectionInterface $from, $message): void
    {
        $id = $from->resourceId;
        if (!$id && !isset($this->connections[$id])) {
            echo 'Connection #' . $id . '  not found' . "\n";
            return;
        }

        $messageData = json_decode($message, true);
        if (!$messageData) {
            echo 'Message failed to decode: ' . $message;
            return;
        }

        if (isset($messageData[self::INPUT_CONNECT])) {
            if ($messageData[self::INPUT_CONNECT] === self::CONNECTION_CLIENT) {
                $this->clients[$id] = [];
                echo 'Connected client #' . $id . "\n";
                $this->sendClientMessage([self::OUTPUT_CLIENT_ID => $id, self::OUTPUT_SERVER_DATA => $this->serverData], $id);
                $this->sendServerMessage([self::OUTPUT_CLIENT_ID => $id, self::OUTPUT_CLIENT_DATA => []]);
            }
            elseif ($messageData[self::INPUT_CONNECT] === self::CONNECTION_SERVER) {
                $this->serverId = $id;
                echo 'Connected server #' . $id . "\n";
                foreach ($this->clients as $clientId => $clientData) {
                    $this->sendServerMessage([self::OUTPUT_CLIENT_ID => $clientId, self::OUTPUT_CLIENT_DATA => $clientData]);
                    $this->sendClientMessage([self::OUTPUT_SERVER_DATA => $this->serverData], $clientId);
                }
            }
            return;
        }

        if (isset($messageData[self::INPUT_DATA])) {
            $data = $messageData[self::INPUT_DATA];
            if (isset($this->clients[$id])) {
                $this->clients[$id] = array_merge($this->clients[$id], $data);
                $this->sendServerMessage([self::OUTPUT_CLIENT_ID => $id, self::OUTPUT_CLIENT_DATA => $this->clients[$id]]);
            }
            elseif ($id === $this->serverId) {
                if (!isset($data[self::INPUT_CLIENT_ID])) {
                    $data = array_merge($this->serverData, $data);
                    $this->serverData = $data;
                }
                $this->sendClientMessage([self::OUTPUT_SERVER_DATA => $data], isset($data[self::INPUT_CLIENT_ID]) ? $data[self::INPUT_CLIENT_ID] : null);
            }
            return;
        }
    }

    public function onError(ConnectionInterface $connection, \Exception $e): void
    {
        echo 'An error occurred: ' . $e->getMessage() . "\n";

        $connection->close();
    }

    public function onClose(ConnectionInterface $connection): void
    {
        $id = $connection->resourceId;
        if ($id === $this->serverId) {
            $this->serverId = null;
            $this->serverData = [];
            $this->sendClientMessage([self::OUTPUT_SERVER_DISCONNECT => true]);
        } elseif (isset($this->connections[$this->serverId])) {
            $this->sendServerMessage([self::OUTPUT_CLIENT_DISCONNECT => $id]);
        }

        if (isset($this->clients[$id])) {
            unset($this->clients[$id]);
        }

        if (isset($this->connections[$id])) {
            unset($this->connections[$id]);
        }

        echo 'Connection #' . $id . ' has disconnected' . "\n";
    }

    private function sendServerMessage(array $message): void
    {
        if (isset($this->connections[$this->serverId])) {
            $this->connections[$this->serverId]->send(json_encode($message));
            echo 'Sent message to server #' . $this->serverId . "\n";
            echo json_encode($message) . "\n";
        }
    }

    private function sendClientMessage(array $message, ?int $clientId = null): void
    {
        if ($clientId !== null && isset($this->connections[$clientId])) {
            $this->connections[$clientId]->send(json_encode($message));
            echo 'Sent message to client #' . $clientId . "\n";
            echo json_encode($message) . "\n";

            return;
        }

        foreach ($this->clients as $clientId => $clientData) {
            if (isset($this->connections[$clientId])) {
                $this->connections[$clientId]->send(json_encode($message));
                echo 'Sent message to client #' . $clientId . "\n";
                echo json_encode($message) . "\n";
            }
        }
    }
}
