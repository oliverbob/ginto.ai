<?php

use PhpMcp\Client\Exception\TransportException;
use PhpMcp\Client\JsonRpc\Request;
use PhpMcp\Client\JsonRpc\Response;
use PhpMcp\Client\Transport\Stdio\StdioClientTransport;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;

beforeEach(function () {
    $this->loop = Loop::get();

    $this->process = Mockery::mock(Process::class);
    $this->stdin = Mockery::mock(WritableStreamInterface::class);
    $this->stdout = Mockery::mock(ReadableStreamInterface::class);
    $this->stderr = Mockery::mock(ReadableStreamInterface::class);

    // Mock Process methods
    $this->process->shouldReceive('start')->with($this->loop)->byDefault();
    $this->process->shouldReceive('isRunning')->withNoArgs()->andReturn(true)->byDefault();
    $this->process->stdin = $this->stdin;
    $this->process->stdout = $this->stdout;
    $this->process->stderr = $this->stderr;
    $this->process->shouldReceive('on')->byDefault();
    $this->process->shouldReceive('terminate')->byDefault();

    // Mock Stream methods
    $this->stdin->shouldReceive('isWritable')->withNoArgs()->andReturn(true)->byDefault();
    $this->stdin->shouldReceive('write')->withAnyArgs()->andReturn(true)->byDefault();
    $this->stdin->shouldReceive('on')->byDefault();
    $this->stdin->shouldReceive('once')->byDefault();
    $this->stdin->shouldReceive('close')->byDefault();
    $this->stdin->shouldReceive('end')->byDefault();
    $this->stdin->shouldReceive('removeListener')->byDefault();

    $this->stdout->shouldReceive('isReadable')->withNoArgs()->andReturn(true)->byDefault();
    $this->stdout->shouldReceive('on')->byDefault();
    $this->stdout->shouldReceive('close')->byDefault();
    $this->stdout->shouldReceive('removeAllListeners')->byDefault();

    $this->stderr->shouldReceive('isReadable')->withNoArgs()->andReturn(true)->byDefault();
    $this->stderr->shouldReceive('on')->byDefault();

    $this->command = 'php';
    $this->args = ['server.php'];
    $this->transport = Mockery::mock(StdioClientTransport::class, [$this->command, $this->args, $this->loop])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $this->transport->shouldReceive('createProcess')->andReturn($this->process);

    $reflector = new ReflectionClass(StdioClientTransport::class);

    $processProp = $reflector->getProperty('process');
    $processProp->setAccessible(true);
    $processProp->setValue($this->transport, $this->process);

    $stdinProp = $reflector->getProperty('stdin');
    $stdinProp->setAccessible(true);
    $stdinProp->setValue($this->transport, $this->stdin);

    $stdoutProp = $reflector->getProperty('stdout');
    $stdoutProp->setAccessible(true);
    $stdoutProp->setValue($this->transport, $this->stdout);
});

it('connects successfully', function () {
    // Arrange
    $capturedListeners = [];
    $this->process->shouldReceive('start')->with($this->loop)->once();
    $this->stdout->shouldReceive('on')->with('data', Mockery::capture($capturedListeners['stdout_data']))->once();
    $this->stderr->shouldReceive('on')->with('data', Mockery::capture($capturedListeners['stderr_data']))->once();
    $this->process->shouldReceive('on')->with('exit', Mockery::capture($capturedListeners['process_exit']))->once();
    $this->stdout->shouldReceive('on')->with('error', Mockery::capture($capturedListeners['stdout_error']))->once();
    $this->stdin->shouldReceive('on')->with('error', Mockery::capture($capturedListeners['stdin_error']))->once();
    $this->stdout->shouldReceive('on')->with('close', Mockery::capture($capturedListeners['stdout_close']))->once();

    // Act
    $promise = $this->transport->connect();

    // Assert promise type and that mocks were called
    expect($promise)->toBeInstanceOf(PromiseInterface::class);
    $this->process->shouldHaveReceived('start');
    expect($capturedListeners['stdout_data'])->toBeCallable();
    expect($capturedListeners['stderr_data'])->toBeCallable();
    expect($capturedListeners['process_exit'])->toBeCallable();
});

it('rejects connection if process fails to start', function () {
    // Arrange
    $exception = new \RuntimeException('Failed to start');
    $this->process->shouldReceive('start')->with($this->loop)->andThrow($exception);

    // Act
    $promise = $this->transport->connect();
    /** @var TransportException $rejectedReason */
    $rejectedReason = null;
    $promise->catch(function ($reason) use (&$rejectedReason) {
        $rejectedReason = $reason;
    });

    // Assert
    expect($rejectedReason)->toBeInstanceOf(TransportException::class);
    expect($rejectedReason->getPrevious())->toBe($exception);
});

test('send(): sends message successfully', function () {
    // Arrange
    $message = new Request(1, 'test/method', ['param' => 1]);
    $expectedJson = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Expect write to be called
    $this->stdin->shouldReceive('write')->with($expectedJson."\n")->once()->andReturn(true);

    // Act
    $promise = $this->transport->send($message);

    $resolved = false;
    $promise->then(function () use (&$resolved) {
        $resolved = true;
    });

    // Assert (should resolve immediately)
    expect($resolved)->toBeTrue();
});

