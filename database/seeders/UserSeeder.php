User::firstOrCreate(
    ['email' => 'admin@example.com'],
    [
        'name' => 'Admin User',
        'password' => Hash::make('password'),
        'role' => 'admin',
        'is_active' => 1,
        'email_verified_at' => now(),
    ]
);