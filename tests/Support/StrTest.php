<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/2/18
 * Time: 19:11.
 */

namespace HughCube\Laravel\Knight\Tests\Support;

use HughCube\Laravel\Knight\Support\Str;
use HughCube\Laravel\Knight\Tests\TestCase;

class StrTest extends TestCase
{
    public function testStripAllSpaces()
    {
        // null 输入返回 null
        $this->assertNull(Str::stripAllSpaces(null));

        // 空字符串保持空字符串
        $this->assertSame('', Str::stripAllSpaces(''));

        // 半角空格 (首尾 + 中间)
        $this->assertSame('张三', Str::stripAllSpaces('张 三'));
        $this->assertSame('张三', Str::stripAllSpaces(' 张三 '));
        $this->assertSame('张三', Str::stripAllSpaces('  张  三  '));

        // 全角空格 U+3000
        $this->assertSame('张三', Str::stripAllSpaces("张\u{3000}三"));
        $this->assertSame('张三', Str::stripAllSpaces("\u{3000}张三\u{3000}"));

        // NBSP U+00A0 (Word/网页复制常见)
        $this->assertSame('张三', Str::stripAllSpaces("张\u{00A0}三"));

        // ZWSP U+200B
        $this->assertSame('张三', Str::stripAllSpaces("张\u{200B}三"));

        // BOM / ZWNBSP U+FEFF
        $this->assertSame('张三', Str::stripAllSpaces("\u{FEFF}张三"));

        // 制表符/换行/回车
        $this->assertSame('张三', Str::stripAllSpaces("张\t三"));
        $this->assertSame('张三', Str::stripAllSpaces("张\n三"));
        $this->assertSame('张三', Str::stripAllSpaces("张\r三"));
        $this->assertSame('张三', Str::stripAllSpaces("张\r\n三"));

        // 其他 Unicode 细空格
        $this->assertSame('张三', Str::stripAllSpaces("张\u{202F}三")); // NARROW NO-BREAK SPACE
        $this->assertSame('张三', Str::stripAllSpaces("张\u{205F}三")); // MEDIUM MATHEMATICAL SPACE
        $this->assertSame('张三', Str::stripAllSpaces("张\u{2000}三")); // EN QUAD
        $this->assertSame('张三', Str::stripAllSpaces("张\u{2009}三")); // THIN SPACE
        $this->assertSame('张三', Str::stripAllSpaces("张\u{1680}三")); // OGHAM SPACE MARK
        $this->assertSame('张三', Str::stripAllSpaces("张\u{2060}三")); // WORD JOINER

        // 混合多种空白
        $this->assertSame('张三丰', Str::stripAllSpaces("张\u{3000}三\u{00A0}丰"));
        $this->assertSame('abc', Str::stripAllSpaces(" a \t b\nc\u{3000}"));

        // 纯空白字符串
        $this->assertSame('', Str::stripAllSpaces("   \t\n\u{3000}\u{00A0}"));

        // 不含空白的字符串保持不变
        $this->assertSame('张三', Str::stripAllSpaces('张三'));
        $this->assertSame('abc', Str::stripAllSpaces('abc'));

        // 保留 ZWNJ (U+200C) 和 ZWJ (U+200D) — 对波斯/印地语有语义
        $this->assertSame("می\u{200C}خواهم", Str::stripAllSpaces("می\u{200C}خواهم"));
        $this->assertSame("क\u{200D}ष", Str::stripAllSpaces("क\u{200D}ष"));

        // 企业名称场景
        $this->assertSame('某某某有限公司', Str::stripAllSpaces('某某某 有限公司'));
        $this->assertSame('某某某有限公司', Str::stripAllSpaces("某某某\u{3000}有限公司"));

        // SOFT HYPHEN U+00AD (0宽, 隐身字符)
        $this->assertSame('张三', Str::stripAllSpaces("张\u{00AD}三"));
        $this->assertSame('admin', Str::stripAllSpaces("ad\u{00AD}min"));

        // COMBINING GRAPHEME JOINER U+034F (0宽)
        $this->assertSame('张三', Str::stripAllSpaces("张\u{034F}三"));

        // MONGOLIAN VOWEL SEPARATOR U+180E (Unicode 6.3 起为 format)
        $this->assertSame('张三', Str::stripAllSpaces("张\u{180E}三"));

        // HANGUL FILLER U+3164 (视觉零宽, 常用于绕过过滤器)
        $this->assertSame('admin', Str::stripAllSpaces("ad\u{3164}min"));
        // 真·韩文姓名 (Hangul 字母 U+AC00-D7A3) 应完全保留
        $this->assertSame('김정일', Str::stripAllSpaces('김정일'));

        // BRAILLE PATTERN BLANK U+2800 (视觉零宽)
        $this->assertSame('admin', Str::stripAllSpaces("ad\u{2800}min"));
        // 其他盲文字符 (有实际点阵内容) 不受影响
        $this->assertSame("\u{2801}\u{28FF}", Str::stripAllSpaces("\u{2801}\u{28FF}"));

        // 变体选择符 U+FE0F 必须保留 (emoji 彩色变体依赖它)
        $this->assertSame("❤\u{FE0F}", Str::stripAllSpaces("❤\u{FE0F}"));

        // BIDI 控制字符必须保留 (阿拉伯/希伯来脚本依赖)
        $this->assertSame("\u{200E}abc", Str::stripAllSpaces("\u{200E}abc"));
        $this->assertSame("\u{202A}abc\u{202C}", Str::stripAllSpaces("\u{202A}abc\u{202C}"));

        // UTF-8 异常输入不抛异常, 且不会被静默清空
        $invalid = "\xff张 三";
        $result = Str::stripAllSpaces($invalid);
        $this->assertIsString($result);
        $this->assertNotSame('', $result);

        // 降级路径应清理 ASCII 范围内所有空白 (不仅是半角空格)
        $invalidWithTab = "\xff张\t三\n";
        $this->assertSame("\xff张三", Str::stripAllSpaces($invalidWithTab));
    }

