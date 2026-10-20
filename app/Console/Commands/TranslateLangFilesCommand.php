<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Stichoza\GoogleTranslate\GoogleTranslate;

#[Signature('lang:translate')]
#[Description('Translate missing keys in English lang files to multiple languages without overwriting existing ones.')]
final class TranslateLangFilesCommand extends Command
{
    /**
     * The languages to fill in, by code.
     *
     * @var list<string>
     */
    private array $targetLanguages = [
        'ar', 'de', 'es', 'fr', 'ha', 'hi', 'id', 'ig', 'it',
        'ja', 'ko', 'nl',
        'pl', 'pt', 'ru', 'sw', 'tr', 'yo', 'zh',
    ];

    public function handle(): int
    {
        $enPath = base_path('lang/en');

        if (! File::exists($enPath)) {
            $this->error('The lang/en directory does not exist.');

            return self::FAILURE;
        }

        $files = File::files($enPath);
        $translator = new GoogleTranslate;

        foreach ($this->targetLanguages as $lang) {
            $this->info("Processing language: {$lang}");
            $targetPath = base_path("lang/{$lang}");
            File::ensureDirectoryExists($targetPath);

            $translator->setSource('en')->setTarget($lang);

            foreach ($files as $file) {
                $filename = $file->getFilename();
                $targetFilePath = $targetPath.'/'.$filename;

                // Load the English array
                $enTranslations = require $file->getPathname();

                // Load existing translations if the file already exists
                $existingTranslations = File::exists($targetFilePath) ? require $targetFilePath : [];

                $this->line(" - Checking {$filename}...");

                // Translate only missing keys
                $translatedArray = $this->translateArray($enTranslations, $existingTranslations, $translator);

                // Write the merged file back
                $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn ".$this->exportArray($translatedArray).";\n";
                File::put($targetFilePath, $content);
            }
        }

        $this->info('Translation complete! Only new keys were translated.');

        return self::SUCCESS;
    }

    /**
     * @param  array<array-key, mixed>  $enArray
     * @param  array<array-key, mixed>  $existingArray
     * @return array<array-key, mixed>
     */
    private function translateArray(array $enArray, array $existingArray, GoogleTranslate $translator): array
    {
        $result = [];
        foreach ($enArray as $key => $value) {
            if (is_array($value)) {
                // Handle nested arrays (e.g., in validation.php)
                $existingSubArray = isset($existingArray[$key]) && is_array($existingArray[$key]) ? $existingArray[$key] : [];
                $result[$key] = $this->translateArray($value, $existingSubArray, $translator);
            } else {
                // If the key already exists in the target language, keep the existing translation
                if (isset($existingArray[$key]) && ! empty($existingArray[$key])) {
                    $result[$key] = $existingArray[$key];
                } else {
                    // It's a new key, translate it
                    $result[$key] = $this->translateString($value, $translator);
                }
            }
        }

        return $result;
    }

    private function translateString(string $text, GoogleTranslate $translator): string
    {
        if (empty(trim($text))) {
            return $text;
        }

        preg_match_all('/:[a-zA-Z0-9_]+/', $text, $matches);
        $variables = $matches[0];

        $maskedText = $text;
        foreach ($variables as $index => $variable) {
            $maskedText = str_replace($variable, "__VAR{$index}__", $maskedText);
        }

        try {
            $translatedText = $translator->translate($maskedText);

            // No translation came back: keep the English rather than writing nothing.
            if ($translatedText === null) {
                return $text;
            }
            // Optional: sleep for a fraction of a second to prevent rate-limiting from Google Translate
            usleep(200000);
        } catch (Exception $e) {
            $this->warn("Failed to translate: {$text}");

            return $text;
        }

        foreach ($variables as $index => $variable) {
            $translatedText = str_replace("__VAR{$index}__", $variable, $translatedText);
        }

        return $translatedText;
    }

    /**
     * @param  array<array-key, mixed>  $array
     */
    private function exportArray(array $array, int $indent = 1): string
    {
        $spaces = str_repeat('    ', $indent);
        $result = "[\n";
        foreach ($array as $key => $value) {
            $safeKey = $this->phpLiteral($key);
            if (is_array($value)) {
                $result .= "{$spaces}{$safeKey} => ".$this->exportArray($value, $indent + 1).",\n";
            } else {
                $safeValue = $this->phpLiteral($value);
                $result .= "{$spaces}{$safeKey} => {$safeValue},\n";
            }
        }
        $result .= str_repeat('    ', $indent - 1).']';

        return $result;
    }

    /**
     * A key or text as PHP source: strings single-quoted with their backslashes and quotes escaped.
     */
    private function phpLiteral(mixed $value): string
    {
        return match (true) {
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            default => "'".addcslashes((string) (is_scalar($value) ? $value : ''), "'\\")."'",
        };
    }
}
