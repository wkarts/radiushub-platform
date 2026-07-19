<?php
namespace Tests\Unit;
use App\Services\Mikrotik\RouterOsApiClient;use PHPUnit\Framework\TestCase;use ReflectionMethod;
class RouterOsApiLengthTest extends TestCase { public function test_short_word_length_encoding(): void { $client=new RouterOsApiClient();$method=new ReflectionMethod($client,'encodeLength');$this->assertSame(chr(42),$method->invoke($client,42)); } }
