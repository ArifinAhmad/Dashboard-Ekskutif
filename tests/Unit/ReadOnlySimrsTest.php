<?php

namespace Tests\Unit;

use App\Services\Simrs\ReadOnlySimrs;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReadOnlySimrsTest extends TestCase
{
    #[DataProvider('writeStatements')]
    public function test_it_rejects_mutating_statements(string $sql): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ReadOnlySimrs)->select($sql);
    }

    public static function writeStatements(): array
    {
        return [
            ['UPDATE reg_periksa SET stts = ?'],
            ['DELETE FROM reg_periksa'],
            ['DROP TABLE pasien'],
            ['WITH changed AS (DELETE FROM pasien RETURNING *) SELECT * FROM changed'],
        ];
    }
}
