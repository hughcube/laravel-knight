<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2021/12/17
 * Time: 14:51.
 */

namespace HughCube\Laravel\Knight\Support;

class Str extends \Illuminate\Support\Str
{
    public static function getMobilePattern(): string
    {
        return '/^(13[0-9]|14[0-9]|15[0-9]|16[0-9]|17[0-9]|18[0-9]|19[0-9])\d{8}$/';
    }

    public static function checkMobile($mobile, $iddCode = null): bool
    {
        if (!is_string($mobile) && !ctype_digit(strval($mobile))) {
            return false;
        }

        if (86 == $iddCode || null == $iddCode) {
            return false != preg_match(static::getMobilePattern(), $mobile);
        }

        return true;
    }

    public static function maskMobile($string, $offset = 3, $length = 4): string
    {
        return substr_replace($string, '****', $offset, $length);
    }

    public static function maskChinaIdCode($string, $offset = 6, $length = 8): string
    {
        return substr_replace($string, '********', $offset, $length);
    }

    public static function splitWhitespace($string, int $limit = -1, int $flags = 0): array
    {
        return preg_split('/\s+/', $string, $limit, $flags) ?: [];
    }

    /**
     * 无语义空白/不可见字符的 PCRE 字符类内容(不含外层方括号与量词).
     *
     * stripAllSpaces(全去) 与 trimEdgeSpaces(仅去两端) 共用同一份白名单, 保证两档清洗口径完全一致 ——
     * 任何字符的增删只改这一处. 各字符的语义/收录理由见 {@see stripAllSpaces} 的"删除范围"说明.
     */
    private const INVISIBLE_CHARS_CLASS =
        '\x{0009}-\x{000D}'
        .'\x{0020}'
        .'\x{0085}'
        .'\x{00A0}'
        .'\x{00AD}'
        .'\x{034F}'
        .'\x{1680}'
        .'\x{180E}'
        .'\x{2000}-\x{200B}'
        .'\x{2028}\x{2029}'
        .'\x{202F}'
        .'\x{205F}'
        .'\x{2060}'
        .'\x{2800}'
        .'\x{3000}'
        .'\x{3164}'
        .'\x{FEFF}';

    /**
     * 去除字符串中所有无语义的空白/不可见字符.
     *
     * 删除范围(显式白名单):
     *   U+0009-U+000D  HT/LF/VT/FF/CR
     *   U+0020          半角空格
     *   U+0085          NEL
     *   U+00A0          NBSP
     *   U+00AD          SOFT HYPHEN (0宽, 常被用于钓鱼/绕过敏感词)
     *   U+034F          COMBINING GRAPHEME JOINER (0宽, 正常业务几乎不用)
     *   U+1680          OGHAM SPACE MARK
     *   U+180E          MONGOLIAN VOWEL SEPARATOR (Unicode 6.3 起为 format, 无视觉宽度)
     *   U+2000-U+200B   EN/EM QUAD..ZWSP (不含 ZWNJ U+200C, ZWJ U+200D)
     *   U+2028          LINE SEPARATOR
     *   U+2029          PARAGRAPH SEPARATOR
     *   U+202F          NARROW NO-BREAK SPACE
     *   U+205F          MEDIUM MATHEMATICAL SPACE
     *   U+2060          WORD JOINER
     *   U+2800          BRAILLE PATTERN BLANK (视觉零宽, 常用于绕过过滤器)
     *   U+3000          全角空格
     *   U+3164          HANGUL FILLER (视觉零宽, 常用于绕过过滤器; 合法韩文使用真·Hangul 字母)
     *   U+FEFF          BOM / ZWNBSP
     *
     * 保留: ZWNJ/ZWJ (印地/波斯/emoji 连字符), 双向控制字符, 阿拉伯格式字符, 变体选择符.
     *
     * 注意: 本方法只做"空白/不可见字符"剥离, 不做 Unicode 规范化.
     * 做姓名/邮箱等相等性比对前, 调用方应自行 Normalizer::normalize($s, Normalizer::FORM_C),
     * 否则 "é" (U+00E9) 与 "é" (U+0065 U+0301) 会被判为不等.
     *
     * @param string|null $value
     * @return string|null
     */
    public static function stripAllSpaces(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if ('' === $value) {
            return '';
        }

        $pattern = '/[' . self::INVISIBLE_CHARS_CLASS . ']+/u';

        $result = preg_replace($pattern, '', $value);

        // UTF-8 异常时 /u 正则会返回 null. 降级到字节级正则, 清理 ASCII 范围空白
        // (HT/LF/VT/FF/CR/SPACE 都是单字节, 不会出现在多字节序列中间, 按字节删除安全)
        return null === $result ? preg_replace('/[\x09-\x0D\x20]+/', '', $value) : $result;
    }