test('send(): handles backpressure and resolves on drain', function () {
    // Arrange
    $message = new Request(1, 'test/method');
    $expectedJson = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $drainListener = null;

    $this->stdin->shouldReceive('write')->with($expectedJson."\n")->once()->andReturn(false); // Simulate buffer full
    $this->stdin->shouldReceive('once')
        ->with('drain', Mockery::capture($drainListener))
        ->once();

    // Act
    $promise = $this->transport->send($message);
    $resolved = false;
    $promise->then(function () use (&$resolved) {
        $resolved = true;
    });

    // Assert not resolved yet
    expect($resolved)->toBeFalse();
    expect($drainListener)->toBeCallable();

    // Simulate drain event (SCHEDULED)
    $this->loop->futureTick($drainListener);
    $this->loop->run(); // Run loop

    // Assert resolved after drain
    expect($resolved)->toBeTrue();

})->group('usesLoop');

it('rejects send if stream not writable', function () {
    // Arrange
    $message = new Request(1, 'test/method');
    $this->stdin->shouldReceive('isWritable')->andReturn(false);

    // Act
    $promise = $this->transport->send($message);
    /** @var TransportException|null $rejectedReason */
    $rejectedReason = null;
    $promise->catch(function ($reason) use (&$rejectedReason) {
        $rejectedReason = $reason;
    });

    // Assert
    expect($rejectedReason)->toBeInstanceOf(TransportException::class)
        ->and($rejectedReason->getMessage())->toContain('not writable');
});

test('handleData(): handles incoming data and emits message event', function () {
    // Arrange
    $responseJson = '{"jsonrpc":"2.0","id":1,"result":{"success":true}}'."\n";

    /** @var Response|null $emittedMessage */
    $emittedMessage = null;

    $this->transport->on('message', function ($msg) use (&$emittedMessage) {
        $emittedMessage = $msg;
    });

    // Act
    $this->transport->handleData($responseJson);

    // Assert
    expect($emittedMessage)->toBeInstanceOf(Response::class);
    expect($emittedMessage->id)->toBe(1);
    expect($emittedMessage->result)->toBe(['success' => true]);
});

test('handleData(): handles incoming invalid json and emits error', function () {
    // Arrange
    $invalidJson = '{"jsonrpc":"2.0", "id":1..}'."\n"; // Invalid JSON

    /** @var TransportException|null $emittedError */
    $emittedError = null;
    $this->transport->on('error', function ($err) use (&$emittedError) {
        $emittedError = $err;
    });

    // Act
    $this->transport->handleData($invalidJson);

    // Assert
    expect($emittedError)->toBeInstanceOf(TransportException::class)
        ->and($emittedError->getMessage())->toContain('Failed to decode message from server');
});

it('emits stderr event', function () {
    // Arrange
    $stderrData = "PHP Warning: Something happened\n";
    $emittedStderr = null;
    $this->transport->on('stderr', function ($data) use (&$emittedStderr) {
        $emittedStderr = $data;
    });

    // Act
    $stderrListener = null;
    $this->stderr->shouldReceive('on')->with('data', Mockery::capture($stderrListener));
    $this->transport->connect();
    $stderrListener($stderrData);

    // Assert
    expect($emittedStderr)->toBe($stderrData);
});

test('handleExit(): handles process exit event and emits close/error', function () {
    // Arrange
    /** @var TransportException|null $emittedError */
    $emittedError = null;
    $emittedCloseReason = null;
    $this->transport->on('error', function ($err) use (&$emittedError) {
        $emittedError = $err;
    });
    $this->transport->on('close', function ($reason) use (&$emittedCloseReason) {
        $emittedCloseReason = $reason;
    });

    // Simulate connection was previously settled (avoid connectPromise checks)
    $reflector = new ReflectionClass($this->transport);
    $connectSettledProp = $reflector->getProperty('connectPromiseSettled');
    $connectSettledProp->setAccessible(true);
    $connectSettledProp->setValue($this->transport, true); // Mark as settled

    // Act: Trigger the internal handler directly
    $this->transport->handleExit(1, null); // Exit code 1

    // Assert
    expect($emittedError)->toBeInstanceOf(TransportException::class)
        ->and($emittedError->getMessage())->toContain('exited with code 1');
    expect($emittedCloseReason)->toContain('exited with code 1');
});

test('close(): closes connection gracefully', function () {
    // Arrange
    // Set internal state to mimic connected
    $reflector = new ReflectionClass($this->transport);
    $processProp = $reflector->getProperty('process');
    $processProp->setAccessible(true);
    $processProp->setValue($this->transport, $this->process);
    $stdinProp = $reflector->getProperty('stdin');
    $stdinProp->setAccessible(true);
    $stdinProp->setValue($this->transport, $this->stdin);
    $stdoutProp = $reflector->getProperty('stdout');
    $stdoutProp->setAccessible(true);
    $stdoutProp->setValue($this->transport, $this->stdout);

    // Expectations for close() actions
    $this->stdin->shouldReceive('end')->once();
    $this->process->shouldReceive('terminate')->with(SIGTERM)->once();
    // Expect exit listener to be attached *by close()*
    $exitCb = null;
    $this->process->shouldReceive('on')
        ->with('exit', Mockery::capture($exitCb))
        ->once();

    // Act
    $this->transport->close();

    // Assert initial calls
    $this->process->shouldHaveReceived('terminate')->with(SIGTERM);
    $this->stdin->shouldHaveReceived('end');
    expect($exitCb)->toBeCallable();

    // Simulate process exiting after terminate call (SCHEDULED)
    $this->loop->futureTick(fn () => $exitCb(0, null)); // Simulate normal exit
    $this->loop->run(); // Run loop

    // Assert cleanup happened (check internal state is nullified)
    expect($processProp->getValue($this->transport))->toBeNull();
    expect($stdinProp->getValue($this->transport))->toBeNull();
    expect($stdoutProp->getValue($this->transport))->toBeNull();

})->group('usesLoop');
