<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Controller;
use Illuminate\Routing\Controller as LaravelController;
use PHPUnit\Framework\TestCase;

final class BaseControllerContractTest extends TestCase
{
    public function test_application_controller_preserves_laravel_authorization_contract(): void
    {
        self::assertTrue(is_subclass_of(Controller::class, LaravelController::class));
        self::assertTrue(method_exists(Controller::class, 'authorize'));
        self::assertTrue(method_exists(Controller::class, 'validate'));
    }
}
