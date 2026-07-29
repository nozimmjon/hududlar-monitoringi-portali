<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('dead promise pipeline tables are dropped', function () {
    expect(Schema::hasTable('guarantee_letters'))->toBeFalse();
    expect(Schema::hasTable('promise_targets'))->toBeFalse();
});

test('tasks no longer carries guarantee_letter_id', function () {
    expect(Schema::hasColumn('tasks', 'guarantee_letter_id'))->toBeFalse();
});
