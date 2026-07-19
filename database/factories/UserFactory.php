<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
class UserFactory extends Factory { protected static ?string $password; public function definition(): array { return ['name'=>fake()->name(),'email'=>fake()->unique()->safeEmail(),'email_verified_at'=>now(),'password'=>static::$password??=Hash::make('password'),'remember_token'=>Str::random(10),'active'=>true,'is_super_admin'=>false]; } public function unverified(): static { return $this->state(fn()=>['email_verified_at'=>null]); } }