    public function testTrimEdgeSpaces()
    {
        // null 输入返回 null
        $this->assertNull(Str::trimEdgeSpaces(null));

        // 空字符串保持空字符串
        $this->assertSame('', Str::trimEdgeSpaces(''));

        // 半角空格: 只去两端, 中间保留 (与 stripAllSpaces 的核心区别)
        $this->assertSame('张三', Str::trimEdgeSpaces(' 张三 '));
        $this->assertSame('张 三', Str::trimEdgeSpaces(' 张 三 '));
        $this->assertSame('张  三', Str::trimEdgeSpaces('  张  三  '));

        // 全角空格 U+3000: 两端去, 中间留
        $this->assertSame('张三', Str::trimEdgeSpaces("\u{3000}张三\u{3000}"));
        $this->assertSame("张\u{3000}三", Str::trimEdgeSpaces("\u{3000}张\u{3000}三\u{3000}"));

        // NBSP U+00A0 (Word/网页复制常见): 两端去, 中间留
        $this->assertSame('张三', Str::trimEdgeSpaces("\u{00A0}张三\u{00A0}"));
        $this->assertSame("张\u{00A0}三", Str::trimEdgeSpaces("\u{00A0}张\u{00A0}三\u{00A0}"));

        // ZWSP U+200B / BOM U+FEFF: 两端去, 中间留
        $this->assertSame('张三', Str::trimEdgeSpaces("\u{200B}张三\u{FEFF}"));
        $this->assertSame("张\u{200B}三", Str::trimEdgeSpaces("\u{FEFF}张\u{200B}三\u{200B}"));

        // 制表符/换行/回车在两端被清理
        $this->assertSame('张三', Str::trimEdgeSpaces("\t张三\n"));
        $this->assertSame('张三', Str::trimEdgeSpaces("\r\n张三\r\n"));
        $this->assertSame("张\t三", Str::trimEdgeSpaces("\n张\t三\n")); // 中间制表符保留

        // 混合多种不可见字符在两端
        $this->assertSame('张三', Str::trimEdgeSpaces("\u{3000}\u{00A0}\t张三\u{200B} \u{FEFF}"));

        // 纯空白字符串两端全部被剥离 -> 空串
        $this->assertSame('', Str::trimEdgeSpaces("   \t\n\u{3000}\u{00A0}"));

        // 不含两端空白的字符串保持不变
        $this->assertSame('张三', Str::trimEdgeSpaces('张三'));
        $this->assertSame('某某 科技', Str::trimEdgeSpaces('某某 科技'));

        // 企业/展示文本典型场景: 两端粘贴噪声去掉, 中间合法空格保留
        $this->assertSame('某某 科技有限公司', Str::trimEdgeSpaces("\u{3000}某某 科技有限公司\u{00A0}"));
        $this->assertSame('Senior Software Engineer', Str::trimEdgeSpaces(" Senior Software Engineer\u{00A0}"));

        // 保留 ZWNJ (U+200C) / ZWJ (U+200D) (不在白名单, 即使在两端也不动)
        $this->assertSame("\u{200C}میخواهم\u{200D}", Str::trimEdgeSpaces("\u{200C}میخواهم\u{200D}"));

        // 幂等: 已清洗值再清洗不变
        $this->assertSame('张三', Str::trimEdgeSpaces(Str::trimEdgeSpaces(' 张三 ')));

        // UTF-8 异常输入不抛异常, 降级路径只清两端 ASCII 空白, 中间保留
        $invalid = "\xff张 三\t";
        $result = Str::trimEdgeSpaces($invalid);
        $this->assertIsString($result);
        $this->assertNotSame('', $result);
        // 两端的 \t 被降级路径清掉, 中间半角空格保留
        $this->assertSame("\xff张 三", Str::trimEdgeSpaces("\t\xff张 三\t"));
    }

