<?php

use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test('tenant-request')
        ->assertStatus(200);
});
