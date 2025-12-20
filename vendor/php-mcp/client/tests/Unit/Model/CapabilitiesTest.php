<?php

use PhpMcp\Client\Model\Capabilities;

it('creates client capabilities correctly', function () {
    // Arrange
    $capsDefault = Capabilities::forClient(); // Default sampling=true, roots=null
    $capsNoSampling = Capabilities::forClient(supportsSampling: false);
    $capsWithRoots = Capabilities::forClient(supportsSampling: true, supportsRootListChanged: true);
    $capsWithRootsNoChange = Capabilities::forClient(supportsSampling: false, supportsRootListChanged: false);
    $capsWithExperimental = Capabilities::forClient(experimental: ['myFeature' => true]);

    // Assert Defaults
    expect($capsDefault->sampling)->toBe([]);
    expect($capsDefault->roots)->toBeNull();
    expect($capsDefault->experimental)->toBeNull();
    expect($capsDefault->toClientArray())->toEqual(['sampling' => new stdClass]); // Empty array becomes {}

    // Assert No Sampling
    expect($capsNoSampling->sampling)->toBeNull();
    expect($capsNoSampling->toClientArray())->toEqual(new stdClass); // Fully empty -> {}

    // Assert With Roots (listChanged=true)
    expect($capsWithRoots->sampling)->toBe([]);
    expect($capsWithRoots->roots)->toBe(['listChanged' => true]);
    expect($capsWithRoots->toClientArray())->toEqual([
        'roots' => ['listChanged' => true],
        'sampling' => new stdClass,
    ]);

    // Assert With Roots (listChanged=false)
    expect($capsWithRootsNoChange->sampling)->toBeNull();
    expect($capsWithRootsNoChange->roots)->toBe(['listChanged' => false]);
    expect($capsWithRootsNoChange->toClientArray())->toEqual([
        'roots' => ['listChanged' => false],
        // No sampling key
    ]);

    // Assert With Experimental
    expect($capsWithExperimental->experimental)->toBe(['myFeature' => true]);
    expect($capsWithExperimental->toClientArray())->toEqual([
        'sampling' => new stdClass,
        'experimental' => ['myFeature' => true],
    ]);

});

it('parses server capabilities correctly', function () {
    // Arrange
    $serverResponseData = [
        // Client capabilities should be ignored/nulled
        'roots' => ['listChanged' => true],
        'sampling' => [],
        // Server capabilities
        'tools' => ['listChanged' => true],
        'resources' => ['subscribe' => true, 'listChanged' => false],
        'prompts' => ['listChanged' => false],
        'logging' => [], // Empty array becomes empty array internally
        'experimental' => ['serverFeature' => 'beta'],
    ];

    // Act
    $caps = Capabilities::fromServerResponse($serverResponseData);

    // Assert Server Caps are set
    expect($caps->tools)->toBe(['listChanged' => true]);
    expect($caps->resources)->toBe(['subscribe' => true, 'listChanged' => false]);
    expect($caps->prompts)->toBe(['listChanged' => false]);
    expect($caps->logging)->toBe([]);
    expect($caps->experimental)->toBe(['serverFeature' => 'beta']);

    // Assert Client Caps are null
    expect($caps->roots)->toBeNull();
    expect($caps->sampling)->toBeNull();

    // Assert Helpers
    expect($caps->serverSupportsTools())->toBeTrue();
    expect($caps->serverSupportsToolListChanged())->toBeTrue();
    expect($caps->serverSupportsResources())->toBeTrue();
    expect($caps->serverSupportsResourceSubscription())->toBeTrue();
    expect($caps->serverSupportsResourceListChanged())->toBeFalse();
    expect($caps->serverSupportsPrompts())->toBeTrue();
    expect($caps->serverSupportsPromptListChanged())->toBeFalse();
    expect($caps->serverSupportsLogging())->toBeTrue(); // Non-null indicates support
});

it('handles missing server capabilities gracefully', function () {
    // Arrange
    $serverResponseData = [
        'tools' => ['listChanged' => true],
        // resources, prompts, logging, experimental are missing
    ];

    // Act
    $caps = Capabilities::fromServerResponse($serverResponseData);

    // Assert Only Tools is set
    expect($caps->tools)->toBe(['listChanged' => true]);
    expect($caps->resources)->toBeNull();
    expect($caps->prompts)->toBeNull();
    expect($caps->logging)->toBeNull();
    expect($caps->experimental)->toBeNull();

    // Assert Helpers
    expect($caps->serverSupportsTools())->toBeTrue();
    expect($caps->serverSupportsResources())->toBeFalse();
    expect($caps->serverSupportsResourceSubscription())->toBeFalse();
    // ... etc
});
