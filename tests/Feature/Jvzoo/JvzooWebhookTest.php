<?php

use App\Mail\AccessGrantedMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Database\Seeders\JvzooAccessSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;

const ALL_PERMISSIONS = [
    'view_app_features',
    'access_reseller',
    'access_agency_vault',
    'access_online_reputation',
    'access_ai_sales_employee',
];

beforeEach(function () {
    $this->seed(JvzooAccessSeeder::class);
    config(['jvzoo.secret_key' => 'test-secret-key', 'jvzoo.welcome_notify_email' => null]);
});

function jvzooSale(string $email, string $productId, string $transactionId = 'TX-1'): array
{
    return jvzooPayload([
        'ctransaction' => 'SALE',
        'ccustemail' => $email,
        'ctransreceipt' => $transactionId,
        'cproditem' => $productId,
    ]);
}

function jvzooPayload(array $fields): array
{
    ksort($fields);

    $pop = '';

    foreach ($fields as $value) {
        $pop .= $value.'|';
    }

    $pop .= config('jvzoo.secret_key');
    $fields['cverify'] = strtoupper(substr(sha1($pop), 0, 8));

    return $fields;
}

test('a sale creates the buyer, a personal team, and emails the password', function () {
    Mail::fake();

    $this->post('/ipn/jvzoo', jvzooSale('buyer@example.com', '462101'))
        ->assertOk()
        ->assertJson([
            'message' => 'User created successfully!',
            'email_sent' => true,
        ]);

    $user = User::query()->where('email', 'buyer@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('FE'))->toBeTrue()
        ->and($user->can('view_app_features'))->toBeTrue()
        ->and($user->can('access_reseller'))->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->personalTeam())->not->toBeNull()
        ->and($user->current_team_id)->toBe($user->personalTeam()->id);

    Mail::assertSent(WelcomeMail::class, function (WelcomeMail $mail): bool {
        return $mail->hasTo('buyer@example.com') && strlen($mail->password) >= 8;
    });
});

test('each product grants the access for that purchase', function (string $productId, string $role, array $expected) {
    Mail::fake();

    $this->post('/ipn/jvzoo', jvzooSale('product@example.com', $productId))->assertOk();

    $user = User::query()->where('email', 'product@example.com')->firstOrFail();

    expect($user->hasRole($role))->toBeTrue();

    foreach (ALL_PERMISSIONS as $permission) {
        expect($user->can($permission))->toBe(in_array($permission, $expected, true));
    }
})->with([
    'FE' => ['462101', 'FE', ['view_app_features']],
    'Bundle' => ['462103', 'Bundle', ALL_PERMISSIONS],
    'Fast-pass 1' => ['463017', 'Bundle', ALL_PERMISSIONS],
    'Fast-pass 2' => ['463019', 'Bundle', ALL_PERMISSIONS],
    'AgencyVault 1' => ['463025', 'AgencyVault', ['view_app_features', 'access_agency_vault']],
    'AgencyVault 2' => ['463027', 'AgencyVault', ['view_app_features', 'access_agency_vault']],
    'Online Reputation 1' => ['463029', 'Online Reputation App', ['view_app_features', 'access_online_reputation']],
    'Online Reputation 2' => ['463033', 'Online Reputation App', ['view_app_features', 'access_online_reputation']],
    'Ai sales 1' => ['463035', 'Ai Multichannel Sales Employee', ['view_app_features', 'access_ai_sales_employee']],
    'Ai sales 2' => ['463037', 'Ai Multichannel Sales Employee', ['view_app_features', 'access_ai_sales_employee']],
    'Reseller 1' => ['463039', 'Reseller', ['view_app_features', 'access_reseller']],
    'Reseller 2' => ['463041', 'Reseller', ['view_app_features', 'access_reseller']],
]);

