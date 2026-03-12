<?php

namespace App\Ship\Tests\Unit\Supports;

use App\Ship\Supports\RequestIncludes;
use App\Ship\Tests\ShipTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RequestIncludes::class)]
final class RequestIncludesTest extends ShipTestCase
{
    public function testParseReturnsUniqueTrimmedIncludes(): void
    {
        $parsed = RequestIncludes::parse(' author, tags ,author,, options ');

        $this->assertSame(['author', 'tags', 'options'], $parsed);
    }

    public function testParseReturnsEmptyArrayForNullOrBlank(): void
    {
        $this->assertSame([], RequestIncludes::parse(null));
        $this->assertSame([], RequestIncludes::parse('   '));
    }

    public function testHasChecksExactIncludeName(): void
    {
        $include = 'author,customFields,options';

        $this->assertTrue(RequestIncludes::has($include, 'author'));
        $this->assertTrue(RequestIncludes::has($include, 'options'));
        $this->assertFalse(RequestIncludes::has($include, 'custom'));
        $this->assertFalse(RequestIncludes::has($include, ''));
    }
}

