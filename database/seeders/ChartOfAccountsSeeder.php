<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * The hierarchical chart of accounts from the build spec.
     *
     * @var array<int, array{name: string, name_bn: string, type: string, children: array<int, array{name: string, name_bn: string}>}>
     */
    private const TREE = [
        '1000' => [
            'name' => 'Assets', 'name_bn' => 'সম্পদ', 'type' => 'asset',
            'children' => [
                '1010' => ['name' => 'Cash in Hand', 'name_bn' => 'হাতে নগদ'],
                '1020' => ['name' => 'Bank', 'name_bn' => 'ব্যাংক'],
                '1030' => ['name' => 'Service Charge Receivable', 'name_bn' => 'সার্ভিস চার্জ প্রাপ্য'],
            ],
        ],
        '2000' => [
            'name' => 'Liabilities', 'name_bn' => 'দায়', 'type' => 'liability',
            'children' => [
                '2010' => ['name' => 'Accounts Payable', 'name_bn' => 'প্রদেয় হিসাব'],
                '2020' => ['name' => 'Advance from Owners', 'name_bn' => 'মালিকদের অগ্রিম'],
                '2030' => ['name' => 'Security Deposits', 'name_bn' => 'জামানত'],
            ],
        ],
        '3000' => [
            'name' => 'Equity & Funds', 'name_bn' => 'মূলধন ও তহবিল', 'type' => 'equity',
            'children' => [
                '3010' => ['name' => 'General Fund', 'name_bn' => 'সাধারণ তহবিল'],
                '3020' => ['name' => 'Sinking / Reserve Fund', 'name_bn' => 'সংরক্ষিত তহবিল'],
            ],
        ],
        '4000' => [
            'name' => 'Income', 'name_bn' => 'আয়', 'type' => 'income',
            'children' => [
                '4010' => ['name' => 'Service Charge Income', 'name_bn' => 'সার্ভিস চার্জ আয়'],
                '4020' => ['name' => 'Late Fee Income', 'name_bn' => 'বিলম্ব ফি আয়'],
                '4030' => ['name' => 'Bank Interest', 'name_bn' => 'ব্যাংক সুদ'],
                '4040' => ['name' => 'Other Income', 'name_bn' => 'অন্যান্য আয়'],
            ],
        ],
        '5000' => [
            'name' => 'Expenses', 'name_bn' => 'ব্যয়', 'type' => 'expense',
            'children' => [
                '5010' => ['name' => 'Guard Salary', 'name_bn' => 'দারোয়ানের বেতন'],
                '5020' => ['name' => 'Common Electricity', 'name_bn' => 'সাধারণ বিদ্যুৎ'],
                '5030' => ['name' => 'Lift Maintenance', 'name_bn' => 'লিফট রক্ষণাবেক্ষণ'],
                '5040' => ['name' => 'Cleaning', 'name_bn' => 'পরিচ্ছন্নতা'],
                '5050' => ['name' => 'Water / WASA', 'name_bn' => 'পানি / ওয়াসা'],
                '5060' => ['name' => 'Generator Fuel', 'name_bn' => 'জেনারেটর জ্বালানি'],
                '5070' => ['name' => 'Repairs & Maintenance', 'name_bn' => 'মেরামত ও রক্ষণাবেক্ষণ'],
                '5080' => ['name' => 'Admin', 'name_bn' => 'প্রশাসনিক'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::TREE as $code => $group) {
            $parent = Account::updateOrCreate(
                ['code' => (string) $code],
                [
                    'name' => $group['name'],
                    'name_bn' => $group['name_bn'],
                    'type' => AccountType::from($group['type']),
                    'parent_id' => null,
                    // Headers are for grouping only; postings must land on a leaf.
                    'is_postable' => false,
                    'is_active' => true,
                ],
            );

            foreach ($group['children'] as $childCode => $child) {
                Account::updateOrCreate(
                    ['code' => (string) $childCode],
                    [
                        'name' => $child['name'],
                        'name_bn' => $child['name_bn'],
                        'type' => AccountType::from($group['type']),
                        'parent_id' => $parent->id,
                        'is_postable' => true,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
