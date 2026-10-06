<?php

declare(strict_types=1);

namespace App\Landlord\Identity;

use App\Landlord\Identity\Actions\CreatePlatformAdmin;
use App\Landlord\Identity\Enums\PlatformRole;
use App\Landlord\Identity\Models\PlatformAdmin;
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
 * option, so it never ends up in shell history or process lists.
 */
final class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'platform:create-admin
        {--name= : Full name}
        {--email= : Email address used to sign in}
        {--super-admin : Give the new admin every permission}';

    protected $description = 'Create a platform admin who can sign in to the platform admin app';

    public function handle(CreatePlatformAdmin $createPlatformAdmin): int
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

        $roles = $this->option('super-admin') === true ? [PlatformRole::SuperAdmin] : [];
        $platformAdmin = $createPlatformAdmin->handle($name, $email, $password, $roles);

        $this->components->info("Platform admin {$platformAdmin->email} created.");

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
