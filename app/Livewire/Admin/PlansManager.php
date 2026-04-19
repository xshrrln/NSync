<?php

namespace App\Livewire\Admin;

use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class PlansManager extends Component
{
    public array $plans = [];
    public ?Plan $editingPlan = null;
    public array $form = [
        'name' => '',
        'price' => '',
        'members_limit' => 5,
        'boards_limit' => 3,
        'storage_limit' => 50,
        'features' => [],
        'is_active' => true,
    ];

    public array $featuresCatalog = [];

    public function mount(): void
    {
        $this->editingPlan = null;
        $this->featuresCatalog = $this->getFeaturesCatalog();
        $this->reloadPlans();
    }

    public function render(): View
    {
        return view('livewire.admin.plans-manager', [
            'plans' => $this->plans,
            'featuresCatalog' => $this->featuresCatalog,
            'editingPlan' => $this->editingPlan,
        ]);
    }

    public function updatedFormIsActive($value): void
    {
        $this->form['is_active'] = (bool) $value;
    }

    public function reloadPlans(): void
    {
        try {
            $this->plans = Plan::orderBy('members_limit')->get()->values()->toArray();
        } catch (\Throwable $e) {
            Log::error('Failed to load plans: ' . $e->getMessage());
            $this->plans = [];
        }
    }

    public function edit(int $id): void
    {
        $plan = Plan::findOrFail($id);
        $this->editingPlan = $plan;
        $this->form = $plan->toArray();
        $this->form['features'] = $plan->features ?? [];
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => 'required|string|max:50',
            'form.price' => 'required|string|max:100',
            'form.members_limit' => 'required|integer|min:1',
            'form.boards_limit' => 'required|integer|min:1',
            'form.storage_limit' => 'required|integer|min:1',
            'form.is_active' => 'boolean',
            'form.features' => 'array',
        ]);

        $planData = array_merge($this->form, [
            'slug' => strtolower(str_replace(' ', '-', trim((string) $this->form['name']))),
        ]);

        if ($this->editingPlan) {
            $this->editingPlan->update($planData);
            $this->dispatch('notify', message: 'Plan updated successfully!', type: 'success');
        } else {
            Plan::create($planData);
            $this->dispatch('notify', message: 'Plan created successfully!', type: 'success');
        }

        $this->editingPlan = null;
        $this->resetForm();
        $this->reloadPlans();
    }

    public function delete(): void
    {
        if (! $this->editingPlan) {
            return;
        }

        $name = $this->editingPlan->name;
        $this->editingPlan->delete();

        $this->dispatch('notify', message: 'Plan "' . $name . '" deleted!', type: 'success');
        $this->editingPlan = null;
        $this->resetForm();
        $this->reloadPlans();
    }

    public function addNew(): void
    {
        $this->editingPlan = null;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'slug' => '',
            'price' => '',
            'members_limit' => 5,
            'boards_limit' => 3,
            'storage_limit' => 50,
            'features' => [],
            'is_active' => true,
        ];
    }

    public function getFeaturesCatalog(): array
    {
        return collect(config('features.categories', []))
            ->flatMap(fn ($category) => array_keys($category['features'] ?? []))
            ->unique()
            ->values()
            ->all();
    }
}
