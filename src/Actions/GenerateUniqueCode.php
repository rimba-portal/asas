<?php

declare(strict_types=1);

namespace Bites\Base\Actions;

class GenerateUniqueCode
{
    /**
     * Executes the action to generate a short alphanumeric code based on a phrase.
     *
     * @param  string  $phrase  The text to turn into a code.
     * @param  int  $length  How long the code should be (defaults to 3).
     * @param  callable|null  $isDuplicateCallback  A function that checks if the code is already taken.
     */
    public function execute(string $phrase, int $length = 3, ?callable $isDuplicateCallback = null): string
    {
        // 1. Clean the phrase (lowercase and remove spaces)
        $cleanPhrase = str_replace(' ', '', strtolower($phrase));

        // 2. Hash the phrase using MD5
        $hash = md5($cleanPhrase);

        // Alphanumeric alphabet: 0-9 and A-Z (36 options)
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $base = strlen($alphabet);

        // 3. Convert a segment of the hash into numbers.
        // We take a larger segment (up to 12 hex chars) to support longer code lengths comfortably.
        $hexSegment = substr($hash, 0, 12);
        $number = hexdec($hexSegment);

        // 4. Shrink the number down to the desired character length
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $remainder = $number % $base;
            $code .= $alphabet[$remainder];
            $number = intdiv($number, $base);
        }

        $finalCode = strrev($code);

        // 5. Database safety loop using the dynamic callback function
        $counter = 1;
        while ($isDuplicateCallback && $isDuplicateCallback($finalCode)) {
            $newHash = md5($cleanPhrase.$counter);
            $hexSegment = substr($newHash, 0, 12);
            $number = hexdec($hexSegment);

            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $remainder = $number % $base;
                $code .= $alphabet[$remainder];
                $number = intdiv($number, $base);
            }

            $finalCode = strrev($code);
            $counter++;
        }

        return $finalCode;
    }
}
