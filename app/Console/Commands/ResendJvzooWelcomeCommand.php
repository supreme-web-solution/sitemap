<?php

namespace App\Console\Commands;

use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class ResendJvzooWelcomeCommand extends Command
{
    protected $signature = 'jvzoo:resend-welcome {email : The buyer\'s account email} {--force : Skip the confirmation prompt}';

    protected $description = 'Set a new password for a buyer and email them their login details';

    public function handle(): int
    {
        $emailArgument = $this->argument('email');

        if (! is_string($emailArgument)) {
            $this->error('A buyer email is required.');

            return self::FAILURE;
        }

        $email = trim($emailArgument);
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("No account found for {$email}.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("This replaces the password for {$email} and emails the new login details. Continue?")) {
            return self::FAILURE;
        }

        $password = Str::random(12);

        try {
            Mail::to($user->email)->send(new WelcomeMail($user, $password));
        } catch (Throwable $exception) {
            $this->error('Email failed to send — the password was NOT changed.');
            $this->line('Mailer: '.config('mail.default').' · From: '.config('mail.from.address'));
            $this->line('Error: '.$exception->getMessage());

            return self::FAILURE;
        }

        $user->forceFill(['password' => $password])->save();

        $this->info("Login details sent to {$user->email}.");

        return self::SUCCESS;
    }
}
