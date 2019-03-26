<?php

namespace Amp\Socket\Test;

use Amp\Loop;
use Amp\Socket;
use PHPUnit\Framework\TestCase;
use function Amp\asyncCall;

class EndpointTest extends TestCase
{
    public function testReceive()
    {
        Loop::run(function () {
            $endpoint = Socket\endpoint('127.0.0.1:0');
            Loop::delay(100, [$endpoint, 'close']);

            $this->assertInternalType('resource', $endpoint->getResource());

            $socket = yield Socket\connect('udp://' . $endpoint->getLocalAddress());
            $remote = $socket->getLocalAddress();

            yield $socket->write('Hello!');

            asyncCall(function () use ($endpoint, $remote) {
                while (list($data, $address) = yield $endpoint->receive()) {
                    $this->assertSame('Hello!', $data);
                    $this->assertSame($remote, $address);
                }
            });
        });
    }

    public function testSend()
    {
        Loop::run(function () {
            $endpoint = Socket\endpoint('127.0.0.1:0');
            Loop::delay(100, [$endpoint, 'close']);

            $this->assertInternalType('resource', $endpoint->getResource());

            $socket = yield Socket\connect('udp://' . $endpoint->getLocalAddress());
            $remote = $socket->getLocalAddress();

            yield $socket->write('a');

            asyncCall(function () use ($endpoint, $remote) {
                while (list($data, $address) = yield $endpoint->receive()) {
                    $this->assertSame('a', $data);
                    $this->assertSame($remote, $address);
                    yield $endpoint->send('b', $address);
                }
            });

            $data = yield $socket->read();

            $this->assertSame('b', $data);
        });
    }

    public function testSendPacketTooLarge()
    {
        $this->expectException(Socket\SocketException::class);
        $this->expectExceptionMessage('Could not send packet on endpoint: stream_socket_sendto(): Message too long');

        Loop::run(function () {
            $endpoint = Socket\endpoint('127.0.0.1:0');
            Loop::delay(100, [$endpoint, 'close']);

            $socket = yield Socket\connect('udp://' . $endpoint->getLocalAddress());

            yield $socket->write('Hello!');

            while (list($data, $address) = yield $endpoint->receive()) {
                yield $endpoint->send(\str_repeat('-', 2 ** 20), $address);
            }
        });
    }

    public function testReceiveThenClose()
    {
        Loop::run(function () {
            $endpoint = Socket\endpoint('127.0.0.1:0');

            $promise = $endpoint->receive();

            $endpoint->close();

            $this->assertNull(yield $promise);
        });
    }

    public function testReceiveAfterClose()
    {
        Loop::run(function () {
            $endpoint = Socket\endpoint('127.0.0.1:0');

            $endpoint->close();

            $this->assertNull(yield $endpoint->receive());
        });
    }

    public function testSimultaneousReceive()
    {
        $this->expectException(Socket\PendingReceiveError::class);

        Loop::run(function () {
            $endpoint = Socket\endpoint('127.0.0.1:0');
            try {
                $promise = $endpoint->receive();
                $endpoint->receive();
            } finally {
                $endpoint->close();
            }
        });
    }
}