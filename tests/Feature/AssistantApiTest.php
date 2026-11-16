<?php

namespace Tests\Feature;

use App\Enums\ContributionDueStatus;
use App\Models\ContributionDue;
use App\Models\MemberProfile;
use App\Models\User;
use App\Support\ContributionRules;
use App\Support\FundCurrency;
use App\Support\FundRules;
use Database\Seeders\CountrySeeder;
use Database\Seeders\FundAccountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Accès en lecture seule du service Assistant IA (routes /api/assistant). */
class AssistantApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'jeton-assistant-de-test';

    protected function setUp(): void
    {
        parent::setUp();
        FundRules::flush();
        FundCurrency::flush();
        $this->seed([RolePermissionSeeder::class, CountrySeeder::class, FundAccountSeeder::class]);
        config(['assistant.token' => self::TOKEN]);
    }

    protected function tearDown(): void
    {
        FundRules::flush();
        FundCurrency::flush();
        parent::tearDown();
    }

    private function member(string $phone = null, string $status = 'active'): User
    {
        $user = User::factory()->create(['name' => 'Romy Jokung']);
        $user->syncRoles(['member']);
        MemberProfile::create(['user_id' => $user->id, 'status' => $status, 'phone' => $phone]);

        return $user;
    }

    private function asAssistant(?User $user = null): static
    {
        return $this->withHeaders(array_filter([
            'Authorization' => 'Bearer '.self::TOKEN,
            'X-Acting-User' => $user?->id,
            'Accept' => 'application/json',
        ]));
    }

    public function test_routes_need_the_secret_token_and_are_disabled_without_it(): void
    {
        $user = $this->member();
        $this->getJson('/api/assistant/rules', ['X-Acting-User' => $user->id])->assertUnauthorized();
        $this->getJson('/api/assistant/rules', ['Authorization' => 'Bearer mauvais', 'X-Acting-User' => $user->id])->assertUnauthorized();

        config(['assistant.token' => null]);
        $this->asAssistant($user)->getJson('/api/assistant/rules')->assertNotFound();
    }

    public function test_an_unknown_or_inactive_member_is_refused(): void
    {
        $this->asAssistant()->getJson('/api/assistant/me')->assertForbidden();
        $this->asAssistant($this->member(status: 'suspended'))->getJson('/api/assistant/me')->assertForbidden();
    }

    public function test_rules_give_the_values_and_the_sections(): void
    {
        $this->asAssistant($this->member())->getJson('/api/assistant/rules')
            ->assertOk()
            ->assertJsonFragment(['key' => 'contribution_quarterly_amount', 'value' => 75, 'section' => '4.1'])
            ->assertJsonFragment(['key' => 'contribution_penalty_amount', 'value' => 2, 'section' => '4.3']);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'assistant', 'event' => 'assistant.tool']);
    }

    public function test_penalty_on_the_28th_of_january_is_two_weeks(): void
    {
        $user = $this->member();
        // Aucune échéance enregistrée : le trimestre est estimé, sans rien créer en base.
        $this->asAssistant($user)->getJson('/api/assistant/me/penalty?date=2027-01-28')
            ->assertOk()
            ->assertJsonPath('dues.0.period', 'T1 2027')
            ->assertJsonPath('dues.0.due_date', '2027-01-15')
            ->assertJsonPath('dues.0.penalty', 4)
            ->assertJsonPath('dues.0.penalty_weeks', 2)
            ->assertJsonPath('dues.0.estimated', true)
            ->assertJsonPath('total_due', 79);
        $this->assertSame(0, ContributionDue::count());

        $this->asAssistant($user)->getJson('/api/assistant/me/penalty?date=2027-01-15')->assertJsonPath('total_due', 75);
        $this->asAssistant($user)->getJson('/api/assistant/me/penalty?date=2027-01-30')->assertJsonPath('total_due', 81);
        $this->asAssistant($user)->getJson('/api/assistant/me/penalty?date=28-01-2027')->assertUnprocessable();
    }

    public function test_a_member_only_sees_his_own_contributions(): void
    {
        $romy = $this->member();
        $other = $this->member();
        $plan = ContributionRules::plan();
        ContributionDue::create([
            'contribution_plan_id' => $plan->id, 'user_id' => $other->id, 'period_key' => '2027-Q1', 'period_label' => 'T1 2027',
            'amount' => 75, 'currency' => 'USD', 'due_date' => '2027-01-15', 'status' => ContributionDueStatus::Pending,
        ]);
        ContributionDue::create([
            'contribution_plan_id' => $plan->id, 'user_id' => $romy->id, 'period_key' => '2026-Q4', 'period_label' => 'T4 2026',
            'amount' => 75, 'currency' => 'USD', 'due_date' => '2026-10-15', 'status' => ContributionDueStatus::Paid, 'paid_at' => '2026-10-10',
        ]);

        $this->asAssistant($romy)->getJson('/api/assistant/me/contributions')
            ->assertOk()
            ->assertJsonCount(1, 'dues')
            ->assertJsonPath('dues.0.period', 'T4 2026')
            ->assertJsonPath('dues.0.status', 'payée')
            ->assertJsonMissing(['email' => $romy->email]);
    }

    public function test_whatsapp_numbers_are_matched_whatever_the_format(): void
    {
        $romy = $this->member('06 12 34 56 78');
        $this->member('+33 7 00 00 00 01', 'suspended');

        $this->getJson('/api/assistant/resolve-phone?phone=33612345678')->assertUnauthorized();
        $this->asAssistant()->getJson('/api/assistant/resolve-phone?phone=33612345678')
            ->assertOk()->assertJson(['found' => true, 'user_id' => $romy->id, 'first_name' => 'Romy']);
        $this->asAssistant()->getJson('/api/assistant/resolve-phone?phone=33799999999')->assertJson(['found' => false, 'reason' => 'unknown']);
        $this->asAssistant()->getJson('/api/assistant/resolve-phone?phone=33700000001')->assertJson(['found' => false, 'reason' => 'inactive']);
    }
}
