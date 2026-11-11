<x-layouts.admin title="Log in">
    <div class="card" style="max-width: 380px; margin: 40px auto;">
        <h1>Log in</h1>

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email') <div class="error">{{ $message }}</div> @enderror

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">
            @error('password') <div class="error">{{ $message }}</div> @enderror

            <div class="actions">
                <button type="submit" class="approve">Log in</button>
            </div>
        </form>
    </div>
</x-layouts.admin>
