<?php declare(strict_types=1);

/*
 * This file is part of the VV package.
 *
 * (c) Volodymyr Sarnytskyi <v00v4n@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace VV\Utils;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \VV\Utils\ArrayReflect
 */
class ArrayReflectTest extends TestCase {

    /**
     * @covers ::__construct
     */
    public function testConstruct() {
        $testVals = [null, false, true, 0, 1, '', 'foo', new \stdClass];
        foreach ($testVals as $k => $v) {
            new ArrayReflect($v);
            $this->assertSame([], $v, "key: $k");
        }

        $origarr = $arr = ['foo', 5, false, true, 'key' => 'val'];
        new ArrayReflect($arr);
        $this->assertSame($origarr, $arr);
    }

    /**
     * @covers ::cast
     * @covers ::__construct
     * @covers ::get
     */
    public function testGet() {
        $arr = self::baseTestArray();
        $reflect = ArrayReflect::cast($arr);

        $this->assertEquals($reflect->get(), $arr);
        foreach ($arr as $k => $v) {
            $this->assertEquals($v, $reflect->get($k, null, $found), "$k");
            $this->assertTrue($found, "$k");
        }
        $this->assertNull($reflect->get('unknown'));
        $this->assertSame(false, $reflect->get('unknown', false, $found));
        $this->assertFalse($found);

        $this->assertSame(
            ['val1', 'val2', 3, true, null, 'i1', 'i0'],
            $reflect->get(['key1', 'key2', 'key3', 'key4', 'key5', 1, 0], null, $found)
        );
        $this->assertTrue($found);

        $this->assertSame(
            ['val1', null, null],
            $reflect->get(['key1', 'unknown', 'unknown2'], null, $found)
        );
        $this->assertFalse($found);

        $this->assertSame(
            [
                'val1',
                $arr['node1'],
                [
                    'i2-1',
                    'i2-0',
                    22,
                    'val2-3',
                    [
                        213,
                        'val2-1-2',
                        true,
                    ],
                ],
            ],
            $reflect->get([
                'key1',
                'node1',
                'node2' => [
                    1,
                    0,
                    'key2-2',
                    'key2-3',
                    'node2-1' => [
                        'key2-1-3',
                        'key2-1-2',
                        'key2-1-1',
                    ],
                ],
            ])
        );
    }

    /**
     * @covers ::cast
     * @covers ::__construct
     * @covers ::aget
     */
    public function testAget() {
        $arr = self::baseTestArray();
        $reflect = ArrayReflect::cast($arr);

        $this->assertSame(
            [
                'key1' => 'val1',
                'key2' => 'val2',
                'key3' => 3,
                'keyNone' => null,
            ],
            $reflect->aget(['key1', 'key2', 'key3', 'keyNone'])
        );

        $this->assertSame(
            [
                'key1' => 'val1',
                'keyNone' => 0,
            ],
            $reflect->aget(['key1', 'keyNone'], 0)
        );

        $this->assertSame(
            [
                'key1' => 'val1',
                'keyNone' => 0,
                'keyNone1' => 1,
                'keyNone2' => 3,
            ],
            $reflect->aget(['key1', 'keyNone', 'keyNone1', 'keyNone2'],
                [
                    'keyNone' => 0,
                ],
                [
                    'keyNone1' => 1,
                    'keyNone2' => 3,
                ]
            )
        );
    }

    /**
     * @covers ::xget
     * @covers ::cast
     * @covers ::__construct
     * @covers ::createLikeThis
     * @covers ::get
     * @covers ::setGetterTypeExceptionClass
     */
    public function testXget() {
        $arr = self::baseTestArray();
        $reflect = ArrayReflect::cast($arr);

        $this->assertSame('val2', $reflect->xget('key2'));
        $this->assertSame(3, $reflect->xget('key3'));
        $this->assertSame('val2-1-2', $reflect->xget('node2', 'node2-1', 'key2-1-2'));
        $this->assertSame(
            ['val2-1-2', 'i2-1-1', 'i2-1-0', 213],
            $reflect->xget('node2', 'node2-1', ['key2-1-2', 1, 0, 'key2-1-3'])
        );

        $this->assertSame(
            ['val2-1-2', null, 213, null],
            $reflect->xget('node2', 'node2-1', ['key2-1-2', 10, 'key2-1-3', 'qwe'])
        );


        $this->assertNull($reflect->xget('key1', 'subkey1'));
        $this->assertSame(
            [null, null, null],
            $reflect->xget('key1', 'subkey1', ['123', '456', 'asdf'])
        );
    }

