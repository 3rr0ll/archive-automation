@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ 
    userName: 'Loading...', 
    init() {
        const token = localStorage.getItem('auth_token');
        
        fetch('/api/user', {
            headers: {
                'Authorization': 'Bearer ' + token,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.name) {
                this.userName = data.name;
            } else {
                window.location.href = '/login';
            }
        })
        .catch(() => window.location.href = '/login');
    }
}">
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 shadow-sm">
        <h1 class="text-2xl font-bold tracking-tight text-white">Dashboard</h1>
        <p class="text-slate-400 text-sm mt-1">
            Welcome back, <span class="text-slate-200 font-medium" x-text="userName"></span>. Manage deployments, servers, and automated archiving.
        </p>
    </div>

    <!-- Quick Stats & Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Deployments</span>
            <p class="text-3xl font-extrabold text-white mt-2">0</p>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Connected Servers</span>
            <p class="text-3xl font-extrabold text-white mt-2">0</p>
        </div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Queue Worker Status</span>
            <p class="text-3xl font-extrabold text-emerald-400 mt-2">Ready</p>
        </div>
    </div>
</div>
@endsection