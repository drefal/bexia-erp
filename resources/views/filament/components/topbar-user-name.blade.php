@php
    $user = filament()->auth()->user();
@endphp

@if($user)
    <div class="hidden md:flex items-center mr-3 text-sm font-medium text-gray-700">
        {{ $user->name }}
    </div>
@endif
