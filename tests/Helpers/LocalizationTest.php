<?php

use App\Helpers\Localization;
use PHPUnit\Framework\TestCase;

final class LocalizationTest extends TestCase
{
    public function testLocaleNormalizationUsesSupportedBaseLocale(): void
    {
        $this->assertSame('es', Localization::normalizeLocale('es-MX'));
        $this->assertSame('fr', Localization::normalizeLocale('FR_ca'));
        $this->assertSame('en', Localization::normalizeLocale('de'));
        $this->assertSame('en', Localization::normalizeLocale(null));
    }

    public function testTranslationUsesNamedParametersAndEnglishFallback(): void
    {
        $this->assertSame(
            '¡Bienvenido de nuevo, Ana!',
            Localization::translate('home.welcome', ['username' => 'Ana'], 'es')
        );
        $this->assertSame('missing.key', Localization::translate('missing.key', [], 'fr'));
    }

    public function testDraftCatalogsHaveTheSameStableKeysAsEnglish(): void
    {
        $englishKeys = array_keys(Localization::catalog('en'));
        sort($englishKeys);
        foreach (['es', 'fr'] as $locale) {
            $keys = array_keys(Localization::catalog($locale));
            sort($keys);
            $this->assertSame($englishKeys, $keys, $locale . ' catalog keys differ from English');
        }
    }

    public function testJavascriptCatalogIsValidAndLimited(): void
    {
        $catalog = json_decode(Localization::javascriptCatalogJson('fr'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('fr', $catalog['locale']);
        $this->assertSame('Enregistrer', $catalog['messages']['common.save']);
        $this->assertArrayNotHasKey('footer.disclaimer', $catalog['messages']);
    }
}
