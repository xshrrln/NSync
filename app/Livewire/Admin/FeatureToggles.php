<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Tenant;
use Illuminate\Support\Facades\Config;

class FeatureToggles extends Component
{
    public Tenant $tenant;
    public $allFeatures = [];
    public $featureCategories = [];
    public $enabledFeatures = [];

    public function mount(Tenant $tenant)
    {
        $this->tenant = $tenant;
        $this->featureCategories = Config::get('features.categories', []);
        $this->allFeatures = collect($this->featureCategories)
            ->flatMap(fn ($category) => array_keys($category['features'] ?? []))
            ->unique()
            ->sort()
            ->values()
            ->all();
        $planKey = strtolower($this->tenant->plan ?? 'free');
        $planFeatures = Config::get("plans.$planKey.features", []);
        $activeFeatures = array_unique(array_merge($planFeatures, $this->tenant->featuresFromActions()));
        $this->enabledFeatures = array_fill_keys($activeFeatures, true);
    }

    public function toggle($feature)
    {
        $planKey = strtolower($this->tenant->plan ?? 'free');
        $planFeatures = Config::get("plans.$planKey.features", []);

        if (in_array($feature, $planFeatures, true)) {
            // Plan defaults cannot be disabled.
            return;
        }

        $currentlyEnabled = isset($this->enabledFeatures[$feature]);
        if (! $currentlyEnabled) {
            $this->enabledFeatures[$feature] = true;
        } else {
            unset($this->enabledFeatures[$feature]);
        }

        $this->persistEnabledFeatures();
    }

    public function toggleCategory(string $categoryKey, bool $enabled): void
    {
        $planKey = strtolower($this->tenant->plan ?? 'free');
        $planFeatures = Config::get("plans.$planKey.features", []);
        $categoryFeatures = array_keys($this->featureCategories[$categoryKey]['features'] ?? []);

        foreach ($categoryFeatures as $feature) {
            if (in_array($feature, $planFeatures, true)) {
                // Keep plan defaults always enabled.
                $this->enabledFeatures[$feature] = true;
                continue;
            }

            if ($enabled) {
                $this->enabledFeatures[$feature] = true;
            } else {
                unset($this->enabledFeatures[$feature]);
            }
        }

        $this->persistEnabledFeatures();
    }

    private function persistEnabledFeatures(): void
    {
        $features = array_keys(array_filter($this->enabledFeatures));

        $existing = is_array($this->tenant->actions)
            ? $this->tenant->actions
            : ($this->tenant->actions ? json_decode((string) $this->tenant->actions, true) ?: [] : []);

        $preserved = collect($existing)
            ->reject(fn ($value, $key) => in_array($key, $this->allFeatures, true))
            ->all();

        $actions = $preserved;
        foreach ($features as $feature) {
            $actions[$feature] = true;
        }

        $this->tenant->update([
            'actions' => $actions,
        ]);

        $this->dispatch('features-updated', features: $features);
    }

    public function render()
    {
        $plans = Config::get('plans');
        $planKey = strtolower($this->tenant->plan ?? 'free');

        return view('livewire.admin.feature-toggles', [
            'featureCategories' => $this->featureCategories,
            'planFeatures' => $plans[$planKey]['features'] ?? [],
        ]);
    }
}