    /**
     * 去除字符串【两端】的无语义空白/不可见字符, 中间内容原样保留.
     *
     * 与 {@see stripAllSpaces} 共用同一份不可见字符白名单({@see INVISIBLE_CHARS_CLASS}), 区别仅在作用位置:
     *   - stripAllSpaces : 删除任意位置的不可见字符(用于证件号/手机/邮箱等"内部不该有空白"的标识/匹配字段)
     *   - trimEdgeSpaces : 只删首尾, 保留中间空格(用于公司名/职位/地址/标题等允许中间空格的展示文本)
     *
     * 典型场景: 用户从 Excel/Word 粘贴公司名 "某某 科技\u{00A0}" —— 两端的 NBSP/全角空格应清掉,
     * 但中间的半角空格是合法分词, 必须保留, 不能像 stripAllSpaces 那样连中间一起删成 "某某科技".
     *
     * 注意: 同 stripAllSpaces, 本方法不做 Unicode 规范化(NFC/NFKC).
     *
     * @param string|null $value
     * @return string|null  null/空串原样返回; 否则返回两端清洗后的字符串
     */
    public static function trimEdgeSpaces(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if ('' === $value) {
            return '';
        }

        $pattern = '/^[' . self::INVISIBLE_CHARS_CLASS . ']+|[' . self::INVISIBLE_CHARS_CLASS . ']+$/u';

        $result = preg_replace($pattern, '', $value);

        // UTF-8 异常时 /u 正则会返回 null. 降级到字节级正则, 仅清理两端的 ASCII 范围空白
        // (HT/LF/VT/FF/CR/SPACE 都是单字节, 出现在两端时按字节删除安全)
        return null === $result ? preg_replace('/^[\x09-\x0D\x20]+|[\x09-\x0D\x20]+$/', '', $value) : $result;
    }

    /**
     * 全角字符转半角(全角字母/数字/符号 → 半角, 全角空格 → 半角空格).
     *
     * 用于证件号/手机/邮箱等需要严格 === 匹配的字段: OCR / 中文输入法常产出全角数字字母
     * (如 "４１０５０４"), 不归一化会让 "４１０..." 与 "410..." 被判为不同, 实名/唯一性比对失败.
     * 依赖 mbstring 的 mb_convert_kana; 缺失时原样返回(降级不报错).
     *
     * @param string|null $value
     * @return string|null
     */
    public static function toHalfWidth(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        if (!function_exists('mb_convert_kana')) {
            return $value;
        }

        // 'a' 全角字母数字符号→半角; 's' 全角空格→半角空格
        $result = @mb_convert_kana($value, 'as', 'UTF-8');

        return is_string($result) ? $result : $value;
    }

    /**
     * 证件号(身份证/护照/港澳台证件等通用)写入清洗.
     *
     * 流水线: 去全部空白/不可见(stripAllSpaces) → 全角转半角 → 转大写(身份证尾位 x→X) → 去【前导】单引号.
     *   - 前导单引号: Excel 把证件号设成"数字单元格"时, 为防科学计数法(4.1E+17)会在值前加一个 ' .
     *   - 用 ltrim 只去头部的 ', 不会碰到尾部合法的校验位 X.
     *   - 与 stripAllSpaces 同源白名单, 兜住导入/OCR/手填所有写入路径, 保证 OCR 实名 === 比对一致.
     *
     * 注意: 本方法不校验证件号合法性, 只做规范化清洗; 也不做 Unicode NFC 归一化(证件号为纯 ASCII, 无需).
     *
     * @param string|null $value
     * @return string|null
     */
    public static function cleanIdCardNo(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        return ltrim(strtoupper(static::toHalfWidth(static::stripAllSpaces($value))), "'");
    }

