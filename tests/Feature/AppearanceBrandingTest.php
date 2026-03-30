<?php

namespace Tests\Feature;

use App\Models\BrandingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AppearanceBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists(public_path('branding'));

        foreach (glob(public_path('branding/logo-*')) ?: [] as $uploadedLogo) {
            File::delete($uploadedLogo);
        }
    }

    protected function tearDown(): void
    {
        foreach (glob(public_path('branding/logo-*')) ?: [] as $uploadedLogo) {
            File::delete($uploadedLogo);
        }

        parent::tearDown();
    }

    public function test_uploaded_logo_is_used_on_settings_and_login_views(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $response = $this->actingAs($user)->patch(route('appearance.update'), [
            'logo' => UploadedFile::fake()->createWithContent(
                'brand-logo.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO/q3ioAAAAASUVORK5CYII=')
            ),
        ]);

        $response->assertRedirect(route('appearance.edit'));

        $branding = BrandingSetting::query()->first();

        $this->assertNotNull($branding);
        $this->assertNotNull($branding?->logo_path);
        $this->assertStringStartsWith('branding/logo-', $branding->logo_path);
        $this->assertFileExists(public_path($branding->logo_path));

        $this->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertOk()
            ->assertSee($branding->logo_path, false)
            ->assertSee('Logo updated.');

        Auth::logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee($branding->logo_path, false);
    }
}
