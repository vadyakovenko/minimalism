<?php

namespace CarloNicora\Minimalism\Tests\Unit\Services\ParameterValidator\Validators;

use CarloNicora\Minimalism\Services\ParameterValidator\Validators\DateTimeValidator;
use CarloNicora\Minimalism\Tests\Unit\AbstractTestCase;
use Exception;
use function date;

class DateTimeValidatorTest extends AbstractTestCase
{

    /**
     * @throws Exception
     */
    public function testSetParameter()
    {
        $dateValue = date('Y-m-d H:i:s');
//        $dateValueHoursMinutesSeconds = date('H:i:s');

        $instance = new DateTimeValidator($this->getServices());

        self::assertNull($instance->transformValue(null));
        self::assertIsString($instance->transformValue($dateValue));
        self::assertEquals($dateValue, $instance->transformValue($dateValue));

//        $this->expectException(Exception::class);
//        $instance->transformValue($dateValueHoursMinutesSeconds);
//
//        $this->assertEquals(
//            $dateValueHoursMinutesSeconds,
//            $instance->transformValue(strtotime($dateValueHoursMinutesSeconds))
//        );
    }
}