    public function testCountCommonChars()
    {
        $this->assertSame(Str::countCommonChars('我喜欢编程', '编程让我快乐'), 3);
        $this->assertSame(Str::countCommonChars('我喜欢编程', '编a程让我快乐'), 3);
        $this->assertSame(Str::countCommonChars('a喜欢编程', '编a程让我快乐'), 3);

        $this->assertSame(Str::countCommonChars('我喜欢编程', '编程让我快乐', true), 1);
        $this->assertSame(Str::countCommonChars('我喜欢编程', '程编让我快乐', true), 1);
        $this->assertSame(Str::countCommonChars('a喜欢编程我', '编a程让我快乐', true), 3);
    }

    public function testBase64Url()
    {
        for ($i = 1; $i <= 100; $i++) {
            $data = random_bytes($i * 100);

            $string = Str::base64UrlEncode($data);
            $this->assertSame($data, Str::base64UrlDecode($string));
        }
    }

    public function testIsChinese()
    {
        $this->assertTrue(!Str::isChinese('a'));
        $this->assertTrue(!Str::isChinese('张三a'));

        $this->assertTrue(Str::isChinese('张三'));
        $this->assertTrue(Str::isChinese('犇猋骉麤毳淼焱垚昍琰'));
    }

    public function testHasChinese()
    {
        $this->assertTrue(!Str::hasChinese('a'));
        $this->assertTrue(!Str::hasChinese('abcd1234ddg&)129'));

        $this->assertTrue(Str::hasChinese('张三a'));

        $this->assertTrue(Str::hasChinese('张三'));
        $this->assertTrue(Str::hasChinese('犇猋骉麤毳淼焱垚昍琰'));
    }

