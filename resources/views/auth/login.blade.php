<x-layouts.app :title="__('auth.sign_in')">
    <div class="mx-auto max-w-sm rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-6 text-lg font-semibold text-slate-900">{{ __('auth.sign_in') }}</h1>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">{{ __('auth.email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">{{ __('auth.password') }}</label>
                <input id="password" name="password" type="password" required
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300">
                {{ __('auth.remember_me') }}
            </label>

            <button type="submit"
                    class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                {{ __('auth.sign_in') }}
            </button>
        </form>
    </div>
</x-layouts.app>
