<?php

namespace Tests\Feature\AccessControl;

use App\Modules\AccessControl\Application\Commands\RegisterCheckInCommand;
use App\Modules\AccessControl\Application\Handlers\RegisterCheckInHandler;
use App\Modules\AccessControl\Domain\Events\CheckInRegistered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterCheckInHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_check_in_and_outbox_event_atomically(): void
    {
        $handler = $this->app->make(RegisterCheckInHandler::class);
        $command = new RegisterCheckInCommand(memberId: 'member-abc');

        $checkIn = $handler->handle($command);

        $this->assertDatabaseHas('check_ins', [
            'id' => $checkIn->id()->value(),
            'member_id' => 'member-abc',
        ]);

        $this->assertDatabaseHas('outbox_events', [
            'aggregate_type' => 'CheckIn',
            'aggregate_id' => $checkIn->id()->value(),
            'event_type' => CheckInRegistered::class,
        ]);
    }

    public function test_outbox_event_is_unpublished_on_creation(): void
    {
        $handler = $this->app->make(RegisterCheckInHandler::class);
        $command = new RegisterCheckInCommand(memberId: 'member-xyz');

        $checkIn = $handler->handle($command);

        $this->assertDatabaseHas('outbox_events', [
            'aggregate_id' => $checkIn->id()->value(),
            'published_at' => null,
        ]);
    }

    public function test_check_in_id_is_a_valid_uuid(): void
    {
        $handler = $this->app->make(RegisterCheckInHandler::class);
        $command = new RegisterCheckInCommand(memberId: 'member-001');

        $checkIn = $handler->handle($command);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $checkIn->id()->value(),
        );
    }

    public function test_multiple_check_ins_for_same_member_are_all_registered(): void
    {
        $handler = $this->app->make(RegisterCheckInHandler::class);

        $handler->handle(new RegisterCheckInCommand(memberId: 'member-repeat'));
        $handler->handle(new RegisterCheckInCommand(memberId: 'member-repeat'));
        $handler->handle(new RegisterCheckInCommand(memberId: 'member-repeat'));

        $this->assertDatabaseCount('check_ins', 3);
        $this->assertDatabaseCount('outbox_events', 3);
    }

    public function test_check_in_endpoint_returns_201(): void
    {
        $response = $this->postJson('/api/check-in', [
            'member_id' => 'member-http',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'check_in_id',
                'member_id',
                'checked_in_at',
            ])
            ->assertJsonFragment(['member_id' => 'member-http']);
    }

    public function test_check_in_endpoint_validates_member_id_required(): void
    {
        $response = $this->postJson('/api/check-in', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['member_id']);
    }
}
