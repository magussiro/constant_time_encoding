<?php
namespace ParagonIE\ConstantTime;

/**
 * Class Base64
 * [A-Z][a-z][0-9]+/
 *
 * @package ParagonIE\ConstantTime
 */
abstract class Base64
{
    /**
     * Encode into Base64
     *
     * Base64 character set "[A-Z][a-z][0-9]+/"
     *
     * @param string $src
     * @return string
     */
    public static function encode($src)
    {
        $dest = '';
        $srcLen = Core::safeStrlen($src);
        for ($i = 0; $i + 3 <= $srcLen; $i += 3) {
            $chunk = \unpack('C*', Core::safeSubstr($src, $i, 3));
            $b0 = $chunk[1];
            $b1 = $chunk[2];
            $b2 = $chunk[3];

            $dest .=
                static::encode6Bits(               $b0 >> 2       ) .
                static::encode6Bits((($b0 << 4) | ($b1 >> 4)) & 63) .
                static::encode6Bits((($b1 << 2) | ($b2 >> 6)) & 63) .
                static::encode6Bits(  $b2                     & 63);
        }
        if ($i < $srcLen) {
            $chunk = \unpack('C*', Core::safeSubstr($src, $i, $srcLen - $i));
            $b0 = $chunk[1];
            if ($i + 1 < $srcLen) {
                $b1 = $chunk[2];
                $dest .=
                    static::encode6Bits(               $b0 >> 2       ) .
                    static::encode6Bits((($b0 << 4) | ($b1 >> 4)) & 63) .
                    static::encode6Bits( ($b1 << 2)               & 63) . '=';
            } else {
                $dest .=
                    static::encode6Bits( $b0 >> 2) .
                    static::encode6Bits(($b0 << 4) & 63) . '==';
            }
        }
        return $dest;
    }

    /**
     * decode from base64 into binary
     *
     * Base64 character set "./[A-Z][a-z][0-9]"
     *
     * @param string $src
     * @return string
     * @throws \RangeException
     */
    public static function decode($src)
    {
        // Remove padding
        $srcLen = Core::safeStrlen($src);
        if ($srcLen === 0) {
            return '';
        }
        if (($srcLen & 3) === 0) {
            if ($src[$srcLen - 1] === '=') {
                $srcLen--;
                if ($src[$srcLen - 1] === '=') {
                    $srcLen--;
                }
            }
        }
        if (($srcLen & 3) === 1) {
            return false;
        }

        $err = 0;
        $dest = '';
        for ($i = 0; $i + 4 <= $srcLen; $i += 4) {
            $chunk = \unpack('C*', Core::safeSubstr($src, $i, 4));
            $c0 = static::decode6Bits($chunk[1]);
            $c1 = static::decode6Bits($chunk[2]);
            $c2 = static::decode6Bits($chunk[3]);
            $c3 = static::decode6Bits($chunk[4]);

            $dest .= \pack(
                'CCC',
                ((($c0 << 2) | ($c1 >> 4)) & 0xff),
                ((($c1 << 4) | ($c2 >> 2)) & 0xff),
                ((($c2 << 6) |  $c3      ) & 0xff)
            );
            $err |= ($c0 | $c1 | $c2 | $c3) >> 8;
        }
        if ($i < $srcLen) {
            $chunk = \unpack('C*', Core::safeSubstr($src, $i, $srcLen - $i));
            $c0 = static::decode6Bits($chunk[1]);
            $c1 = static::decode6Bits($chunk[2]);

            if ($i + 2 < $srcLen) {
                $c2 = static::decode6Bits($chunk[3]);
                $dest .= \pack(
                    'CC',
                    ((($c0 << 2) | ($c1 >> 4)) & 0xff),
                    ((($c1 << 4) | ($c2 >> 2)) & 0xff)
                );
                $err |= ($c0 | $c1 | $c2) >> 8;
            } elseif($i + 1 < $srcLen) {
                $dest .= \pack(
                    'C',
                    ((($c0 << 2) | ($c1 >> 4)) & 0xff)
                );
                $err |= ($c0 | $c1) >> 8;
            }
        }
        if ($err !== 0) {
            throw new \RangeException(
                'base64decode() only expects characters in the correct base64 alphabet'
            );
        }
        return $dest;
    }

    /**
     *
     * Base64 character set:
     * [A-Z]      [a-z]      [0-9]      +     /
     * 0x41-0x5a, 0x61-0x7a, 0x30-0x39, 0x2b, 0x2f
     *
     * @param int $src
     * @return int
     */
    protected static function decode6Bits($src)
    {
        $ret = -1;

        // if ($src > 0x40 && $src < 0x5b) $ret += $src - 0x41 + 1; // -64
        $ret += (((0x40 - $src) & ($src - 0x5b)) >> 8) & ($src - 64);

        // if ($src > 0x60 && $src < 0x7b) $ret += $src - 0x61 + 26 + 1; // -70
        $ret += (((0x60 - $src) & ($src - 0x7b)) >> 8) & ($src - 70);

        // if ($src > 0x2f && $src < 0x3a) $ret += $src - 0x30 + 52 + 1; // 5
        $ret += (((0x2f - $src) & ($src - 0x3a)) >> 8) & ($src + 5);

        // if ($src == 0x2b) $ret += 62 + 1;
        $ret += (((0x2a - $src) & ($src - 0x2c)) >> 8) & 63;

        // if ($src == 0x2f) ret += 63 + 1;
        $ret += (((0x2e - $src) & ($src - 0x30)) >> 8) & 64;

        return $ret;
    }

    /**
     * @param int $src
     * @return string
     */
    protected static function encode6Bits($src)
    {
        $diff = 0x41;

        // if ($src > 25) $diff += 0x61 - 0x41 - 26; // 6
        $diff += ((25 - $src) >> 8) & 6;

        // if ($src > 51) $diff += 0x30 - 0x61 - 26; // -75
        $diff -= ((51 - $src) >> 8) & 75;

        // if ($src > 61) $diff += 0x2b - 0x30 - 10; // -15
        $diff -= ((61 - $src) >> 8) & 15;

        // if ($src > 62) $diff += 0x2f - 0x2b - 1; // 3
        $diff += ((62 - $src) >> 8) & 3;

        return \pack('C', $src + $diff);
    }
}
