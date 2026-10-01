<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Providers;

/**
 * High-performance realistic Arabic and Saudi/MENA region data provider.
 * Supports realistic names, mobile numbers, Saudi IBANs, ZATCA tax numbers, and Arabic cities.
 */
class ArabicProvider
{
    private static array $firstNamesMale = [
        'محمد', 'أحمد', 'عبدالله', 'عمر', 'علي', 'خالد', 'سعد', 'سلطان', 'فيصل', 'فهد',
        'عبدالعزيز', 'سعود', 'تركي', 'بندر', 'ماجد', 'نايف', 'سالم', 'يوسف', 'إبراهيم', 'حمزة',
        'طارق', 'زياد', 'ريان', 'وليد', 'باسم', 'سامي', 'هشام', 'معاذ', 'عثمان', 'حاتم'
    ];

    private static array $firstNamesFemale = [
        'سارة', 'نورة', 'فاطمة', 'مريم', 'ريم', 'شهد', 'هند', 'العنود', 'منى', 'دلال',
        'مها', 'أمل', 'ليلى', 'أسماء', 'خلود', 'عبير', 'بسمة', 'روان', 'رغد', 'هيا',
        'لجين', 'جود', 'يارا', 'دانة', 'نجود', 'سلمى', 'وفاء', 'سمية', 'إيناس', 'جمانة'
    ];

    private static array $lastNames = [
        'القحطاني', 'العتيبي', 'الدوسري', 'الشهري', 'الحربي', 'الغامدي', 'الزهراني', 'المطيري', 'الشمراني', 'القرني',
        'العنزي', 'الشمري', 'الجهني', 'المالكي', 'السبيعي', 'الخالدي', 'التميمي', 'الرشيدي', 'العمري', 'الحازمي',
        'السهلي', 'العصيمي', 'الشريف', 'السليمان', 'الفيصل', 'الهاشمي', 'البقمي', 'الحارثي', 'الثبيتي', 'الصالح'
    ];

    private static array $cities = [
        'الرياض', 'جدة', 'الدمام', 'مكة المكرمة', 'المدينة المنورة', 'الخبر', 'الظهران', 'الأحساء', 'الطائف', 'أبها',
        'خميس مشيط', 'تبوك', 'بريدة', 'عنيزة', 'حائل', 'نجران', 'جازان', 'ينبع', 'الجبيل', 'عرعر'
    ];

    private static array $districts = [
        'الملقا', 'النرجس', 'الياسمين', 'حطين', 'الصحافة', 'العليا', 'السليمانية', 'الروضة', 'الملز', 'النسيم',
        'الحمراء', 'الشاطئ', 'الزهراء', 'الفيصلية', 'الخالدية', 'الدانة', 'الدوحة', 'الهدا', 'العزيزية', 'الربوة'
    ];

    private static array $streetTypes = [
        'شارع الملك فهد', 'طريق الملك عبدالعزيز', 'طريق الملك عبدالله', 'طريق الأمير محمد بن سلمان',
        'طريق الإمام سعود', 'شارع التخصصي', 'طريق الأمير سلطان', 'طريق مكة المكرمة', 'شارع الستين'
    ];

    public static function firstNameMale(): string
    {
        return self::$firstNamesMale[array_rand(self::$firstNamesMale)];
    }

    public static function firstNameFemale(): string
    {
        return self::$firstNamesFemale[array_rand(self::$firstNamesFemale)];
    }

    public static function firstName(): string
    {
        return mt_rand(0, 1) === 0 ? self::firstNameMale() : self::firstNameFemale();
    }

    public static function lastName(): string
    {
        return self::$lastNames[array_rand(self::$lastNames)];
    }

    public static function fullName(): string
    {
        return self::firstName() . ' ' . self::lastName();
    }

    public static function city(): string
    {
        return self::$cities[array_rand(self::$cities)];
    }

    public static function district(): string
    {
        return self::$districts[array_rand(self::$districts)];
    }

    public static function street(): string
    {
        return self::$streetTypes[array_rand(self::$streetTypes)];
    }

    public static function address(): string
    {
        $buildingNo = mt_rand(1000, 9999);
        return self::city() . '، حي ' . self::district() . '، ' . self::street() . '، مبنى ' . $buildingNo;
    }

    /**
     * Saudi Mobile Number (+966 5X XXX XXXX or 05XXXXXXXX)
     */
    public static function phoneNumber(bool $international = true): string
    {
        $prefixes = ['50', '53', '54', '55', '56', '58', '59'];
        $prefix = $prefixes[array_rand($prefixes)];
        $number = sprintf('%s%07d', $prefix, mt_rand(0, 9999999));

        return $international ? '+966' . $number : '0' . $number;
    }

    /**
     * Saudi ZATCA 15-digit Tax Identification Number (TIN).
     * Must start with 3 and end with 3.
     */
    public static function zatcaTaxNumber(): string
    {
        $middle = '';
        for ($i = 0; $i < 13; $i++) {
            $middle .= mt_rand(0, 9);
        }
        return '3' . $middle . '3';
    }

    /**
     * Saudi Bank IBAN (SA + 2 digits checksum + 2 digits bank code + 18 digits account)
     */
    public static function iban(): string
    {
        $bankCodes = ['10', '20', '05', '45', '55', '80'];
        $bankCode = $bankCodes[array_rand($bankCodes)];
        $checksum = sprintf('%02d', mt_rand(10, 98));

        $account = '';
        for ($i = 0; $i < 18; $i++) {
            $account .= mt_rand(0, 9);
        }

        return 'SA' . $checksum . $bankCode . $account;
    }

    /**
     * Saudi National ID / Iqama (10 digits starting with 1 for citizens or 2 for residents)
     */
    public static function nationalId(?int $type = null): string
    {
        $prefix = $type ?? (mt_rand(0, 1) === 0 ? 1 : 2);
        $rest = '';
        for ($i = 0; $i < 9; $i++) {
            $rest .= mt_rand(0, 9);
        }
        return (string) $prefix . $rest;
    }
}
