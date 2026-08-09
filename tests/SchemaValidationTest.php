<?php

use Timadey\LazySettings\Exceptions\SchemaException;
use Timadey\LazySettings\Tests\Fixtures\BadSettings;
use Timadey\LazySettings\Tests\Fixtures\BadStore;
use Timadey\LazySettings\Tests\Fixtures\NullDefaultBool;
use Timadey\LazySettings\Tests\Fixtures\NullDefaultStore;

it('throws at first touch when a declared default is invalid', function () {
    BadStore::get(BadSettings::RATE);
})->throws(SchemaException::class);

it('names the offending case when validation fails', function () {
    try {
        BadStore::get(BadSettings::RATE);
    } catch (SchemaException $e) {
        expect($e->getMessage())->toContain('BadSettings::RATE');
    }
});

it('rejects a non-nullable default of null', function () {
    NullDefaultStore::get(NullDefaultBool::FLAG);
})->throws(SchemaException::class);