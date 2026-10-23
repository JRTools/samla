<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Samla\Core\Web\StorageSizeInput;

final class StorageSizeInputTest extends TestCase
{
    public function testQuotaConversionUsesExactBytesAndRoundTripsExistingQuotas(): void
    {
        self::assertSame(100000000000,StorageSizeInput::parse('100','GB'));
        self::assertSame(1610612736,StorageSizeInput::parse('1,5','GiB'));
        self::assertSame(1,StorageSizeInput::parse('0.000001','MB'));
        self::assertSame(1099511627776,StorageSizeInput::parse('1024','GiB'));
        foreach([1,113,100000000000,10737418240,1099511627775,1099511627776] as$bytes){
            $size=StorageSizeInput::display($bytes);
            self::assertSame($bytes,StorageSizeInput::parse($size['value'],$size['unit']));
        }
    }

    public function testMalformedOversizedOrFractionalByteInputNeverRounds(): void
    {
        foreach([['0','GB'],['-1','GB'],['1e3','GB'],['1.0000001','GB'],['1.1','B'],['1.000001','GiB'],['1025','GiB'],['9999999999999','GB'],['1','TB'],['1,2.3','GB'],[' 1','GB']] as[$value,$unit]){
            try {StorageSizeInput::parse($value,$unit);self::fail('Invalid size accepted.');}
            catch(\InvalidArgumentException){self::assertTrue(true);}
        }
    }
}
