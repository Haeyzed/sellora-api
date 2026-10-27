<?php

declare(strict_types=1);

namespace App\Landlord\Identity;

use App\Landlord\Identity\Actions\CreatePlatformAdmin;
use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Models\PlatformAdmin;
use App\Shared\Auth\TwoFactor\TwoFactorAuthenticator;
use chillerlan\QRCode\Output\QRStringText;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Creates a platform admin from the command line, the only way to add the very first one.
 *
 * The password is always typed at a hidden prompt, never passed as an
 * option, so it never ends up in shell history or process lists. Two-factor
 * authentication is set up before the admin is created: the command shows a
 * QR code and authenticator secret, asks for a code from the app, and prints
 * the recovery codes once. The QR code is drawn only in the terminal, from
 * the same setup link printed under it; --no-qr leaves it out for terminals
 * that can't show it.
 */
final class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'platform:create-admin
        {--name= : Full name}
        {--email= : Email address used to sign in}
        {--super-admin : Give the new admin every permission}
        {--no-qr : Leave out the QR code, for terminals that can\'t show it; the setup link and key are shown either way}';

    protected $description = 'Create a platform admin, with two-factor authentication set up, who can sign in to the platform admin app';

    private const int MAXIMUM_CODE_ATTEMPTS = 3;

    public function handle(CreatePlatformAdmin $createPlatformAdmin, TwoFactorAuthenticator $twoFactorAuthenticator): int
    {
        $name = $this->stringOption('name') ?? text('Full name', required: true);
        $email = mb_strtolower(trim($this->stringOption('email') ?? text('Email address', required: true)));
        $password = password('Password (at least 12 characters)', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email:rfc', 'max:254', Rule::unique(PlatformAdmin::class, 'email')],
                'password' => ['required', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $twoFactorSecret = $twoFactorAuthenticator->newSecret();
        $confirmedTimestep = $this->confirmAuthenticatorApp($twoFactorAuthenticator, $email, $twoFactorSecret);

        if ($confirmedTimestep === null) {
            $this->components->error('The authenticator app could not be confirmed. No admin was created; run the command again.');

            return self::FAILURE;
        }

        $roles = $this->option('super-admin') === true ? [PlatformRole::SuperAdmin] : [];
        $created = $createPlatformAdmin->handle($name, $email, $password, $roles, $twoFactorSecret, $confirmedTimestep);

        $this->components->info("Platform admin {$created->platformAdmin->email} created, with two-factor authentication on.");
        $this->components->warn('Recovery codes, shown only now. Give them to the admin to store safely; each works once if they lose their phone:');
        $this->components->bulletList($created->recoveryCodes);

        return self::SUCCESS;
    }

    /**
     * Shows the authenticator secret and asks for a code from the app, so the admin is never created with a secret nobody has.
     *
     * @return int|null The step of the accepted code, or null after too many wrong codes.
     */
    private function confirmAuthenticatorApp(TwoFactorAuthenticator $twoFactorAuthenticator, string $email, string $twoFactorSecret): ?int
    {
        $setupUrl = $twoFactorAuthenticator->setupUrl($email, $twoFactorSecret);

        if ($this->option('no-qr') === true) {
            $this->components->info('Two-factor authentication is required for platform admins. Add the account to an authenticator app:');
        } else {
            $this->components->info('Two-factor authentication is required for platform admins. Scan this QR code with an authenticator app:');
            $this->line((new QRCode(new QROptions(['outputInterface' => QRStringText::class])))->render($setupUrl));
            $this->line('If the QR code looks garbled, use the setup link or key below, or run the command again with --no-qr.');
        }

        $this->components->twoColumnDetail('Setup link', $setupUrl);
        $this->components->twoColumnDetail('Or type this key into the app', $twoFactorSecret);

        for ($attempt = 1; $attempt <= self::MAXIMUM_CODE_ATTEMPTS; $attempt++) {
            $code = trim(text('6-digit code from the authenticator app', required: true));
            $timestep = preg_match('/^\d{6}$/', $code) === 1 ? $twoFactorAuthenticator->matchingTimestep($twoFactorSecret, $code) : null;

            if ($timestep !== null) {
                return $timestep;
            }

            $this->components->error('That code is incorrect. Check the time on the phone and try the current code.');
        }

        return null;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
