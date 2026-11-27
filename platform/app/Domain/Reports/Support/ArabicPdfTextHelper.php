<?php

declare(strict_types=1);

namespace App\Domain\Reports\Support;

/**
 * Lightweight Arabic glyph shaping & RTL visual helper for DomPDF (DejaVu Sans).
 */
final class ArabicPdfTextHelper
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> */
    private const GLYPH_FORMS = [
        'ء' => ['\u{FE80}', '\u{FE80}', '\u{FE80}', '\u{FE80}'],
        'آ' => ['\u{FE81}', '\u{FE82}', '\u{FE81}', '\u{FE82}'],
        'أ' => ['\u{FE83}', '\u{FE84}', '\u{FE83}', '\u{FE84}'],
        'ؤ' => ['\u{FE85}', '\u{FE86}', '\u{FE85}', '\u{FE86}'],
        'إ' => ['\u{FE87}', '\u{FE88}', '\u{FE87}', '\u{FE88}'],
        'ئ' => ['\u{FE89}', '\u{FE8A}', '\u{FE8B}', '\u{FE8C}'],
        'ا' => ['\u{FE8D}', '\u{FE8E}', '\u{FE8D}', '\u{FE8E}'],
        'ب' => ['\u{FE8F}', '\u{FE90}', '\u{FE91}', '\u{FE92}'],
        'ة' => ['\u{FE93}', '\u{FE94}', '\u{FE93}', '\u{FE94}'],
        'ت' => ['\u{FE95}', '\u{FE96}', '\u{FE97}', '\u{FE98}'],
        'ث' => ['\u{FE99}', '\u{FE9A}', '\u{FE9B}', '\u{FE9C}'],
        'ج' => ['\u{FE9D}', '\u{FE9E}', '\u{FE9F}', '\u{FEA0}'],
        'ح' => ['\u{FEA1}', '\u{FEA2}', '\u{FEA3}', '\u{FEA4}'],
        'خ' => ['\u{FEA5}', '\u{FEA6}', '\u{FEA7}', '\u{FEA8}'],
        'د' => ['\u{FEA9}', '\u{FEAA}', '\u{FEA9}', '\u{FEAA}'],
        'ذ' => ['\u{FEAB}', '\u{FEAC}', '\u{FEAB}', '\u{FEAC}'],
        'ر' => ['\u{FEAD}', '\u{FEAE}', '\u{FEAD}', '\u{FEAE}'],
        'ز' => ['\u{FEAF}', '\u{FEB0}', '\u{FEAF}', '\u{FEB0}'],
        'س' => ['\u{FEB1}', '\u{FEB2}', '\u{FEB3}', '\u{FEB4}'],
        'ش' => ['\u{FEB5}', '\u{FEB6}', '\u{FEB7}', '\u{FEB8}'],
        'ص' => ['\u{FEB9}', '\u{FEBA}', '\u{FEBB}', '\u{FEBC}'],
        'ض' => ['\u{FEBD}', '\u{FEBE}', '\u{FEBF}', '\u{FEC0}'],
        'ط' => ['\u{FEC1}', '\u{FEC2}', '\u{FEC3}', '\u{FEC4}'],
        'ظ' => ['\u{FEC5}', '\u{FEC6}', '\u{FEC7}', '\u{FEC8}'],
        'ع' => ['\u{FEC9}', '\u{FECA}', '\u{FECB}', '\u{FECC}'],
        'غ' => ['\u{FECD}', '\u{FECE}', '\u{FECF}', '\u{FED0}'],
        'ف' => ['\u{FED1}', '\u{FED2}', '\u{FED3}', '\u{FED4}'],
        'ق' => ['\u{FED5}', '\u{FED6}', '\u{FED7}', '\u{FED8}'],
        'ك' => ['\u{FED9}', '\u{FEDA}', '\u{FEDB}', '\u{FEDC}'],
        'ل' => ['\u{FEDD}', '\u{FEDE}', '\u{FEDF}', '\u{FEE0}'],
        'م' => ['\u{FEE1}', '\u{FEE2}', '\u{FEE3}', '\u{FEE4}'],
        'ن' => ['\u{FEE5}', '\u{FEE6}', '\u{FEE7}', '\u{FEE8}'],
        'ه' => ['\u{FEE9}', '\u{FEEA}', '\u{FEEB}', '\u{FEEC}'],
        'و' => ['\u{FEED}', '\u{FEEE}', '\u{FEED}', '\u{FEEE}'],
        'ى' => ['\u{FEEF}', '\u{FEF0}', '\u{FEEF}', '\u{FEF0}'],
        'ي' => ['\u{FEF1}', '\u{FEF2}', '\u{FEF3}', '\u{FEF4}'],
    ];

    /** @var list<string> */
    private const RIGHT_JOIN_ONLY = ['ء', 'آ', 'أ', 'ؤ', 'إ', 'ا', 'ة', 'د', 'ذ', 'ر', 'ز', 'و', 'ى'];

    public static function format(string $text): string
    {
        if (! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        // Convert Arabic words into shaped presentation forms & reverse Arabic runs for LTR PDF renderers
        $tokens = preg_split('/([^\p{Arabic}\s]+|\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if (! is_array($tokens)) {
            return $text;
        }

        $shapedTokens = array_map(function (string $token): string {
            if (! preg_match('/^\p{Arabic}+$/u', $token)) {
                return $token;
            }

            $chars = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY);
            if (! is_array($chars) || $chars === []) {
                return $token;
            }

            $count = count($chars);
            $out = [];
            for ($i = 0; $i < $count; $i++) {
                $ch = $chars[$i];
                if (! isset(self::GLYPH_FORMS[$ch])) {
                    $out[] = $ch;

                    continue;
                }

                $prev = $i > 0 ? $chars[$i - 1] : null;
                $next = $i + 1 < $count ? $chars[$i + 1] : null;

                $connectPrev = $prev !== null && isset(self::GLYPH_FORMS[$prev]) && ! in_array($prev, self::RIGHT_JOIN_ONLY, true);
                $connectNext = $next !== null && isset(self::GLYPH_FORMS[$next]) && ! in_array($ch, self::RIGHT_JOIN_ONLY, true);

                $idx = match (true) {
                    $connectPrev && $connectNext => 3, // medial
                    $connectPrev => 1,                  // final
                    $connectNext => 2,                  // initial
                    default => 0,                       // isolated
                };

                $unicodeEsc = self::GLYPH_FORMS[$ch][$idx];
                $out[] = json_decode('"'.$unicodeEsc.'"') ?: $ch;
            }

            return implode('', array_reverse($out));
        }, $tokens);

        return implode('', array_reverse($shapedTokens));
    }
}
