<?php

declare(strict_types=1);

namespace EidCloud\FakeData\Providers;

/**
 * Standard English & International data provider.
 */
class EnglishProvider
{
    private static array $firstNames = [
        'James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda',
        'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica',
        'Thomas', 'Sarah', 'Charles', 'Karen', 'Christopher', 'Nancy', 'Daniel', 'Lisa',
        'Matthew', 'Betty', 'Anthony', 'Margaret', 'Mark', 'Sandra', 'Donald', 'Ashley',
        'Steven', 'Kimberly', 'Paul', 'Emily', 'Andrew', 'Donna', 'Joshua', 'Michelle'
    ];

    private static array $lastNames = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
        'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
        'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson',
        'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker'
    ];

    private static array $domains = [
        'eidcloud.com', 'gmail.com', 'outlook.com', 'yahoo.com', 'example.org', 'cloudtech.io', 'company.com'
    ];

    private static array $cities = [
        'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'London', 'Toronto', 'Sydney', 'Berlin', 'Dubai'
    ];

    private static array $streets = [
        'Main St', 'High Street', 'Broadway', 'Oak Avenue', 'Maple Road', 'Cedar Lane', 'Park Boulevard'
    ];

    private static array $companySuffixes = ['Inc', 'LLC', 'Corp', 'Group', 'Solutions', 'Holdings', 'Technologies'];

    public static function firstName(): string
    {
        return self::$firstNames[array_rand(self::$firstNames)];
    }

    public static function lastName(): string
    {
        return self::$lastNames[array_rand(self::$lastNames)];
    }

    public static function fullName(): string
    {
        return self::firstName() . ' ' . self::lastName();
    }

    public static function email(?string $name = null): string
    {
        $prefix = $name !== null
            ? strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $name))
            : strtolower(self::firstName() . '.' . self::lastName());
        
        $domain = self::$domains[array_rand(self::$domains)];
        return $prefix . mt_rand(10, 9999) . '@' . $domain;
    }

    public static function phoneNumber(): string
    {
        return sprintf('+1-%03d-%03d-%04d', mt_rand(200, 999), mt_rand(100, 999), mt_rand(1000, 9999));
    }

    public static function company(): string
    {
        return self::lastName() . ' ' . self::$companySuffixes[array_rand(self::$companySuffixes)];
    }

    public static function city(): string
    {
        return self::$cities[array_rand(self::$cities)];
    }

    public static function address(): string
    {
        return mt_rand(100, 9999) . ' ' . self::$streets[array_rand(self::$streets)] . ', ' . self::city();
    }

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function text(int $words = 6): string
    {
        $vocabulary = [
            'cloud', 'database', 'relational', 'scalability', 'performance', 'latency', 'transaction',
            'enterprise', 'distributed', 'integrity', 'security', 'optimizer', 'architecture', 'pipeline',
            'analytics', 'stream', 'buffer', 'cluster', 'replication', 'storage', 'solution', 'infrastructure'
        ];
        $selected = [];
        for ($i = 0; $i < $words; $i++) {
            $selected[] = $vocabulary[array_rand($vocabulary)];
        }
        return ucfirst(implode(' ', $selected)) . '.';
    }
}
