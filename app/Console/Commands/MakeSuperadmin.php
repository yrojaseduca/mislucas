<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class MakeSuperadmin extends Command
{
    protected $signature = 'app:make-superadmin {email}';

    protected $description = 'Promote an existing user to global superadmin';

    public function handle(): int
    {
        $email = mb_strtolower((string) $this->argument('email'));
        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $password = Str::password(20);
            $user = User::query()->create([
                'name' => Str::headline(Str::before($email, '@')),
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            $this->warn('Cuenta creada. Contraseña temporal (se muestra solo ahora): '.$password);
        }

        $user->update(['is_superadmin' => true]);
        $this->info("{$user->email} ahora es superadmin.");

        return self::SUCCESS;
    }
}
