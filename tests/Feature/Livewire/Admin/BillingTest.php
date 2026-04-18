<?php

namespace Tests\Feature\Livewire\Admin;

use Livewire\Livewire;
use Tests\TestCase;

class BillingTest extends TestCase
{
    public function test_renders_successfully(): void
    {
        Livewire::test('admin.billing')
            ->assertStatus(200);
    }

    public function test_can_open_and_close_new_plan_modal(): void
    {
        Livewire::test('admin.billing')
            ->call('openNewPlan')
            ->assertSet('newPlanOpen', true)
            ->call('closeNewPlan')
            ->assertSet('newPlanOpen', false);
    }
}
