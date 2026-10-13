<?php

namespace Tests;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected ?User $currentUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureAuthenticatedUser();
    }

    protected function ensureAuthenticatedUser(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $user = User::first();

        if (! $user) {
            $workspace = Workspace::firstOrCreate(
                ['name' => 'المحفظة الرئيسية (العائلة)'],
                ['currency' => 'ILS']
            );

            $user = User::firstOrCreate(
                ['email' => 'admin@masroofi.com'],
                [
                    'name' => 'المدير (أدمن)',
                    'username' => 'admin',
                    'phone' => '0599000000',
                    'password' => Hash::make('admin1234'),
                    'pin_code' => Hash::make('1234'),
                    'pin_enabled' => true,
                    'role' => 'admin',
                    'workspace_id' => $workspace->id,
                ]
            );
        }

        if ($user) {
            $this->currentUser = $user;
            $this->actingAs($user);
        }
    }
}
