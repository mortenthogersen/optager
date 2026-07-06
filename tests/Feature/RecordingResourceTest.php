<?php

use App\Filament\Resources\Recordings\RecordingResource;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('list recordings page loads', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->withoutMiddleware()
        ->get(RecordingResource::getUrl('index'));

    $response->assertSuccessful();
});

test('create recording page loads', function () {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->withoutMiddleware()
        ->get(RecordingResource::getUrl('create'));

    $response->assertSuccessful();
});

test('view recording page loads', function () {
    $user = User::factory()->create();
    $recording = Recording::factory()->create(['created_by' => $user->id]);

    $response = actingAs($user)
        ->withoutMiddleware()
        ->get(RecordingResource::getUrl('view', ['record' => $recording]));

    $response->assertSuccessful();
});

test('edit recording page loads', function () {
    $user = User::factory()->create();
    $recording = Recording::factory()->create(['created_by' => $user->id]);

    $response = actingAs($user)
        ->withoutMiddleware()
        ->get(RecordingResource::getUrl('edit', ['record' => $recording]));

    $response->assertSuccessful();
});

test('view recording page shows transcript when available', function () {
    $user = User::factory()->create();
    $recording = Recording::factory()->withTranscript()->create(['created_by' => $user->id]);

    $response = actingAs($user)
        ->withoutMiddleware()
        ->get(RecordingResource::getUrl('view', ['record' => $recording]));

    $response->assertSuccessful();
});

test('view recording page shows summary when available', function () {
    $user = User::factory()->create();
    $recording = Recording::factory()->withTranscript()->withSummary()->create(['created_by' => $user->id]);

    $response = actingAs($user)
        ->withoutMiddleware()
        ->get(RecordingResource::getUrl('view', ['record' => $recording]));

    $response->assertSuccessful();
});

test('list recordings page filters by status', function () {
    $user = User::factory()->create();

    Recording::factory()->count(3)->create(['status' => 'completed']);
    Recording::factory()->count(2)->create(['status' => 'failed']);

    $response = actingAs($user)
        ->withoutMiddleware()
        ->get(RecordingResource::getUrl('index').'?tableFilters[status][value]=completed');

    $response->assertSuccessful();
});

test('unauthenticated user cannot access recordings', function () {
    $response = $this->get(
        RecordingResource::getUrl('index')
    );

    $response->assertRedirect('/admin/login');
});