    /**
     * @covers ::has
     * @covers ::cast
     * @covers ::__construct
     */
    public function testHas() {
        $arr = self::baseTestArray();
        $reflect = ArrayReflect::cast($arr);

        $this->assertTrue($reflect->has('val1'));
        $this->assertFalse($reflect->has('valNone'));

        $this->assertTrue($reflect->has(true));
        $this->assertFalse($reflect->has(false));

        $this->assertTrue($reflect->has($arr['node1']));
        $this->assertFalse($reflect->has(['none']));

        $this->assertTrue($reflect->has('val2', 3, true, null, 'i1'));
        $this->assertFalse($reflect->has('val2', 3, false, null, 'i1'));
    }

    /**
     * @covers ::hasKey
     * @covers ::cast
     * @covers ::__construct
     */
    public function testHasKey() {
        $arr = self::baseTestArray();
        $reflect = ArrayReflect::cast($arr);

        $this->assertTrue($reflect->hasKey('key1'));
        $this->assertFalse($reflect->hasKey('keyNone'));

        $this->assertTrue($reflect->hasKey(0));
        $this->assertFalse($reflect->hasKey(10));

        $this->assertTrue($reflect->hasKey('key1', 'key2', 'node2'));
        $this->assertFalse($reflect->hasKey('key1', 'keyNone', 'node2'));
    }

    /**
     * @covers ::__construct
     * @covers ::ref
     */
    public function testRef() {
        $arr = [];
        $reflect = new ArrayReflect($arr);


        $var = &$reflect->ref('qwe');
        $var = 'rty';
        $this->assertTrue(isset($arr['qwe']));
        $this->assertSame('rty', $arr['qwe']);


        $var = &$reflect->ref('k1', 'k2', 'k3');
        $this->assertArrayHasKey('k1', $arr);
        $this->assertArrayHasKey('k2', $arr['k1']);

        $var = 3;
        $this->assertTrue(isset($arr['k1']['k2']['k3']));
        $this->assertSame(3, $arr['k1']['k2']['k3']);
    }

    /**
     * @covers ::set
     * @covers ::__construct
     * @covers ::get
     */
    public function testSet() {
        $arr = [];
        $reflect = new ArrayReflect($arr);

        $reflect->set('qwe', 'rty');
        $this->assertTrue(isset($arr['qwe']));
        $this->assertSame('rty', $arr['qwe']);
        $this->assertSame('rty', $reflect->get('qwe'));

        $reflect->set(['qwe', 'rty']);
        $this->assertSame(['qwe', 'rty'], $arr);
        $this->assertSame(['qwe', 'rty'], $reflect->get());
    }

    /**
     * @covers ::__construct
     * @covers ::iref
     * @covers ::set
     * @covers ::ref
     */
    public function testIref() {
        $arr = [];
        $reflect = new ArrayReflect($arr);

        $sub = $reflect->iref('k1', 'k2');
        $this->assertTrue(isset($arr['k1']['k2']));
        $this->assertSame([], $arr['k1']['k2']);

        $sub->set('k3', 'v3');
        $this->assertSame('v3', $arr['k1']['k2']['k3']);
    }

    public function typeDataProvider(): array {
        return [
            [null, ['scalar', 'string', 'int', 'float', 'bool', 'array', 'arrayReflect']],

            ['sdvfgbhn', ['scalar', 'string']],
            [123456, ['scalar', 'string', 'int', 'float']],
            ['1', ['scalar', 'string', 'int', 'float', 'bool']],
            ['0', ['scalar', 'string', 'int', 'float', 'bool']],
            ['', ['scalar', 'string', 'bool']],

            [true, ['scalar', 'bool']],
            [false, ['scalar', 'bool']],
            [[], ['array', 'arrayReflect']],
            [[123, 'qwe', 'rty' => 'asd'], ['array', 'arrayReflect']],
            [new \stdClass, []],
            [STDOUT, []],
        ];
    }

