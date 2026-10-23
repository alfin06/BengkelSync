<?php

use App\Enums\RoleName;
use Illuminate\Support\Arr;

// CLAUDE.md rule 4: every key exists in BOTH languages.

it('has the same keys in id.json and en.json', function () {
    $id = json_decode((string) file_get_contents(lang_path('id.json')), true, flags: JSON_THROW_ON_ERROR);
    $en = json_decode((string) file_get_contents(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);

    expect(array_keys($id))->toEqualCanonicalizing(array_keys($en))
        ->and(array_filter($id, fn ($value) => trim((string) $value) === ''))->toBe([])
        ->and(array_filter($en, fn ($value) => trim((string) $value) === ''))->toBe([]);
});

it('has every custom English PHP translation in Indonesian too', function (string $group) {
    $en = require lang_path("en/{$group}.php");
    $id = require lang_path("id/{$group}.php");

    $missing = array_diff_key(Arr::dot($en), Arr::dot($id));

    expect($missing)->toBe([]);
})->with(['auth', 'passwords', 'validation']);

it('translates every role name', function () {
    foreach (RoleName::cases() as $role) {
        foreach (['id', 'en'] as $locale) {
            app()->setLocale($locale);
            expect($role->label())->not->toBe('roles.'.$role->value);
        }
    }
});