    /**
     * 手机/电话号写入清洗: 去全部空白 → 全角转半角 → 只保留数字(去掉 - 、( 、) 、. 等分隔符).
     *
     * 用于参与 === 匹配 / 唯一性反查的号码字段; 国际区号请单独存(见调用方的 idd_code 字段).
     * "138-0000 0000" → "13800000000".
     *
     * @param string|null $value
     * @return string|null
     */
    public static function cleanMobile(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        $digits = preg_replace('/[^0-9]+/', '', static::toHalfWidth(static::stripAllSpaces($value)));

        // 极端情况 preg_replace 返回 null 时降级为去空白后的原值
        return null === $digits ? static::stripAllSpaces($value) : $digits;
    }

    /**
     * 邮箱写入清洗: 去全部空白/不可见 → 全角转半角 → 统一小写(邮箱实务上大小写不敏感).
     *
     * @param string|null $value
     * @return string|null
     */
    public static function cleanEmail(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        $normalized = static::toHalfWidth(static::stripAllSpaces($value));

        return function_exists('mb_strtolower') ? mb_strtolower($normalized, 'UTF-8') : strtolower($normalized);
    }

    /**
     * Excel 来源的编码类标识(工号/证书编号等)写入清洗: 去全部空白 → 全角转半角 → 去【前导】单引号.
     *
     * 与 cleanIdCardNo 的区别: 不转大写(编码可能区分大小写), 不只留数字(编码可能含字母).
     *
     * @param string|null $value
     * @return string|null
     */
    public static function cleanExcelCode(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return $value;
        }

