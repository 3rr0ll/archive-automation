@extends('layouts.app')

@section('content')
<div class="mx-auto py-6" x-data="{
    showModal: false,
    isSubmitting: false,
    editingId: null,
    formData: {
        type: 'secret',
        key: '',
        value: '',
        note: '',
        environments: 'Production'
    },
    errors: {},
    openCreate() {
        this.editingId = null;
        this.formData = { type: 'secret', key: '', value: '', note: '', environments: 'Production' };
        this.errors = {};
        this.showModal = true;
    },
    openEdit(variable) {
        this.editingId = variable.id;
        this.formData = {
            type: variable.type || 'secret',
            key: variable.key,
            value: '',
            note: variable.note || '',
            environments: variable.environments || 'Production'
        };
        this.errors = {};
        this.showModal = true;
    },
    async submitForm() {
        this.isSubmitting = true;
        this.errors = {};
        try {
            const endpoint = this.editingId
                ? `/api/environment-variables/${this.editingId}`
                : '/api/environment-variables';
            const response = await fetch(endpoint, {
                method: this.editingId ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(this.formData)
            });
            const result = await response.json();
            if (!response.ok) {
                if (response.status === 422) {
                    this.errors = result.errors || {};
                } else {
                    alert(result.message || 'Something went wrong');
                }
            } else {
                this.showModal = false;
                window.location.reload();
            }
        } catch(e) {
            alert('An error occurred while saving.');
        } finally {
            this.isSubmitting = false;
        }
    }
}" @edit-environment-variable.window="openEdit($event.detail)">
    
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 tracking-tight">Environment Variables</h1>
            <p class="mt-1 text-sm text-zinc-500">
                Store API keys, tokens, and config securely.
            </p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" @click="openCreate()" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-zinc-900 hover:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-zinc-900 transition-colors">
                Add Environment Variable
            </button>
        </div>
    </div>

    <!-- Right-side Drawer -->
    <div x-show="showModal" style="display: none;" class="relative z-50" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
        <div x-show="showModal" x-transition.opacity class="fixed inset-0 bg-white/80 backdrop-blur-[2px] transition-opacity" @click="showModal = false"></div>
        <div class="fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 overflow-hidden">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">
                    <div x-show="showModal" 
                         x-transition:enter="transform transition ease-in-out duration-200 sm:duration-300" 
                         x-transition:enter-start="translate-x-full" 
                         x-transition:enter-end="translate-x-0" 
                         x-transition:leave="transform transition ease-in-out duration-200 sm:duration-300" 
                         x-transition:leave-start="translate-x-0" 
                         x-transition:leave-end="translate-x-full" 
                         class="pointer-events-auto w-screen max-w-md">
                        <div class="flex h-full flex-col bg-white shadow-xl">
                            <div class="px-4 py-6 sm:px-6 flex items-start justify-between border-b border-zinc-200">
                                <h2 class="text-base font-semibold leading-6 text-zinc-900" id="slide-over-title" x-text="editingId ? 'Update Environment Variable' : 'Add Environment Variable'"></h2>
                                <div class="ml-3 flex h-7 items-center">
                                    <button type="button" @click="showModal = false" class="relative rounded-md bg-white text-zinc-400 hover:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-zinc-900 focus:ring-offset-2">
                                        <span class="sr-only">Close panel</span>
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="flex-1 overflow-y-auto">
                                <form @submit.prevent="submitForm" class="flex h-full flex-col">
                                    <div class="px-4 py-6 sm:px-6 space-y-6 flex-1">
                                        <!-- Type -->
                                        <div>
                                            <label class="block text-sm font-medium leading-6 text-zinc-900">Type</label>
                                            <div class="mt-2 grid grid-cols-2 gap-3">
                                                <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none" :class="{ 'border-blue-500 ring-1 ring-blue-500 bg-blue-50': formData.type === 'secret', 'border-zinc-300': formData.type !== 'secret' }">
                                                    <input type="radio" name="type" value="secret" x-model="formData.type" class="sr-only">
                                                    <div class="flex flex-col">
                                                        <span class="block text-sm font-medium" :class="{ 'text-blue-700': formData.type === 'secret', 'text-zinc-900': formData.type !== 'secret' }">Secret</span>
                                                        <span class="mt-1 flex items-center text-xs" :class="{ 'text-blue-600': formData.type === 'secret', 'text-zinc-500': formData.type !== 'secret' }">You can't reveal this value after saving.</span>
                                                    </div>
                                                </label>
                                                <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none" :class="{ 'border-zinc-900 ring-1 ring-zinc-900': formData.type === 'config', 'border-zinc-300': formData.type !== 'config' }">
                                                    <input type="radio" name="type" value="config" x-model="formData.type" class="sr-only">
                                                    <div class="flex flex-col">
                                                        <span class="block text-sm font-medium text-zinc-900">Config</span>
                                                        <span class="mt-1 flex items-center text-xs text-zinc-500">Readable after saving. Use for non-sensitive values.</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <!-- Key -->
                                        <div>
                                            <label for="key" class="block text-sm font-medium leading-6 text-zinc-900">Key</label>
                                            <div class="mt-2">
                                                <input type="text" name="key" id="key" x-model="formData.key" required class="block w-full rounded-md border-zinc-300 py-2 text-zinc-900 focus:ring-zinc-900 focus:border-zinc-900 sm:text-sm sm:leading-6">
                                            </div>
                                            <template x-if="errors.key"><span class="text-xs text-red-500 mt-1" x-text="errors.key[0]"></span></template>
                                        </div>

                                        <!-- Value -->
                                        <div>
                                            <label for="value" class="block text-sm font-medium leading-6 text-zinc-900">Value</label>
                                            <div class="mt-2">
                                                <input type="text" name="value" id="value" x-model="formData.value" required class="block w-full rounded-md border-zinc-300 py-2 text-zinc-900 focus:ring-zinc-900 focus:border-zinc-900 sm:text-sm sm:leading-6">
                                            </div>
                                            <p x-show="editingId" class="mt-1 text-xs text-zinc-500">Saved values are hidden. Enter the new value to replace it.</p>
                                            <template x-if="errors.value"><span class="text-xs text-red-500 mt-1" x-text="errors.value[0]"></span></template>
                                        </div>
                                        
                                        <button type="button" class="mt-2 flex w-full items-center justify-center gap-x-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50">
                                            <svg class="h-4 w-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                            Add new variable
                                        </button>
                                    </div>
                                    
                                    <div class="flex flex-shrink-0 justify-between items-center px-4 py-4 border-t border-zinc-200 bg-white">
                                        <button type="button" class="inline-flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-zinc-900 shadow-sm ring-1 ring-inset ring-zinc-300 hover:bg-zinc-50">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" /></svg>
                                            Import .env
                                        </button>
                                        <button type="submit" :disabled="isSubmitting" class="ml-4 inline-flex justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-zinc-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-900 disabled:opacity-50">
                                            <span x-show="!isSubmitting" x-text="editingId ? 'Update' : 'Save'"></span>
                                            <span x-show="isSubmitting">Saving...</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>    <!-- Filters Section -->
    <div class="flex flex-col lg:flex-row gap-4 mb-6">
        <div class="flex-1 min-w-0">
            <div class="relative rounded-lg">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" class="block w-full pl-10 pr-3 py-2 border border-zinc-200 rounded-lg text-sm text-zinc-900 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900" placeholder="Search variables...">
            </div>
        </div>
        
        <div class="flex flex-wrap items-center gap-3">
            <select class="block w-full sm:w-auto pl-3 pr-10 py-2 text-sm border-zinc-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900">
                <option>All Types</option>
            </select>
            <select class="block w-full sm:w-auto pl-3 pr-10 py-2 text-sm border-zinc-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900">
                <option>All Environments</option>
            </select>
            <div class="relative flex-1 min-w-[150px]">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-4 w-4 text-zinc-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" class="block w-full pl-10 pr-3 py-2 border border-zinc-200 rounded-lg text-sm text-zinc-900 placeholder-zinc-400 focus:outline-none focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900" placeholder="All Editors">
            </div>
            <select class="block w-full sm:w-auto pl-3 pr-10 py-2 text-sm border-zinc-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-zinc-900 focus:border-zinc-900">
                <option>Last Updated</option>
            </select>
        </div>
    </div>

    <!-- Variables List -->
    <div class="bg-white border border-zinc-200 rounded-lg">
        <ul role="list" class="divide-y divide-zinc-200">
            @forelse($variables as $variable)
                <x-environment-variables.list-item 
                    :id="$variable->id"
                    :name="$variable->key"
                    :type="$variable->type"
                    :environments="$variable->environments"
                    :note="$variable->note"
                    date="{{ $variable->created_at->format('M d') }}" 
                    initial="{{ $variable->user ? substr($variable->user->name, 0, 1) : 'U' }}" 
                />
            @empty
                <li class="px-4 py-8 text-center text-zinc-500 text-sm">
                    No environment variables added yet.
                </li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