    public function testIsChineseName()
    {
        $this->assertTrue(!Str::isChineseName('a'));
        $this->assertTrue(!Str::isChineseName('abcd1234ddg&)129'));

        $this->assertTrue(!Str::isChineseName('张三a'));

        $this->assertTrue(!Str::isChineseName('张'));

        $this->assertTrue(Str::isChineseName('张三'));
        $this->assertTrue(Str::isChineseName('犇猋骉麤毳淼焱垚昍琰'));

        $this->assertFalse(Str::isChineseName('·犇猋骉麤毳淼焱垚昍琰'));
        $this->assertTrue(Str::isChineseName('犇猋骉麤毳·淼焱垚昍琰'));
        $this->assertTrue(!Str::isChineseName('犇猋骉麤毳淼焱垚昍琰·'));

        $this->assertFalse(Str::isChineseName('犇猋骉·麤毳·淼焱垚昍琰'));
    }

    public function testIsCnCarLicensePlate()
    {
        // 测试普通车牌（7位）
        $this->assertTrue(Str::isCnCarLicensePlate('粤B85XS6'));
        $this->assertTrue(Str::isCnCarLicensePlate('京A12345'));
        $this->assertTrue(Str::isCnCarLicensePlate('沪B23456'));
        $this->assertTrue(Str::isCnCarLicensePlate('川A12345'));
        $this->assertTrue(Str::isCnCarLicensePlate('宁B54321')); // 修复：南->宁

        // 测试新能源车牌（8位）
        $this->assertTrue(Str::isCnCarLicensePlate('京AD12345'));
        $this->assertTrue(Str::isCnCarLicensePlate('沪AF23456'));
        $this->assertTrue(Str::isCnCarLicensePlate('粤BD12345'));

        // 测试特殊车牌
        $this->assertTrue(Str::isCnCarLicensePlate('京A88888警')); // 警车
        $this->assertTrue(Str::isCnCarLicensePlate('京A12345学')); // 教练车
        $this->assertTrue(Str::isCnCarLicensePlate('京A12345使')); // 使馆车
        $this->assertTrue(Str::isCnCarLicensePlate('京A12345领')); // 领馆车
        $this->assertTrue(Str::isCnCarLicensePlate('粤Z12345港')); // 港澳车牌
        $this->assertTrue(Str::isCnCarLicensePlate('赣E12345挂')); // 挂车
        $this->assertTrue(Str::isCnCarLicensePlate('京A12345试')); // 试验车
        $this->assertTrue(Str::isCnCarLicensePlate('京A12345超')); // 超限车
        $this->assertTrue(Str::isCnCarLicensePlate('京X1234应急')); // 应急救援车

        // 测试武警车牌
        $this->assertTrue(Str::isCnCarLicensePlate('WJ京12345'));
        $this->assertTrue(Str::isCnCarLicensePlate('WJ12345'));
        $this->assertTrue(Str::isCnCarLicensePlate('WJ·12345'));

        // 测试军车车牌
        $this->assertTrue(Str::isCnCarLicensePlate('A12345'));
        $this->assertTrue(Str::isCnCarLicensePlate('AB12345'));

        // 测试各省份简称
        $this->assertTrue(Str::isCnCarLicensePlate('京A12345')); // 北京
        $this->assertTrue(Str::isCnCarLicensePlate('津A12345')); // 天津
        $this->assertTrue(Str::isCnCarLicensePlate('冀A12345')); // 河北
        $this->assertTrue(Str::isCnCarLicensePlate('晋A12345')); // 山西
        $this->assertTrue(Str::isCnCarLicensePlate('蒙A12345')); // 内蒙古
        $this->assertTrue(Str::isCnCarLicensePlate('辽A12345')); // 辽宁
        $this->assertTrue(Str::isCnCarLicensePlate('吉A12345')); // 吉林
        $this->assertTrue(Str::isCnCarLicensePlate('黑A12345')); // 黑龙江
        $this->assertTrue(Str::isCnCarLicensePlate('沪A12345')); // 上海
        $this->assertTrue(Str::isCnCarLicensePlate('苏A12345')); // 江苏
        $this->assertTrue(Str::isCnCarLicensePlate('浙A12345')); // 浙江
        $this->assertTrue(Str::isCnCarLicensePlate('皖A12345')); // 安徽
        $this->assertTrue(Str::isCnCarLicensePlate('闽A12345')); // 福建
        $this->assertTrue(Str::isCnCarLicensePlate('赣A12345')); // 江西
        $this->assertTrue(Str::isCnCarLicensePlate('鲁A12345')); // 山东
        $this->assertTrue(Str::isCnCarLicensePlate('豫A12345')); // 河南
        $this->assertTrue(Str::isCnCarLicensePlate('鄂A12345')); // 湖北
        $this->assertTrue(Str::isCnCarLicensePlate('湘A12345')); // 湖南
        $this->assertTrue(Str::isCnCarLicensePlate('粤A12345')); // 广东
        $this->assertTrue(Str::isCnCarLicensePlate('桂A12345')); // 广西
        $this->assertTrue(Str::isCnCarLicensePlate('琼A12345')); // 海南
        $this->assertTrue(Str::isCnCarLicensePlate('渝A12345')); // 重庆
        $this->assertTrue(Str::isCnCarLicensePlate('川A12345')); // 四川
        $this->assertTrue(Str::isCnCarLicensePlate('贵A12345')); // 贵州
        $this->assertTrue(Str::isCnCarLicensePlate('云A12345')); // 云南
        $this->assertTrue(Str::isCnCarLicensePlate('藏A12345')); // 西藏
        $this->assertTrue(Str::isCnCarLicensePlate('陕A12345')); // 陕西
        $this->assertTrue(Str::isCnCarLicensePlate('甘A12345')); // 甘肃
        $this->assertTrue(Str::isCnCarLicensePlate('青A12345')); // 青海
        $this->assertTrue(Str::isCnCarLicensePlate('宁A12345')); // 宁夏
        $this->assertTrue(Str::isCnCarLicensePlate('新A12345')); // 新疆

        // 测试无效车牌
        $this->assertFalse(Str::isCnCarLicensePlate('')); // 空字符串
        $this->assertFalse(Str::isCnCarLicensePlate('赣E1234')); // 太短
        $this->assertFalse(Str::isCnCarLicensePlate('京A1234')); // 太短
        $this->assertFalse(Str::isCnCarLicensePlate('京A12345678')); // 太长
        $this->assertFalse(Str::isCnCarLicensePlate('京I12345')); // 包含I
        $this->assertFalse(Str::isCnCarLicensePlate('京O12345')); // 包含O
        $this->assertFalse(Str::isCnCarLicensePlate('英A12345')); // 无效省份
        $this->assertFalse(Str::isCnCarLicensePlate('123456')); // 纯数字
        $this->assertFalse(Str::isCnCarLicensePlate('ABCDEF')); // 纯字母
        $this->assertFalse(Str::isCnCarLicensePlate('京a12345')); // 小写字母
        $this->assertFalse(Str::isCnCarLicensePlate(null)); // null值
        $this->assertFalse(Str::isCnCarLicensePlate(123)); // 数字类型
    }

