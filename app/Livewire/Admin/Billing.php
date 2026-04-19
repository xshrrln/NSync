<?php

use Livewire\Volt\Component;
use App\Models\Plan;
use App\Models\Tenant;

new class extends Component {
    public $editingPlanId = null;
    public $newPlanOpen = false;
    public $form = [
        'name' => '',
        'slug' => '',
        'price' => '',
        'members_limit' => 5,
        'boards_limit' => 3,
        'storage_limit' => 50,
        'features' => [],
        'is_active' => true,
    ];

    public function getPlansProperty() {
        return Plan::all();
    }

    public function getUsageProperty() {
        return [
            'tenants' => Tenant::count(),
            'paid' => Tenant::whereNotNull('plan')->where('plan', '!=', 'free')->count(),
            'free' => Tenant::where('plan', 'free')->orWhereNull('plan')->count(),
        ];
    }

    public function getRecentTenantsProperty() {
        return Tenant::latest('created_at')->limit(5)->get(['id', 'name', 'plan', 'status', 'created_at']);
    }

    public function edit($id) {
        $plan = Plan::find($id);
        if ($plan) {
            $this->editingPlanId = $id;
            $this->form = $plan->toArray();
            $this->form['features'] = $plan->features ?? [];
        }
    }

    public function cancelEdit() {
        $this->editingPlanId = null;
        $this->resetForm();
    }

    public function saveEdit() {
        $this->validate([
            'form.name' => 'required|string|max:50',
            'form.price' => 'required|string|max:100',
            'form.members_limit' => 'required|integer|min:1',
            'form.boards_limit' => 'required|integer|min:1',
            'form.storage_limit' => 'required|integer|min:1',
            'form.is_active' => 'boolean',
            'form.features' => 'array',
        ]);

        $plan = Plan::find($this->editingPlanId);
        if ($plan) {
            $planData = array_merge($this->form, [
                'slug' => strtolower(str_replace(' ', '-', trim($this->form['name']))),
            ]);
            $plan->update($planData);
            $this->dispatch('notify', message: 'Plan updated!', type: 'success');
        }

        $this->cancelEdit();
        // Refresh computed properties
        $this->dispatch('refresh');
    }

    public function openNewPlan() {
        $this->newPlanOpen = true;
        $this->resetForm();
    }

    public function closeNewPlan() {
        $this->newPlanOpen = false;
        $this->resetForm();
    }

    public function saveNewPlan() {
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
            'slug' => strtolower(str_replace(' ', '-', trim($this->form['name']))),
        ]);
        Plan::create($planData);

        $this->dispatch('notify', message: 'New plan created!', type: 'success');
        $this->closeNewPlan();
        // Refresh computed properties
        $this->dispatch('refresh');
    }

    public function deletePlan($id) {
        $plan = Plan::find($id);
        if ($plan) {
            $name = $plan->name;
            $plan->delete();
            $this->dispatch('notify', message: "Plan '$name' deleted!", type: 'success');
            // Refresh computed properties
            $this->dispatch('refresh');
        }
    }

    public function resetForm() {
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

    public function getFeaturesCatalogProperty() {
        return collect(config('features.categories', []))
            ->flatMap(fn ($category) => array_keys($category['features'] ?? []))
            ->unique()
            ->values()
            ->all();
    }

    public function with(): array
    {
        return [
            'plans' => $this->plans,
            'usage' => $this->usage,
            'recentTenants' => $this->recentTenants,
            'featuresCatalog' => $this->featuresCatalog,
        ];
    }
};