test('an unknown product is rejected', function () {
    $this->post('/ipn/jvzoo', jvzooSale('old@example.com', '111111'))->assertNotFound();

    expect(User::query()->where('email', 'old@example.com')->first())->toBeNull();
});

test('an add-on purchase keeps the access the buyer already had', function () {
    Mail::fake();

    $this->post('/ipn/jvzoo', jvzooSale('stack@example.com', '462101'))->assertOk();
    $this->post('/ipn/jvzoo', jvzooSale('stack@example.com', '463039', 'TX-2'))
        ->assertOk()
        ->assertJson(['message' => 'User role updated successfully!']);

    $user = User::query()->where('email', 'stack@example.com')->firstOrFail();

    expect($user->hasRole('FE'))->toBeTrue()
        ->and($user->hasRole('Reseller'))->toBeTrue()
        ->and($user->can('view_app_features'))->toBeTrue()
        ->and($user->can('access_reseller'))->toBeTrue();

    Mail::assertSent(WelcomeMail::class, 1);
    Mail::assertSent(AccessGrantedMail::class, fn (AccessGrantedMail $mail): bool => $mail->hasTo('stack@example.com') && $mail->roleName === 'Reseller');
});

test('a refund removes only the refunded product', function () {
    Mail::fake();

    $this->post('/ipn/jvzoo', jvzooSale('refund@example.com', '462101'))->assertOk();
    $this->post('/ipn/jvzoo', jvzooSale('refund@example.com', '463025', 'TX-AV'))->assertOk();

    $this->post('/ipn/jvzoo', jvzooPayload([
        'ctransaction' => 'RFND',
        'ccustemail' => 'refund@example.com',
        'ctransreceipt' => 'TX-AV-R',
        'cproditem' => '463025',
    ]))->assertOk()->assertJson(['message' => 'User access revoked successfully!']);

    $user = User::query()->where('email', 'refund@example.com')->firstOrFail();

    expect($user->hasRole('FE'))->toBeTrue()
        ->and($user->hasRole('AgencyVault'))->toBeFalse()
        ->and($user->can('access_agency_vault'))->toBeFalse();
});

test('a bad signature is rejected', function () {
    $this->post('/ipn/jvzoo', [
        'ctransaction' => 'SALE',
        'ccustemail' => 'buyer@example.com',
        'ctransreceipt' => 'TX-000',
        'cproditem' => '462101',
        'cverify' => 'INVALID',
    ])->assertForbidden();
});

test('the account is still created when the welcome email cannot be sent', function () {
    Sleep::fake();
    Mail::shouldReceive('to')->times(3)->andThrow(new RuntimeException('Resend API error'));

    $this->post('/ipn/jvzoo', jvzooSale('mail-fail@example.com', '462101'))
        ->assertOk()
        ->assertJson(['email_sent' => false]);

    expect(User::query()->where('email', 'mail-fail@example.com')->first())->not->toBeNull();
});

test('the welcome email is retried after a transient failure', function () {
    Sleep::fake();
    $attempts = 0;
    $mailer = Mockery::mock();
    $mailer->shouldReceive('send')->andReturnUsing(function () use (&$attempts): void {
        $attempts++;
        if ($attempts === 1) {
            throw new RuntimeException('Too many requests');
        }
    });
    Mail::shouldReceive('to')->andReturn($mailer);

    $this->post('/ipn/jvzoo', jvzooSale('retry@example.com', '462101'))
        ->assertOk()
        ->assertJson(['email_sent' => true]);

    expect($attempts)->toBe(2);
});

test('resend welcome replaces the password only after the email sends', function () {
    Mail::fake();
    $this->post('/ipn/jvzoo', jvzooSale('again@example.com', '462101'))->assertOk();
    $oldHash = User::query()->where('email', 'again@example.com')->value('password');

    $this->artisan('jvzoo:resend-welcome', ['email' => 'again@example.com', '--force' => true])
        ->expectsOutputToContain('Login details sent')
        ->assertSuccessful();

    $user = User::query()->where('email', 'again@example.com')->firstOrFail();

    expect($user->password)->not->toBe($oldHash);
    Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail): bool => Hash::check($mail->password, $user->password));
});

