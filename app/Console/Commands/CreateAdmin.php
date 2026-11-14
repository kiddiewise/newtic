<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'newtic:admin {email?}';
    protected $description = 'Create or grant a secure admin account interactively';

    public function handle(): int
    {
        $email = trim((string) ($this->argument('email') ?: $this->ask('Admin e-postası')));
        $name = trim((string) $this->ask('Ad soyad'));
        $password = (string) $this->secret('En az 12 karakterli güçlü şifre');
        $validator = Validator::make(compact('email', 'name', 'password'), [
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            $this->error('Girdi geçersiz: '.$validator->errors()->first());

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        if ($user->exists && ! $this->confirm('Mevcut hesabı yönetici yapıp şifresini değiştirelim mi?', false)) {
            return self::FAILURE;
        }

        $user->forceFill([
            'name' => $name,
            'password' => Hash::make($password),
            'is_admin' => true,
        ])->save();

        $this->info('Yönetici hesabı hazır. Şifre kayıtlara yazılmadı.');

        return self::SUCCESS;
    }
}
