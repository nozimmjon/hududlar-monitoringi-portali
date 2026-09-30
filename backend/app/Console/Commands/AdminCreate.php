<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** The portal has one admin; this creates it, or resets its password when the e-mail already exists. */
class AdminCreate extends Command
{
    protected $signature = 'admin:create
        {email : Login e-mail}
        {--name=Администратор : Display name (only used when creating)}
        {--password= : Password; prompted (twice, hidden) when omitted}';

    protected $description = 'Create the admin user, or reset the password of an existing one.';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error("«{$email}» электрон почта эмас.");

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        if ($password === '') {
            $password = (string) $this->secret('Парол');
            if ($password !== (string) $this->secret('Паролни такрорланг')) {
                $this->error('Пароллар мос эмас.');

                return self::FAILURE;
            }
        }
        if (mb_strlen($password) < 8) {
            $this->error('Парол камида 8 белги бўлсин.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            // Rotate the remember-me token and drop live sessions so a reset actually locks the old session out.
            // Only the database driver keeps sessions in a table we can reach; file/redis/array have no such row.
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
            $this->info("Парол янгиланди: {$email}");
        } else {
            User::create(['name' => (string) $this->option('name'), 'email' => $email, 'password' => $password]);
            $this->info("Админ яратилди: {$email}");
        }

        return self::SUCCESS;
    }
}
