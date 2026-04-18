<div class="mt-6 p-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-lg">
    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-3">
        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        Feature Toggles
    </h3>

    <div class="space-y-5">
        @foreach($featureCategories as $categoryKey => $category)
            <div class="bg-gray-50 dark:bg-gray-700/40 rounded-2xl border border-gray-200 dark:border-gray-700">
                <div class="px-4 py-3 flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-gray-500 dark:text-gray-400 font-semibold">{{ $category['label'] ?? ucfirst($categoryKey) }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ count($category['features'] ?? []) }} features</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="toggleCategory('{{ $categoryKey }}', true)"
                            class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                        >
                            Select All
                        </button>
                        <button
                            type="button"
                            wire:click="toggleCategory('{{ $categoryKey }}', false)"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600"
                        >
                            Clear
                        </button>
                    </div>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                    @foreach($category['features'] as $featureKey => $meta)
                        @php
                            $isDefault = in_array($featureKey, $planFeatures);
                            $name = $meta['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $featureKey));
                            $description = $meta['description'] ?? $featureKey;
                        @endphp
                        <div class="flex items-center justify-between px-4 py-4 hover:bg-white dark:hover:bg-gray-700 transition-colors">
                            <div class="max-w-xl">
                                <div class="flex items-center gap-2">
                                    <label class="font-semibold text-gray-900 dark:text-white text-sm block">{{ $name }}</label>
                                    @if($isDefault)
                                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-100">Plan default</span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $description }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input
                                    type="checkbox"
                                    wire:change="toggle('{{ $featureKey }}')"
                                    class="sr-only peer"
                                    @checked(isset($enabledFeatures[$featureKey]))
                                    {{ $isDefault ? 'disabled' : '' }}
                                >
                                <div class="w-11 h-6 bg-gray-200 rounded-full peer-focus:outline-none peer dark:bg-gray-700 peer-disabled:opacity-60 peer-disabled:cursor-not-allowed peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    @if (empty($allFeatures))
        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p>No features available for toggling</p>
        </div>
    @endif
</div>