        return ltrim(static::toHalfWidth(static::stripAllSpaces($value)), "'");
    }

    /**
     * 判断一个字符串的编码是否为UTF-8.
     */
    public static function isUtf8($string): bool
    {
        if (null === $string) {
            return true;
        }

        $json = @json_encode([$string]);

        return '[null]' !== $json && !empty($json);

        // $temp1 = @iconv("GBK", "UTF-8", $string);
        // $temp2 = @iconv("UTF-8", "GBK", $temp1);
        // return $temp1 == $temp2;

        // return preg_match('%^(?:
        //     [\x09\x0A\x0D\x20-\x7E]              # ASCII
        //     | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
        //     | \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
        //     | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
        //     | \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
        //     | \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
        //     | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
        //     | \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
        //     )*$%xs', $string);

        //return static::encoding($string, 'UTF-8');
    }

    /**
     * 判断一个字符串是否为八进制字符.
     */
    public static function isOctal($string): bool
    {
        return 0 < preg_match('/[^0-7]+/', $string);
    }

    /**
     * 判断一个字符串是否为二进制字符.
     */
    public static function isBinary($string): bool
    {
        return 0 < preg_match('/[^01]+/', $string);
    }

    /**
     * 判断一个字符串是否为十六进制字符.
     */
    public static function isHex($string): bool
    {
        return 0 < preg_match('/[^0-9a-f]+/i', $string);
    }

    /**
     * 判断一个字符串是否是数字和字母组成.
     */
    public static function isAlnum($string): bool
    {
        return ctype_alnum($string);
    }

    /**
     * 判断一个字符串是否是字母组成.
     */
    public static function isAlpha($string): bool
    {
        return ctype_alpha($string);
    }

    /**
     * 判断一个字符串是否是符合的命名规则.
     */
    public static function isNaming($string): bool
    {
        return 0 < preg_match('/^[a-z\_][a-z1-9\_]*/i', $string);
    }

    /**
     * 判断一个字符串是否为空白符,空格制表符回车等都被视作为空白符,类是\n\r\t;.
     */
    public static function isWhitespace($string): bool
    {
        return ctype_cntrl($string);
    }

    /**
     * 判断是否为整数.
     */
    public static function isDigit($string): bool
    {
        return is_numeric($string) && ctype_digit(strval($string));
    }

    /**
     * 判断是否是一个合法的邮箱.
     */
    public static function isEmail($string, bool $isStrict = false): bool
    {
        $result = false !== filter_var($string, FILTER_VALIDATE_EMAIL);

        if ($result && $isStrict && function_exists('getmxrr')) {
            list($prefix, $domain) = explode('@', $string);
            $result = getmxrr($domain, $mxhosts);
        }

        return $result;
    }

    /**
     * 判断是否是一个合法的固定电话号码;.
     */
    public static function isTel($string): bool
    {
        $pattern = '/^((\(\d{2,3}\))|(\d{3}\-))?(\(0\d{2,3}\)|0\d{2,3}-)?[1-9]\d{6,7}(\-\d{1,4})?$/';

        return 0 < preg_match($pattern, $string);
    }

    /**
     * 判断是否为一个合法的IP地址
     */
    public static function isIp($string): bool
    {
        return false !== filter_var($string, FILTER_VALIDATE_IP);
    }

    /**
     * 判断是否为一个合法的IPv4地址
     */
    public static function isIp4($string): bool
    {
        return false !== filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /**
     * 判断是否为一个合法的IPv6地址
     */
    public static function isIp6($string): bool
    {
        return false !== filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * 判断是否是内网ip.
     */
    public static function isPrivateIp($string): bool
    {
        return false === filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)
            && false !== filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * 判断是否是外网ip.
     */
    public static function isPublicIp($string): bool
    {
        return !static::isPrivateIp($string);
    }

    /**
     * 是否合法的端口.
     */
    public static function isPort($string): bool
    {
        return is_numeric($string)
            && ctype_digit(strval($string))
            && 1 <= $string
            && $string <= 65535;
    }

    /**
     * 判断是否是真值
     */
    public static function isTrue($string): bool
    {
        if (is_bool($string) && $string) {
            return true;
        }

        if (true === filter_var($string, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return false;
    }

    /**
     * 判断是否中文名字.
     */
    public static function isChineseName($string): bool
    {
        if (str_starts_with($string, '·') || str_ends_with($string, '·')) {
            return false;
        }

        if (substr_count($string, '·') > 1) {
            return false;
        }

        return 0 < preg_match('/^(?=.{2,12}$)[\p{Han}]+(?:\x{00B7}[\p{Han}]+)?$/u', $string);
    }

    /**
     * 是否含有中文.
     */
    public static function hasChinese($string): bool
    {
        return 0 < preg_match('/\p{Han}/u', $string);
    }

    /**
     * 是否中文.
     */
    public static function isChinese($string): bool
    {
        return 0 < preg_match('/^\p{Han}+$/u', $string);
    }

    /**
     * 是否是车牌号.
     */
    public static function isCnCarLicensePlate($string): bool
    {
        if (!is_string($string) || empty($string)) {
            return false;
        }

        $patterns = [
            // 普通车牌格式：省份简称 + 地区代码 + 5位数字字母组合
            '/^[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼渝川贵云藏陕甘青宁新][A-HJ-NP-Z][0-9A-Z]{5}$/u',

            // 新能源车牌格式：省份简称 + 地区代码 + 6位数字字母组合
            '/^[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼渝川贵云藏陕甘青宁新][A-HJ-NP-Z][0-9A-Z]{6}$/u',

            // 特殊车牌格式：包含警、学、使、领、港、澳、挂、试、超等特殊标识
            '/^[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼渝川贵云藏陕甘青宁新][A-HJ-NP-Z].*[警学使领港澳挂试超].*$/u',

            // 应急救援车牌格式
            '/^[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼渝川贵云藏陕甘青宁新][A-HJ-NP-Z].*应急$/u',

            // 武警车牌格式：WJ + 地区 + 数字
            '/^WJ[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼渝川贵云藏陕甘青宁新]?[·\-]?[0-9A-Z]{4,5}[A-Z]?$/u',

            // 武警总部车牌格式
            '/^WJ[·\-]?[0-9]{4,5}$/u',

            // 军车车牌格式
            '/^[A-Z]{1,2}[0-9]{4,5}[A-Z]?$/',

            // 临时车牌格式：包含"临时"标识
            '/^[京津冀晋蒙辽吉黑沪苏浙皖闽赣鲁豫鄂湘粤桂琼渝川贵云藏陕甘青宁新][A-HJ-NP-Z].*临时.*$/u',
        ];

        // 循环检查各种格式
        foreach ($patterns as $pattern) {
            if (1 === preg_match($pattern, $string)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 改变字符的编码
     */
    public static function convEncoding($contents, $from = 'gbk', $to = 'utf-8'): string
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        $from = $from == 'UTF8' ? 'utf-8' : $from;
        $to = $to == 'UTF8' ? 'utf-8' : $to;

        if ($from === $to || empty($contents) || (is_scalar($contents) && !is_string($contents))) {
            return $contents;
        }

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($contents, $to, $from);
        } else {
            return iconv($from, $to, $contents);
        }
    }

    /**
     * 函数msubstr,实现中文截取字符串;.
     */
    public static function msubstr($str, $start = 0, $length = null, $suffix = '...', $charset = 'utf-8'): string
    {
        $length = null === $length ? strlen($str) : $length;
        $charLen = in_array($charset, ['utf-8', 'UTF8']) ? 3 : 2;

        // 小于指定长度，直接返回
        if (strlen($str) <= ($length * $charLen)) {
            return $str;
        }

        if (function_exists('mb_substr')) {
            $slice = mb_substr($str, $start, $length, $charset);
        } elseif (function_exists('iconv_substr')) {
            $slice = iconv_substr($str, $start, $length, $charset);
        } else {
            // @codingStandardsIgnoreStart
            $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
            $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
            $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
            $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
            // @codingStandardsIgnoreEnd

            preg_match_all($re[$charset], $str, $match);
            $slice = join('', array_slice($match[0], $start, $length));
        }

        return $slice.$suffix;
    }

    /**
     * 统计单词数.
     */
    public static function countWords($string): int
    {
        return count(preg_split('/\s+/u', $string, -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * 获取指定位置的字符, 不能支持负数位置.
     */
    public static function offsetGet($string, $index): ?string
    {
        $char = substr($string, $index, 1);

        return is_string($char) ? $char : '';
    }

    /**
     * 过滤不完整的UTF8字符，UTF8的合法字符范围为：.
     *
     * 一字节字符：0x00-0x7F
     * 二字节字符：0xC0-0xDF 0x80-0xBF
     * 三字节字符：0xE0-0xEF 0x80-0xBF 0x80-0xBF
     * 四字节字符：0xF0-0xF7 0x80-0xBF 0x80-0xBF 0x80-0xBF
     */
    public static function filterPartialUTF8($string): string
    {
        // @codingStandardsIgnoreStart
        $string = preg_replace('/[\\xC0-\\xDF](?=[\\x00-\\x7F\\xC0-\\xDF\\xE0-\\xEF\\xF0-\\xF7]|$)/', '', $string);
        $string = preg_replace('/[\\xE0-\\xEF][\\x80-\\xBF]{0,1}(?=[\\x00-\\x7F\\xC0-\\xDF\\xE0-\\xEF\\xF0-\\xF7]|$)/', '', $string);
        $string = preg_replace('/[\\xF0-\\xF7][\\x80-\\xBF]{0,2}(?=[\\x00-\\x7F\\xC0-\\xDF\\xE0-\\xEF\\xF0-\\xF7]|$)/', '', $string);
        // @codingStandardsIgnoreEnd

        return strval($string);
    }

    /**
     * 比较两个版本的大小
     * 0: 两个版本相等
     * 1: $a > $b
     * 2: $b < $a
     * <、 lt、<=、 le、>、 gt、>=、 ge、==、 =、eq、 !=、<> 和 ne。
     */
    public static function versionCompare(
        string $a,
        string $b,
        ?string $operator = null,
        ?int $compareDepth = null
    ): int {
        /**
         * 分割成数组.
         */
        $a = explode('.', $a);
        $b = explode('.', $b);

        /**
         * 确定最大比较的深度.
         */
        $maxDepth = max(count($a), count($b));
        $maxDepth = (null != $compareDepth && $maxDepth > $compareDepth) ? $compareDepth : $maxDepth;

        /**
         * 补全长度, 防止 1.0.1 < 1.0.1.0 的情况.
         */
        $a = array_pad($a, $maxDepth, '0');
        $b = array_pad($b, $maxDepth, '0');

        /**
         * 截取长度, 只比较指定深度.
         */
        $a = array_slice($a, 0, $maxDepth);
        $b = array_slice($b, 0, $maxDepth);

        /**
         * 重新拼接成字符串.
         */
        $a = implode('.', $a);
        $b = implode('.', $b);

        return null === $operator ? version_compare($a, $b) : version_compare($a, $b, $operator);
    }

    public static function mbSplit(string $string, int $length = 1, ?string $encoding = null): array
    {
        $strlen = mb_strlen($string);
        $encoding = $encoding ?? 'UTF-8';

        $array = [];
        while ($strlen > 0) {
            $array[] = mb_substr($string, 0, $length, $encoding);
            $string = mb_substr($string, $length, $strlen, $encoding);
            $strlen = mb_strlen($string);
        }

        return $array;
    }

    /**
     * 计算两个字符串的相同的字符.
     */
    public static function countCommonChars(string $a, string $b, bool $inOrder = false): int
    {
        $aChars = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY);
        $bChars = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY);

        if (!$inOrder) {
            return count(array_intersect($aChars, $bChars));
        }

        $aCount = count($aChars);
        $bCount = count($bChars);

        $count = 0;

        $lastIndex = 0;
        for ($aIndex = 0; $aIndex < $aCount; $aIndex++) {
            for ($bIndex = $lastIndex; $bIndex < $bCount; $bIndex++) {
                if ($aChars[$aIndex] === $bChars[$bIndex]) {
                    $count++;
                    $lastIndex = $bIndex;
                    break;
                }
            }
        }

        return $count;
    }

    public static function matchKeywordPrefix($text, $keyword): int
    {
        $match_length = 0;
        $keyword_length = mb_strlen($keyword);
        while (true) {
            if ($match_length >= $keyword_length
                || false === mb_strpos($text, mb_substr($keyword, 0, $match_length + 1))
            ) {
                break;
            }
            $match_length++;
        }

        return $match_length;
    }

    public static function matchKeywordSuffix($text, $keyword): int
    {
        $match_length = 0;
        $keyword_length = mb_strlen($keyword);
        while (true) {
            if ($match_length >= $keyword_length
                || false === mb_strpos($text, mb_substr($keyword, 0 - ($match_length + 1)))
            ) {
                break;
            }
            $match_length++;
        }

        return $match_length;
    }

    public static function matchKeywordExact($text, $keyword): int
    {
        return false === mb_strpos($text, $keyword) ? 0 : mb_strlen($keyword);
    }

    public static function base64UrlEncode($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode($data): string
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }

    public static function generalCiEq($a, $b, $model = 'rnask'): bool
    {
        return strtolower(mb_convert_kana($a, $model, 'UTF-8'))
            == strtolower(mb_convert_kana($b, $model, 'UTF-8'));
    }

    /**
     * 邮箱脱敏: u**r@example.com.
     */
    public static function maskEmail($string): string
    {
        if (!is_string($string) || empty($string)) {
            return '';
        }

        $atPos = strpos($string, '@');
        if (false === $atPos) {
            return $string;
        }

        $local = substr($string, 0, $atPos);
        $domain = substr($string, $atPos);
        $localLen = strlen($local);

        if ($localLen <= 1) {
            return '*'.$domain;
        }

        if ($localLen <= 2) {
            return $local[0].'*'.$domain;
        }

        return $local[0].str_repeat('*', $localLen - 2).$local[$localLen - 1].$domain;
    }

    /**
     * 银行卡脱敏: 6222 **** **** 1234.
     */
    public static function maskBankCard($string): string
    {
        if (!is_string($string) || empty($string)) {
            return '';
        }

        $len = strlen($string);
        if ($len <= 8) {
            return $string;
        }

        return substr($string, 0, 4).str_repeat('*', $len - 8).substr($string, -4);
    }

    /**
     * 中文姓名脱敏: 张*、张*丰.
     */
    public static function maskName($string): string
    {
        if (!is_string($string) || empty($string)) {
            return '';
        }

        $len = mb_strlen($string, 'UTF-8');
        if ($len <= 1) {
            return '*';
        }

        if ($len == 2) {
            return mb_substr($string, 0, 1, 'UTF-8').'*';
        }

        return mb_substr($string, 0, 1, 'UTF-8')
            .str_repeat('*', $len - 2)
            .mb_substr($string, -1, 1, 'UTF-8');
    }

    /**
     * 地址脱敏: 保留前N个字符，其余用*替换.
     */
    public static function maskAddress($string, int $keepLength = 6): string
    {
        if (!is_string($string) || empty($string)) {
            return '';
        }

        $len = mb_strlen($string, 'UTF-8');
        if ($len <= $keepLength) {
            return $string;
        }

        return mb_substr($string, 0, $keepLength, 'UTF-8').str_repeat('*', $len - $keepLength);
    }

    /**
     * 车牌号脱敏: 京A****8.
     */
    public static function maskPlateNumber($string): string
    {
        if (!is_string($string) || empty($string)) {
            return '';
        }

        $len = mb_strlen($string, 'UTF-8');
        if ($len <= 2) {
            return $string;
        }

        return mb_substr($string, 0, 2, 'UTF-8')
            .str_repeat('*', $len - 3)
            .mb_substr($string, -1, 1, 'UTF-8');
    }
}
