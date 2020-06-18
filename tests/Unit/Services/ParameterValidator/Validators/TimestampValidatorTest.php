<?php

namespace CarloNicora\Minimalism\Tests\Unit\Services\ParameterValidator\Validators;

use CarloNicora\Minimalism\Services\ParameterValidator\Validators\TimestampValidator;
use CarloNicora\Minimalism\Tests\Unit\AbstractTestCase;
use function date;

class TimestampValidatorTest extends AbstractTestCase
{

    public function testTransformValue()
    {
        $dateValue = date('Y-m-d H:i:s');
        $dateValueHoursMinutesSeconds = date('H:i:s');

        $instance = new TimestampValidator($this->getServices());

        $this->assertNull($instance->transformValue(null));
        $this->assertEquals(\strtotime($dateValue), $instance->transformValue($dateValue));

        $this->expectException(\Exception::class);
        $instance->transformValue($dateValueHoursMinutesSeconds);

        $this->assertEquals(
            \strtotime($dateValueHoursMinutesSeconds),
            $instance->transformValue(\strtotime($dateValueHoursMinutesSeconds))
        );
    }
}
