<?php

use App\Enums\ContractState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pricing_model')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->decimal('offered_rate', 10, 2)->nullable();
            $table->decimal('agreed_rate', 10, 2)->nullable();
            $table->string('state')->default(ContractState::DRAFT->value);
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->text('ended_reason')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->unique('lead_id');
            $table->index(['service_provider_id', 'state']);
            $table->index(['user_id', 'state']);
        });

        Schema::create('contract_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->string('from_state')->nullable();
            $table->string('to_state')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'created_at']);
            $table->index('event_type');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->after('contract_agreed_rate')->constrained('contracts')->nullOnDelete();
            $table->index('contract_id');
        });

        $this->backfillExistingLeadContracts();
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_id');
        });

        Schema::dropIfExists('contract_events');
        Schema::dropIfExists('contracts');
    }

    protected function backfillExistingLeadContracts(): void
    {
        $leads = DB::table('leads')
            ->where(function ($query) {
                $query->whereNotNull('contract_sent_at')
                    ->orWhereNotNull('contract_accepted_at')
                    ->orWhereNotNull('contract_offered_rate')
                    ->orWhereNotNull('contract_agreed_rate');
            })
            ->select([
                'id',
                'user_id',
                'service_provider_id',
                'status',
                'contract_sent_at',
                'contract_accepted_at',
                'contract_offered_rate',
                'contract_agreed_rate',
                'closed_at',
            ])
            ->get();

        foreach ($leads as $lead) {
            $conversationId = DB::table('conversations')->where('lead_id', $lead->id)->value('id');
            $state = ContractState::DRAFT->value;

            if ($lead->contract_accepted_at) {
                $state = in_array((string) $lead->status, ['closed', 'declined'], true)
                    ? ContractState::ENDED->value
                    : ContractState::ACCEPTED->value;
            } elseif ($lead->contract_sent_at) {
                $state = ContractState::OFFERED->value;
            }

            $contractId = DB::table('contracts')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'conversation_id' => $conversationId,
                'service_provider_id' => $lead->service_provider_id,
                'user_id' => $lead->user_id,
                'pricing_model' => null,
                'currency' => 'USD',
                'offered_rate' => $lead->contract_offered_rate,
                'agreed_rate' => $lead->contract_agreed_rate,
                'state' => $state,
                'offered_at' => $lead->contract_sent_at,
                'accepted_at' => $lead->contract_accepted_at,
                'withdrawn_at' => null,
                'ended_at' => in_array((string) $lead->status, ['closed', 'declined'], true) ? ($lead->closed_at ?? now()) : null,
                'ended_reason' => null,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('leads')->where('id', $lead->id)->update(['contract_id' => $contractId]);

            DB::table('contract_events')->insert([
                'contract_id' => $contractId,
                'actor_id' => null,
                'event_type' => 'migrated_from_lead',
                'from_state' => null,
                'to_state' => $state,
                'payload_json' => json_encode(['lead_status' => $lead->status], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
