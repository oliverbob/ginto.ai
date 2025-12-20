<?php

use PhpMcp\Client\Exception\ProtocolException;
use PhpMcp\Client\Model\Content\ContentFactory;
use PhpMcp\Client\Model\Content\EmbeddedResource;
use PhpMcp\Client\Model\Content\PromptMessage;
use PhpMcp\Client\Model\Content\TextContent;

// Add other content types as they are implemented: ImageContent, AudioContent, ResourceContent

// --- TextContent ---
it('creates text content and converts to/from array', function () {
    $text = 'Hello MCP!';
    $content = new TextContent($text);
    $array = $content->toArray();
    $rehydrated = TextContent::fromArray($array);

    expect($content->text)->toBe($text);
    expect($content->getType())->toBe('text');
    expect($array)->toBe(['type' => 'text', 'text' => $text]);
    expect($rehydrated)->toEqual($content);
});

it('throws protocol exception for invalid text content from array', function () {
    TextContent::fromArray(['type' => 'text']); // Missing text
})->throws(ProtocolException::class);

// --- EmbeddedResource ---
it('creates embedded resource and converts to/from array', function () {
    // Text version
    $resText = new EmbeddedResource('file://a.txt', 'text/plain', text: 'content');
    $arrayText = $resText->toArray();
    $rehydratedText = EmbeddedResource::fromArray($arrayText);
    expect($arrayText)->toBe(['uri' => 'file://a.txt', 'mimeType' => 'text/plain', 'text' => 'content']);
    expect($rehydratedText)->toEqual($resText);

    // Blob version
    $blob = base64_encode('binary');
    $resBlob = new EmbeddedResource('res://b.bin', 'app/octet', blob: $blob);
    $arrayBlob = $resBlob->toArray();
    $rehydratedBlob = EmbeddedResource::fromArray($arrayBlob);
    expect($arrayBlob)->toBe(['uri' => 'res://b.bin', 'mimeType' => 'app/octet', 'blob' => $blob]);
    expect($rehydratedBlob)->toEqual($resBlob);
});

it('throws invalid argument exception for resource with neither text nor blob', function () {
    new EmbeddedResource('uri', 'mime');
})->throws(InvalidArgumentException::class);

it('throws invalid argument exception for resource with both text and blob', function () {
    new EmbeddedResource('uri', 'mime', text: 't', blob: 'b');
})->throws(InvalidArgumentException::class);

it('throws protocol exception for invalid embedded resource from array', function (array $data) {
    EmbeddedResource::fromArray($data);
})->throws(ProtocolException::class)->with([
    [['mimeType' => 'm']], // Missing uri
    [['uri' => 'u']], // Missing mimeType
    [['uri' => 'u', 'mimeType' => 'm']], // Missing text AND blob
    [['uri' => 'u', 'mimeType' => 'm', 'text' => 't', 'blob' => 'b']], // Has both text AND blob
]);

// --- PromptMessage ---
it('creates prompt message and converts to/from array', function () {
    $content = new TextContent('User query');
    $msg = new PromptMessage('user', $content);
    $array = $msg->toArray();
    // Need ContentFactory for fromArray
    // $rehydrated = PromptMessage::fromArray($array);

    expect($msg->role)->toBe('user');
    expect($msg->content)->toBe($content);
    expect($array)->toBe(['role' => 'user', 'content' => $content->toArray()]);
    // expect($rehydrated)->toEqual($msg); // Test after ContentFactory is done
});

it('throws invalid argument exception for invalid role in prompt message', function () {
    new PromptMessage('system', new TextContent('test'));
})->throws(InvalidArgumentException::class);

// Add tests for PromptMessage::fromArray once ContentFactory is ready

// --- ContentFactory ---
it('uses content factory to create text content from array', function () {
    $data = ['type' => 'text', 'text' => 'factory test'];
    $content = ContentFactory::createFromArray($data);

    expect($content)->toBeInstanceOf(TextContent::class);
    expect($content->text)->toBe('factory test');
});

// Add tests for other content types (Image, Audio, Resource) in ContentFactory

it('throws protocol exception for unknown type in content factory', function () {
    ContentFactory::createFromArray(['type' => 'video', 'url' => '...']);
})->throws(ProtocolException::class, "Unsupported content type 'video'");

it('throws protocol exception for missing type in content factory', function () {
    ContentFactory::createFromArray(['text' => '...']);
})->throws(ProtocolException::class, "Missing or invalid 'type' field");
