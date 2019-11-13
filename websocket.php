<?php
namespace App\Services;
(PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('cli only');
require 'vendor/autoload.php'; 
use Ratchet\Http\HttpServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;

class RatchetServer implements MessageComponentInterface
{
    protected $clients;
    public function start($port)
    {
        $server = IoServer::factory(
            new HttpServer(
                new WsServer(
                    $this
                )
            ),
            $port
        );
        $server->run();
    }
    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->roomId = null;
        echo "New connection! ({$conn->resourceId})\n";
    }
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
    	if(is_null($from->roomId)){
    		if($data['type'] == "createRoom"){
	    	    $from->roomId = bin2hex(openssl_random_pseudo_bytes(16));
	            $from->send(json_encode(["type"=>"createdRoom","content"=>$from->roomId]));
    		    return;
    		}

    		if($data['type'] == "joinRoom"){
                $joined = false;
                foreach ($this->clients as $client)
                    if ($data['content'] === $client->roomId && $from !== $client)
                        $joinAnnounceClients[] = $client;

                if(count($joinAnnounceClients) == 1){
                    foreach($joinAnnounceClients as $joinAnnounceClient)
                        $joinAnnounceClient->send(json_encode(["type"=>"userJoined"]));

                    $from->roomId = $data['content'];
    	            $from->send(json_encode(["type"=>"joinedRoom"]));
                } else {
                    $from->send(json_encode(["type"=>"error","content"=>"Invalid room ID"]));
                }
    		}
            return;
    	}


        foreach ($this->clients as $client) {
            if ($from->roomId === $client->roomId && $from !== $client && !is_null($from->roomId)) {
                if($data['type'] == 'keyExchange')
                   $client->send(json_encode(["type"=>"keyExchange","content"=>$data['content']]));
                else if($data['type'] == 'message')
                   $client->send(json_encode(["type"=>"message","content"=>$data['content']]));
            }
        }
    }
    public function onClose(ConnectionInterface $conn) {
        foreach ($this->clients as $client) {
            if ($conn->roomId === $client->roomId && $conn !== $client && !is_null($conn->roomId)) {
                $client->send(json_encode(["type"=>"userDisconnect"]));
            }
        }

        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

$server = new RatchetServer();
$server->start(8089);