    public function testCheckMobileAndMasking()
    {
        $this->assertTrue(Str::checkMobile('13800138000'));
        $this->assertFalse(Str::checkMobile('not-a-number'));
        $this->assertTrue(Str::checkMobile('12345', 1));

        $this->assertSame('138****8000', Str::maskMobile('13800138000'));
        $this->assertSame('123456********5678', Str::maskChinaIdCode('123456789012345678'));
    }

    public function testWhitespaceSplitAndOffsets()
    {
        $this->assertSame(['one', 'two', 'three'], Str::splitWhitespace("one\t two \nthree"));
        $this->assertSame('b', Str::offsetGet('abc', 1));
        $this->assertSame('', Str::offsetGet('abc', 9));
    }

    public function testStringChecks()
    {
        $this->assertTrue(Str::isUtf8(null));
        $this->assertTrue(Str::isUtf8('plain'));
        $this->assertFalse(Str::isUtf8("\xB1\x31"));

        $this->assertFalse(Str::isOctal('127'));
        $this->assertTrue(Str::isOctal('128'));

        $this->assertFalse(Str::isBinary('101'));
        $this->assertTrue(Str::isBinary('102'));

        $this->assertFalse(Str::isHex('abc123'));
        $this->assertTrue(Str::isHex('abc123z'));

        $this->assertTrue(Str::isAlnum('abc123'));
        $this->assertFalse(Str::isAlnum('abc-123'));

        $this->assertTrue(Str::isAlpha('abc'));
        $this->assertFalse(Str::isAlpha('abc1'));

        $this->assertTrue(Str::isNaming('name_1'));
        $this->assertFalse(Str::isNaming('1name'));

        $this->assertTrue(Str::isWhitespace("\n"));
        $this->assertFalse(Str::isWhitespace(' '));

        $this->assertTrue(Str::isDigit('123'));
        $this->assertFalse(Str::isDigit('12.3'));
    }