    /**
     * @dataProvider typeDataProvider
     *
     * @param $val
     * @param $availableMethods
     *
     * @covers ::scalar
     * @covers ::cast
     * @covers ::__construct
     * @covers ::get
     * @covers ::createGetterTypeException
     */
    public function testScalar($val, array $availableMethods) {
        $this->runTypeTest($val, $availableMethods, 'scalar');
    }

    /**
     * @dataProvider typeDataProvider
     *
     * @param $val
     * @param $availableMethods
     *
     * @covers ::string
     * @covers ::cast
     * @covers ::__construct
     * @covers ::get
     * @covers ::scalar
     * @covers ::createGetterTypeException
     */
    public function testString($val, array $availableMethods) {
        $this->runTypeTest($val, $availableMethods, 'string');
    }

    /**
     * @dataProvider typeDataProvider
     *
     * @param $val
     * @param $availableMethods
     *
     * @covers ::int
     * @covers ::cast
     * @covers ::__construct
     * @covers ::get
     * @covers ::scalar
     * @covers ::createGetterTypeException
     */
    public function testInt($val, array $availableMethods) {
        $this->runTypeTest($val, $availableMethods, 'int');
    }

    /**
     * @dataProvider typeDataProvider
     *
     * @param $val
     * @param $availableMethods
     *
     * @covers ::float
     * @covers ::cast
     * @covers ::__construct
     * @covers ::get
     * @covers ::scalar
     * @covers ::createGetterTypeException
     */
    public function testFloat($val, array $availableMethods) {
        $this->runTypeTest($val, $availableMethods, 'float');
    }

    /**
     * @dataProvider typeDataProvider
     *
     * @param $val
     * @param $availableMethods
     *
     * @covers ::bool
     * @covers ::cast
     * @covers ::__construct
     * @covers ::get
     * @covers ::scalar
     * @covers ::createGetterTypeException
     */
    public function testBool($val, array $availableMethods) {
        $this->runTypeTest($val, $availableMethods, 'bool');
    }

    /**
     * @dataProvider typeDataProvider
     *
     * @param $val
     * @param $availableMethods
     *
     * @covers ::array
     * @covers ::cast
     * @covers ::__construct
     * @covers ::get
     * @covers ::scalar
     * @covers ::createGetterTypeException
     */
    public function testArray($val, array $availableMethods) {
        $this->runTypeTest($val, $availableMethods, 'array');
    }

    /**
     * @dataProvider typeDataProvider
     *
     * @param $val
     * @param $availableMethods
     *
     * @covers ::arrayReflect
     * @covers ::cast
     * @covers ::__construct
     * @covers ::array
     * @covers ::get
     * @covers ::createGetterTypeException
     * @covers ::createLikeThis
     * @covers ::setGetterTypeExceptionClass
     */
    public function testArrayReflect($val, array $availableMethods) {
        $this->runTypeTest($val, $availableMethods, 'arrayReflect');
    }

    /**
     * @param       $arrval
     * @param array $availableMethods
     * @param       $method
     */
    private function runTypeTest($arrval, array $availableMethods, $method) {
        $reflect = ArrayReflect::cast([$arrval]);

        $val = $e = null;
        $isgood = in_array($method, $availableMethods);
        try {
            $val = $reflect->$method('0');
            $this->assertTrue($isgood);
        } catch (\Throwable) {
            $this->assertFalse($isgood);
        }

        if ($val !== null) {
            if ($method == 'arrayReflect') {
                $this->assertInstanceOf(ArrayReflect::class, $val);
            } else {
                $this->assertTrue(("is_$method")($val));
            }
        }
    }

    public static function baseTestArray(): array {
        return [
            'key1' => 'val1',
            'key2' => 'val2',
            'key3' => 3,
            'key4' => true,
            'key5' => null,
            'i0',
            'i1',
            'node1' => [
                'i1-0',
                'key1-1' => 'val1-1',
                'i1-1',
                'key1-2' => 'val1-2',
                'key1-3' => 13,
            ],
            'node2' => [
                'key2-1' => 'val2-1',
                'i2-0',
                'node2-1' => [
                    'key2-1-1' => true,
                    'key2-1-2' => 'val2-1-2',
                    'key2-1-3' => 213,
                    'i2-1-0',
                    'i2-1-1',
                ],
                'key2-2' => 22,
                'key2-3' => 'val2-3',
                'i2-1',
            ],
        ];
    }
}
