<?php

namespace Tests\Feature\Livewire\Admin;

use Livewire\Livewire;
use Tests\TestCase;

class PlansManagerTest extends TestCase
{
    public function test_renders_successfully(): void
    {
        Livewire::test('admin.plans-manager')
            ->assertStatus(200);
    }
}
