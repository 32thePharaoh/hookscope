<?php

namespace Tests\Feature;

use App\Models\CapturedRequest;
use App\Models\Endpoint;
use App\Models\Replay;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_tables_exist_with_the_pinned_columns(): void
    {
        $this->assertTrue(Schema::hasTable('endpoints'));
        $this->assertTrue(Schema::hasTable('captured_requests'));
        $this->assertTrue(Schema::hasTable('replays'));

        $this->assertSame(
            ['user_id', 'name', 'token', 'retention_days'],
            $this->columnNames('endpoints', ['user_id', 'name', 'token', 'retention_days']),
        );

        $this->assertSame(
            [
                'endpoint_id',
                'method',
                'path',
                'query',
                'headers',
                'body',
                'body_encoding',
                'content_type',
                'ip',
                'size_bytes',
                'received_at',
            ],
            $this->columnNames('captured_requests', [
                'endpoint_id',
                'method',
                'path',
                'query',
                'headers',
                'body',
                'body_encoding',
                'content_type',
                'ip',
                'size_bytes',
                'received_at',
            ]),
        );

        $this->assertSame(
            [
                'captured_request_id',
                'target_url',
                'status_code',
                'duration_ms',
                'error',
                'response_snippet',
                'forwarded_headers',
            ],
            $this->columnNames('replays', [
                'captured_request_id',
                'target_url',
                'status_code',
                'duration_ms',
                'error',
                'response_snippet',
                'forwarded_headers',
            ]),
        );
    }

    public function test_captured_request_body_is_longblob(): void
    {
        $body = $this->column('captured_requests', 'body');

        $this->assertSame('longblob', $body['type']);
    }

    public function test_endpoint_token_is_unique_and_requests_are_indexed_for_the_dashboard(): void
    {
        $this->assertTrue(Schema::hasIndex('endpoints', ['token'], 'unique'));
        $this->assertTrue(Schema::hasIndex('captured_requests', ['endpoint_id', 'received_at']));
    }

    public function test_foreign_keys_cascade_on_delete(): void
    {
        $requestFk = collect(Schema::getForeignKeys('captured_requests'))
            ->first(fn (array $fk): bool => $fk['columns'] === ['endpoint_id']);
        $replayFk = collect(Schema::getForeignKeys('replays'))
            ->first(fn (array $fk): bool => $fk['columns'] === ['captured_request_id']);

        $this->assertNotNull($requestFk);
        $this->assertSame('cascade', strtolower((string) $requestFk['on_delete']));
        $this->assertNotNull($replayFk);
        $this->assertSame('cascade', strtolower((string) $replayFk['on_delete']));
    }

    public function test_deleting_an_endpoint_removes_its_requests_and_replays(): void
    {
        $endpoint = Endpoint::factory()->create();
        $request = CapturedRequest::factory()->for($endpoint)->create();
        $replay = Replay::factory()->for($request)->create();

        $endpoint->delete();

        $this->assertDatabaseMissing('endpoints', ['id' => $endpoint->id]);
        $this->assertDatabaseMissing('captured_requests', ['id' => $request->id]);
        $this->assertDatabaseMissing('replays', ['id' => $replay->id]);
    }

    public function test_deleting_a_captured_request_removes_its_replays(): void
    {
        $request = CapturedRequest::factory()->create();
        $replay = Replay::factory()->for($request)->create();

        $request->delete();

        $this->assertDatabaseMissing('captured_requests', ['id' => $request->id]);
        $this->assertDatabaseMissing('replays', ['id' => $replay->id]);
    }

    public function test_endpoint_tokens_are_high_entropy_random_and_unique(): void
    {
        $tokens = Endpoint::factory()->count(8)->create()->pluck('token');

        $this->assertCount(8, $tokens->unique());
        $tokens->each(function (string $token): void {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        });

        $this->expectException(UniqueConstraintViolationException::class);
        Endpoint::factory()->create(['token' => $tokens->first()]);
    }

    public function test_creating_an_endpoint_without_a_token_generates_one(): void
    {
        $endpoint = Endpoint::query()->create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Inbox',
            'retention_days' => 7,
        ]);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $endpoint->token);
    }

    public function test_forwarded_headers_round_trip_as_a_list_of_names(): void
    {
        $replay = Replay::factory()->create([
            'forwarded_headers' => ['Content-Type', 'X-Request-Id'],
        ]);

        $this->assertSame(
            ['Content-Type', 'X-Request-Id'],
            $replay->fresh()->forwarded_headers,
        );
    }

    public function test_binary_factory_state_persists_invalid_utf8_bodies(): void
    {
        $request = CapturedRequest::factory()->binary()->create();
        $fresh = $request->fresh();

        $this->assertFalse(mb_check_encoding($fresh->body, 'UTF-8'));
        $this->assertSame('binary', $fresh->body_encoding);
        $this->assertSame($request->body, $fresh->body);
        $this->assertSame(strlen($request->body), $fresh->size_bytes);
    }

    public function test_database_seeder_creates_all_three_domain_models(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(1, Endpoint::query()->count());
        $this->assertGreaterThanOrEqual(1, CapturedRequest::query()->count());
        $this->assertGreaterThanOrEqual(1, Replay::query()->count());
        $this->assertTrue(
            CapturedRequest::query()->where('body_encoding', 'binary')->exists(),
        );
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function columnNames(string $table, array $names): array
    {
        $present = Schema::getColumnListing($table);

        return array_values(array_intersect($names, $present));
    }

    /**
     * @return array{name: string, type: string, type_name: string, collation: string|null, nullable: bool, default: mixed, auto_increment: bool, comment: string|null, generation: array{type: string, expression: string|null}|null}
     */
    private function column(string $table, string $name): array
    {
        $column = collect(Schema::getColumns($table))->firstWhere('name', $name);
        $this->assertIsArray($column);

        return $column;
    }
}
