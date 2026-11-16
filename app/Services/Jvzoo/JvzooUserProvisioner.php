<?php

namespace App\Services\Jvzoo;

use App\Actions\Teams\CreateTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JvzooUserProvisioner
{
    public function __construct(
        private readonly CreateTeam $createTeam,
    ) {}

    /**
     * Grants the role on top of any roles the buyer already has, so add-on purchases keep earlier access.
     *
     * @return array{user: User, password: string|null, created: bool}
     */
    public function provision(string $email, string $roleName): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            if (! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }

            return [
                'user' => $user,
                'password' => null,
                'created' => false,
            ];
        }

        return [
            ...$this->createUser($email, $roleName),
            'created' => true,
        ];
    }

    /**
     * @return array{user: User, password: string}
     */
    public function createUser(string $email, string $roleName, ?string $name = null, ?User $reseller = null): array
    {
        $password = Str::random(12);
        $name = $name !== null && trim($name) !== '' ? trim($name) : Str::before($email, '@');
        $name = $name !== '' ? $name : 'User';

        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'reseller_id' => $reseller?->id,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->createTeam->handle($user, $name."'s Team", isPersonal: true);
        $user->assignRole($roleName);

        return [
            'user' => $user,
            'password' => $password,
        ];
    }

    public function revokeRole(User $user, string $roleName): void
    {
        $user->removeRole($roleName);
    }

    public function deleteAccount(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->teams()->get()
                ->filter(fn (Team $team) => $team->members()->count() === 1)
                ->each(fn (Team $team) => $team->delete());

            $user->delete();
        });
    }
}
