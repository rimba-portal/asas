<?php

declare(strict_types=1);

namespace Rimba\Base\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Rimba\Ldap\Models\AdUser;

#[Description('Export LDAP accounts by userAccountControl value as JSON')]
#[Signature('
        ldap:list-acc
        {uac : userAccountControl value}
        {--save : Save JSON to storage/app/private}
    ')]
final class LdapListAccounts extends Command
{
    public function handle(): int
    {
        $uac = (int) $this->argument('uac');

        $accounts = AdUser::query()
            ->get()
            ->filter(fn ($user): bool => (int) ($user->getFirstAttribute('useraccountcontrol') ?? 0) === $uac
            )
            ->map(fn (AdUser $user): array => [
                'staff_no' => $this->attribute($user, 'employeenumber'),
                'username' => $this->attribute($user, 'samaccountname'),
                'email' => $this->attribute($user, 'mail'),
                'name' => $this->attribute($user, 'displayname'),
                'title' => $this->attribute($user, 'title'),
                'department' => $this->attribute($user, 'department'),
                'company' => $this->attribute($user, 'company'),
                'uac' => (int) ($user->getFirstAttribute('useraccountcontrol') ?? 0),
            ])
            ->values()
            ->all();

        $json = json_encode(
            $accounts,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        if ($this->option('save')) {

            $filename = "ldap/accounts-{$uac}.json";

            Storage::disk('local')->put(
                $filename,
                $json
            );

            $this->info(
                'Saved to: storage/app/private/'.$filename
            );
        }

        $this->line($json);

        return self::SUCCESS;
    }

    private function attribute(
        AdUser $user,
        string $attribute,
    ): ?string {
        return $user->getFirstAttribute($attribute);
    }
}
