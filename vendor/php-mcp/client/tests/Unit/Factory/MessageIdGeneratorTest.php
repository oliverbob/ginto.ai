<?php

use PhpMcp\Client\Factory\MessageIdGenerator;

it('generates unique ids', function () {
    // Arrange
    $generator = new MessageIdGenerator;

    // Act
    $id1 = $generator->generate();
    $id2 = $generator->generate();
    $id3 = $generator->generate();

    // Assert
    expect($id1)->toBeString()->not->toBeEmpty();
    expect($id2)->toBeString()->not->toBe($id1);
    expect($id3)->toBeString()->not->toBe($id1)->not->toBe($id2);
    // Check counter increment (internal detail, but good sanity check)
    expect($id1)->toContain('-1');
    expect($id2)->toContain('-2');
    expect($id3)->toContain('-3');
});

it('generates unique ids with custom prefix', function () {
    // Arrange
    $prefix = 'my-req-';
    $generator = new MessageIdGenerator($prefix);

    // Act
    $id1 = $generator->generate();
    $id2 = $generator->generate();

    // Assert
    expect($id1)->toStartWith($prefix);
    expect($id2)->toStartWith($prefix);
    expect($id1)->not->toBe($id2);
});

it('generates unique ids across different instances', function () {
    // Arrange
    $generator1 = new MessageIdGenerator;
    $generator2 = new MessageIdGenerator;

    // Act
    $ids1 = [$generator1->generate(), $generator1->generate()];
    $ids2 = [$generator2->generate(), $generator2->generate()];

    // Assert
    expect($ids1[0])->not->toBe($ids1[1]);
    expect($ids2[0])->not->toBe($ids2[1]);
    // Due to PID/random element, IDs from different instances should not clash
    expect($ids1[0])->not->toBe($ids2[0]);
    expect($ids1[1])->not->toBe($ids2[1]);
});
