<?php
namespace Crunch\FastCGI\Protocol;

use PHPUnit_Framework_TestCase as TestCase;

/**
 * @coversDefaultClass \Crunch\FastCGI\Protocol\Header
 * @covers \Crunch\FastCGI\Protocol\Header
 */
class HeaderTest extends TestCase
{
    public static function validConstructorArguments()
    {
        return [
            /* $type, $requestId, $length, $padding */
            [RecordType::instance(4), 12, 4, null],
            [RecordType::instance(4), 12, 2, 6],
            [RecordType::instance(4), 12, 32, 0],
        ];
    }

    /**
     * @covers ::__construct
     * @dataProvider validConstructorArguments
     * @param RecordType $type
     * @param int $requestId
     * @param int $length
     * @param int $paddingLength
     */
    public function testConstructKeepsValues(RecordType $type, $requestId, $length, $paddingLength)
    {
        $header = new Header($type, $requestId, $length, $paddingLength);

        self::assertSame($type, $header->getType());
        self::assertEquals($requestId, $header->getRequestId());
        self::assertEquals($length, $header->getLength());
        if ($paddingLength) {
            self::assertEquals($paddingLength, $header->getPaddingLength());
            self::assertEquals($length + $paddingLength, $header->getPayloadLength());
        }
        self::assertEquals(0, $header->getPayloadLength() % 8);
    }

    public static function invalidConstructorArguments()
    {
        return [
            /* $type, $requestId, $length, $padding */
            [RecordType::instance(4), 12, 4, 9],
            [RecordType::instance(4), 12, 4, 2],
        ];
    }

    /**
     * @covers ::__construct
     * @dataProvider invalidConstructorArguments
     * @param RecordType $type
     * @param int $requestId
     * @param int $length
     * @param int|null $padding
     */
    public function testInvalidConstructorArguments(RecordType $type, $requestId, $length, $padding)
    {
        $this->setExpectedException('\Assert\AssertionFailedException');

        new Header($type, $requestId, $length, $padding);
    }

    // TODO test invalid values (exception)

    public static function lengthAndPaddingProvider()
    {
        return [
            /* $length, $expectedPadding */
            [0, 0],
            [4, 4],
            [1, 7],
            [8, 0],
        ];
    }

    /**
     * @covers ::__construct
     * @dataProvider lengthAndPaddingProvider
     * @param int $length
     * @param int $expectedPadding
     */
    public function testCorrectPaddingCalculation($length, $expectedPadding)
    {
        $header = new Header(RecordType::instance(2), 3, $length);

        self::assertEquals($expectedPadding, $header->getPaddingLength());
    }

    public static function encodedHeaderProvider()
    {
        /*
         * First byte "version"
         * Second byte "type"
         * Byte 3 and 4 "request id"
         * Byte 5 and 6 "length"
         * Byte 7 "paddingLength"
         * Byte 8 "unused" (still required)
         */
        return [
            /* $header, $type, $requestId, $length, $paddingLength */
            ["\x01\x02\x00\x03\x00\x04\x04\x00", RecordType::instance(2), 3, 4, 4],
            ["\x01\x06\x00\x12\x00\x10\x00\x00", RecordType::instance(6), 18, 16, 0],
            ["\x01\x06\x00\x12\x1f\xa4\x04\x00", RecordType::instance(6), 18, 8100, 4],
        ];
    }

    /**
     * @covers ::decode
     * @dataProvider encodedHeaderProvider
     * @param string $headerString
     * @param RecordType $type
     * @param int $requestId
     * @param int $length
     * @param int $paddingLength
     */
    public function testDecodeHeader($headerString, RecordType $type, $requestId, $length, $paddingLength)
    {
        $header = Header::decode($headerString);

        self::assertSame($type, $header->getType());
        self::assertEquals($requestId, $header->getRequestId());
        self::assertEquals($length, $header->getLength());
        self::assertEquals($paddingLength, $header->getPaddingLength());
        self::assertEquals($length + $paddingLength, $header->getPayloadLength());
        self::assertEquals(0, $header->getPayloadLength() % 8);
    }

    /**
     * @covers ::encode
     * @dataProvider encodedHeaderProvider
     * @param string $headerString
     * @param RecordType $type
     * @param int $requestId
     * @param int $length
     * @param int $paddingLength
     */
    public function testEncodeHeader($headerString, RecordType $type, $requestId, $length, $paddingLength)
    {
        $header = new Header($type, $requestId, $length, $paddingLength);

        self::assertEquals($headerString, $header->encode());
    }

    public static function invalidHeaderStrings()
    {
        return [
            /* $headerString */

            // to short
            ["\x01"],
            ["\x01\x02"],
            ["\x01\x02\x00"],
            ["\x01\x02\x00\x03"],
            ["\x01\x02\x00\x03\x00"],
            ["\x01\x02\x00\x03\x00\x04"],
            ["\x01\x02\x00\x03\x00\x04\x05"],

            // to long
            ["\x01\x02\x00\x03\x00\x04\x04\x06\x07"],
            ["\x01\x02\x00\x03\x00\x04\x04\x06\x07\x08"],

            ["\x01\x02\x00\x03\x00\x04\x09\x00"], // Invalid padding
        ];
    }

    /**
     * @dataProvider invalidHeaderStrings
     * @param string $headerString
     */
    public function testInvalidHeaderStrings($headerString)
    {
        $this->setExpectedException('\Assert\AssertionFailedException');

        Header::decode($headerString);
    }
}