test('menus and pages follow the product that was bought', function () {
    Mail::fake();

    $this->post('/ipn/jvzoo', jvzooSale('fe@example.com', '462101'))->assertOk();
    $fe = User::query()->where('email', 'fe@example.com')->firstOrFail();

    $this->actingAs($fe)->get('/reseller')->assertForbidden();
    $this->actingAs($fe)->get('/agency-vault')->assertForbidden();
    $this->actingAs($fe)->get(route('dashboard', ['current_team' => $fe->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.canViewAppFeatures', true)->where('auth.permissions', ['view_app_features']));

    $this->post('/ipn/jvzoo', jvzooSale('bundle@example.com', '462103'))->assertOk();
    $bundle = User::query()->where('email', 'bundle@example.com')->firstOrFail();

    $this->actingAs($bundle)->get('/reseller')->assertOk();
    $this->actingAs($bundle)->get('/agency-vault')->assertOk()->assertInertia(fn ($page) => $page
        ->component('upsells/Show')
        ->where('page.title', 'AgencyVault')
        ->where('page.paragraphs.1', fn ($text) => str_contains($text, 'AgencyVault upsell')));
    $this->actingAs($bundle)->get('/online-reputation')->assertOk();
    $this->actingAs($bundle)->get('/ai-sales-employee')->assertOk();

    $this->post('/ipn/jvzoo', jvzooSale('vault@example.com', '463025'))->assertOk();
    $vault = User::query()->where('email', 'vault@example.com')->firstOrFail();

    $this->actingAs($vault)->get('/agency-vault')->assertOk();
    $this->actingAs($vault)->get('/reseller')->assertForbidden();
    $this->actingAs($vault)->get(route('dashboard', ['current_team' => $vault->currentTeam->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.canViewAppFeatures', true)
            ->where('auth.permissions', ['view_app_features', 'access_agency_vault']));
});

test('a reseller can create and delete only their own customer accounts', function () {
    Mail::fake();

    $this->post('/ipn/jvzoo', jvzooSale('seller@example.com', '463039'))->assertOk();
    $reseller = User::query()->where('email', 'seller@example.com')->firstOrFail();
    $other = User::factory()->create();
    $other->assignRole('Reseller');

    $this->actingAs($reseller)->post('/reseller/accounts', [
        'name' => 'Jane Customer',
        'email' => 'jane@example.com',
    ])->assertRedirect();

    $account = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($account->reseller_id)->toBe($reseller->id)
        ->and($account->hasRole('FE'))->toBeTrue()
        ->and($account->can('access_reseller'))->toBeFalse()
        ->and($account->personalTeam())->not->toBeNull();

    Mail::assertSent(WelcomeMail::class, fn (WelcomeMail $mail): bool => $mail->hasTo('jane@example.com'));

    $this->actingAs($other)->delete("/reseller/accounts/{$account->id}")->assertNotFound();
    expect(User::query()->whereKey($account->id)->exists())->toBeTrue();

    $this->actingAs($reseller)->delete("/reseller/accounts/{$account->id}")->assertRedirect();
    expect(User::query()->whereKey($account->id)->exists())->toBeFalse();
});

test('an admin can grant and remove product access', function () {
    config(['admin.emails' => ['boss@example.com']]);
    $admin = User::factory()->create(['email' => 'boss@example.com']);
    $user = User::factory()->create();

    $this->actingAs($admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'verified' => true,
        'roles' => ['Bundle', 'AgencyVault'],
    ])->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->hasRole('Bundle'))->toBeTrue()
        ->and($user->can('access_agency_vault'))->toBeTrue()
        ->and($user->can('access_reseller'))->toBeTrue();

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('rolesEnabled', true)->has('assignableRoles', 6));
});
