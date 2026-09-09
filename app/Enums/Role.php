<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Committee = 'committee';
    case Owner = 'owner';
    case Tenant = 'tenant';

    /**
     * Roles belonging to the management side, as opposed to flat owners.
     *
     * @return list<string>
     */
    public static function staff(): array
    {
        return [self::Admin->value, self::Accountant->value, self::Committee->value];
    }

    /**
     * Roles allowed to create bills, take payments and post journal entries.
     *
     * @return list<string>
     */
    public static function moneyHandlers(): array
    {
        return [self::Admin->value, self::Accountant->value];
    }

    /**
     * Roles belonging to the residential side (flat owners and tenants).
     *
     * @return list<string>
     */
    public static function residents(): array
    {
        return [self::Owner->value, self::Tenant->value];
    }
}
