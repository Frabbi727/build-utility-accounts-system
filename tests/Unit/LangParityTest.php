<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `.ai/rules/localization.md` says the Bangla and English trees move together and are
 * currently at exact parity — but nothing enforced it. A key added to one locale only
 * shows up as the raw key in the UI, which is easy to miss in the locale you do not
 * read. This test is that enforcement.
 */
class LangParityTest extends TestCase
{
    private const BASE = __DIR__.'/../../lang';

    public function test_every_domain_file_exists_in_both_locales(): void
    {
        $this->assertSame(
            $this->domains('en'),
            $this->domains('bn'),
            'The two locales must carry the same domain files.',
        );
    }

    #[DataProvider('domainProvider')]
    public function test_a_domain_has_the_same_keys_in_both_locales(string $domain): void
    {
        $en = $this->keys(require self::BASE."/en/{$domain}.php");
        $bn = $this->keys(require self::BASE."/bn/{$domain}.php");

        $this->assertSame(
            [],
            array_values(array_diff($en, $bn)),
            "Keys in lang/en/{$domain}.php are missing from lang/bn/{$domain}.php.",
        );

        $this->assertSame(
            [],
            array_values(array_diff($bn, $en)),
            "Keys in lang/bn/{$domain}.php are missing from lang/en/{$domain}.php.",
        );
    }

    /**
     * @return list<array{string}>
     */
    public static function domainProvider(): array
    {
        return array_map(
            fn (string $domain): array => [$domain],
            array_map(
                fn (string $path): string => basename($path, '.php'),
                glob(self::BASE.'/en/*.php') ?: [],
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function domains(string $locale): array
    {
        $files = array_map(
            fn (string $path): string => basename($path),
            glob(self::BASE."/{$locale}/*.php") ?: [],
        );

        sort($files);

        return $files;
    }

    /**
     * Flattened dot-notation keys, so a nested array added to one locale is caught too.
     *
     * @param  array<string, mixed>  $translations
     * @return list<string>
     */
    private function keys(array $translations, string $prefix = ''): array
    {
        $keys = [];

        foreach ($translations as $key => $value) {
            $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $keys = [...$keys, ...$this->keys($value, $full)];

                continue;
            }

            $keys[] = $full;
        }

        sort($keys);

        return $keys;
    }
}