    public function testNetworkAndPortChecks()
    {
        $this->assertTrue(Str::isEmail('user@example.com'));
        $this->assertFalse(Str::isEmail('invalid'));

        $this->assertTrue(Str::isTel('010-12345678'));
        $this->assertFalse(Str::isTel('abc'));

        $this->assertTrue(Str::isIp('127.0.0.1'));
        $this->assertTrue(Str::isIp4('127.0.0.1'));
        $this->assertTrue(Str::isIp6('2001:db8::1'));
        $this->assertFalse(Str::isIp4('2001:db8::1'));

        $this->assertTrue(Str::isPrivateIp('192.168.1.1'));
        $this->assertFalse(Str::isPublicIp('192.168.1.1'));
        $this->assertTrue(Str::isPublicIp('8.8.8.8'));

        $this->assertTrue(Str::isPort('80'));
        $this->assertFalse(Str::isPort('0'));
        $this->assertFalse(Str::isPort('70000'));
    }

    public function testMiscStringUtilities()
    {
        $this->assertTrue(Str::isTrue(true));
        $this->assertTrue(Str::isTrue('true'));
        $this->assertFalse(Str::isTrue('false'));

        $this->assertSame('plain', Str::convEncoding('plain', 'utf-8', 'utf-8'));
        $this->assertSame('', Str::convEncoding('', 'utf-8', 'gbk'));
        $this->assertSame('123', Str::convEncoding('123', 'gbk', 'utf-8'));

        $this->assertSame('abc', Str::msubstr('abc', 0, 2));
        $this->assertSame('ab...', Str::msubstr('abcdefghij', 0, 2));

        $this->assertSame(3, Str::countWords('one two  three'));
        $this->assertSame('abc', Str::filterPartialUTF8('abc'));

        $this->assertSame(0, Str::versionCompare('1.2.3', '1.2.3'));
        $this->assertSame(-1, Str::versionCompare('1.2.3', '1.2.4'));
        $this->assertSame(0, Str::versionCompare('1.2.3', '1.2.4', null, 2));
        $this->assertSame(1, Str::versionCompare('1.2.3', '1.2.4', '<'));

        $this->assertSame(['ab', 'cd'], Str::mbSplit('abcd', 2));
        $this->assertSame(['a', 'b', 'c'], Str::mbSplit('abc'));

        $this->assertSame(3, Str::matchKeywordPrefix('hello world', 'help'));
        $this->assertSame(5, Str::matchKeywordSuffix('hello world', 'world'));
        $this->assertSame(5, Str::matchKeywordExact('hello world', 'world'));
        $this->assertSame(0, Str::matchKeywordExact('hello world', 'nope'));

        $this->assertTrue(Str::generalCiEq('Test', 'test'));
        $this->assertFalse(Str::generalCiEq('Test', 'toast'));
    }
}
